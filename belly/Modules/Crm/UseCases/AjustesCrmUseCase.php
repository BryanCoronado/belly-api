<?php

namespace Belly\Crm\UseCases;

use Belly\Conversacion\Models\EtiquetaModel;
use Belly\Conversacion\Models\MotivoConversacionModel;
use Belly\Crm\Models\ClienteTipoModel;
use Belly\Crm\Models\EmbudoEtapaModel;
use Belly\Crm\Services\ConsultaClientes;
use CodeIgniter\Database\BaseConnection;
use DomainException;

/** Etapas del embudo, tipos de cliente, etiquetas y motivos de cierre: cada empresa arma las suyas. */
class AjustesCrmUseCase
{
    private const ETAPAS_INICIALES = [
        ['Nuevo', '#64748b', 'abierta'],
        ['Contactado', '#0ea5e9', 'abierta'],
        ['Interesado', '#8b5cf6', 'abierta'],
        ['Negociación', '#f59e0b', 'abierta'],
        ['Ganado', '#10b981', 'ganada'],
        ['Perdido', '#f43f5e', 'perdida'],
    ];

    private const TIPOS_INICIALES = [
        ['Prospecto', '#64748b'],
        ['Cliente', '#0ea5e9'],
        ['Frecuente', '#10b981'],
        ['VIP', '#f59e0b'],
    ];

    private const MOTIVOS_INICIALES = [
        ['Venta cerrada', '#10b981'],
        ['No le interesó', '#f43f5e'],
        ['Precio', '#f59e0b'],
        ['No respondió', '#64748b'],
        ['Volver a contactar', '#0ea5e9'],
        ['Gestionado por otro medio', '#8b5cf6'],
        ['Otro', '#94a3b8'],
    ];

    private const RESULTADOS = ['abierta', 'ganada', 'perdida'];

    private BaseConnection $db;
    private ConsultaClientes $consulta;

    public function __construct(?ConsultaClientes $consulta = null)
    {
        $this->db = db_connect();
        $this->consulta = $consulta ?? new ConsultaClientes($this->db);
    }

    /** @return array<string, mixed> */
    public function todo(): array
    {
        return [
            'etapas' => $this->etapas(),
            'tipos' => $this->tipos(),
            'etiquetas' => $this->etiquetas(),
            'motivos' => $this->motivos(),
        ];
    }

    // ----- Etapas -----

