<?php

namespace Belly\Crm\UseCases;

use Belly\Crm\Services\ConsultaClientes;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use DomainException;

/** Lista, embudo y ficha de los clientes con su etapa, tipo y etiquetas. */
class CarteraClientesUseCase
{
    private const POR_COLUMNA = 50;

    private BaseConnection $db;
    private ConsultaClientes $consulta;
    private AjustesCrmUseCase $ajustes;

    public function __construct(?ConsultaClientes $consulta = null, ?AjustesCrmUseCase $ajustes = null)
    {
        $this->db = db_connect();
        $this->consulta = $consulta ?? new ConsultaClientes($this->db);
        $this->ajustes = $ajustes ?? new AjustesCrmUseCase($this->consulta);
    }

    /** @param array<string, mixed> $filtros @return array<string, mixed> */
    public function listar(array $filtros, int $pagina, int $porPagina): array
    {
        $pagina = max(1, $pagina);
        $porPagina = min(100, max(5, $porPagina));
        $primera = $this->ajustes->idPrimeraEtapa();

        $total = $this->consulta->builder($filtros, $primera)->countAllResults();
        $filas = $this->seleccionar($this->consulta->builder($filtros, $primera))
            ->orderBy('ultimo_mensaje_cliente IS NULL', '', false)
            ->orderBy('ultimo_mensaje_cliente', 'DESC')
            ->orderBy('c.id', 'DESC')
            ->limit($porPagina, ($pagina - 1) * $porPagina)
            ->get()->getResultArray();

        return [
            'data' => $this->presentar($filas, $primera),
            'pagination' => [
                'total' => $total,
                'currentPage' => $pagina,
                'perPage' => $porPagina,
                'hasNext' => $pagina * $porPagina < $total,
                'hasPrev' => $pagina > 1,
            ],
        ];
    }

    /** Columnas del embudo con los clientes más recientes de cada etapa. @return list<array<string, mixed>> */
    public function tablero(array $filtros): array
    {
        $etapas = $this->ajustes->etapas();
        $primera = $etapas === [] ? null : (int) $etapas[0]['id'];
        unset($filtros['id_etapa']);

        $columnas = [];
        foreach ($etapas as $etapa) {
            $conEtapa = $filtros + ['id_etapa' => $etapa['id']];
            $total = $this->consulta->builder($conEtapa, $primera)->countAllResults();
            $filas = $this->seleccionar($this->consulta->builder($conEtapa, $primera))
                ->orderBy('COALESCE(c.etapa_at, c.created_at)', 'DESC', false)
                ->limit(self::POR_COLUMNA)
                ->get()->getResultArray();

            $columnas[] = $etapa + ['total' => $total, 'clientes' => $this->presentar($filas, $primera)];
        }

        return $columnas;
    }

    /** @return array<string, mixed> */
    public function ficha(int $id): array
    {
        $primera = $this->ajustes->idPrimeraEtapa();
        $fila = $this->seleccionar($this->consulta->builder([], $primera))->where('c.id', $id)->get()->getRowArray();
        if ($fila === null) {
            throw new DomainException('No encontramos ese cliente o no lo atiendes.');
        }
        $cliente = $this->presentar([$fila], $primera)[0];

        $cliente['conversaciones'] = $this->db->table('conversacion cv')
            ->select('cv.id, cv.id_estado, cv.created_at, cv.updated_at, up.nombres AS asesor_nombre')
            ->join('usuario_perfil up', 'up.id_usuario = cv.id_usuario AND up.deleted_at IS NULL', 'left')
            ->where('cv.id_cliente', $id)
            ->where('cv.id_empresa', $this->consulta->idEmpresa())
            ->where('cv.deleted_at', null)
            ->orderBy('cv.id', 'DESC')
            ->limit(5)
            ->get()->getResultArray();

        return $cliente;
    }

