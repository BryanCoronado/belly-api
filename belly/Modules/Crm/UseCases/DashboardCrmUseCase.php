<?php

namespace Belly\Crm\UseCases;

use Belly\Crm\Services\ConsultaClientes;
use Belly\Services\Autorizacion;
use Belly\Usuarios\Services\ConsultaAsesores;
use CodeIgniter\Database\BaseConnection;
use DomainException;

/** Números del inicio: el asesor ve lo suyo; el administrador, la empresa. */
class DashboardCrmUseCase
{
    private BaseConnection $db;
    private ConsultaClientes $consulta;
    private AjustesCrmUseCase $ajustes;

    public function __construct(?ConsultaClientes $consulta = null, ?AjustesCrmUseCase $ajustes = null)
    {
        $this->db = db_connect();
        $this->consulta = $consulta ?? new ConsultaClientes($this->db);
        $this->ajustes = $ajustes ?? new AjustesCrmUseCase($this->consulta);
    }

    /** @return array<string, mixed> */
    public function resumen(int $dias = 14): array
    {
        $dias = in_array($dias, [7, 14, 30], true) ? $dias : 14;
        $admin = Autorizacion::esAdministradorEmpresa();
        $usuario = Autorizacion::usuario();
        if ($usuario === null) {
            throw new DomainException('Tu sesión terminó. Vuelve a entrar.');
        }

        $idEmpresa = $this->consulta->idEmpresa();
        $idUsuario = $admin ? null : (int) $usuario->id;
        $desde = date('Y-m-d 00:00:00', strtotime("-{$dias} days"));

        return [
            'rol' => $admin ? 'admin' : 'asesor',
            'dias' => $dias,
            'cuadros' => $this->cuadros($idEmpresa, $idUsuario, $desde, $admin),
            'actividad' => $this->actividad($idEmpresa, $idUsuario, $dias, $desde),
            'embudo' => $this->conteoPorLista($this->ajustes->etapas(), $this->totalesEtapa($idUsuario)),
            'tipos' => $this->conteoPorLista($this->ajustes->tipos(), $this->totalesTipo($idUsuario), true),
            'motivos' => $this->conteoPorLista($this->ajustes->motivos(), $this->totalesMotivo($idEmpresa, $idUsuario, $desde)),
            'pendientes' => $this->pendientes($idEmpresa, $idUsuario),
            'equipo' => $admin ? $this->equipo($idEmpresa, $desde) : [],
        ];
    }

    /** @return array<string, int> */
    private function cuadros(int $idEmpresa, ?int $idUsuario, string $desde, bool $admin): array
    {
        $alcance = $this->alcanceConversacion($idEmpresa, $idUsuario);
        $inicioVentana = $this->consulta->inicioVentana();

        $cuadros = [
            'sin_leer' => $this->entero(
                "SELECT COUNT(*) AS total FROM mensaje m
                 INNER JOIN conversacion c ON c.id = m.id_conversacion
                 WHERE {$alcance}
                   AND c.id_estado IN (1, 3)
                   AND m.deleted_at IS NULL AND m.contesta = 'cliente' AND m.id_estado = 3"
            ),
            'por_responder' => $this->entero(
                "SELECT COUNT(*) AS total FROM conversacion c
                 WHERE {$alcance} AND c.id_estado IN (1, 3)
                   AND {$this->sqlUltimoContesta()} = 'cliente'"
            ),
            'abiertos' => $this->entero("SELECT COUNT(*) AS total FROM conversacion c WHERE {$alcance} AND c.id_estado IN (1, 3)"),
            'cerrados' => $this->entero("SELECT COUNT(*) AS total FROM conversacion c WHERE {$alcance} AND c.id_estado = 2 AND c.updated_at >= " . $this->db->escape($desde)),
            'clientes' => $this->consulta->builder([], $this->ajustes->idPrimeraEtapa())->countAllResults(),
            'nuevos' => $this->consulta->builder([], $this->ajustes->idPrimeraEtapa())
                ->where('c.created_at >=', $desde)->countAllResults(),
            'ventana_abierta' => $this->consulta->builder(['ventana' => 'abierta'], $this->ajustes->idPrimeraEtapa())->countAllResults(),
            'ganados' => $this->clientesPorResultado($idEmpresa, $idUsuario, $desde, 'ganada'),
            'perdidos' => $this->clientesPorResultado($idEmpresa, $idUsuario, $desde, 'perdida'),
        ];

        if ($admin) {
            $cuadros['equipo_activo'] = $this->entero(
                'SELECT COUNT(*) AS total FROM usuario u WHERE ' . ConsultaAsesores::condicion(),
                [$idEmpresa]
            );
            $cuadros['sin_asignar'] = $this->entero(
                "SELECT COUNT(*) AS total FROM conversacion c
                 LEFT JOIN usuario u ON u.id = c.id_usuario
                 WHERE c.id_empresa = {$idEmpresa} AND c.deleted_at IS NULL AND c.id_estado IN (1, 3)
                   AND (c.id_usuario IS NULL OR u.es_bot = 1)"
            );
        }

        return $cuadros;
    }