    /** @return list<array<string, mixed>> */
    public function etapas(): array
    {
        $this->sembrarSiHaceFalta('embudo_etapa', function (int $idEmpresa) {
            foreach (self::ETAPAS_INICIALES as $orden => [$nombre, $color, $resultado]) {
                $this->db->table('embudo_etapa')->insert([
                    'id_empresa' => $idEmpresa, 'nombre' => $nombre, 'color' => $color, 'orden' => $orden + 1,
                    'resultado' => $resultado, 'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        });

        $filas = (new EmbudoEtapaModel())->orderBy('orden', 'ASC')->orderBy('id', 'ASC')->findAll();
        $totales = $this->totalesPor('id_etapa');
        $primera = isset($filas[0]) ? (int) $filas[0]['id'] : null;

        return array_map(function (array $f) use ($totales, $primera) {
            $id = (int) $f['id'];
            $total = ($totales[$id] ?? 0) + ($id === $primera ? ($totales[0] ?? 0) : 0);

            return [
                'id' => $id,
                'nombre' => $f['nombre'],
                'color' => $f['color'],
                'orden' => (int) $f['orden'],
                'resultado' => $f['resultado'],
                'total_clientes' => $total,
            ];
        }, $filas);
    }

    public function idPrimeraEtapa(): ?int
    {
        $etapas = $this->etapas();

        return $etapas === [] ? null : (int) $etapas[0]['id'];
    }

    /** @param array<string, mixed> $datos @return list<array<string, mixed>> */
    public function guardarEtapa(?int $id, array $datos): array
    {
        $modelo = new EmbudoEtapaModel();
        $actual = $id !== null ? $this->buscar($modelo, $id, 'esa etapa') : null;
        $cambios = [];

        if ($actual === null || array_key_exists('nombre', $datos)) {
            $cambios['nombre'] = $this->nombre($datos['nombre'] ?? null);
            $this->exigirNombreLibre($modelo, $cambios['nombre'], $id, 'Ya tienes una etapa con ese nombre.');
        }
        if ($actual === null || array_key_exists('color', $datos)) {
            $cambios['color'] = $this->color($datos['color'] ?? null);
        }
        if ($actual === null || array_key_exists('resultado', $datos)) {
            $resultado = (string) ($datos['resultado'] ?? 'abierta');
            $cambios['resultado'] = in_array($resultado, self::RESULTADOS, true) ? $resultado : 'abierta';
        }

        if ($actual === null) {
            $this->etapas();
            $cambios['id_empresa'] = $this->consulta->idEmpresa();
            $cambios['orden'] = $this->siguienteOrden('embudo_etapa');
            $modelo->insert($cambios);
        } elseif ($cambios !== []) {
            $modelo->update($id, $cambios);
        }

        return $this->etapas();
    }

    /** @param list<int> $ids @return list<array<string, mixed>> */
    public function ordenarEtapas(array $ids): array
    {
        $this->ordenar(new EmbudoEtapaModel(), $ids);

        return $this->etapas();
    }

    /** Sus clientes pasan a otra etapa (por defecto, la primera que quede). */
    public function eliminarEtapa(int $id, ?int $moverA): array
    {
        $modelo = new EmbudoEtapaModel();
        $this->buscar($modelo, $id, 'esa etapa');
        $restantes = array_values(array_filter($this->etapas(), static fn (array $e) => $e['id'] !== $id));
        if ($restantes === []) {
            throw new DomainException('El embudo necesita al menos una etapa.');
        }

        $destino = $moverA !== null && in_array($moverA, array_column($restantes, 'id'), true) ? $moverA : (int) $restantes[0]['id'];
        $this->db->table('cliente')
            ->where('id_empresa', $this->consulta->idEmpresa())
            ->where('id_etapa', $id)
            ->update(['id_etapa' => $destino, 'etapa_at' => date('Y-m-d H:i:s')]);
        $modelo->delete($id);

        return $this->etapas();
    }

    // ----- Tipos -----

    /** @return list<array<string, mixed>> */
    public function tipos(): array
    {
        $this->sembrarSiHaceFalta('cliente_tipo', function (int $idEmpresa) {
            foreach (self::TIPOS_INICIALES as $orden => [$nombre, $color]) {
                $this->db->table('cliente_tipo')->insert([
                    'id_empresa' => $idEmpresa, 'nombre' => $nombre, 'color' => $color, 'orden' => $orden + 1,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        });

        $filas = (new ClienteTipoModel())->orderBy('orden', 'ASC')->orderBy('id', 'ASC')->findAll();
        $totales = $this->totalesPor('id_tipo');

        return array_map(static fn (array $f) => [
            'id' => (int) $f['id'],
            'nombre' => $f['nombre'],
            'color' => $f['color'],
            'orden' => (int) $f['orden'],
            'total_clientes' => $totales[(int) $f['id']] ?? 0,
        ], $filas);
    }

    /** @param array<string, mixed> $datos @return list<array<string, mixed>> */
    public function guardarTipo(?int $id, array $datos): array
    {
        $modelo = new ClienteTipoModel();
        $actual = $id !== null ? $this->buscar($modelo, $id, 'ese tipo') : null;
        $cambios = [];

        if ($actual === null || array_key_exists('nombre', $datos)) {
            $cambios['nombre'] = $this->nombre($datos['nombre'] ?? null);
            $this->exigirNombreLibre($modelo, $cambios['nombre'], $id, 'Ya tienes un tipo con ese nombre.');
        }
        if ($actual === null || array_key_exists('color', $datos)) {
            $cambios['color'] = $this->color($datos['color'] ?? null);
        }

        if ($actual === null) {
            $this->tipos();
            $cambios['id_empresa'] = $this->consulta->idEmpresa();
            $cambios['orden'] = $this->siguienteOrden('cliente_tipo');
            $modelo->insert($cambios);
        } elseif ($cambios !== []) {
            $modelo->update($id, $cambios);
        }

        return $this->tipos();
    }

    /** @param list<int> $ids @return list<array<string, mixed>> */
    public function ordenarTipos(array $ids): array
    {
        $this->ordenar(new ClienteTipoModel(), $ids);

        return $this->tipos();
    }

    /** Los clientes de ese tipo quedan sin tipo. */
    public function eliminarTipo(int $id): array
    {
        $modelo = new ClienteTipoModel();
        $this->buscar($modelo, $id, 'ese tipo');
        $this->db->table('cliente')
            ->where('id_empresa', $this->consulta->idEmpresa())
            ->where('id_tipo', $id)
            ->update(['id_tipo' => null]);
        $modelo->delete($id);

        return $this->tipos();
    }

    // ----- Etiquetas -----

    /** @return list<array<string, mixed>> */
    public function etiquetas(): array
    {
        $idEmpresa = $this->consulta->idEmpresa();
        $totales = [];
        $filas = $this->db->table('cliente_etiqueta ce')
            ->select('ce.id_etiqueta, COUNT(*) AS total')
            ->join('cliente c', 'c.id = ce.id_cliente AND c.deleted_at IS NULL')
            ->where('ce.id_empresa', $idEmpresa)
            ->groupBy('ce.id_etiqueta')
            ->get()->getResultArray();
        foreach ($filas as $fila) {
            $totales[(int) $fila['id_etiqueta']] = (int) $fila['total'];
        }

        $etiquetas = (new EtiquetaModel())->asArray()->orderBy('nombre', 'ASC')->findAll();

        return array_map(static fn (array $e) => [
            'id' => (int) $e['id'],
            'nombre' => $e['nombre'],
            'color' => $e['color'] ?: '#64748b',
            'total_clientes' => $totales[(int) $e['id']] ?? 0,
        ], $etiquetas);
    }

    /** @param array<string, mixed> $datos @return list<array<string, mixed>> */
    public function guardarEtiqueta(?int $id, array $datos): array
    {
        $modelo = new EtiquetaModel();
        if ($id !== null) {
            $this->buscar($modelo->asArray(), $id, 'esa etiqueta');
        }
        $cambios = [];
        if ($id === null || array_key_exists('nombre', $datos)) {
            $cambios['nombre'] = $this->nombre($datos['nombre'] ?? null);
            $this->exigirNombreLibre(new EtiquetaModel(), $cambios['nombre'], $id, 'Ya tienes una etiqueta con ese nombre.');
        }
        if ($id === null || array_key_exists('color', $datos)) {
            $cambios['color'] = $this->color($datos['color'] ?? null);
        }

        if ($id === null) {
            $cambios['id_empresa'] = $this->consulta->idEmpresa();
            (new EtiquetaModel())->insert($cambios);
        } elseif ($cambios !== []) {
            (new EtiquetaModel())->update($id, $cambios);
        }

        return $this->etiquetas();
    }

    /** Se quita de todos los clientes y chats que la llevaban. */
    public function eliminarEtiqueta(int $id): array
    {
        $this->buscar((new EtiquetaModel())->asArray(), $id, 'esa etiqueta');
        $idEmpresa = $this->consulta->idEmpresa();
        $this->db->table('cliente_etiqueta')->where('id_empresa', $idEmpresa)->where('id_etiqueta', $id)->delete();
        $this->db->table('conversacion')->where('id_empresa', $idEmpresa)->where('id_etiqueta', $id)->update(['id_etiqueta' => null]);
        // El nombre es único por empresa aunque la fila esté borrada: se libera para poder volver a usarlo.
        $this->db->table('etiqueta')->where('id_empresa', $idEmpresa)->where('id', $id)
            ->set('nombre', "LEFT(CONCAT('~', id, ' ', nombre), 100)", false)->update();
        (new EtiquetaModel())->delete($id);

        return $this->etiquetas();
    }

    // ----- Motivos de cierre -----

    /** @return list<array<string, mixed>> */
    public function motivos(): array
    {
        $this->sembrarMotivosSiHaceFalta();

        $filas = (new MotivoConversacionModel())->asArray()->orderBy('orden', 'ASC')->orderBy('id', 'ASC')->findAll();
        $totales = $this->totalesMotivo();

        return array_map(static fn (array $f) => [
            'id' => (int) $f['id'],
            'nombre' => $f['nombre'],
            'color' => $f['color'] ?: '#64748b',
            'orden' => (int) $f['orden'],
            'total_conversaciones' => $totales[(int) $f['id']] ?? 0,
        ], $filas);
    }

    public function exigirMotivo(int $id): void
    {
        $ids = array_column($this->motivos(), 'id');
        if (! in_array($id, $ids, true)) {
            throw new DomainException('Ese motivo no existe en tu empresa.');
        }
    }

    /** @param array<string, mixed> $datos @return list<array<string, mixed>> */
    public function guardarMotivo(?int $id, array $datos): array
    {
        $modelo = new MotivoConversacionModel();
        $actual = $id !== null ? $this->buscar($modelo, $id, 'ese motivo') : null;
        $cambios = [];

        if ($actual === null || array_key_exists('nombre', $datos)) {
            $cambios['nombre'] = $this->nombre($datos['nombre'] ?? null);
            $this->exigirNombreLibre($modelo, $cambios['nombre'], $id, 'Ya tienes un motivo con ese nombre.');
        }
        if ($actual === null || array_key_exists('color', $datos)) {
            $cambios['color'] = $this->color($datos['color'] ?? null);
        }

        if ($actual === null) {
            $this->motivos();
            $cambios['id_empresa'] = $this->consulta->idEmpresa();
            $cambios['orden'] = $this->siguienteOrden('motivo_conversacion');
            $modelo->insert($cambios);
        } elseif ($cambios !== []) {
            $modelo->update($id, $cambios);
        }

        return $this->motivos();
    }

    /** @param list<int> $ids @return list<array<string, mixed>> */
    public function ordenarMotivos(array $ids): array
    {
        $this->ordenar(new MotivoConversacionModel(), $ids);

        return $this->motivos();
    }

    public function eliminarMotivo(int $id): array
    {
        $modelo = new MotivoConversacionModel();
        $this->buscar($modelo, $id, 'ese motivo');
        if (count($this->motivos()) <= 1) {
            throw new DomainException('Necesitas al menos un motivo para cerrar chats.');
        }
        $modelo->delete($id);

        return $this->motivos();
    }

    // ----- Apoyo -----

    /** Crea los valores de ejemplo la primera vez que la empresa entra (si luego los borra, no vuelven). */
    private function sembrarSiHaceFalta(string $tabla, callable $sembrar): void
    {
        $idEmpresa = $this->consulta->idEmpresa();
        if ($this->db->table($tabla)->where('id_empresa', $idEmpresa)->countAllResults() > 0) {
            return;
        }
        $sembrar($idEmpresa);
    }

    /** Si la empresa ya tenía motivos globales, se los copia; si no, arma una lista corta. */
    private function sembrarMotivosSiHaceFalta(): void
    {
        $this->sembrarSiHaceFalta('motivo_conversacion', function (int $idEmpresa) {
            $globales = $this->db->table('motivo_conversacion')
                ->where('id_empresa', null)
                ->where('deleted_at', null)
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();
            $fuente = $globales !== []
                ? $globales
                : array_map(static fn (array $m) => ['nombre' => $m[0], 'color' => $m[1]], self::MOTIVOS_INICIALES);

            foreach ($fuente as $orden => $fila) {
                $this->db->table('motivo_conversacion')->insert([
                    'id_empresa' => $idEmpresa,
                    'nombre' => $fila['nombre'],
                    'color' => $fila['color'] ?? '#64748b',
                    'orden' => $orden + 1,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        });
    }

    /** @return array<int, int> */
    private function totalesMotivo(): array
    {
        $filas = $this->db->table('conversacion')
            ->select('id_motivo, COUNT(*) AS total')
            ->where('id_empresa', $this->consulta->idEmpresa())
            ->where('deleted_at', null)
            ->where('id_motivo IS NOT NULL', null, false)
            ->groupBy('id_motivo')
            ->get()
            ->getResultArray();
        $totales = [];
        foreach ($filas as $fila) {
            $totales[(int) $fila['id_motivo']] = (int) $fila['total'];
        }

        return $totales;
    }

    /** @return array<int, int> Clave 0: clientes sin valor. */
    private function totalesPor(string $columna): array
    {
        $filas = $this->db->table('cliente')
            ->select("COALESCE({$columna}, 0) AS clave, COUNT(*) AS total", false)
            ->where('id_empresa', $this->consulta->idEmpresa())
            ->where('deleted_at', null)
            ->groupBy('clave')
            ->get()->getResultArray();
        $totales = [];
        foreach ($filas as $fila) {
            $totales[(int) $fila['clave']] = (int) $fila['total'];
        }

        return $totales;
    }

    /** @param list<int> $ids */
    private function ordenar(\CodeIgniter\Model $modelo, array $ids): void
    {
        $validos = array_map('intval', array_column($modelo->asArray()->select('id')->findAll(), 'id'));
        $orden = 1;
        foreach (array_map('intval', $ids) as $id) {
            if (in_array($id, $validos, true)) {
                $modelo->update($id, ['orden' => $orden++]);
            }
        }
    }

    private function siguienteOrden(string $tabla): int
    {
        $fila = $this->db->table($tabla)
            ->selectMax('orden', 'maximo')
            ->where('id_empresa', $this->consulta->idEmpresa())
            ->where('deleted_at', null)
            ->get()->getRowArray();

        return (int) ($fila['maximo'] ?? 0) + 1;
    }

    /** @return array<string, mixed> */
    private function buscar(\CodeIgniter\Model $modelo, int $id, string $que): array
    {
        $fila = $modelo->asArray()->find($id);
        if (! is_array($fila)) {
            throw new DomainException("No encontramos {$que}.");
        }

        return $fila;
    }

    private function exigirNombreLibre(\CodeIgniter\Model $modelo, string $nombre, ?int $id, string $mensaje): void
    {
        $modelo->asArray()->where('nombre', $nombre);
        if ($id !== null) {
            $modelo->where('id !=', $id);
        }
        if ($modelo->first() !== null) {
            throw new DomainException($mensaje);
        }
    }

    private function nombre(mixed $valor): string
    {
        $nombre = trim(preg_replace('/\s+/u', ' ', (string) ($valor ?? '')) ?? '');
        if ($nombre === '') {
            throw new DomainException('Escribe un nombre.');
        }
        if (mb_strlen($nombre) > 60) {
            throw new DomainException('El nombre no puede pasar de 60 letras.');
        }

        return $nombre;
    }

    private function color(mixed $valor): string
    {
        $color = strtolower(trim((string) ($valor ?? '')));

        return preg_match('/^#[0-9a-f]{6}$/', $color) === 1 ? $color : '#64748b';
    }
}
