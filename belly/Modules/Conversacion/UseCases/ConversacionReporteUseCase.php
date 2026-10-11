<?php
namespace Belly\Conversacion\UseCases;

use Belly\Conversacion\Config\ConversacionConfig;
use Belly\Conversacion\Entities\ReporteLeadsPorAsesorItemEntity;
use Belly\Conversacion\Models\ConversacionModel;
use Belly\Clientes\Models\ClienteModel;

class ConversacionReporteUseCase {

    /** Vacío a propósito: las métricas cuentan también al bot y a usuarios de prueba. */
    private const IDS_USUARIO_EXCLUIDOS_METRICAS_REPORTE = [];

    protected ConversacionConfig $config;
    protected ?ConversacionModel $conversacion_provider;
    protected ?ClienteModel $cliente_provider;

    public function __construct(ConversacionConfig $config)
    {
        $this->config = $config;
        $this->conversacion_provider = null;
        $this->cliente_provider = null;
    }

    public function getConversacionProvider(): ConversacionModel
    {
        if ($this->conversacion_provider !== null) {
            return $this->conversacion_provider;
        }

        $class_name = $this->config->conversacion_provider;
        $this->conversacion_provider = new $class_name();

        return $this->conversacion_provider;
    }

    private function sqlFragmentoExcluirUsuariosMetricasReporte(): string
    {
        if (self::IDS_USUARIO_EXCLUIDOS_METRICAS_REPORTE === []) {
            return '';
        }

        $ids = implode(',', array_map('intval', self::IDS_USUARIO_EXCLUIDOS_METRICAS_REPORTE));

        return ' AND c.id_usuario NOT IN (' . $ids . ') ';
    }