    /** @param array<string, mixed> $datos @return array<string, mixed> */
    public function actualizar(int $id, array $datos): array
    {
        $this->ficha($id);
        $idEmpresa = $this->consulta->idEmpresa();

        $cliente = [];
        if (array_key_exists('id_etapa', $datos)) {
            $cliente['id_etapa'] = $this->idValido('embudo_etapa', $datos['id_etapa'], 'Esa etapa no existe.');
            $cliente['etapa_at'] = date('Y-m-d H:i:s');
        }
        if (array_key_exists('id_tipo', $datos)) {
            $cliente['id_tipo'] = $this->idValido('cliente_tipo', $datos['id_tipo'], 'Ese tipo no existe.');
        }
        if (array_key_exists('notas', $datos)) {
            $notas = trim((string) ($datos['notas'] ?? ''));
            if (mb_strlen($notas) > 5000) {
                throw new DomainException('Las notas no pueden pasar de 5000 letras.');
            }
            $cliente['notas'] = $notas === '' ? null : $notas;
        }
        if ($cliente !== []) {
            $this->db->table('cliente')->where('id', $id)->where('id_empresa', $idEmpresa)->update($cliente + ['updated_at' => date('Y-m-d H:i:s')]);
        }

        $perfil = [];
        foreach (['nombre' => 100, 'apellido' => 100, 'correo' => 150, 'distrito' => 100] as $campo => $maximo) {
            if (array_key_exists($campo, $datos)) {
                $valor = trim((string) ($datos[$campo] ?? ''));
                if (mb_strlen($valor) > $maximo) {
                    throw new DomainException("El campo {$campo} no puede pasar de {$maximo} letras.");
                }
                $perfil[$campo] = $valor === '' ? null : $valor;
            }
        }
        if (isset($perfil['correo']) && filter_var($perfil['correo'], FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('Ese correo no es válido.');
        }
        if ($perfil !== []) {
            $this->guardarPerfil($id, $perfil);
        }

        if (array_key_exists('etiquetas', $datos)) {
            $this->ponerEtiquetas($id, $this->consulta->listaIds($datos['etiquetas']));
        }

        return $this->ficha($id);
    }

    /**
     * Cambios a varios clientes a la vez.
     * @param list<int> $ids
     * @param array<string, mixed> $cambios id_etapa, id_tipo, agregar_etiqueta, quitar_etiqueta
     */
    public function actualizarVarios(array $ids, array $cambios): int
    {
        $ids = $this->consulta->listaIds($ids);
        if ($ids === []) {
            throw new DomainException('Elige al menos un cliente.');
        }
        if (count($ids) > 500) {
            throw new DomainException('Puedes cambiar hasta 500 clientes a la vez.');
        }

        $visibles = array_map('intval', array_column(
            $this->consulta->builder([], $this->ajustes->idPrimeraEtapa())->select('c.id')->whereIn('c.id', $ids)->get()->getResultArray(),
            'id'
        ));
        if ($visibles === []) {
            return 0;
        }
        $idEmpresa = $this->consulta->idEmpresa();

        $cliente = [];
        if (array_key_exists('id_etapa', $cambios)) {
            $cliente['id_etapa'] = $this->idValido('embudo_etapa', $cambios['id_etapa'], 'Esa etapa no existe.');
            $cliente['etapa_at'] = date('Y-m-d H:i:s');
        }
        if (array_key_exists('id_tipo', $cambios)) {
            $cliente['id_tipo'] = $this->idValido('cliente_tipo', $cambios['id_tipo'], 'Ese tipo no existe.');
        }
        if ($cliente !== []) {
            $this->db->table('cliente')->where('id_empresa', $idEmpresa)->whereIn('id', $visibles)->update($cliente);
        }

        $agregar = (int) ($cambios['agregar_etiqueta'] ?? 0);
        if ($agregar > 0) {
            $this->exigirEtiqueta($agregar);
            foreach ($visibles as $idCliente) {
                $this->db->query(
                    'INSERT IGNORE INTO cliente_etiqueta (id_empresa, id_cliente, id_etiqueta, created_at) VALUES (?, ?, ?, ?)',
                    [$idEmpresa, $idCliente, $agregar, date('Y-m-d H:i:s')]
                );
            }
        }
        $quitar = (int) ($cambios['quitar_etiqueta'] ?? 0);
        if ($quitar > 0) {
            $this->db->table('cliente_etiqueta')->where('id_empresa', $idEmpresa)->where('id_etiqueta', $quitar)->whereIn('id_cliente', $visibles)->delete();
        }

        return count($visibles);
    }

    private function seleccionar(BaseBuilder $builder): BaseBuilder
    {
        $ultimo = $this->consulta->sqlUltimoMensajeCliente();

        return $builder->select(
            "c.id, c.telefono, c.id_etapa, c.etapa_at, c.id_tipo, c.notas, c.origen, c.id_responsable, c.created_at,
             cp.nombre, cp.apellido, cp.nombre_completo, cp.correo, cp.distrito,
             (SELECT ur.nombres FROM usuario_perfil ur WHERE ur.id_usuario = c.id_responsable AND ur.deleted_at IS NULL LIMIT 1) AS responsable_nombre,
             {$ultimo} AS ultimo_mensaje_cliente,
             (SELECT CONCAT(cv.id, '|', cv.id_estado, '|', COALESCE(up.nombres, ''))
                FROM conversacion cv
                LEFT JOIN usuario_perfil up ON up.id_usuario = cv.id_usuario AND up.deleted_at IS NULL
               WHERE cv.id_cliente = c.id AND cv.deleted_at IS NULL
               ORDER BY cv.id DESC LIMIT 1) AS ultima_conversacion",
            false
        );
    }

    /** @param list<array<string, mixed>> $filas @return list<array<string, mixed>> */
    private function presentar(array $filas, ?int $primera): array
    {
        if ($filas === []) {
            return [];
        }
        $etiquetas = $this->etiquetasDe(array_map(static fn (array $f) => (int) $f['id'], $filas));
        $inicioVentana = $this->consulta->inicioVentana();

        return array_map(static function (array $f) use ($etiquetas, $primera, $inicioVentana) {
            [$idConversacion, $estado, $asesor] = array_pad(explode('|', (string) ($f['ultima_conversacion'] ?? ''), 3), 3, '');
            $nombre = trim((string) ($f['nombre_completo'] ?? '')) ?: trim(($f['nombre'] ?? '') . ' ' . ($f['apellido'] ?? ''));
            $ultimo = $f['ultimo_mensaje_cliente'] ?? null;

            return [
                'id' => (int) $f['id'],
                'nombre' => $nombre,
                'nombres' => $f['nombre'],
                'apellidos' => $f['apellido'],
                'telefono' => $f['telefono'],
                'correo' => $f['correo'],
                'distrito' => $f['distrito'],
                'notas' => $f['notas'],
                'id_etapa' => $f['id_etapa'] !== null ? (int) $f['id_etapa'] : $primera,
                'id_tipo' => $f['id_tipo'] !== null ? (int) $f['id_tipo'] : null,
                'etiquetas' => $etiquetas[(int) $f['id']] ?? [],
                'asesor_nombre' => $asesor !== '' ? $asesor : ($f['responsable_nombre'] ?? null),
                'id_responsable' => $f['id_responsable'] !== null ? (int) $f['id_responsable'] : null,
                'origen' => $f['origen'] ?? 'whatsapp',
                'id_conversacion' => $idConversacion !== '' ? (int) $idConversacion : null,
                'conversacion_activa' => in_array((int) $estado, [1, 3], true),
                'ultimo_mensaje_cliente' => $ultimo,
                'ventana_abierta' => $ultimo !== null && $ultimo >= $inicioVentana,
                'creado' => $f['created_at'],
            ];
        }, $filas);
    }

    /** @param list<int> $ids @return array<int, list<array<string, mixed>>> */
    private function etiquetasDe(array $ids): array
    {
        $filas = $this->db->table('cliente_etiqueta ce')
            ->select('ce.id_cliente, e.id, e.nombre, e.color')
            ->join('etiqueta e', 'e.id = ce.id_etiqueta AND e.deleted_at IS NULL')
            ->where('ce.id_empresa', $this->consulta->idEmpresa())
            ->whereIn('ce.id_cliente', $ids)
            ->orderBy('e.nombre', 'ASC')
            ->get()->getResultArray();

        $porCliente = [];
        foreach ($filas as $fila) {
            $porCliente[(int) $fila['id_cliente']][] = ['id' => (int) $fila['id'], 'nombre' => $fila['nombre'], 'color' => $fila['color'] ?: '#64748b'];
        }

        return $porCliente;
    }

    /** @param list<int> $ids */
    private function ponerEtiquetas(int $idCliente, array $ids): void
    {
        $idEmpresa = $this->consulta->idEmpresa();
        foreach ($ids as $idEtiqueta) {
            $this->exigirEtiqueta($idEtiqueta);
        }

        $tabla = $this->db->table('cliente_etiqueta');
        $tabla->where('id_empresa', $idEmpresa)->where('id_cliente', $idCliente);
        if ($ids !== []) {
            $tabla->whereNotIn('id_etiqueta', $ids);
        }
        $tabla->delete();

        foreach ($ids as $idEtiqueta) {
            $this->db->query(
                'INSERT IGNORE INTO cliente_etiqueta (id_empresa, id_cliente, id_etiqueta, created_at) VALUES (?, ?, ?, ?)',
                [$idEmpresa, $idCliente, $idEtiqueta, date('Y-m-d H:i:s')]
            );
        }
    }

    private function exigirEtiqueta(int $id): void
    {
        $existe = $this->db->table('etiqueta')
            ->where('id', $id)->where('id_empresa', $this->consulta->idEmpresa())->where('deleted_at', null)
            ->countAllResults() > 0;
        if (! $existe) {
            throw new DomainException('Esa etiqueta no existe.');
        }
    }

    private function idValido(string $tabla, mixed $valor, string $mensaje): ?int
    {
        $id = (int) ($valor ?? 0);
        if ($id <= 0) {
            return null;
        }
        $existe = $this->db->table($tabla)
            ->where('id', $id)->where('id_empresa', $this->consulta->idEmpresa())->where('deleted_at', null)
            ->countAllResults() > 0;
        if (! $existe) {
            throw new DomainException($mensaje);
        }

        return $id;
    }

    /** @param array<string, ?string> $perfil */
    private function guardarPerfil(int $idCliente, array $perfil): void
    {
        $actual = $this->db->table('cliente_perfil')->where('id_cliente', $idCliente)->get()->getRowArray();
        $nombre = array_key_exists('nombre', $perfil) ? $perfil['nombre'] : ($actual['nombre'] ?? null);
        $apellido = array_key_exists('apellido', $perfil) ? $perfil['apellido'] : ($actual['apellido'] ?? null);
        $perfil['nombre_completo'] = trim(($nombre ?? '') . ' ' . ($apellido ?? '')) ?: null;
        $perfil['updated_at'] = date('Y-m-d H:i:s');

        if ($actual !== null) {
            $this->db->table('cliente_perfil')->where('id', $actual['id'])->update($perfil + ['deleted_at' => null]);

            return;
        }
        $this->db->table('cliente_perfil')->insert($perfil + ['id_cliente' => $idCliente, 'created_at' => date('Y-m-d H:i:s')]);
    }
}