    /** @return list<array{fecha:string,recibidos:int,enviados:int,cerrados:int}> */
    private function actividad(int $idEmpresa, ?int $idUsuario, int $dias, string $desde): array
    {
        $mapa = [];
        for ($i = $dias - 1; $i >= 0; $i--) {
            $fecha = date('Y-m-d', strtotime("-{$i} days"));
            $mapa[$fecha] = ['fecha' => $fecha, 'recibidos' => 0, 'enviados' => 0, 'cerrados' => 0];
        }

        $alcance = $this->alcanceConversacion($idEmpresa, $idUsuario);
        $desdeSql = $this->db->escape($desde);

        $mensajes = $this->db->query(
            "SELECT DATE(m.created_at) AS fecha,
                    SUM(m.contesta = 'cliente') AS recibidos,
                    SUM(m.contesta <> 'cliente') AS enviados
             FROM mensaje m
             INNER JOIN conversacion c ON c.id = m.id_conversacion
             WHERE {$alcance} AND m.deleted_at IS NULL AND m.created_at >= {$desdeSql}
             GROUP BY DATE(m.created_at)"
        )->getResultArray();
        foreach ($mensajes as $fila) {
            $fecha = (string) $fila['fecha'];
            if (isset($mapa[$fecha])) {
                $mapa[$fecha]['recibidos'] = (int) $fila['recibidos'];
                $mapa[$fecha]['enviados'] = (int) $fila['enviados'];
            }
        }

        $cierres = $this->db->query(
            "SELECT DATE(c.updated_at) AS fecha, COUNT(*) AS total
             FROM conversacion c
             WHERE {$alcance} AND c.id_estado = 2 AND c.updated_at >= {$desdeSql}
             GROUP BY DATE(c.updated_at)"
        )->getResultArray();
        foreach ($cierres as $fila) {
            $fecha = (string) $fila['fecha'];
            if (isset($mapa[$fecha])) {
                $mapa[$fecha]['cerrados'] = (int) $fila['total'];
            }
        }

        return array_values($mapa);
    }

    /** @return array<int, int> */
    private function totalesEtapa(?int $idUsuario): array
    {
        $primera = $this->ajustes->idPrimeraEtapa() ?? 0;
        $clave = "COALESCE(c.id_etapa, {$primera})";
        $builder = $this->consulta->builder($idUsuario ? ['id_asesor' => $idUsuario] : [], $primera)
            ->select("{$clave} AS clave, COUNT(*) AS total", false)
            ->groupBy($clave, false);

        return $this->mapaConteo($builder->get()->getResultArray());
    }

    /** @return array<int, int> */
    private function totalesTipo(?int $idUsuario): array
    {
        $builder = $this->consulta->builder($idUsuario ? ['id_asesor' => $idUsuario] : [], $this->ajustes->idPrimeraEtapa())
            ->select('COALESCE(c.id_tipo, 0) AS clave, COUNT(*) AS total', false)
            ->groupBy('COALESCE(c.id_tipo, 0)', false);

        return $this->mapaConteo($builder->get()->getResultArray());
    }