    public function obtenerLeadsPorAsesor($fechaInicio = null, $fechaFin = null, $gestion = 'captacion')
    {
        [$fechaInicio, $fechaFin] = $this->normalizarRangoFechas($fechaInicio, $fechaFin);

        $db = \Config\Database::connect();
        $params = [$fechaInicio, $fechaFin];

        $tempNorm = $this->sqlExpresionTemperaturaNormalizada('c');
        $excluirUsuarios = $this->sqlFragmentoExcluirUsuariosMetricasReporte();

        $sqlAgregados = <<<SQL
SELECT
    u.id AS id_usuario,
    up.nombres,
    up.apellidos,
    COUNT(*) AS total_conversaciones,
    SUM(CASE WHEN c.id_estado = 1 THEN 1 ELSE 0 END) AS cnt_estado_1_en_atencion,
    SUM(CASE WHEN c.id_estado = 2 THEN 1 ELSE 0 END) AS cnt_estado_2_finalizado,
    SUM(CASE WHEN c.id_estado = 3 THEN 1 ELSE 0 END) AS cnt_estado_3_sin_abrir,
    SUM(CASE WHEN c.id_estado IS NOT NULL AND c.id_estado NOT IN (1, 2, 3) THEN 1 ELSE 0 END) AS cnt_estado_otros,
    SUM(CASE WHEN {$tempNorm} = 'frio' THEN 1 ELSE 0 END) AS cnt_temp_frio,
    SUM(CASE WHEN {$tempNorm} = 'tibio' THEN 1 ELSE 0 END) AS cnt_temp_tibio,
    SUM(CASE WHEN {$tempNorm} = 'caliente' THEN 1 ELSE 0 END) AS cnt_temp_caliente,
    SUM(CASE WHEN {$tempNorm} = 'venta' THEN 1 ELSE 0 END) AS cnt_temp_venta
FROM conversacion c
INNER JOIN usuario u ON c.id_usuario = u.id
LEFT JOIN usuario_perfil up ON u.id = up.id_usuario
WHERE DATE(c.created_at) >= ?
    AND DATE(c.created_at) <= ?
    AND c.id_usuario IS NOT NULL
    AND c.deleted_at IS NULL
    {$excluirUsuarios}
GROUP BY u.id, up.nombres, up.apellidos
ORDER BY u.id
SQL;

        $filas = $db->query($sqlAgregados, $params)->getResult();

        $sqlEtiquetas = 'SELECT
                c.id_usuario,
                c.id_etiqueta,
                COALESCE(MAX(e.nombre), CASE WHEN c.id_etiqueta IS NULL THEN \'Sin etiqueta\' ELSE CONCAT(\'Etiqueta #\', c.id_etiqueta) END) AS etiqueta_nombre,
                COUNT(*) AS cantidad
            FROM conversacion c
            LEFT JOIN etiqueta e ON e.id = c.id_etiqueta AND e.deleted_at IS NULL
            WHERE DATE(c.created_at) >= ?
                AND DATE(c.created_at) <= ?
                AND c.id_usuario IS NOT NULL
                AND c.deleted_at IS NULL
                ' . $this->sqlFragmentoExcluirUsuariosMetricasReporte() . '
            GROUP BY c.id_usuario, c.id_etiqueta
            ORDER BY c.id_usuario, cantidad DESC';

        $filasEtiqueta = $db->query($sqlEtiquetas, $params)->getResult();
        $db->close();

        $etiquetasPorUsuario = [];
        foreach ($filasEtiqueta as $fe) {
            $uid = (int) $fe->id_usuario;
            if (!isset($etiquetasPorUsuario[$uid])) {
                $etiquetasPorUsuario[$uid] = [];
            }
            $etiquetasPorUsuario[$uid][] = [
                'id_etiqueta' => $fe->id_etiqueta !== null ? (int) $fe->id_etiqueta : null,
                'nombre' => $fe->etiqueta_nombre,
                'cantidad' => (int) $fe->cantidad,
            ];
        }

        $asesores = [];
        foreach ($filas as $row) {
            $total = (int) $row->total_conversaciones;
            $uid = (int) $row->id_usuario;
            $asesores[] = ReporteLeadsPorAsesorItemEntity::desdeAgregadosSql(
                $row,
                $etiquetasPorUsuario[$uid] ?? [],
                $total
            );
        }

        return [
            'leyenda' => 'Por cada asesor, los tres bloques en para_graficos (estado, temperatura, etiqueta) son tres vistas del mismo conjunto de conversaciones: en cada bloque la suma de cantidades coincide con total_conversaciones de ese asesor. Para tortas/barras usa porcentaje dentro de cada bloque.',
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'asesores' => $asesores,
        ];
    }


    public function obtenerTasaCierrePorAsesor(?string $fechaInicio = null, ?string $fechaFin = null): array
    {
        [$fechaInicio, $fechaFin] = $this->normalizarRangoFechas($fechaInicio, $fechaFin);

        return $this->armarPayloadTasaCierrePorAsesor(
            $this->consultarTasaCierrePorAsesorFilas($fechaInicio, $fechaFin),
            $fechaInicio,
            $fechaFin
        );
    }

    /**
     * Filas agregadas por asesor: total conversaciones y ventas cerradas (estado 2, temperatura venta, etiqueta Entregado).
     *
     * @return list<object>
     */
    private function consultarTasaCierrePorAsesorFilas(string $fechaInicio, string $fechaFin): array
    {
        $db = \Config\Database::connect();
        $sql = 'SELECT
                    u.id AS id_usuario,
                    up.nombres,
                    up.apellidos,
                    COUNT(DISTINCT c.id) AS total_conversaciones,
                    COUNT(DISTINCT CASE
                        WHEN c.id_estado = 2
                            AND LOWER(TRIM(COALESCE(c.temperatura, \'\'))) = \'venta\'
                            AND c.id_etiqueta = 6
                        THEN c.id END) AS ventas_cerradas
                FROM conversacion c
                INNER JOIN usuario u ON c.id_usuario = u.id
                LEFT JOIN usuario_perfil up ON u.id = up.id_usuario
                WHERE DATE(c.created_at) >= ?
                    AND DATE(c.created_at) <= ?
                    AND c.id_usuario IS NOT NULL
                    AND c.deleted_at IS NULL
                    ' . $this->sqlFragmentoExcluirUsuariosMetricasReporte() . '
                GROUP BY u.id, up.nombres, up.apellidos
                ORDER BY u.id';
        $rows = $db->query($sql, [$fechaInicio, $fechaFin])->getResult();
        $db->close();

        return $rows;
    }

    /**
     * @param list<object> $filas
     * @return array{fecha_inicio: string, fecha_fin: string, asesores: list<array<string, mixed>>}
     */
    private function armarPayloadTasaCierrePorAsesor(array $filas, string $fechaInicio, string $fechaFin): array
    {
        $asesores = [];
        foreach ($filas as $row) {
            $total = (int) $row->total_conversaciones;
            $ventas = (int) $row->ventas_cerradas;
            $tasa = $total > 0 ? round(100 * $ventas / $total, 2) : 0.0;

            $asesores[] = [
                'id_usuario' => (int) $row->id_usuario,
                'nombres' => $row->nombres,
                'apellidos' => $row->apellidos,
                'total_conversaciones' => $total,
                'ventas_cerradas' => $ventas,
                'tasa_cierre_pct' => $tasa,
            ];
        }

        return [
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'asesores' => $asesores,
        ];
    }

    public function obtenerMetricasCampanias(?string $fechaInicio = null, ?string $fechaFin = null, $idApp = null): array
    {
        [$fechaInicio, $fechaFin] = $this->normalizarRangoFechas($fechaInicio, $fechaFin);

        $idAppFiltro = null;
        if ($idApp !== null && $idApp !== '' && $idApp !== '0') {
            $idAppFiltro = (int) $idApp;
        }

        $params = [$fechaInicio, $fechaFin];
        $sqlFiltroApp = '';
        if ($idAppFiltro !== null) {
            $sqlFiltroApp = ' AND c.id_app = ? ';
            $params[] = $idAppFiltro;
        }

        $db = \Config\Database::connect();
        $tempNorm = $this->sqlExpresionTemperaturaNormalizada('c');
        $excluirUsuarios = $this->sqlFragmentoExcluirUsuariosMetricasReporte();
        $sql = <<<SQL
SELECT
    c.id_app,
    MAX(NULLIF(TRIM(ccm.campaign_id), '')) AS campaign_id,
    MAX(ccm.source_id) AS source_id,
    MAX(ccm.nombre_campania) AS nombre_campania,
    COUNT(ccm.id) AS total_registros_meta,
    COUNT(DISTINCT CASE WHEN {$tempNorm} = 'frio' THEN c.id END) AS conversaciones_frio,
    COUNT(DISTINCT CASE WHEN {$tempNorm} = 'tibio' THEN c.id END) AS conversaciones_tibio,
    COUNT(DISTINCT CASE WHEN {$tempNorm} = 'caliente' THEN c.id END) AS conversaciones_caliente,
    COUNT(DISTINCT CASE WHEN {$tempNorm} = 'venta' THEN c.id END) AS conversaciones_venta,
    COUNT(DISTINCT CASE WHEN c.id_estado = 2
        AND {$tempNorm} = 'venta'
        AND c.id_etiqueta = 6
        THEN c.id END) AS ventas_cerradas
FROM conversacion c
INNER JOIN conversacion_campania_meta ccm
    ON ccm.id_conversacion = c.id
    AND ccm.deleted_at IS NULL
    AND DATE(ccm.created_at) >= ?
    AND DATE(ccm.created_at) <= ?
WHERE c.deleted_at IS NULL
  AND c.id_usuario IS NOT NULL
  {$excluirUsuarios}
  {$sqlFiltroApp}
GROUP BY c.id_app,
    CASE
        WHEN NULLIF(TRIM(ccm.campaign_id), '') IS NOT NULL THEN TRIM(ccm.campaign_id)
        ELSE CONCAT('__sin_campaign_id__', IFNULL(TRIM(ccm.source_id), ''))
    END
ORDER BY total_registros_meta DESC, nombre_campania ASC
SQL;

        $rows = $db->query($sql, $params)->getResult();
        $db->close();

        $campanias = [];
        foreach ($rows as $row) {
            $campanias[] = [
                'id_app' => (int) $row->id_app,
                'campaign_id' => $row->campaign_id,
                'source_id' => $row->source_id,
                'nombre_campania' => $row->nombre_campania,
                'total_registros_meta' => (int) $row->total_registros_meta,
                'por_temperatura' => [
                    'frio' => (int) $row->conversaciones_frio,
                    'tibio' => (int) $row->conversaciones_tibio,
                    'caliente' => (int) $row->conversaciones_caliente,
                    'venta' => (int) $row->conversaciones_venta,
                ],
                'ventas_cerradas' => (int) $row->ventas_cerradas,
            ];
        }

        return [
            'leyenda' => 'Rango por ccm.created_at. total_registros_meta = filas meta. Temperatura y venta cerrada por conversación (est.2, temp. venta, etiq.6). id_app opcional; excluye usuarios de prueba. Tasas en eficiencia-campanias.',
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'id_app_filtro' => $idAppFiltro,
            'total_grupos_campania' => count($campanias),
            'campanias' => $campanias,
        ];
    }


    public function obtenerEficienciaCampanias(?string $fechaInicio = null, ?string $fechaFin = null, $idApp = null): array
    {
        [$fechaInicio, $fechaFin] = $this->normalizarRangoFechas($fechaInicio, $fechaFin);

        $idAppFiltro = null;
        if ($idApp !== null && $idApp !== '' && $idApp !== '0') {
            $idAppFiltro = (int) $idApp;
        }

        $params = [$fechaInicio, $fechaFin];
        $sqlFiltroApp = '';
        if ($idAppFiltro !== null) {
            $sqlFiltroApp = ' AND c.id_app = ? ';
            $params[] = $idAppFiltro;
        }

        $db = \Config\Database::connect();
        $sql = 'SELECT
                c.id_app,
                MAX(NULLIF(TRIM(ccm.campaign_id), \'\')) AS campaign_id,
                MAX(ccm.source_id) AS source_id,
                MAX(ccm.nombre_campania) AS nombre_campania,
                COUNT(ccm.id) AS total_registros_meta,
                COUNT(DISTINCT c.id) AS conversaciones_unicas_con_meta,
                COUNT(DISTINCT CASE WHEN c.id_estado = 2
                    AND LOWER(TRIM(COALESCE(c.temperatura, \'\'))) = \'venta\'
                    AND c.id_etiqueta = 6
                    THEN c.id END) AS ventas_cerradas
            FROM conversacion c
            INNER JOIN conversacion_campania_meta ccm
                ON ccm.id_conversacion = c.id
                AND ccm.deleted_at IS NULL
                AND DATE(ccm.created_at) >= ?
                AND DATE(ccm.created_at) <= ?
            WHERE c.deleted_at IS NULL
              AND c.id_usuario IS NOT NULL
              ' . $this->sqlFragmentoExcluirUsuariosMetricasReporte() . '
              ' . $sqlFiltroApp . '
            GROUP BY c.id_app,
                CASE
                    WHEN NULLIF(TRIM(ccm.campaign_id), \'\') IS NOT NULL THEN TRIM(ccm.campaign_id)
                    ELSE CONCAT(\'__sin_campaign_id__\', IFNULL(TRIM(ccm.source_id), \'\'))
                END
            ORDER BY total_registros_meta DESC, nombre_campania ASC';

        $rows = $db->query($sql, $params)->getResult();
        $db->close();

        $campanias = [];
        foreach ($rows as $row) {
            $regMeta = (int) $row->total_registros_meta;
            $convUnicas = (int) $row->conversaciones_unicas_con_meta;
            $ventas = (int) $row->ventas_cerradas;
            $campanias[] = [
                'id_app' => (int) $row->id_app,
                'campaign_id' => $row->campaign_id,
                'source_id' => $row->source_id,
                'nombre_campania' => $row->nombre_campania,
                'total_registros_meta' => $regMeta,
                'conversaciones_unicas_con_meta' => $convUnicas,
                'ventas_cerradas' => $ventas,
                'tasa_cierre_venta_sobre_conversaciones_pct' => $convUnicas > 0 ? round(100 * $ventas / $convUnicas, 2) : 0.0,
                'tasa_cierre_venta_sobre_registros_meta_pct' => $regMeta > 0 ? round(100 * $ventas / $regMeta, 2) : 0.0,
                'promedio_registros_meta_por_conversacion' => $convUnicas > 0 ? round($regMeta / $convUnicas, 3) : 0.0,
            ];
        }

        return [
            'leyenda' => 'Misma ventana, agrupación y filtros que metricas-campanias. Leads únicos, tasas % y promedio meta por conversación. Venta cerrada: est.2, temp. venta, etiq.6.',
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'id_app_filtro' => $idAppFiltro,
            'total_grupos_campania' => count($campanias),
            'campanias' => $campanias,
        ];
    }

    public function obtenerUltimaConversacionAsesores()
    {
        $db = \Config\Database::connect();

        $sql = "SELECT 
                    q1.id AS id_usuario,
                    q1.usuario,
                    up.nombres,
                    up.apellidos,
                    q1.activo,
                    q1.id_estado_usuario,
                    q2.ult_conv_asignada,
                    q2.telefono,
                    NOW() AS ahora,
                    IFNULL(TIMESTAMPDIFF(SECOND, q2.ult_conv_asignada, NOW()), 1000) AS diferencia

                FROM usuario AS q1
                INNER JOIN (
                    SELECT 
                        s1.id_usuario,
                        MAX(s1.created_at) AS ult_conv_asignada,
                        (SELECT s2.telefono 
                         FROM cliente s2 
                         WHERE s2.id = (
                             SELECT s1b.id_cliente 
                             FROM conversacion s1b 
                             WHERE s1.id_usuario = s1b.id_usuario 
                             AND s1b.tipo = 'contact_center'
                             ORDER BY s1b.created_at DESC LIMIT 1
                         )
                        ) AS telefono
                    FROM conversacion AS s1
                    WHERE s1.id_usuario IS NOT NULL
                    AND s1.tipo = 'contact_center'
                    GROUP BY s1.id_usuario
                ) AS q2 ON (q2.id_usuario = q1.id)
                INNER JOIN usuario_perfil AS up ON (up.id_usuario = q1.id)
                WHERE q1.activo = 1 
                    AND q1.cuenta_bloqueada = 0 
                    AND NOW() BETWEEN DATE_FORMAT(NOW(), '%Y-%m-%d 08:30:00') AND DATE_FORMAT(NOW(), '%Y-%m-%d 19:00:00')
                ORDER BY diferencia DESC";
        $result = $db->query($sql)->getResult();
        $db->close();

        return $result;
    }

    public function estadosAsesores()
    {
        $db = \Config\Database::connect();

        $estados = $db->table('estado_usuario')
            ->select('id, nombre')
            ->where('deleted_at', null)
            ->orderBy('nombre', 'ASC')
            ->get()
            ->getResult();

        $usuarios = $db->table('usuario')
            ->select('
                estado_usuario.nombre as estado,
                usuario.id as id_usuario,
                CONCAT(usuario_perfil.nombres, " ", usuario_perfil.apellidos) as nombre_usuario
            ')
            ->join('estado_usuario', 'estado_usuario.id = usuario.id_estado_usuario')
            ->join('usuario_perfil', 'usuario_perfil.id_usuario = usuario.id', 'left')
            ->where('usuario.activo', 1)
            ->where('usuario.cuenta_bloqueada', 0)
            ->where('usuario.deleted_at', null)
            ->where('estado_usuario.deleted_at', null)
            ->get()
            ->getResult();

        $header = array_map(function ($estado) {
            return $estado->nombre;
        }, $estados);

        $usuariosPorEstado = [];
        foreach ($usuarios as $usuario) {
            $estadoNombre = $usuario->estado;
            if (!isset($usuariosPorEstado[$estadoNombre])) {
                $usuariosPorEstado[$estadoNombre] = [];
            }
            $usuariosPorEstado[$estadoNombre][] = [
                'id_usuario' => (string) $usuario->id_usuario,
                'nombre_usuario' => $usuario->nombre_usuario
            ];
        }

        $data = [];
        foreach ($estados as $estado) {
            $estadoNombre = $estado->nombre;
            $usuariosEstado = $usuariosPorEstado[$estadoNombre] ?? [];

            if (count($usuariosEstado) > 0) {
                $data[] = [
                    'estado' => $estadoNombre,
                    'usuarios' => $usuariosEstado,
                    'cantidad' => count($usuariosEstado)
                ];
            }
        }

        $db->close();

        return [
            'header' => $header,
            'data' => $data
        ];
    }

    /**
     * Leads agrupados por conversacion.registro (origen web, Google Ads, etc.).
     *
     * @return array<string, mixed>
     */
    public function obtenerMetricasPorOrigen(?string $fechaInicio = null, ?string $fechaFin = null): array
    {
        [$fechaInicio, $fechaFin] = $this->normalizarRangoFechas($fechaInicio, $fechaFin);

        $db = \Config\Database::connect();
        // El origen se cuenta aunque la conversación siga en el bot (usuario 1).
        // Esas métricas de asesor sí excluyen pruebas; esta no.
        $tempNorm = $this->sqlExpresionTemperaturaNormalizada('c');
        $origenExpr = $this->sqlExpresionOrigenNormalizado('c');

        $sql = <<<SQL
SELECT
    {$origenExpr} AS registro,
    COUNT(*) AS total,
    SUM(CASE WHEN c.id_estado = 1 THEN 1 ELSE 0 END) AS en_atencion,
    SUM(CASE WHEN c.id_estado = 2 THEN 1 ELSE 0 END) AS finalizados,
    SUM(CASE WHEN c.id_estado = 3 THEN 1 ELSE 0 END) AS sin_abrir,
    SUM(CASE WHEN {$tempNorm} = 'venta' THEN 1 ELSE 0 END) AS ventas,
    SUM(CASE WHEN {$tempNorm} = 'caliente' THEN 1 ELSE 0 END) AS calientes,
    SUM(CASE WHEN {$tempNorm} = 'tibio' THEN 1 ELSE 0 END) AS tibios,
    SUM(CASE WHEN {$tempNorm} = 'frio' THEN 1 ELSE 0 END) AS frios
FROM conversacion c
WHERE DATE(c.created_at) >= ?
    AND DATE(c.created_at) <= ?
    AND c.deleted_at IS NULL
GROUP BY {$origenExpr}
ORDER BY total DESC, registro ASC
SQL;

        $filas = $db->query($sql, [$fechaInicio, $fechaFin])->getResult();
        $db->close();

        $nombres = $this->mapaNombresOrigenWeb($filas);
        $items = [];
        $totalGeneral = 0;
        $totalMeta = 0;
        $totalSinOrigen = 0;

        foreach ($filas as $row) {
            $registro = (string) ($row->registro ?? 'sin_origen');
            $total = (int) ($row->total ?? 0);
            $totalGeneral += $total;

            if ($registro === 'anuncio_meta') {
                $totalMeta += $total;
            } elseif ($registro === 'sin_origen') {
                $totalSinOrigen += $total;
            }

            $items[] = [
                'registro' => $registro,
                'tipo' => $this->tipoOrigenRegistro($registro),
                'clave' => $this->claveOrigenRegistro($registro),
                'etiqueta' => $this->etiquetaOrigenRegistro($registro, $nombres),
                'total' => $total,
                'en_atencion' => (int) ($row->en_atencion ?? 0),
                'finalizados' => (int) ($row->finalizados ?? 0),
                'sin_abrir' => (int) ($row->sin_abrir ?? 0),
                'ventas' => (int) ($row->ventas ?? 0),
                'calientes' => (int) ($row->calientes ?? 0),
                'tibios' => (int) ($row->tibios ?? 0),
                'frios' => (int) ($row->frios ?? 0),
                'porcentaje' => 0.0,
            ];
        }

        foreach ($items as &$item) {
            $item['porcentaje'] = $totalGeneral > 0
                ? round(100 * $item['total'] / $totalGeneral, 1)
                : 0.0;
        }
        unset($item);

        return [
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'resumen' => [
                'total' => $totalGeneral,
                'anuncio_meta' => $totalMeta,
                'sin_origen' => $totalSinOrigen,
                'otros' => max(0, $totalGeneral - $totalMeta - $totalSinOrigen),
            ],
            'items' => $items,
            'leyenda' => 'Conversaciones creadas en el rango, agrupadas por registro. anuncio_meta = anuncio de WhatsApp; sin_origen = sin captación detectada.',
        ];
    }

    /**
     * Listado paginado de conversaciones de un origen (drill-down de métricas).
     *
     * @return array{fecha_inicio: string, fecha_fin: string, registro: string, etiqueta: string, page: int, per_page: int, total: int, total_pages: int, items: list<array<string, mixed>>}
     */
    public function listarConversacionesPorOrigen(
        ?string $fechaInicio = null,
        ?string $fechaFin = null,
        ?string $registro = null,
        int $page = 1,
        int $perPage = 20,
        ?string $buscar = null,
        ?int $idEstado = null
    ): array {
        [$fechaInicio, $fechaFin] = $this->normalizarRangoFechas($fechaInicio, $fechaFin);

        $registro = strtolower(trim((string) $registro));
        if ($registro === '') {
            throw new \InvalidArgumentException('El origen (registro) es obligatorio');
        }
        if (! preg_match('/^[a-z0-9:_-]{1,100}$/', $registro)) {
            throw new \InvalidArgumentException('Origen inválido');
        }

        $page = max(1, $page);
        $perPage = min(100, max(5, $perPage));
        $offset = ($page - 1) * $perPage;

        $db = \Config\Database::connect();
        $origenExpr = $this->sqlExpresionOrigenNormalizado('c');
        $tempNorm = $this->sqlExpresionTemperaturaNormalizada('c');

        $where = [
            "DATE(c.created_at) >= ?",
            "DATE(c.created_at) <= ?",
            "c.deleted_at IS NULL",
            "{$origenExpr} = ?",
        ];
        $params = [$fechaInicio, $fechaFin, $registro];

        if ($idEstado !== null && $idEstado > 0) {
            $where[] = 'c.id_estado = ?';
            $params[] = $idEstado;
        }

        $buscar = trim((string) $buscar);
        if ($buscar !== '') {
            $where[] = '(cl.telefono LIKE ? OR cp.nombre LIKE ? OR CAST(c.id AS CHAR) LIKE ?)';
            $like = '%' . $buscar . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $whereSql = implode(' AND ', $where);

        $sqlCount = <<<SQL
SELECT COUNT(*) AS total
FROM conversacion c
LEFT JOIN cliente cl ON cl.id = c.id_cliente AND cl.deleted_at IS NULL
LEFT JOIN cliente_perfil cp ON cp.id_cliente = cl.id
WHERE {$whereSql}
SQL;
        $total = (int) ($db->query($sqlCount, $params)->getRow()->total ?? 0);

        $sqlItems = <<<SQL
SELECT
    c.id,
    c.id_cliente,
    c.id_usuario,
    c.id_estado,
    c.temperatura,
    c.tipo,
    c.origen,
    c.registro AS registro_raw,
    c.source_id,
    c.campaign_id,
    c.created_at,
    c.asignado_at,
    {$origenExpr} AS registro,
    {$tempNorm} AS temperatura_norm,
    cl.telefono AS cliente_telefono,
    cp.nombre AS cliente_nombre,
    up.nombres AS asesor_nombres,
    up.apellidos AS asesor_apellidos,
    e.nombre AS etiqueta_nombre
FROM conversacion c
LEFT JOIN cliente cl ON cl.id = c.id_cliente AND cl.deleted_at IS NULL
LEFT JOIN cliente_perfil cp ON cp.id_cliente = cl.id
LEFT JOIN usuario u ON u.id = c.id_usuario
LEFT JOIN usuario_perfil up ON up.id_usuario = u.id
LEFT JOIN etiqueta e ON e.id = c.id_etiqueta AND e.deleted_at IS NULL
WHERE {$whereSql}
ORDER BY c.created_at DESC, c.id DESC
LIMIT {$perPage} OFFSET {$offset}
SQL;

        $filas = $db->query($sqlItems, $params)->getResult();
        $db->close();

        $nombres = $this->mapaNombresOrigenWeb([(object) ['registro' => $registro]]);
        $etiqueta = $this->etiquetaOrigenRegistro($registro, $nombres);

        $estados = [
            1 => 'En atención',
            2 => 'Cerrado',
            3 => 'Sin abrir',
        ];

        $items = [];
        foreach ($filas as $row) {
            $asesor = trim(trim((string) ($row->asesor_nombres ?? '')) . ' ' . trim((string) ($row->asesor_apellidos ?? '')));
            $items[] = [
                'id' => (int) $row->id,
                'id_cliente' => $row->id_cliente !== null ? (int) $row->id_cliente : null,
                'id_usuario' => $row->id_usuario !== null ? (int) $row->id_usuario : null,
                'id_estado' => $row->id_estado !== null ? (int) $row->id_estado : null,
                'estado_nombre' => $estados[(int) ($row->id_estado ?? 0)] ?? 'Otro',
                'temperatura' => (string) ($row->temperatura_norm ?? 'frio'),
                'tipo' => $row->tipo,
                'origen' => $row->origen,
                'registro' => (string) ($row->registro ?? $registro),
                'registro_raw' => $row->registro_raw,
                'source_id' => $row->source_id,
                'campaign_id' => $row->campaign_id,
                'created_at' => $row->created_at,
                'asignado_at' => $row->asignado_at,
                'cliente_telefono' => $row->cliente_telefono,
                'cliente_nombre' => $row->cliente_nombre,
                'asesor' => $asesor !== '' ? $asesor : '—',
                'etiqueta' => $row->etiqueta_nombre,
                'url' => '/conversaciones/cliente/' . (int) $row->id,
            ];
        }

        return [
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'registro' => $registro,
            'etiqueta' => $etiqueta,
            'tipo' => $this->tipoOrigenRegistro($registro),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $total > 0 ? (int) ceil($total / $perPage) : 0,
            'items' => $items,
        ];
    }

    private function sqlExpresionOrigenNormalizado(string $aliasTabla = 'c'): string
    {
        $r = $aliasTabla . '.registro';
        $s = $aliasTabla . '.source_id';
        $camp = $aliasTabla . '.campaign_id';

        return "CASE"
            . " WHEN {$r} IS NOT NULL AND TRIM({$r}) <> '' THEN LOWER(TRIM({$r}))"
            . " WHEN NULLIF(TRIM({$s}), '') IS NOT NULL OR NULLIF(TRIM({$camp}), '') IS NOT NULL THEN 'anuncio_meta'"
            . " ELSE 'sin_origen' END";
    }

    /**
     * @param list<object> $filas
     * @return array{productos: array<string, string>, familias: array<string, string>}
     */
    private function mapaNombresOrigenWeb(array $filas): array
    {
        $slugsProducto = [];
        $slugsFamilia = [];

        foreach ($filas as $row) {
            $registro = strtolower(trim((string) ($row->registro ?? '')));
            if (preg_match('/^web:producto:([a-z0-9_-]+)$/', $registro, $m)) {
                $slugsProducto[] = $m[1];
            } elseif (preg_match('/^web:familia:([a-z0-9_-]+)$/', $registro, $m)) {
                $slugsFamilia[] = $m[1];
            }
        }

        $productos = [];
        $familias = [];
        $db = \Config\Database::connect();

        $slugsProducto = array_values(array_unique($slugsProducto));
        if ($slugsProducto !== []) {
            $ph = implode(',', array_fill(0, count($slugsProducto), '?'));
            $rows = $db->query(
                "SELECT slug, nombre FROM producto WHERE slug IN ({$ph}) AND deleted_at IS NULL",
                $slugsProducto
            )->getResult();
            foreach ($rows as $r) {
                $productos[strtolower((string) $r->slug)] = (string) $r->nombre;
            }
        }

        $slugsFamilia = array_values(array_unique($slugsFamilia));
        if ($slugsFamilia !== []) {
            $ph = implode(',', array_fill(0, count($slugsFamilia), '?'));
            $rows = $db->query(
                "SELECT slug, nombre FROM grupo_producto WHERE slug IN ({$ph}) AND deleted_at IS NULL",
                $slugsFamilia
            )->getResult();
            foreach ($rows as $r) {
                $familias[strtolower((string) $r->slug)] = (string) $r->nombre;
            }
        }

        $db->close();

        return ['productos' => $productos, 'familias' => $familias];
    }

    private function tipoOrigenRegistro(string $registro): string
    {
        if ($registro === 'sin_origen') {
            return 'sin_origen';
        }
        if ($registro === 'anuncio_meta') {
            return 'anuncio_meta';
        }

        return 'otro';
    }

    private function claveOrigenRegistro(string $registro): string
    {
        if (preg_match('/^web:(?:producto|familia|pagina):([a-z0-9_-]+)$/', $registro, $m)) {
            return $m[1];
        }

        return $registro;
    }

    /**
     * @param array{productos: array<string, string>, familias: array<string, string>} $nombres
     */
    private function etiquetaOrigenRegistro(string $registro, array $nombres): string
    {
        if ($registro === 'sin_origen') {
            return 'Sin origen detectado';
        }
        if ($registro === 'anuncio_meta') {
            return 'Anuncio de WhatsApp';
        }

        return $registro;
    }

    private function normalizarRangoFechas(?string $fechaInicio, ?string $fechaFin): array
    {
        if ($fechaInicio === null || trim($fechaInicio) === '' || $fechaFin === null || trim($fechaFin) === '') {
            throw new \InvalidArgumentException('fechaInicio y fechaFin son obligatorias');
        }
        try {
            $ini = (new \DateTime($fechaInicio))->format('Y-m-d');
            $fin = (new \DateTime($fechaFin))->format('Y-m-d');
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Las fechas no son válidas');
        }

        return [$ini, $fin];
    }

    /**
     * Vacío o NULL en conversacion.temperatura cuenta como frío (default del CRM).
     */
    private function sqlExpresionTemperaturaNormalizada(string $aliasTabla = 'c'): string
    {
        $col = $aliasTabla . '.temperatura';

        return "CASE WHEN LOWER(TRIM(COALESCE({$col}, ''))) IN ('tibio','caliente','venta')"
            . " THEN LOWER(TRIM({$col}))"
            . " ELSE 'frio' END";
    }

}