    /** @return array<int, int> */
    private function totalesMotivo(int $idEmpresa, ?int $idUsuario, string $desde): array
    {
        $alcance = $this->alcanceConversacion($idEmpresa, $idUsuario);
        $filas = $this->db->query(
            "SELECT c.id_motivo AS clave, COUNT(*) AS total
             FROM conversacion c
             WHERE {$alcance} AND c.id_estado = 2 AND c.id_motivo IS NOT NULL
               AND c.updated_at >= " . $this->db->escape($desde) . '
             GROUP BY c.id_motivo'
        )->getResultArray();

        return $this->mapaConteo($filas);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param array<int, int> $totales
     * @return list<array<string, mixed>>
     */
    private function conteoPorLista(array $items, array $totales, bool $incluirSin = false): array
    {
        $lista = array_map(static fn (array $item) => [
            'id' => (int) $item['id'],
            'nombre' => $item['nombre'],
            'color' => $item['color'] ?: '#64748b',
            'total' => $totales[(int) $item['id']] ?? 0,
        ], $items);

        if ($incluirSin && ($totales[0] ?? 0) > 0) {
            $lista[] = ['id' => 0, 'nombre' => 'Sin tipo', 'color' => '#94a3b8', 'total' => $totales[0]];
        }

        return $lista;
    }

    /** @return list<array<string, mixed>> */
    private function pendientes(int $idEmpresa, ?int $idUsuario): array
    {
        $alcance = $this->alcanceConversacion($idEmpresa, $idUsuario);
        $filas = $this->db->query(
            "SELECT c.id,
                    cl.telefono,
                    cp.nombre,
                    cp.apellido,
                    cp.nombre_completo,
                    (SELECT m.mensaje FROM mensaje m WHERE m.id_conversacion = c.id AND m.deleted_at IS NULL ORDER BY m.id DESC LIMIT 1) AS preview,
                    (SELECT m.created_at FROM mensaje m WHERE m.id_conversacion = c.id AND m.deleted_at IS NULL ORDER BY m.id DESC LIMIT 1) AS fecha,
                    (SELECT COUNT(*) FROM mensaje m WHERE m.id_conversacion = c.id AND m.deleted_at IS NULL AND m.contesta = 'cliente' AND m.id_estado = 3) AS sin_leer
             FROM conversacion c
             INNER JOIN cliente cl ON cl.id = c.id_cliente
             LEFT JOIN cliente_perfil cp ON cp.id_cliente = cl.id AND cp.deleted_at IS NULL
             WHERE {$alcance} AND c.id_estado IN (1, 3)
               AND {$this->sqlUltimoContesta()} = 'cliente'
             ORDER BY fecha DESC
             LIMIT 8"
        )->getResultArray();

        return array_map(static function (array $fila) {
            $nombre = trim((string) ($fila['nombre_completo'] ?: trim(($fila['nombre'] ?? '') . ' ' . ($fila['apellido'] ?? ''))));

            return [
                'id' => (int) $fila['id'],
                'nombre' => $nombre !== '' ? $nombre : ($fila['telefono'] ?: 'Sin nombre'),
                'telefono' => $fila['telefono'] ?: null,
                'preview' => $fila['preview'] ?: null,
                'fecha' => $fila['fecha'] ?: null,
                'sin_leer' => (int) $fila['sin_leer'],
            ];
        }, $filas);
    }

    /** @return list<array<string, mixed>> */
    private function equipo(int $idEmpresa, string $desde): array
    {
        $desdeSql = $this->db->escape($desde);
        $sql = 'SELECT u.id, u.usuario, u.activo, p.nombres, p.apellidos, p.foto_url,
                    (SELECT COUNT(*) FROM conversacion c
                      WHERE c.id_usuario = u.id AND c.deleted_at IS NULL AND c.id_estado IN (1, 3)) AS abiertos,
                    (SELECT COUNT(*) FROM conversacion c
                      WHERE c.id_usuario = u.id AND c.deleted_at IS NULL AND c.id_estado = 2 AND c.updated_at >= ' . $desdeSql . ') AS cerrados,
                    (SELECT COUNT(*) FROM mensaje m
                      INNER JOIN conversacion c ON c.id = m.id_conversacion
                      WHERE c.id_usuario = u.id AND c.deleted_at IS NULL AND c.id_estado IN (1, 3)
                        AND m.deleted_at IS NULL AND m.contesta = \'cliente\' AND m.id_estado = 3) AS sin_leer,
                    (SELECT COUNT(*) FROM cliente cl
                      WHERE cl.id_empresa = u.id_empresa AND cl.deleted_at IS NULL
                        AND (cl.id_responsable = u.id
                             OR cl.id IN (SELECT a.id_cliente FROM conversacion a WHERE a.deleted_at IS NULL AND a.id_usuario = u.id))) AS clientes
                FROM usuario u
                LEFT JOIN usuario_perfil p ON p.id_usuario = u.id AND p.deleted_at IS NULL
                WHERE ' . ConsultaAsesores::condicion(false) . '
                ORDER BY u.activo DESC, cerrados DESC, abiertos DESC, p.nombres ASC';

        $equipo = [];
        foreach ($this->db->query($sql, [$idEmpresa])->getResultArray() as $fila) {
            $nombre = trim(($fila['nombres'] ?? '') . ' ' . ($fila['apellidos'] ?? ''));
            $equipo[] = [
                'id' => (int) $fila['id'],
                'nombre' => $nombre !== '' ? $nombre : (string) $fila['usuario'],
                'foto' => $fila['foto_url'] ?: null,
                'activo' => (int) $fila['activo'] === 1,
                'abiertos' => (int) $fila['abiertos'],
                'cerrados' => (int) $fila['cerrados'],
                'sin_leer' => (int) $fila['sin_leer'],
                'clientes' => (int) $fila['clientes'],
            ];
        }

        return $equipo;
    }

    private function clientesPorResultado(int $idEmpresa, ?int $idUsuario, string $desde, string $resultado): int
    {
        $sql = "SELECT COUNT(*) AS total
                FROM cliente c
                INNER JOIN embudo_etapa e ON e.id = c.id_etapa AND e.deleted_at IS NULL
                WHERE c.id_empresa = {$idEmpresa} AND c.deleted_at IS NULL
                  AND e.resultado = " . $this->db->escape($resultado) . '
                  AND COALESCE(c.etapa_at, c.created_at) >= ' . $this->db->escape($desde);
        if ($idUsuario !== null) {
            $sql .= " AND (c.id_responsable = {$idUsuario}
                     OR c.id IN (SELECT a.id_cliente FROM conversacion a WHERE a.deleted_at IS NULL AND a.id_usuario = {$idUsuario}))";
        }

        return $this->entero($sql);
    }

    private function alcanceConversacion(int $idEmpresa, ?int $idUsuario): string
    {
        $sql = "c.id_empresa = {$idEmpresa} AND c.deleted_at IS NULL";
        if ($idUsuario !== null) {
            $sql .= " AND c.id_usuario = {$idUsuario}";
        }

        return $sql;
    }

    private function sqlUltimoContesta(string $alias = 'c'): string
    {
        return "(SELECT m.contesta FROM mensaje m
                 WHERE m.id_conversacion = {$alias}.id AND m.deleted_at IS NULL
                 ORDER BY m.id DESC LIMIT 1)";
    }

    /** @param list<array<string, mixed>> $filas @return array<int, int> */
    private function mapaConteo(array $filas): array
    {
        $mapa = [];
        foreach ($filas as $fila) {
            $mapa[(int) $fila['clave']] = (int) $fila['total'];
        }

        return $mapa;
    }

    /** @param list<int|string> $binds */
    private function entero(string $sql, array $binds = []): int
    {
        $fila = $binds === []
            ? $this->db->query($sql)->getRowArray()
            : $this->db->query($sql, $binds)->getRowArray();

        return (int) ($fila['total'] ?? 0);
    }
}
