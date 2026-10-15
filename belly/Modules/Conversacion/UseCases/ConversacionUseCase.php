<?php
namespace Belly\Conversacion\UseCases;

use Belly\Conversacion\Config\ConversacionConfig;
use Belly\Conversacion\Models\ConversacionModel;
use Belly\Conversacion\Models\EtiquetaModel;
use Belly\Conversacion\Entities\ConversacionEntity;

class ConversacionUseCase {
    protected ConversacionConfig $config;
    protected ?ConversacionModel $conversacion_provider = NULL;

    public function __construct(ConversacionConfig $config)
    {
        $this->config = $config;
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

    private function sqlUltimoMensajeContestaEnConversacion(): string
    {
        return '(SELECT mensaje.contesta FROM mensaje
            WHERE mensaje.id_conversacion = conversacion.id
            AND mensaje.deleted_at IS NULL
            ORDER BY mensaje.id DESC
            LIMIT 1)';
    }

    private function selectUltimoMensajeContestaSql(): string
    {
        return $this->sqlUltimoMensajeContestaEnConversacion() . ' as ultimo_mensaje_contesta';
    }


    private function selectUltimoMensajeSql(): string
    {
        return '(SELECT mensaje.mensaje FROM mensaje
            WHERE mensaje.id_conversacion = conversacion.id
            AND mensaje.deleted_at IS NULL
            ORDER BY mensaje.id DESC
            LIMIT 1) as texto_ultimo_mensaje';
    }

    private function selectUltimoMensajeFechaSql(): string
    {
        return $this->sqlOrdenUltimoMensajeReciente() . ' as ultimo_mensaje_fecha';
    }

    /** Orden WhatsApp: preferir id (índice) frente a created_at. */
    private function sqlOrdenUltimoMensajeReciente(): string
    {
        return '(SELECT mensaje.created_at FROM mensaje '
            . 'WHERE mensaje.id_conversacion = conversacion.id '
            . 'AND mensaje.deleted_at IS NULL '
            . 'ORDER BY mensaje.id DESC LIMIT 1)';
    }

    private function aplicarOrdenUltimoMensajeReciente($conversacion_model): void
    {
        // updated_at se toca al crear mensajes → orden barato con índice de conversacion.
        $conversacion_model->orderBy('conversacion.updated_at', 'DESC', false);
        $conversacion_model->orderBy('conversacion.id', 'DESC', false);
    }

    /**
     * Select liviano del sidebar de chat (sin subqueries por fila de mensaje/IA).
     * Los campos de preview/unread se rellenan luego en batch.
     */
    private function selectListadoSidebarChatBaseSql(): string
    {
        return 'conversacion.*, cliente.telefono, cliente.wa_user_id,'
            . ' cliente_perfil.nombre as nombre_cliente,'
            . ' cliente_perfil.apellido as apellido_cliente,'
            . ' cliente_perfil.nombre_completo as nombre_completo_cliente,'
            . ' app.nombre as nombre_app,'
            . ' etiqueta.nombre as etiqueta_nombre,'
            . ' etiqueta.color as etiqueta_color,'
            . ' etiqueta.descripcion as etiqueta_descripcion,'
            . ' usuario.es_bot AS usuario_es_bot';
    }

    /**
     * Completa preview/unread/IA de un listado sidebar con 2-3 queries batch
     * en lugar de N subqueries correlacionadas por fila.
     *
     * @param list<object> $rows
     * @return list<object>
     */
    private function enriquecerListadoSidebarChat(array $rows): array
    {
        if ($rows === []) {
            return $rows;
        }

        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row->id ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return $rows;
        }

        $db = \Config\Database::connect();
        $inList = implode(',', $ids);

        $unreadById = [];
        try {
            $unreadRows = $db->query(
                "SELECT id_conversacion,
                        COUNT(*) AS mensajes_no_leidos,
                        MAX(created_at) AS ultimo_mensaje_no_leido_fecha
                 FROM mensaje
                 WHERE id_conversacion IN ({$inList})
                   AND contesta = 'cliente'
                   AND id_estado = 3
                   AND deleted_at IS NULL
                 GROUP BY id_conversacion"
            )->getResult();
            foreach ($unreadRows as $u) {
                $unreadById[(int) $u->id_conversacion] = $u;
            }
        } catch (\Throwable $e) {
            log_message('warning', '[LISTADO SIDEBAR] unread batch: ' . $e->getMessage());
        }

        $ultimoById = [];
        try {
            $ultimoRows = $db->query(
                "SELECT m.id_conversacion,
                        m.mensaje AS texto_ultimo_mensaje,
                        m.created_at AS ultimo_mensaje_fecha,
                        m.contesta AS ultimo_mensaje_contesta
                 FROM mensaje m
                 INNER JOIN (
                    SELECT id_conversacion, MAX(id) AS max_id
                    FROM mensaje
                    WHERE id_conversacion IN ({$inList})
                      AND deleted_at IS NULL
                    GROUP BY id_conversacion
                 ) t ON t.max_id = m.id"
            )->getResult();
            foreach ($ultimoRows as $u) {
                $ultimoById[(int) $u->id_conversacion] = $u;
            }
        } catch (\Throwable $e) {
            log_message('warning', '[LISTADO SIDEBAR] ultimo batch: ' . $e->getMessage());
        }

        foreach ($rows as $row) {
            $id = (int) ($row->id ?? 0);
            $unread = $unreadById[$id] ?? null;
            $ultimo = $ultimoById[$id] ?? null;

            $row->mensajes_no_leidos = $unread ? (int) $unread->mensajes_no_leidos : 0;
            $row->ultimo_mensaje_no_leido_fecha = $unread->ultimo_mensaje_no_leido_fecha ?? null;
            $row->texto_ultimo_mensaje = $ultimo->texto_ultimo_mensaje ?? null;
            $row->ultimo_mensaje_fecha = $ultimo->ultimo_mensaje_fecha ?? ($row->updated_at ?? null);
            $row->ultimo_mensaje_contesta = $ultimo->ultimo_mensaje_contesta ?? null;
        }

        return $rows;
    }

    private function normalizarPerPageListado($perPage)
    {
        if ($perPage === 'all' || $perPage === 'sidebar') {
            return $perPage;
        }

        $n = (int) $perPage;

        return max(5, min(100, $n > 0 ? $n : 25));
    }

    private const CHAT_SIDEBAR_PAGE1_SIZE = 100;

    private const CHAT_SIDEBAR_NEXT_PAGE_SIZE = 100;

    /**
     * @return array{limit: int, offset: int}
     */
    private function resolverLimitOffsetSidebarChat(int $page): array
    {
        $page = max(1, $page);

        if ($page === 1) {
            return [
                'limit' => self::CHAT_SIDEBAR_PAGE1_SIZE,
                'offset' => 0,
            ];
        }

        return [
            'limit' => self::CHAT_SIDEBAR_NEXT_PAGE_SIZE,
            'offset' => self::CHAT_SIDEBAR_PAGE1_SIZE + ($page - 2) * self::CHAT_SIDEBAR_NEXT_PAGE_SIZE,
        ];
    }

    /**
     * Paginación del sidebar de chat sin COUNT(*) completo (muy costoso con subqueries).
     * Usa limit+1 para saber si hay más páginas.
     *
     * @return array{data: list<mixed>, pagination: array<string, mixed>}
     */
    private function ejecutarListadoPaginadoSidebarChat($conversacion_model, int $page): array
    {
        $page = max(1, $page);
        ['limit' => $limit, 'offset' => $offset] = $this->resolverLimitOffsetSidebarChat($page);

        $rows = $conversacion_model->findAll($limit + 1, $offset);
        $hasNext = count($rows) > $limit;
        if ($hasNext) {
            $rows = array_slice($rows, 0, $limit);
        }

        $rows = $this->enriquecerListadoSidebarChat($rows);

        $loaded = $offset + count($rows);
        // total aproximado: evita COUNT(*) pesado; el front usa hasNext para “cargar más”.
        $totalApprox = $hasNext ? ($loaded + 1) : $loaded;

        return [
            'data' => $rows,
            'pagination' => [
                'total' => $totalApprox,
                'currentPage' => $page,
                'perPage' => $limit,
                'from' => $loaded > 0 ? $offset + 1 : 0,
                'to' => $loaded,
                'hasNext' => $hasNext,
                'hasPrev' => $page > 1,
                'nextPage' => $hasNext ? $page + 1 : null,
            ],
        ];
    }

    /**
     * @return array{data: list<mixed>, pagination: array<string, mixed>}
     */
    private function ejecutarListadoPaginado($conversacion_model, int $page, $perPage): array
    {
        $perPage = $this->normalizarPerPageListado($perPage);

        if ($perPage === 'sidebar') {
            return $this->ejecutarListadoPaginadoSidebarChat($conversacion_model, $page);
        }

        if ($perPage === 'all') {
            $total = (int) $conversacion_model->countAllResults(false);
            $result = $conversacion_model->findAll();

            return [
                'data' => $result,
                'pagination' => [
                    'total' => $total,
                    'currentPage' => 1,
                    'perPage' => 'all',
                    'from' => $total > 0 ? 1 : 0,
                    'to' => $total,
                    'hasNext' => false,
                    'hasPrev' => false,
                ],
            ];
        }

        $perPage = (int) $perPage;
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $total = (int) $conversacion_model->countAllResults(false);
        $result = $conversacion_model->findAll($perPage, $offset);
        $lastPage = max(1, (int) ceil($total / $perPage));

        return [
            'data' => $result,
            'pagination' => [
                'total' => $total,
                'currentPage' => $page,
                'perPage' => $perPage,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => $total > 0 ? min($total, $offset + count($result)) : 0,
                'hasNext' => $page < $lastPage,
                'hasPrev' => $page > 1,
            ],
        ];
    }

    private function obtenerModoFiltroVentana24h(array $filtros): ?string
    {
        if (! array_key_exists('ventana_24h', $filtros)) {
            return null;
        }

        $valor = $filtros['ventana_24h'];
        if ($valor === true || $valor === 1) {
            return 'dentro';
        }
        if ($valor === false || $valor === 0 || $valor === '0') {
            return 'fuera';
        }

        $normalizado = strtolower(trim((string) $valor));
        if (in_array($normalizado, ['1', 'true', 'si', 'yes', 'dentro'], true)) {
            return 'dentro';
        }
        if (in_array($normalizado, ['0', 'false', 'no', 'fuera'], true)) {
            return 'fuera';
        }

        return null;
    }

    private function sqlUltimoMensajeClienteAt(): string
    {
        return '(SELECT MAX(mensaje.created_at) FROM mensaje '
            . 'WHERE mensaje.id_conversacion = conversacion.id '
            . "AND mensaje.contesta = 'cliente' "
            . 'AND mensaje.deleted_at IS NULL)';
    }

    /**
     * Filtra por ventana Meta: dentro o fuera de las 24h desde el último mensaje del cliente.
     */
    private function aplicarFiltroVentana24Horas($conversacion_model, array $filtros): void
    {
        $modo = $this->obtenerModoFiltroVentana24h($filtros);
        if ($modo === null) {
            return;
        }

        $desde = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $db = \Config\Database::connect();
        $escapedDesde = $db->escape($desde);
        $subquery = $this->sqlUltimoMensajeClienteAt();

        if ($modo === 'dentro') {
            $condicion = "{$subquery} >= {$escapedDesde}";
        } else {
            $condicion = "({$subquery} IS NULL OR {$subquery} < {$escapedDesde})";
        }

        $conversacion_model->where($condicion, null, false);
    }

    private function selectVentaRegistradaAtSql(): string
    {
        return '(SELECT cv.registrada_at FROM conversacion_venta cv '
            . 'WHERE cv.id_conversacion = conversacion.id AND cv.deleted_at IS NULL '
            . 'LIMIT 1) AS venta_registrada_at';
    }

    /**
     * @param array<string, mixed> $filtros registro_venta (1|0), fecha_venta_inicio, fecha_venta_fin
     */
    private function aplicarFiltroRegistroVenta($conversacion_model, array $filtros): void
    {
        if (! empty($filtros['registro_venta'])) {
            $valor = trim((string) $filtros['registro_venta']);
            $exists = 'EXISTS (SELECT 1 FROM conversacion_venta cv '
                . 'WHERE cv.id_conversacion = conversacion.id AND cv.deleted_at IS NULL)';

            if ($valor === '1') {
                $conversacion_model->where($exists, null, false);
            } elseif ($valor === '0') {
                $conversacion_model->where('NOT ' . $exists, null, false);
            }
        }

        if (! empty($filtros['fecha_venta_inicio']) || ! empty($filtros['fecha_venta_fin'])) {
            $db = \Config\Database::connect();
            $exists = 'EXISTS (SELECT 1 FROM conversacion_venta cv '
                . 'WHERE cv.id_conversacion = conversacion.id AND cv.deleted_at IS NULL';

            if (! empty($filtros['fecha_venta_inicio'])) {
                $exists .= ' AND DATE(cv.registrada_at) >= ' . $db->escape($filtros['fecha_venta_inicio']);
            }
            if (! empty($filtros['fecha_venta_fin'])) {
                $exists .= ' AND DATE(cv.registrada_at) <= ' . $db->escape($filtros['fecha_venta_fin']);
            }

            $exists .= ')';
            $conversacion_model->where($exists, null, false);
        }
    }

    private function obtenerUltimoMensajeContesta(int $id_conversacion): ?string
    {
        $row = model('MensajeModel')
            ->select('contesta')
            ->where('id_conversacion', $id_conversacion)
            ->where('deleted_at', null)
            ->orderBy('created_at', 'DESC')
            ->limit(1)
            ->get()
            ->getRow();

        return $row->contesta ?? null;
    }

    public function crearConversacion(array $datos)
    {
        $conversacion_model = $this->getConversacionProvider();

        $conversacion_entity = new ConversacionEntity();
        $conversacion_entity->id_empresa = $this->resolverIdEmpresaConversacion($datos);
        $conversacion_entity->id_cliente = $datos['id_cliente'] ?? null;
        $conversacion_entity->id_usuario = $datos['id_usuario'] ?? null;
        $conversacion_entity->id_app = $datos['id_app'] ?? null;
        $conversacion_entity->id_estado = $datos['id_estado'] ?? null;
        $conversacion_entity->id_motivo = $datos['id_motivo'] ?? null;
        $conversacion_entity->origen = $datos['origen'] ?? null;
        $conversacion_entity->servicio = $datos['servicio'] ?? null;
        $conversacion_entity->registro = $datos['registro'] ?? null;
        $conversacion_entity->id_asignado_por = $datos['id_asignado_por'] ?? null;
        $conversacion_entity->asignado_at = $datos['asignado_at'] ?? null;
        $conversacion_entity->temperatura = $datos['temperatura'] ?? 'frio';
        $conversacion_entity->id_etiqueta = $datos['id_etiqueta'] ?? null;
        $conversacion_entity->source_id = $datos['source_id'] ?? null;
        $conversacion_entity->campaign_id = $datos['campaign_id'] ?? null;

        
        // Determinar el tipo basándose en el modo de la app del usuario
        $conversacion_entity->tipo = $this->determinarTipoConversacion($conversacion_entity->id_usuario);

        if ($conversacion_model->save($conversacion_entity)) {
            $conversacion_entity->id = $conversacion_model->insertID;

            $conversacion_completa = $conversacion_model->find($conversacion_entity->id);

            $cliente = clientes()->getClienteProvider()->find($conversacion_entity->id_cliente);
            $cliente_perfil = clientes()->getClientePerfilProvider()->where('id_cliente', $conversacion_entity->id_cliente)->first();
            
            $usuario = usuario()->getUsuarioProvider()->find($conversacion_entity->id_usuario);
            $usuario_perfil = usuario()->getPerfilProvider()->where('id_usuario', $conversacion_entity->id_usuario)->first();

            // Obtener app para nombre_app
            $app = null;
            if ($conversacion_completa->id_app) {
                $app = model('AppModel')->find($conversacion_completa->id_app);
            }

            // Calcular mensajes no leídos
            $mensaje_model = model('MensajeModel');
            $mensajes_no_leidos = $mensaje_model
                ->where('id_conversacion', $conversacion_completa->id)
                ->where('contesta', 'cliente')
                ->where('id_estado', 3)
                ->where('deleted_at', null)
                ->countAllResults(false);
            
            $ultimo_mensaje_no_leido = $mensaje_model
                ->select('created_at')
                ->where('id_conversacion', $conversacion_completa->id)
                ->where('contesta', 'cliente')
                ->where('id_estado', 3)
                ->where('deleted_at', null)
                ->orderBy('created_at', 'DESC')
                ->limit(1)
                ->get()
                ->getRow();

            $conversacion_entity = [
                'id' => $conversacion_entity->id,
                'id_cliente' => $conversacion_entity->id_cliente,
                'id_usuario' => $conversacion_entity->id_usuario,
                'id_app' => $conversacion_entity->id_app,
                'id_estado' => $conversacion_entity->id_estado,
                'origen' => $conversacion_entity->origen,
                'servicio' => $conversacion_entity->servicio,
                'registro' => $conversacion_entity->registro,
                'id_asignado_por' => $conversacion_entity->id_asignado_por,
                'asignado_at' => $conversacion_entity->asignado_at,
            ];

            try {
                $socket_data = [
                    'id' => $conversacion_completa->id,
                    'id_cliente' => $conversacion_completa->id_cliente,
                    'id_usuario' => $conversacion_completa->id_usuario,
                    'id_estado' => $conversacion_completa->id_estado,
                    'id_app' => $conversacion_completa->id_app,
                    'id_motivo' => $conversacion_completa->id_motivo,
                    'tipo' => $conversacion_completa->tipo,
                    'registro' => $conversacion_completa->registro,
                    'origen' => $conversacion_completa->origen,
                    'id_asignado_por' => $conversacion_completa->id_asignado_por,
                    'servicio' => $conversacion_completa->servicio,
                    'telefono' => $cliente->telefono ?? null,
                    'nombre_cliente' => $cliente_perfil->nombre ?? null,
                    'apellido_cliente' => $cliente_perfil->apellido ?? null,
                    'nombre_completo_cliente' => $cliente_perfil->nombre_completo ?? null,
                    'nombre_app' => $app->nombre ?? null,
                    'mensajes_no_leidos' => $mensajes_no_leidos,
                    'ultimo_mensaje_no_leido_fecha' => $ultimo_mensaje_no_leido ? $ultimo_mensaje_no_leido->created_at : null,
                    'ultimo_mensaje_contesta' => $this->obtenerUltimoMensajeContesta((int) $conversacion_completa->id),
                    'created_at' => $conversacion_completa->created_at,
                    'updated_at' => $conversacion_completa->updated_at,
                    'asignado_at' => $conversacion_completa->asignado_at,
                    'deleted_at' => $conversacion_completa->deleted_at,
                ];
                emitir_conversacion_socket($socket_data);
            }catch (\Exception $e) {
                log_message('error', 'Socket error: ' . $e->getMessage());
            }

            return $conversacion_entity;

        } else {
            throw new \Exception(json_encode($conversacion_model->errors()));
        }
    }

    public function obtenerConversacionPorEstado($id_cliente, $id_estado)
    {
        $conversacion_model = $this->getConversacionProvider();

        return $conversacion_model
            ->where('id_cliente', $id_cliente)
            ->where('id_estado', $id_estado)
            ->first();
    }

    public function obtenerDatosConversacionCompleta($id_conversacion, bool $incluirCredencialesApp = false)
    {
        $conversacion = $this->getConversacionProvider()->find($id_conversacion);
        if (!$conversacion) {
            throw new \Exception('Conversación no encontrada');
        }

        //id_etiqueta traer el nombre de la etiqueta
        $detalleRaw = $this->getConversacionProvider()
            ->asArray()
            ->select('
                cliente.telefono,
                cliente.psid,
                cliente.wa_user_id,
                cliente.wa_parent_user_id,
                cliente_perfil.nombre as nombre_cliente,
                cliente_perfil.apellido as apellido_cliente,
                cliente_perfil.nombre_completo as nombre_completo_cliente,
                usuario_perfil.nombres as nombres_usuario,
                usuario.es_bot as usuario_es_bot,
                etiqueta.nombre as nombre_etiqueta,
                etiqueta.color as color_etiqueta,
                usuario_perfil.apellidos as apellidos_usuario,
                app.nombre as nombre_app,
                estado_conversacion.nombre as estado_nombre,
                (SELECT COUNT(*) FROM mensaje
                    WHERE mensaje.id_conversacion = conversacion.id
                    AND mensaje.contesta = "cliente"
                    AND mensaje.id_estado = 3
                    AND mensaje.deleted_at IS NULL) as mensajes_no_leidos,
                (SELECT mensaje.mensaje FROM mensaje
                    WHERE mensaje.id_conversacion = conversacion.id
                    AND mensaje.deleted_at IS NULL
                    ORDER BY mensaje.created_at DESC
                    LIMIT 1) as ultimo_mensaje,
                (SELECT mensaje.created_at FROM mensaje
                    WHERE mensaje.id_conversacion = conversacion.id
                    AND mensaje.deleted_at IS NULL
                    ORDER BY mensaje.created_at DESC
                    LIMIT 1) as ultimo_mensaje_fecha,
                (SELECT mensaje.contesta FROM mensaje
                    WHERE mensaje.id_conversacion = conversacion.id
                    AND mensaje.deleted_at IS NULL
                    ORDER BY mensaje.created_at DESC
                    LIMIT 1) as ultimo_mensaje_contesta,
                (SELECT ccm.nombre_campania FROM conversacion_campania_meta ccm
                    WHERE ccm.id_conversacion = conversacion.id
                    AND ccm.deleted_at IS NULL
                    ORDER BY ccm.id DESC
                    LIMIT 1) as nombre_campania,
                (SELECT ccm.codigo FROM conversacion_campania_meta ccm
                    WHERE ccm.id_conversacion = conversacion.id
                    AND ccm.deleted_at IS NULL
                    ORDER BY ccm.id DESC
                    LIMIT 1) as codigo_campania
            ')
            ->join('cliente', 'cliente.id = conversacion.id_cliente', 'left')
            ->join('cliente_perfil', 'cliente_perfil.id_cliente = cliente.id', 'left')
            ->join('usuario', 'usuario.id = conversacion.id_usuario', 'left')
            ->join('usuario_perfil', 'usuario_perfil.id_usuario = usuario.id', 'left')
            ->join('etiqueta', 'etiqueta.id = conversacion.id_etiqueta AND etiqueta.deleted_at IS NULL', 'left')
            ->join('app', 'app.id = conversacion.id_app', 'left')
            ->join('estado_conversacion', 'estado_conversacion.id = conversacion.id_estado', 'left')
            ->where('conversacion.id', $id_conversacion)
            ->first();

        $detalle = [
            'estado' => [
                'id' => $conversacion->id_estado ? (int) $conversacion->id_estado : null,
                'nombre' => $detalleRaw['estado_nombre'] ?? null,
            ],
            'temperatura' => $conversacion->temperatura ?? null,
            'mensajes_no_leidos' => isset($detalleRaw['mensajes_no_leidos']) ? (int) $detalleRaw['mensajes_no_leidos'] : 0,
            'ultimo_mensaje' => [
                'texto' => $detalleRaw['ultimo_mensaje'] ?? null,
                'fecha' => $detalleRaw['ultimo_mensaje_fecha'] ?? null,
                'contesta' => $detalleRaw['ultimo_mensaje_contesta'] ?? null,
            ],
            'campania' => [
                'nombre' => $detalleRaw['nombre_campania'] ?? null,
                'codigo' => $detalleRaw['codigo_campania'] ?? null,
            ],
            'etiqueta' => [
                'id' => $conversacion->id_etiqueta ? (int) $conversacion->id_etiqueta : null,
                'nombre' => $detalleRaw['nombre_etiqueta'] ?? null,
                'color' => $detalleRaw['color_etiqueta'] ?? null,
            ],
        ];

        /** @var object|null Registro completo de app (tokens, phone_number_id): nunca debe salir hacia el cliente HTTP */
        $appEntidad = $incluirCredencialesApp && $conversacion->id_app
            ? model('AppModel')->find($conversacion->id_app)
            : null;

        $iaConversacion = $this->obtenerMetaIaConversacion($id_conversacion, $conversacion, $detalleRaw);

        return [
            'conversacion' => $conversacion,
            'usuario' => [
                'id' => $conversacion->id_usuario ? (int) $conversacion->id_usuario : null,
                'nombre' => $detalleRaw['nombres_usuario'] ?? null,
                'apellido' => $detalleRaw['apellidos_usuario'] ?? null,
                'es_bot' => isset($detalleRaw['usuario_es_bot']) ? (int) $detalleRaw['usuario_es_bot'] : 0,
            ],
            'app' => [
                'id' => $conversacion->id_app ? (int) $conversacion->id_app : null,
                'nombre' => $detalleRaw['nombre_app'] ?? null,
            ],
            'app_entidad' => $appEntidad,
            'cliente' => [
                'id' => $conversacion->id_cliente ? (int) $conversacion->id_cliente : null,
                'nombre' => $detalleRaw['nombre_cliente'] ?? null,
                'apellido' => $detalleRaw['apellido_cliente'] ?? null,
                'nombre_completo' => $detalleRaw['nombre_completo_cliente'] ?? null,
                'telefono' => $detalleRaw['telefono'] ?? null,
                'psid' => $detalleRaw['psid'] ?? null,
                'wa_user_id' => $detalleRaw['wa_user_id'] ?? null,
                'wa_parent_user_id' => $detalleRaw['wa_parent_user_id'] ?? null,
            ],
            'telefono' => $detalleRaw['telefono'] ?? null,
            'psid' => $detalleRaw['psid'] ?? null,
            'wa_user_id' => $detalleRaw['wa_user_id'] ?? null,
            'wa_parent_user_id' => $detalleRaw['wa_parent_user_id'] ?? null,
            'detalle' => $detalle,
            'ia' => $iaConversacion,
        ];
    }

    /**
     * Metadatos del agente IA para la conversación (toggle y estado por chat).
     *
     * @param array<string, mixed>|null $detalleRaw
     *
     * @return array<string, mixed>
     */
    private function obtenerMetaIaConversacion(int $idConversacion, object $conversacion, ?array $detalleRaw = null): array
    {
        return [
            'usuario_es_bot' => isset($detalleRaw['usuario_es_bot']) ? (int) $detalleRaw['usuario_es_bot'] : 0,
            'id_usuario_asignado' => (int) ($conversacion->id_usuario ?? 0),
            'id_app' => (int) ($conversacion->id_app ?? 0),
            'es_conversacion_ia' => false,
            'ia_activa' => false,
            'estado' => null,
            'motivo' => 'sin_ia',
        ];
    }

    /**
     * Contadores livianos para badge del menú lateral (sin payloads de listado).
     *
     * @return array{mensajes_no_leidos:int,falta_responder:int,badge_chat:int}
     */
    public function contadoresSidebarChat(int $id_usuario): array
    {
        $db = \Config\Database::connect();
        $idUsuario = max(0, $id_usuario);

        $noLeidos = 0;
        $faltaResponder = 0;

        try {
            $rowNoLeidos = $db->query(
                "SELECT COUNT(*) AS total
                 FROM mensaje m
                 INNER JOIN conversacion c ON c.id = m.id_conversacion
                 WHERE c.id_usuario = ?
                   AND c.deleted_at IS NULL
                   AND c.id_estado IN (1, 3)
                   AND m.deleted_at IS NULL
                   AND m.contesta = 'cliente'
                   AND m.id_estado = 3",
                [$idUsuario]
            )->getRow();
            $noLeidos = (int) ($rowNoLeidos->total ?? 0);
        } catch (\Throwable $e) {
            log_message('warning', '[CONTADORES SIDEBAR] no_leidos: ' . $e->getMessage());
        }

        try {
            $rowFalta = $db->query(
                "SELECT COUNT(*) AS total
                 FROM conversacion c
                 WHERE c.id_usuario = ?
                   AND c.deleted_at IS NULL
                   AND c.id_estado IN (1, 3)
                   AND (
                     SELECT m.contesta
                     FROM mensaje m
                     WHERE m.id_conversacion = c.id
                       AND m.deleted_at IS NULL
                     ORDER BY m.id DESC
                     LIMIT 1
                   ) = 'cliente'",
                [$idUsuario]
            )->getRow();
            $faltaResponder = (int) ($rowFalta->total ?? 0);
        } catch (\Throwable $e) {
            log_message('warning', '[CONTADORES SIDEBAR] falta_responder: ' . $e->getMessage());
        }

        return [
            'mensajes_no_leidos' => $noLeidos,
            'falta_responder' => $faltaResponder,
            'badge_chat' => $noLeidos + $faltaResponder,
        ];
    }

    public function listarConversacion($id_usuario, array $filtros = [], int $page = 1, $perPage = 'all'): array
    {
        $page = max(1, $page);

        $conversacion_model = $this->getConversacionProvider();

        $conversacion_model
            ->select($this->selectListadoSidebarChatBaseSql())
            ->join('cliente', 'cliente.id = conversacion.id_cliente')
            ->join('cliente_perfil', 'cliente_perfil.id_cliente = cliente.id', 'left')
            ->join('app', 'app.id = conversacion.id_app')
            ->join('usuario', 'usuario.id = conversacion.id_usuario', 'left')
            ->join('etiqueta', 'etiqueta.id = conversacion.id_etiqueta AND etiqueta.deleted_at IS NULL', 'left')
            ->where('conversacion.id_usuario', $id_usuario);

        if (isset($filtros['id_estado']) && $filtros['id_estado'] !== '' && $filtros['id_estado'] !== null) {
            $conversacion_model->where('conversacion.id_estado', (int) $filtros['id_estado']);
        } else {
            $conversacion_model->whereIn('conversacion.id_estado', [1, 3]);
        }

        if (!empty($filtros['search'])) {
            $search = trim((string) $filtros['search']);
            $conversacion_model->groupStart();
            $conversacion_model->like('cliente.telefono', $search);
            $conversacion_model->orLike('cliente_perfil.nombre', $search);
            $conversacion_model->orLike('cliente_perfil.apellido', $search);
            $conversacion_model->orLike('cliente_perfil.nombre_completo', $search);
            $conversacion_model->groupEnd();
        }

        if (!empty($filtros['fechaInicio'])) {
            $conversacion_model->where('DATE(conversacion.created_at) >=', $filtros['fechaInicio']);
        }
        if (!empty($filtros['fechaFin'])) {
            $conversacion_model->where('DATE(conversacion.created_at) <=', $filtros['fechaFin']);
        }

        if (array_key_exists('id_etiqueta', $filtros) && $filtros['id_etiqueta'] !== '' && $filtros['id_etiqueta'] !== null) {
            if ($filtros['id_etiqueta'] === '0' || $filtros['id_etiqueta'] === 0) {
                $conversacion_model->where('conversacion.id_etiqueta', null);
            } else {
                $conversacion_model->where('conversacion.id_etiqueta', (int) $filtros['id_etiqueta']);
            }
        }
        if (!empty($filtros['temperatura'])) {
            $conversacion_model->where('conversacion.temperatura', trim((string) $filtros['temperatura']));
        }

        $this->aplicarFiltroVentana24Horas($conversacion_model, $filtros);
        $this->aplicarFiltroRegistroVenta($conversacion_model, $filtros);
        $this->aplicarFiltroBusquedaMensaje($conversacion_model, $filtros);
        $this->aplicarFiltroOrigenCanal($conversacion_model, $filtros);

        $this->aplicarOrdenUltimoMensajeReciente($conversacion_model);

        return $this->ejecutarListadoPaginado($conversacion_model, $page, $perPage);
    }

    /**
     * @param array<string, mixed> $filtros search, fechaInicio, fechaFin, id_etiqueta, temperatura (mismo criterio que listarConversacion)
     */
    public function conversacionNoLeido($id_usuario, array $filtros = [], int $page = 1)
    {
        $conversacion_model = $this->getConversacionProvider();

        $conversacion_model
            ->select($this->selectListadoSidebarChatBaseSql())
            ->join('cliente', 'cliente.id = conversacion.id_cliente')
            ->join('cliente_perfil', 'cliente_perfil.id_cliente = cliente.id', 'left')
            ->join('app', 'app.id = conversacion.id_app', 'left')
            ->join('usuario', 'usuario.id = conversacion.id_usuario', 'left')
            ->join('etiqueta', 'etiqueta.id = conversacion.id_etiqueta AND etiqueta.deleted_at IS NULL', 'left')
            ->where('conversacion.id_usuario', $id_usuario)
            ->where(
                "EXISTS (
                    SELECT 1 FROM mensaje m
                    WHERE m.id_conversacion = conversacion.id
                      AND m.contesta = 'cliente'
                      AND m.id_estado = 3
                      AND m.deleted_at IS NULL
                )",
                null,
                false
            );

        if (! empty($filtros['search'])) {
            $search = trim((string) $filtros['search']);
            $conversacion_model->groupStart();
            $conversacion_model->like('cliente.telefono', $search);
            $conversacion_model->orLike('cliente_perfil.nombre', $search);
            $conversacion_model->orLike('cliente_perfil.apellido', $search);
            $conversacion_model->orLike('cliente_perfil.nombre_completo', $search);
            $conversacion_model->groupEnd();
        }

        if (! empty($filtros['fechaInicio'])) {
            $conversacion_model->where('DATE(conversacion.created_at) >=', $filtros['fechaInicio']);
        }
        if (! empty($filtros['fechaFin'])) {
            $conversacion_model->where('DATE(conversacion.created_at) <=', $filtros['fechaFin']);
        }

        if (array_key_exists('id_etiqueta', $filtros) && $filtros['id_etiqueta'] !== '' && $filtros['id_etiqueta'] !== null) {
            $conversacion_model->where('conversacion.id_etiqueta', (int) $filtros['id_etiqueta']);
        }
        if (! empty($filtros['temperatura'])) {
            $conversacion_model->where('conversacion.temperatura', trim((string) $filtros['temperatura']));
        }

        $this->aplicarFiltroVentana24Horas($conversacion_model, $filtros);
        $this->aplicarFiltroRegistroVenta($conversacion_model, $filtros);
        $this->aplicarFiltroBusquedaMensaje($conversacion_model, $filtros);
        $this->aplicarFiltroOrigenCanal($conversacion_model, $filtros);

        $this->aplicarOrdenUltimoMensajeReciente($conversacion_model);

        return $this->ejecutarListadoPaginadoSidebarChat($conversacion_model, $page);
    }

    /**
     * Conversaciones activas del usuario cuyo último mensaje fue enviado por el cliente (pendiente de respuesta).
     *
     * @param array<string, mixed> $filtros search, fechaInicio, fechaFin, id_etiqueta, temperatura, ventana_24h, origen_canal
     */
    public function conversacionFaltaResponder($id_usuario, array $filtros = [], int $page = 1)
    {
        $conversacion_model = $this->getConversacionProvider();

        $ultimoContestaSql = $this->sqlUltimoMensajeContestaEnConversacion();

        $conversacion_model
            ->select($this->selectListadoSidebarChatBaseSql())
            ->join('cliente', 'cliente.id = conversacion.id_cliente')
            ->join('cliente_perfil', 'cliente_perfil.id_cliente = cliente.id', 'left')
            ->join('app', 'app.id = conversacion.id_app', 'left')
            ->join('usuario', 'usuario.id = conversacion.id_usuario', 'left')
            ->join('etiqueta', 'etiqueta.id = conversacion.id_etiqueta AND etiqueta.deleted_at IS NULL', 'left')
            ->where('conversacion.id_usuario', $id_usuario)
            ->whereIn('conversacion.id_estado', [1, 3])
            ->where("{$ultimoContestaSql} = 'cliente'", null, false);

        if (! empty($filtros['search'])) {
            $search = trim((string) $filtros['search']);
            $conversacion_model->groupStart();
            $conversacion_model->like('cliente.telefono', $search);
            $conversacion_model->orLike('cliente_perfil.nombre', $search);
            $conversacion_model->orLike('cliente_perfil.apellido', $search);
            $conversacion_model->orLike('cliente_perfil.nombre_completo', $search);
            $conversacion_model->groupEnd();
        }

        if (! empty($filtros['fechaInicio'])) {
            $conversacion_model->where('DATE(conversacion.created_at) >=', $filtros['fechaInicio']);
        }
        if (! empty($filtros['fechaFin'])) {
            $conversacion_model->where('DATE(conversacion.created_at) <=', $filtros['fechaFin']);
        }

        if (array_key_exists('id_etiqueta', $filtros) && $filtros['id_etiqueta'] !== '' && $filtros['id_etiqueta'] !== null) {
            $conversacion_model->where('conversacion.id_etiqueta', (int) $filtros['id_etiqueta']);
        }
        if (! empty($filtros['temperatura'])) {
            $conversacion_model->where('conversacion.temperatura', trim((string) $filtros['temperatura']));
        }

        $this->aplicarFiltroVentana24Horas($conversacion_model, $filtros);
        $this->aplicarFiltroRegistroVenta($conversacion_model, $filtros);
        $this->aplicarFiltroBusquedaMensaje($conversacion_model, $filtros);
        $this->aplicarFiltroOrigenCanal($conversacion_model, $filtros);

        $this->aplicarOrdenUltimoMensajeReciente($conversacion_model);

        return $this->ejecutarListadoPaginadoSidebarChat($conversacion_model, $page);
    }

    public function listarConversacionFinalizada($id_usuario, int $page = 1, array $filtros = [])
    {
        $conversacion_model = $this->getConversacionProvider();

        $conversacion_model
            ->select($this->selectListadoSidebarChatBaseSql())
            ->join('cliente', 'cliente.id = conversacion.id_cliente')
            ->join('cliente_perfil', 'cliente_perfil.id_cliente = cliente.id', 'left')
            ->join('app', 'app.id = conversacion.id_app', 'left')
            ->join('usuario', 'usuario.id = conversacion.id_usuario', 'left')
            ->join('etiqueta', 'etiqueta.id = conversacion.id_etiqueta AND etiqueta.deleted_at IS NULL', 'left')
            ->where('conversacion.id_usuario', $id_usuario)
            ->where('conversacion.id_estado', 2);

        if (! empty($filtros['search'])) {
            $search = trim((string) $filtros['search']);
            $conversacion_model->groupStart();
            $conversacion_model->like('cliente.telefono', $search);
            $conversacion_model->orLike('cliente_perfil.nombre', $search);
            $conversacion_model->orLike('cliente_perfil.apellido', $search);
            $conversacion_model->orLike('cliente_perfil.nombre_completo', $search);
            $conversacion_model->groupEnd();
        }

        $this->aplicarFiltroOrigenCanal($conversacion_model, $filtros);
        $this->aplicarOrdenUltimoMensajeReciente($conversacion_model);

        return $this->ejecutarListadoPaginadoSidebarChat($conversacion_model, $page);
    }

    /**
     * @param array<string, mixed> $filtros search, id_estado, temperatura, id_etiqueta, fechaInicio, fechaFin, campaign_id
     */
    public function listarMisConversaciones($page = 1, $perPage = 10, $search = null, array $filtros = [])
    {
        $usuario = auth()->getUsuario();
        if (!$usuario) {
            throw new \Exception('No se pudo obtener el usuario autenticado');
        }

        if ($search !== null && $search !== '') {
            $filtros['search'] = $search;
        }

        $sqNombreCampania = '(SELECT ccm.nombre_campania FROM conversacion_campania_meta ccm '
            . 'WHERE ccm.id_conversacion = conversacion.id AND ccm.deleted_at IS NULL '
            . 'ORDER BY ccm.id DESC LIMIT 1)';

        $conversacion_model = $this->getConversacionProvider();
        $conversacion_model->select('
            conversacion.*, cliente.telefono, cliente_perfil.nombre as nombre_cliente, cliente_perfil.apellido as apellido_cliente, cliente_perfil.nombre_completo as nombre_completo_cliente, app.nombre as nombre_app,
            etiqueta.nombre as etiqueta_nombre, etiqueta.color as etiqueta_color,
            ' . $sqNombreCampania . ' as nombre_campania,
            (SELECT COUNT(*) FROM mensaje 
                 WHERE mensaje.id_conversacion = conversacion.id 
                 AND mensaje.contesta = "cliente" 
                 AND mensaje.id_estado = 3 
                 AND mensaje.deleted_at IS NULL) as mensajes_no_leidos,
            (SELECT MAX(mensaje.created_at) FROM mensaje 
                 WHERE mensaje.id_conversacion = conversacion.id 
                 AND mensaje.contesta = "cliente" 
                 AND mensaje.id_estado = 3 
                 AND mensaje.deleted_at IS NULL) as ultimo_mensaje_no_leido_fecha,
            ' . $this->selectUltimoMensajeContestaSql() . '
        ', false);
        $conversacion_model->join('cliente', 'cliente.id = conversacion.id_cliente');
        $conversacion_model->join('cliente_perfil', 'cliente_perfil.id_cliente = cliente.id', 'left');
        $conversacion_model->join('app', 'app.id = conversacion.id_app', 'left');
        $conversacion_model->join('etiqueta', 'etiqueta.id = conversacion.id_etiqueta AND etiqueta.deleted_at IS NULL', 'left');
        $conversacion_model->where('conversacion.id_usuario', $usuario->id);

        if (isset($filtros['id_estado']) && $filtros['id_estado'] !== '' && $filtros['id_estado'] !== null) {
            $conversacion_model->where('conversacion.id_estado', (int) $filtros['id_estado']);
        }

        if (! empty($filtros['search'])) {
            $term = trim((string) $filtros['search']);
            $conversacion_model->groupStart();
            $conversacion_model->like('cliente.telefono', $term);
            $conversacion_model->orLike('cliente_perfil.nombre', $term);
            $conversacion_model->orLike('cliente_perfil.apellido', $term);
            $conversacion_model->orLike('cliente_perfil.nombre_completo', $term);
            $conversacion_model->orLike('app.nombre', $term);
            $conversacion_model->groupEnd();
        }

        if (! empty($filtros['fechaInicio'])) {
            $conversacion_model->where('DATE(conversacion.created_at) >=', $filtros['fechaInicio']);
        }
        if (! empty($filtros['fechaFin'])) {
            $conversacion_model->where('DATE(conversacion.created_at) <=', $filtros['fechaFin']);
        }

        if (array_key_exists('id_etiqueta', $filtros) && $filtros['id_etiqueta'] !== '' && $filtros['id_etiqueta'] !== null) {
            if ($filtros['id_etiqueta'] === '0' || $filtros['id_etiqueta'] === 0) {
                $conversacion_model->where('conversacion.id_etiqueta', null);
            } else {
                $conversacion_model->where('conversacion.id_etiqueta', (int) $filtros['id_etiqueta']);
            }
        }

        if (! empty($filtros['temperatura'])) {
            $conversacion_model->where('conversacion.temperatura', trim((string) $filtros['temperatura']));
        }

        if (! empty($filtros['campaign_id'])) {
            $this->aplicarFiltroCampaignMeta($conversacion_model, (string) $filtros['campaign_id']);
        }

        $this->aplicarFiltroVentana24Horas($conversacion_model, $filtros);
        $this->aplicarFiltroRegistroVenta($conversacion_model, $filtros);
        $this->aplicarFiltroBusquedaMensaje($conversacion_model, $filtros);
        $this->aplicarFiltroOrigenCanal($conversacion_model, $filtros);

        $this->aplicarOrdenUltimoMensajeReciente($conversacion_model);

        return $this->ejecutarListadoPaginado($conversacion_model, max(1, (int) $page), $perPage);
    }

    
    public function editarConversacion(int $id, array $datos)
    {
        $conversacion_model = $this->getConversacionProvider();
        $conversacion_entity = $conversacion_model->find($id);
        
        if (!$conversacion_entity) {
            throw new \Exception('Conversación no encontrada');
        }

        $temperaturaAnterior = trim(strtolower((string) ($conversacion_entity->temperatura ?? 'frio')));
        if ($temperaturaAnterior === '') {
            $temperaturaAnterior = 'frio';
        }

        // Actualizar campos
        $conversacion_entity->id = $id;
        if (isset($datos['id_usuario'])) {
            $conversacion_entity->id_usuario = $datos['id_usuario'];
        }
        if (isset($datos['id_estado'])) {
            $conversacion_entity->id_estado = $datos['id_estado'];
        }
        if (isset($datos['id_motivo'])) {
            $conversacion_entity->id_motivo = $datos['id_motivo'];
        }
        if (array_key_exists('temperatura', $datos)) {
            $nuevaTemperatura = trim(strtolower((string) $datos['temperatura']));
            if ($temperaturaAnterior === 'venta' && $nuevaTemperatura !== 'venta') {
                throw new \Exception('La temperatura "venta" es definitiva y no puede cambiarse.');
            }
            $conversacion_entity->temperatura = $datos['temperatura'];
        }
        if (array_key_exists('id_etiqueta', $datos)) {
            $conversacion_entity->id_etiqueta = $datos['id_etiqueta'];
        }
        if (array_key_exists('id_asignado_por', $datos)) {
            $conversacion_entity->id_asignado_por = $datos['id_asignado_por'];
        }
        if (array_key_exists('asignado_at', $datos)) {
            $conversacion_entity->asignado_at = $datos['asignado_at'];
        }

        if (!$conversacion_model->save($conversacion_entity)) {
            throw new \Exception(json_encode($conversacion_model->errors()));
        }

        if (array_key_exists('temperatura', $datos)) {
            $nuevaTemperatura = trim(strtolower((string) $datos['temperatura']));
            if ($nuevaTemperatura === 'venta' && $temperaturaAnterior !== 'venta') {
                try {
                    $idUsuarioRegistro = null;
                    if (function_exists('auth')) {
                        $usuarioAuth = auth()->getUsuario();
                        $idUsuarioRegistro = $usuarioAuth ? (int) $usuarioAuth->id : null;
                    }
                    conversacion_venta()->registrarDesdeConversacion(
                        $id,
                        (int) $conversacion_entity->id_cliente,
                        (int) $conversacion_entity->id_usuario,
                        $idUsuarioRegistro,
                        $temperaturaAnterior
                    );
                } catch (\Throwable $e) {
                    log_message('error', '[CONVERSACION_VENTA] Error al registrar venta: ' . $e->getMessage());
                }
            }
        }

        // Obtener la conversación completa con relaciones para el socket
        $conversacion_completa = $conversacion_model->find($conversacion_entity->id);
        $cliente = clientes()->getClienteProvider()->find($conversacion_entity->id_cliente);
        
        // Emitir socket para notificar la actualización
        try {
            $socket_data = [
                'id' => $conversacion_completa->id,
                'id_cliente' => $conversacion_completa->id_cliente,
                'id_usuario' => $conversacion_completa->id_usuario,
                'id_estado' => $conversacion_completa->id_estado,
                'id_app' => $conversacion_completa->id_app,
                'id_motivo' => $conversacion_completa->id_motivo,
                'id_etiqueta' => $conversacion_completa->id_etiqueta ?? null,
                'temperatura' => $conversacion_completa->temperatura,
                'registro' => $conversacion_completa->registro,
                'origen' => $conversacion_completa->origen,
                'id_asignado_por' => $conversacion_completa->id_asignado_por,
                'servicio' => $conversacion_completa->servicio,
                'telefono' => $cliente->telefono,
                'created_at' => $conversacion_completa->created_at,
                'updated_at' => $conversacion_completa->updated_at,
                'asignado_at' => $conversacion_completa->asignado_at,
                'deleted_at' => $conversacion_completa->deleted_at,
            ];
            emitir_actualizacion_conversacion_socket($socket_data);
        } catch (\Exception $e) {
            log_message('error', 'Socket error al actualizar conversación: ' . $e->getMessage());
        }
        
        return $conversacion_entity;
    }

    /** @return list<string> */
    public function temperaturasPermitidas(): array
    {
        return ['frio', 'tibio', 'caliente', 'venta'];
    }

    /**
     * Actualiza temperatura si la nueva es "mayor" (frio &lt; tibio &lt; caliente &lt; venta), salvo $forzar.
     */
    public function aplicarTemperaturaConversacion(int $id, string $temperatura, bool $soloSiMayor = true): void
    {
        $temperatura = trim(strtolower($temperatura));
        if (! in_array($temperatura, $this->temperaturasPermitidas(), true)) {
            return;
        }

        $conversacion = $this->getConversacionProvider()->find($id);
        if (! $conversacion) {
            return;
        }

        $actual = trim(strtolower((string) ($conversacion->temperatura ?? 'frio')));
        if ($actual === '') {
            $actual = 'frio';
        }

        if ($soloSiMayor && ! $this->temperaturaEsMayorQue($temperatura, $actual)) {
            return;
        }

        if ($actual === $temperatura) {
            return;
        }

        $this->editarConversacion($id, ['temperatura' => $temperatura]);
    }

    private function temperaturaEsMayorQue(string $nueva, string $actual): bool
    {
        $rank = ['frio' => 0, 'tibio' => 1, 'caliente' => 2, 'venta' => 3];

        return ($rank[$nueva] ?? 0) > ($rank[$actual] ?? 0);
    }

    public function actualizarTemperaturaConversacion(int $id, string $temperatura): array
    {
        $temperatura = trim(strtolower($temperatura));
        $temperaturasPermitidas = $this->temperaturasPermitidas();

        if (! in_array($temperatura, $temperaturasPermitidas, true)) {
            throw new \Exception('La temperatura debe ser frio, tibio, caliente o venta');
        }

        $conversacionActualizada = $this->editarConversacion($id, [
            'temperatura' => $temperatura,
        ]);

        return [
            'id' => $conversacionActualizada->id,
            'temperatura' => $conversacionActualizada->temperatura,
            'message' => 'Temperatura actualizada correctamente',
        ];
    }


    /**
     * @param array<string, mixed> $opcionesTarea fecha_programada, contexto, origen_ia, etc.
     */
    public function actualizarEtiquetaConversacion(int $id, ?int $idEtiqueta, bool $dispararTarea = true, array $opcionesTarea = []): array
    {
        $conversacion_model = $this->getConversacionProvider();
        $conversacion_previa = $conversacion_model->find($id);
        if ($conversacion_previa === null) {
            throw new \Exception('Conversación no encontrada');
        }

        if ($idEtiqueta !== null) {
            $etiquetaModel = new EtiquetaModel();
            $etiqueta = $etiquetaModel->find($idEtiqueta);
            if (!$etiqueta) {
                throw new \Exception('Etiqueta no encontrada');
            }
        }

        $this->editarConversacion($id, [
            'id_etiqueta' => $idEtiqueta,
        ]);

        $conversacionFinal = $conversacion_model->find($id);
        if ($conversacionFinal === null) {
            throw new \Exception('Conversación no encontrada');
        }

        $respuesta = [
            'id' => $conversacionFinal->id,
            'id_etiqueta' => $conversacionFinal->id_etiqueta ?? null,
            'message' => 'Etiqueta de la conversación actualizada correctamente',
        ];

        if ($conversacionFinal->id_etiqueta !== null && $conversacionFinal->id_etiqueta !== '') {
            $etiquetaFinal = (new EtiquetaModel())->find((int) $conversacionFinal->id_etiqueta);
            if ($etiquetaFinal !== null) {
                $respuesta['etiqueta_nombre'] = $etiquetaFinal->nombre ?? null;
                $respuesta['etiqueta_color'] = $etiquetaFinal->color ?? null;
            }
        }

        return $respuesta;
    }

    public function abrirConversacion(int $id): array
    {
        $conversacion_model = $this->getConversacionProvider();
        $conversacion_entity = $conversacion_model->find($id);
        
        if (!$conversacion_entity) {
            throw new \Exception('Conversación no encontrada');
        }

        // Siempre marcar mensajes del cliente como leídos (estado 3 -> 4) al entrar al chat
        $mensaje_model = model('MensajeModel');
        $mensaje_model
            ->where('id_conversacion', $id)
            ->where('contesta', 'cliente')
            ->where('id_estado', 3)
            ->where('deleted_at', null)
            ->set(['id_estado' => 4])
            ->update();
        
        $mensajes_actualizados = $mensaje_model->affectedRows();
        
        // Actualizar contadores de mensajes no leídos si hubo cambios
        if ($mensajes_actualizados > 0) {
            try {
                calcular_y_emitir_contadores_no_leidos($id);
            } catch (\Exception $e) {
                log_message('error', 'Error al actualizar contadores: ' . $e->getMessage());
            }
        }
        
        // Solo cambiar estado de conversación si está en estado 3 (sin abrir) a 1 (en atención)
        $cambio_estado = false;
        if ($conversacion_entity->id_estado == 3) {
            $conversacion_actualizada = $this->editarConversacion($id, ['id_estado' => 1]);
            $cambio_estado = true;
        } else {
            $conversacion_actualizada = $conversacion_entity;
        }
        
        return [
            'message' => $cambio_estado ? 'Conversación abierta correctamente' : 'Mensajes marcados como leídos',
            'id' => $conversacion_actualizada->id,
            'id_estado' => $conversacion_actualizada->id_estado,
            'mensajes_marcados_leidos' => $mensajes_actualizados
        ];
    }

    public function finalizarConversacion(int $id, array $datos): array
    {
        // Validar que se proporcione el motivo de finalización
        if (!isset($datos['id_motivo']) || empty($datos['id_motivo'])) {
            throw new \Exception('El motivo de finalización es obligatorio');
        }

        $conversacion_previa = $this->getConversacionProvider()->find($id);
        if (!$conversacion_previa) {
            throw new \Exception('Conversación no encontrada');
        }

        // Verificar que el motivo existe
        $motivo_class = $this->config->motivo_conversacion_provider;
        $motivo_model = new $motivo_class();
        $motivo = $motivo_model->find($datos['id_motivo']);
        if (!$motivo) {
            throw new \Exception('El motivo de finalización no existe');
        }

        // Llamar a editarConversacion para cambiar el estado y emitir socket
        $conversacion_actualizada = $this->editarConversacion($id, [
            'id_estado' => 2,
            'id_motivo' => $datos['id_motivo'],
        ]);

        return [
            'message' => 'Conversación finalizada correctamente',
            'id' => $conversacion_actualizada->id,
            'id_estado' => $conversacion_actualizada->id_estado,
            'id_motivo' => $conversacion_actualizada->id_motivo,
        ];
    }

    public function listarConversacionesFinalizadasPorCliente(int $id_cliente): array
    {
        $conversacion_model = $this->getConversacionProvider();
        
        // Obtener datos del cliente
        $cliente = clientes()->getClienteProvider()->find($id_cliente);
        if (!$cliente) {
            throw new \Exception('Cliente no encontrado');
        }
        
        $cliente_perfil = clientes()->getClientePerfilProvider()->where('id_cliente', $id_cliente)->first();
        
        // Obtener conversaciones finalizadas
        $conversacion_model
            ->select('
                conversacion.*,
                usuario.usuario as nombre_usuario,
                usuario_perfil.nombres,
                usuario_perfil.apellidos,
                (SELECT COUNT(*) FROM mensaje WHERE mensaje.id_conversacion = conversacion.id) as total_mensajes,
                (SELECT mensaje FROM mensaje WHERE mensaje.id_conversacion = conversacion.id ORDER BY mensaje.created_at DESC LIMIT 1) as ultimo_mensaje
            ')
            ->join('usuario', 'usuario.id = conversacion.id_usuario')
            ->join('usuario_perfil', 'usuario_perfil.id_usuario = usuario.id', 'left')
            ->where('conversacion.id_cliente', $id_cliente)
            ->where('conversacion.id_estado', 2);

        $this->aplicarOrdenUltimoMensajeReciente($conversacion_model);

        $conversaciones = $conversacion_model->findAll();
        
        // Retornar estructura con datos del cliente y conversaciones
        return [
            'cliente' => [
                'id' => $cliente->id,
                'telefono' => $cliente->telefono,
                'nombre' => $cliente_perfil->nombre ?? null,
            ],
            'total_conversaciones' => count($conversaciones),
            'conversaciones' => $conversaciones
        ];
    }

    /**
     * Último asesor humano que atendió al cliente en esta app (historial, sin exigir que siga activa).
     */
    public function obtenerUltimoAsesorHumanoCliente(int $id_cliente, int $id_app, ?int $id_conversacion_excluir = null): ?int
    {
        if ($id_cliente <= 0 || $id_app <= 0) {
            return null;
        }

        $builder = $this->getConversacionProvider()
            ->select('conversacion.id_usuario')
            ->join('usuario', 'usuario.id = conversacion.id_usuario')
            ->where('conversacion.id_cliente', $id_cliente)
            ->where('conversacion.id_app', $id_app)
            ->where('conversacion.deleted_at', null)
            ->where('conversacion.id_usuario IS NOT NULL')
            ->where('usuario.es_bot', 0)
            ->where('usuario.deleted_at', null);

        if ($id_conversacion_excluir !== null && $id_conversacion_excluir > 0) {
            $builder->where('conversacion.id !=', $id_conversacion_excluir);
        }

        $row = $builder
            ->orderBy('conversacion.updated_at', 'DESC')
            ->limit(1)
            ->get()
            ->getRow();

        if ($row !== null && ! empty($row->id_usuario)) {
            return (int) $row->id_usuario;
        }

        $venta = model('ConversacionVentaModel')
            ->select('conversacion_venta.id_usuario')
            ->join('conversacion', 'conversacion.id = conversacion_venta.id_conversacion')
            ->join('usuario', 'usuario.id = conversacion_venta.id_usuario')
            ->where('conversacion_venta.id_cliente', $id_cliente)
            ->where('conversacion.id_app', $id_app)
            ->where('conversacion_venta.deleted_at', null)
            ->where('conversacion.deleted_at', null)
            ->where('usuario.es_bot', 0)
            ->where('usuario.deleted_at', null);

        if ($id_conversacion_excluir !== null && $id_conversacion_excluir > 0) {
            $venta->where('conversacion_venta.id_conversacion !=', $id_conversacion_excluir);
        }

        $rowVenta = $venta
            ->orderBy('conversacion_venta.registrada_at', 'DESC')
            ->limit(1)
            ->get()
            ->getRow();

        if ($rowVenta !== null && ! empty($rowVenta->id_usuario)) {
            return (int) $rowVenta->id_usuario;
        }

        return null;
    }

    /**
     * Cliente recurrente: mismo asesor solo si puede recibir chats (rol 5 en app, sin rol 8 off).
     * Si el asesor previo no califica, reparto equitativo entre personal CC (excluyéndolo).
     *
     * @return array{
     *     id_usuario: ?int,
     *     origen: 'sin_historial'|'asesor_previo'|'apoyo_equitativo'|'sin_asesor_disponible',
     *     id_asesor_previo: ?int,
     *     nombre_asesor_previo: ?string
     * }
     */
    public function resolverAsignacionClienteRecurrente(int $id_cliente, int $id_app, $app, ?int $id_conversacion_excluir = null): array
    {
        $sinHistorial = [
            'id_usuario' => null,
            'origen' => 'sin_historial',
            'id_asesor_previo' => null,
            'nombre_asesor_previo' => null,
        ];

        $id_asesor_previo = $this->obtenerUltimoAsesorHumanoCliente($id_cliente, $id_app, $id_conversacion_excluir);
        if ($id_asesor_previo === null) {
            return $sinHistorial;
        }

        $nombre_previo = $this->obtenerNombreVisibleAsesor($id_asesor_previo);

        if (app()->usuarioPuedeRecibirChatsEnApp($id_asesor_previo, $id_app)) {
            log_message('debug', "[CLIENTE RECURRENTE] Asignación al asesor previo (rol CC): {$id_asesor_previo}");

            return [
                'id_usuario' => $id_asesor_previo,
                'origen' => 'asesor_previo',
                'id_asesor_previo' => $id_asesor_previo,
                'nombre_asesor_previo' => $nombre_previo,
            ];
        }

        $id_equitativo = app()->obtenerUsuarioParaApp($app, $id_asesor_previo);
        if ($id_equitativo !== null) {
            log_message(
                'debug',
                "[CLIENTE RECURRENTE] Asesor previo {$id_asesor_previo} sin rol CC/disponible; equitativo: {$id_equitativo}"
            );

            return [
                'id_usuario' => $id_equitativo,
                'origen' => 'apoyo_equitativo',
                'id_asesor_previo' => $id_asesor_previo,
                'nombre_asesor_previo' => $nombre_previo,
            ];
        }

        log_message(
            'warning',
            "[CLIENTE RECURRENTE] Sin asesor disponible (previo {$id_asesor_previo} inactivo/off y sin equitativo en app {$id_app})"
        );

        return [
            'id_usuario' => null,
            'origen' => 'sin_asesor_disponible',
            'id_asesor_previo' => $id_asesor_previo,
            'nombre_asesor_previo' => $nombre_previo,
        ];
    }

    /**
     * @return int|null null si el cliente no tiene historial con asesor humano en la app
     */
    public function resolverAsesorClienteConHistorial(int $id_cliente, int $id_app, $app, ?int $id_conversacion_excluir = null): ?int
    {
        $asignacion = $this->resolverAsignacionClienteRecurrente($id_cliente, $id_app, $app, $id_conversacion_excluir);

        return $asignacion['id_usuario'];
    }

    /**
     * Contexto para el front: apoyo a otra asesora (sin persistir mensaje en BD).
     *
     * @return array{id_asesor_previo: int, nombre_asesor_previo: string, id_usuario_actual: int}|null
     */
    public function construirContextoApoyoRecurrente(int $id_conversacion): ?array
    {
        if ($id_conversacion <= 0) {
            return null;
        }

        $conversacion = $this->getConversacionProvider()->find($id_conversacion);
        if ($conversacion === null) {
            return null;
        }

        $id_usuario_actual = (int) ($conversacion->id_usuario ?? 0);
        $id_cliente = (int) ($conversacion->id_cliente ?? 0);
        $id_app = (int) ($conversacion->id_app ?? 0);

        if ($id_usuario_actual <= 0 || $id_cliente <= 0 || $id_app <= 0) {
            return null;
        }

        $usuarioActual = model('UsuarioModel')->find($id_usuario_actual);
        if ($usuarioActual === null || (int) ($usuarioActual->es_bot ?? 0) === 1) {
            return null;
        }

        $id_asesor_previo = $this->obtenerUltimoAsesorHumanoCliente($id_cliente, $id_app, $id_conversacion);
        if ($id_asesor_previo === null || $id_asesor_previo === $id_usuario_actual) {
            return null;
        }

        return [
            'id_asesor_previo' => $id_asesor_previo,
            'nombre_asesor_previo' => $this->obtenerNombreVisibleAsesor($id_asesor_previo),
            'id_usuario_actual' => $id_usuario_actual,
        ];
    }

    private function obtenerNombreVisibleAsesor(int $id_usuario): string
    {
        $perfil = model('UsuarioPerfilModel')
            ->where('id_usuario', $id_usuario)
            ->where('deleted_at', null)
            ->first();

        if ($perfil !== null) {
            $nombre = trim((string) ($perfil->nombres ?? '') . ' ' . (string) ($perfil->apellidos ?? ''));
            if ($nombre !== '') {
                return $nombre;
            }
        }

        $usuario = model('UsuarioModel')->find($id_usuario);
        if ($usuario !== null) {
            $login = trim((string) ($usuario->usuario ?? ''));
            if ($login !== '') {
                return $login;
            }
        }

        return 'Asesora #' . $id_usuario;
    }

    private function determinarTipoConversacion(?int $id_usuario): ?string
    {
        if (!$id_usuario) {
            return null;
        }
        // Obtener la app del usuario a través de la tabla pivot
        $usuario_app = model('UsuarioAppModel')
            ->where('id_usuario', $id_usuario)
            ->where('deleted_at', null)
            ->first();
        
        if (!$usuario_app) {
            log_message('warning', "Usuario $id_usuario no tiene app asignada para determinar tipo de conversación");
            return null;
        }
        $app = app()->getAppProvider()->find($usuario_app->id_app);
        
        if (!$app) {
            log_message('warning', "App {$usuario_app->id_app} no encontrada para usuario $id_usuario");
            return null;
        }
        
        if ($app->modo === 'compartido') {
            return 'contact_center';
        } elseif ($app->modo === 'individual') {
            return 'customer_success';
        }
        return null;
    }
    
    /**
     * @param bool $agruparPorAnuncio true: una opción por anuncio (source_id); false: por campaña Meta
     *
     * @return list<array{campaign_id: string, nombre_campania: ?string, source_id?: ?string, meta_campaign_id?: ?string}>
     */
    public function listarCampaniasMetaFiltroContactCenter(bool $agruparPorAnuncio = true): array
    {
        $db = \Config\Database::connect();

        if ($agruparPorAnuncio) {
            $sql = 'SELECT MAX(TRIM(ccm.source_id)) AS source_id,
                    MAX(TRIM(ccm.campaign_id)) AS meta_campaign_id,
                    MAX(ccm.nombre_campania) AS nombre_campania
                FROM conversacion_campania_meta ccm
                INNER JOIN conversacion c ON c.id = ccm.id_conversacion
                    AND c.deleted_at IS NULL
                    AND c.tipo = \'contact_center\'
                WHERE ccm.deleted_at IS NULL
                  AND (
                    NULLIF(TRIM(ccm.source_id), \'\') IS NOT NULL
                    OR NULLIF(TRIM(ccm.campaign_id), \'\') IS NOT NULL
                  )
                GROUP BY COALESCE(NULLIF(TRIM(ccm.source_id), \'\'), CONCAT(\'c:\', TRIM(ccm.campaign_id)))
                ORDER BY nombre_campania ASC';
        } else {
            $sql = 'SELECT MAX(TRIM(ccm.campaign_id)) AS meta_campaign_id, MAX(ccm.nombre_campania) AS nombre_campania
                FROM conversacion_campania_meta ccm
                INNER JOIN conversacion c ON c.id = ccm.id_conversacion
                    AND c.deleted_at IS NULL
                    AND c.tipo = \'contact_center\'
                WHERE ccm.deleted_at IS NULL
                  AND NULLIF(TRIM(ccm.campaign_id), \'\') IS NOT NULL
                GROUP BY TRIM(ccm.campaign_id)
                ORDER BY nombre_campania ASC';
        }

        $rows = $db->query($sql)->getResult();
        $db->close();

        $out = [];
        foreach ($rows as $row) {
            if ($agruparPorAnuncio) {
                $sourceId = trim((string) ($row->source_id ?? ''));
                $metaCampaignId = trim((string) ($row->meta_campaign_id ?? ''));
                $filterId = $sourceId !== '' ? $sourceId : $metaCampaignId;
                if ($filterId === '') {
                    continue;
                }
                $out[] = [
                    'campaign_id' => $filterId,
                    'nombre_campania' => $row->nombre_campania,
                    'source_id' => $sourceId !== '' ? $sourceId : null,
                    'meta_campaign_id' => $metaCampaignId !== '' ? $metaCampaignId : null,
                ];
            } else {
                $metaCampaignId = trim((string) ($row->meta_campaign_id ?? ''));
                if ($metaCampaignId === '') {
                    continue;
                }
                $out[] = [
                    'campaign_id' => $metaCampaignId,
                    'nombre_campania' => $row->nombre_campania,
                ];
            }
        }

        return $out;
    }

    private function aplicarFiltroCampaignMeta($conversacion_model, string $campaignIdFilter): void
    {
        $cid = trim($campaignIdFilter);
        if ($cid === '') {
            return;
        }

        $db = \Config\Database::connect();
        $escaped = $db->escape($cid);
        $existsMeta = 'EXISTS (SELECT 1 FROM conversacion_campania_meta ccm '
            . 'WHERE ccm.id_conversacion = conversacion.id AND ccm.deleted_at IS NULL '
            . "AND (TRIM(ccm.source_id) = {$escaped} OR TRIM(ccm.campaign_id) = {$escaped}))";
        $conversacion_model->groupStart();
        $conversacion_model->where('conversacion.campaign_id', $cid);
        $conversacion_model->orWhere('conversacion.source_id', $cid);
        $conversacion_model->orWhere($existsMeta, null, false);
        $conversacion_model->groupEnd();
    }

    /**
     * Asesores con conversaciones asignadas (para filtro "ocultar" en listados).
     *
     * @return list<array{id: int, usuario: string, nombre_completo: string}>
     */
    public function listarAsesoresParaFiltroListado(?string $tipo = null): array
    {
        $model = $this->getConversacionProvider();
        $builder = $model->builder();
        $builder
            ->select('usuario.id, usuario.usuario, usuario_perfil.nombres, usuario_perfil.apellidos')
            ->join('usuario', 'usuario.id = conversacion.id_usuario', 'inner')
            ->join('usuario_perfil', 'usuario_perfil.id_usuario = usuario.id', 'left')
            ->where('conversacion.deleted_at', null)
            ->where('conversacion.id_usuario IS NOT NULL')
            ->where('usuario.deleted_at', null);

        if ($tipo !== null && $tipo !== '') {
            $builder->where('conversacion.tipo', $tipo);
        }

        $rows = $builder
            ->groupBy('usuario.id, usuario.usuario, usuario_perfil.nombres, usuario_perfil.apellidos')
            ->orderBy('usuario_perfil.nombres', 'ASC')
            ->orderBy('usuario_perfil.apellidos', 'ASC')
            ->get()
            ->getResult();

        $out = [];
        foreach ($rows as $row) {
            $nombre = trim(($row->nombres ?? '') . ' ' . ($row->apellidos ?? ''));
            $out[] = [
                'id' => (int) $row->id,
                'usuario' => (string) $row->usuario,
                'nombre_completo' => $nombre !== '' ? $nombre : (string) $row->usuario,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $valor int, string "1,2", o array desde query
     * @return list<int>
     */
    private function normalizarExcluirIdUsuario($valor): array
    {
        if ($valor === null || $valor === '' || $valor === []) {
            return [];
        }

        if (is_array($valor)) {
            $ids = $valor;
        } elseif (is_string($valor) && str_contains($valor, ',')) {
            $ids = explode(',', $valor);
        } else {
            $ids = [$valor];
        }

        $ids = array_map(static fn ($id) => (int) $id, $ids);

        return array_values(array_unique(array_filter($ids, static fn ($id) => $id > 0)));
    }

    private function aplicarFiltroExcluirUsuarios($conversacion_model, array $filtros): void
    {
        $ids = $this->normalizarExcluirIdUsuario($filtros['excluir_id_usuario'] ?? null);
        if ($ids !== []) {
            $conversacion_model->whereNotIn('conversacion.id_usuario', $ids);
        }
    }

    /**
     * @param array<string, mixed> $filtros origen_canal: whatsapp
     */
    private function aplicarFiltroOrigenCanal($conversacion_model, array $filtros): void
    {
        $canalRaw = strtolower(trim((string) ($filtros['origen_canal'] ?? '')));
        if ($canalRaw !== 'whatsapp' && $canalRaw !== 'api-crm') {
            return;
        }

        $col = "LOWER(COALESCE(conversacion.origen, ''))";
        $conversacion_model->where(
            "({$col} IN ('api-crm','whatsapp','wa') OR {$col} LIKE '%whatsapp%')",
            null,
            false,
        );
    }

    /**
     * Filtra conversaciones cuyos mensajes contienen todas las palabras clave (AND, sin distinguir mayúsculas).
     * Usa EXISTS para no duplicar filas; conviene combinarlo con fecha/estado/tipo del listado.
     *
     * @param array<string, mixed> $filtros search_mensaje
     */
    private function aplicarFiltroBusquedaMensaje($conversacion_model, array $filtros): void
    {
        $raw = $filtros['search_mensaje'] ?? null;
        if ($raw === null || trim((string) $raw) === '') {
            return;
        }

        $texto = trim((string) $raw);
        $palabras = mensaje()->extraerPalabrasClaveBusqueda($texto);

        if ($palabras === []) {
            $conversacion_model->where('1 = 0', null, false);

            return;
        }

        $existsSql = mensaje()->construirExistsSqlBusquedaMensaje($palabras);
        if ($existsSql !== null) {
            $conversacion_model->where($existsSql, null, false);
        }
    }

    public function listarConversacionesContactCenter($page = 1, $perPage = 10, $filtros = []): array
    {
        $conversacion_model = $this->getConversacionProvider();
        $sqNombre = '(SELECT ccm.nombre_campania FROM conversacion_campania_meta ccm '
            . 'WHERE ccm.id_conversacion = conversacion.id AND ccm.deleted_at IS NULL '
            . 'ORDER BY ccm.id DESC LIMIT 1)';
        $sqCodigo = '(SELECT ccm.codigo FROM conversacion_campania_meta ccm '
            . 'WHERE ccm.id_conversacion = conversacion.id AND ccm.deleted_at IS NULL '
            . 'ORDER BY ccm.id DESC LIMIT 1)';

        $sqNombreAsignadoPor = 'COALESCE(NULLIF(TRIM(CONCAT(COALESCE(usuario_perfil_asignador.nombres, \'\'), \' \', '
            . 'COALESCE(usuario_perfil_asignador.apellidos, \'\'))), \'\'), usuario_asignador.usuario)';

        $conversacion_model
            ->select(
                'conversacion.*, cliente.telefono, usuario.usuario as nombre_usuario, '
                . 'usuario_perfil.nombres, usuario_perfil.apellidos, cliente_perfil.nombre as nombre_cliente, '
                . 'cliente_perfil.apellido as apellido_cliente, '
                . 'etiqueta.nombre AS etiqueta_nombre, etiqueta.color AS etiqueta_color, '
                . 'usuario_asignador.usuario AS asignado_por_usuario, '
                . 'usuario_perfil_asignador.nombres AS asignado_por_nombres, '
                . 'usuario_perfil_asignador.apellidos AS asignado_por_apellidos, '
                . 'usuario_asignador.es_bot AS asignado_por_es_bot, '
                . 'usuario.es_bot AS usuario_es_bot, '
                . "{$sqNombreAsignadoPor} AS nombre_asignado_por, "
                . "{$sqNombre} AS nombre_campania, "
                . "{$sqCodigo} AS codigo, "
                . $this->selectUltimoMensajeContestaSql() . ', '
                . $this->selectVentaRegistradaAtSql()
            )
            ->join('cliente', 'cliente.id = conversacion.id_cliente')
            ->join('cliente_perfil', 'cliente_perfil.id_cliente = cliente.id', 'left')
            ->join('usuario', 'usuario.id = conversacion.id_usuario', 'left')
            ->join('usuario_perfil', 'usuario_perfil.id_usuario = usuario.id', 'left')
            ->join('usuario AS usuario_asignador', 'usuario_asignador.id = conversacion.id_asignado_por', 'left')
            ->join(
                'usuario_perfil AS usuario_perfil_asignador',
                'usuario_perfil_asignador.id_usuario = usuario_asignador.id',
                'left'
            )
            ->join('etiqueta', 'etiqueta.id = conversacion.id_etiqueta AND etiqueta.deleted_at IS NULL', 'left')
            ->where('conversacion.id_usuario IS NOT NULL')
            ->where('conversacion.tipo', 'contact_center');
        
        // Filtro por estado
        if (isset($filtros['id_estado']) && $filtros['id_estado'] !== '' && $filtros['id_estado'] !== null) {
            $conversacion_model->where('conversacion.id_estado', $filtros['id_estado']);
        }
        
        // Búsqueda: cliente, asesor y campaña Meta (nombre_campania / codigo en conversacion_campania_meta)
        if (!empty($filtros['search'])) {
            $search = $filtros['search'];
            $db = \Config\Database::connect();
            $likePattern = '%' . $db->escapeLikeString($search) . '%';
            $likeSql = $db->escape($likePattern);
            $existsCampania = 'EXISTS (SELECT 1 FROM conversacion_campania_meta ccm '
                . 'WHERE ccm.id_conversacion = conversacion.id AND ccm.deleted_at IS NULL '
                . "AND (ccm.nombre_campania LIKE {$likeSql} OR ccm.codigo LIKE {$likeSql}))";

            $conversacion_model->groupStart();
            $conversacion_model->like('cliente.telefono', $search);
            $conversacion_model->orLike('cliente_perfil.nombre', $search);
            $conversacion_model->orLike('cliente_perfil.apellido', $search);
            $conversacion_model->orLike('cliente_perfil.nombre_completo', $search);
            $conversacion_model->orLike('usuario_perfil.nombres', $search);
            $conversacion_model->orLike('usuario_perfil.apellidos', $search);
            $conversacion_model->orWhere($existsCampania, null, false);
            $conversacion_model->groupEnd();
        }
        
        // Filtro por rango de fechas
        if (!empty($filtros['fechaInicio'])) {
            $conversacion_model->where('DATE(conversacion.created_at) >=', $filtros['fechaInicio']);
        }
        if (!empty($filtros['fechaFin'])) {
            $conversacion_model->where('DATE(conversacion.created_at) <=', $filtros['fechaFin']);
        }

        //filtro por temperatura
        if (!empty($filtros['temperatura'])) {
            $conversacion_model->where('conversacion.temperatura', $filtros['temperatura']);
        }

        // Filtro por etiqueta (conversacion.id_etiqueta); valor 0 = sin etiqueta (NULL)
        if (array_key_exists('id_etiqueta', $filtros) && $filtros['id_etiqueta'] !== '' && $filtros['id_etiqueta'] !== null) {
            if ($filtros['id_etiqueta'] === '0' || $filtros['id_etiqueta'] === 0) {
                $conversacion_model->where('conversacion.id_etiqueta', null);
            } else {
                $conversacion_model->where('conversacion.id_etiqueta', (int) $filtros['id_etiqueta']);
            }
        }

        // Filtro por anuncio/campaña Meta (source_id o campaign_id)
        if (!empty($filtros['campaign_id'])) {
            $this->aplicarFiltroCampaignMeta($conversacion_model, (string) $filtros['campaign_id']);
        }

        $this->aplicarFiltroExcluirUsuarios($conversacion_model, $filtros);
        $this->aplicarFiltroVentana24Horas($conversacion_model, $filtros);
        $this->aplicarFiltroRegistroVenta($conversacion_model, $filtros);
        $this->aplicarFiltroBusquedaMensaje($conversacion_model, $filtros);
        $this->aplicarFiltroOrigenCanal($conversacion_model, $filtros);

        $this->aplicarOrdenUltimoMensajeReciente($conversacion_model);

        return $this->ejecutarListadoPaginado($conversacion_model, max(1, (int) $page), $perPage);
    }

    public function listarConversacionesCustomerSuccess($page = 1, $perPage = 10, $filtros = []): array
    {
        $conversacion_model = $this->getConversacionProvider();
        $conversacion_model
            ->select(
                'conversacion.*, cliente.telefono, usuario.usuario as nombre_usuario, usuario.es_bot AS usuario_es_bot, usuario_perfil.nombres, usuario_perfil.apellidos, cliente_perfil.nombre as nombre_cliente, cliente_perfil.apellido as apellido_cliente, etiqueta.nombre AS etiqueta_nombre, etiqueta.color AS etiqueta_color, '
                . $this->selectUltimoMensajeContestaSql() . ', '
                . $this->selectVentaRegistradaAtSql()
            )
            ->join('cliente', 'cliente.id = conversacion.id_cliente')
            ->join('cliente_perfil', 'cliente_perfil.id_cliente = cliente.id', 'left')
            ->join('usuario', 'usuario.id = conversacion.id_usuario', 'left')
            ->join('usuario_perfil', 'usuario_perfil.id_usuario = usuario.id', 'left')
            ->join('etiqueta', 'etiqueta.id = conversacion.id_etiqueta AND etiqueta.deleted_at IS NULL', 'left')
            ->where('conversacion.id_usuario IS NOT NULL')
            ->where('conversacion.tipo', 'customer_success');
        
        // Filtro por estado
        if (isset($filtros['id_estado']) && $filtros['id_estado'] !== '' && $filtros['id_estado'] !== null) {
            $conversacion_model->where('conversacion.id_estado', $filtros['id_estado']);
        }
        
        // Búsqueda por teléfono o nombre del cliente
        if (!empty($filtros['search'])) {
            $search = $filtros['search'];
            $conversacion_model->groupStart();
            $conversacion_model->like('cliente.telefono', $search);
            $conversacion_model->orLike('cliente_perfil.nombre', $search);
            $conversacion_model->orLike('cliente_perfil.apellido', $search);
            $conversacion_model->orLike('cliente_perfil.nombre_completo', $search);
            $conversacion_model->orLike('usuario_perfil.nombres', $search);
            $conversacion_model->orLike('usuario_perfil.apellidos', $search);
            $conversacion_model->groupEnd();
        }
        
        // Filtro por rango de fechas
        if (!empty($filtros['fechaInicio'])) {
            $conversacion_model->where('DATE(conversacion.created_at) >=', $filtros['fechaInicio']);
        }
        if (!empty($filtros['fechaFin'])) {
            $conversacion_model->where('DATE(conversacion.created_at) <=', $filtros['fechaFin']);
        }

        if (array_key_exists('id_etiqueta', $filtros) && $filtros['id_etiqueta'] !== '' && $filtros['id_etiqueta'] !== null) {
            if ($filtros['id_etiqueta'] === '0' || $filtros['id_etiqueta'] === 0) {
                $conversacion_model->where('conversacion.id_etiqueta', null);
            } else {
                $conversacion_model->where('conversacion.id_etiqueta', (int) $filtros['id_etiqueta']);
            }
        }

        $this->aplicarFiltroExcluirUsuarios($conversacion_model, $filtros);
        $this->aplicarFiltroVentana24Horas($conversacion_model, $filtros);
        $this->aplicarFiltroRegistroVenta($conversacion_model, $filtros);
        $this->aplicarFiltroBusquedaMensaje($conversacion_model, $filtros);
        $this->aplicarFiltroOrigenCanal($conversacion_model, $filtros);

        $this->aplicarOrdenUltimoMensajeReciente($conversacion_model);

        return $this->ejecutarListadoPaginado($conversacion_model, max(1, (int) $page), $perPage);
    }

    public function transferirConversacion(
        int $id_conversacion,
        int $id_usuario_destino,
        ?int $id_usuario_origen = null,
        ?string $temperatura = null,
        bool $exigirUsuarioApp = true,
    ): array {
        $conversacion_model = $this->getConversacionProvider();
        $conversacion = $conversacion_model->find($id_conversacion);
        
        if (!$conversacion) {
            throw new \Exception('Conversación no encontrada');
        }

        // Validar que el usuario destino existe y está activo
        $usuario_destino = model('UsuarioModel')->find($id_usuario_destino);
        if (!$usuario_destino || $usuario_destino->activo != 1) {
            throw new \Exception('El usuario destino no existe o no está activo');
        }

        // Validar que el usuario destino pertenece a la misma app
        $usuario_app_destino = model('UsuarioAppModel')
            ->where('id_usuario', $id_usuario_destino)
            ->where('id_app', $conversacion->id_app)
            ->where('deleted_at', null)
            ->first();
        
        if ($exigirUsuarioApp && !$usuario_app_destino) {
            throw new \Exception('El usuario destino no pertenece a la app de esta conversación');
        }

        // Si se proporciona id_usuario_origen, validar que la conversación pertenece a ese usuario
        if ($id_usuario_origen !== null && $conversacion->id_usuario != $id_usuario_origen) {
            throw new \Exception('La conversación no pertenece al usuario origen especificado');
        }

        // No permitir transferir a sí mismo
        if ($conversacion->id_usuario == $id_usuario_destino) {
            throw new \Exception('No se puede transferir la conversación al mismo usuario');
        }

        // Transferir la conversación
        $datosEditar = [
            'id_usuario' => $id_usuario_destino,
            'id_estado' => 3,
            'id_asignado_por' => $id_usuario_origen ?? $conversacion->id_usuario,
            'asignado_at' => date('Y-m-d H:i:s'),
        ];
        if ($temperatura !== null && trim($temperatura) !== '') {
            $datosEditar['temperatura'] = trim(strtolower($temperatura));
        }

        $conversacion_actualizada = $this->editarConversacion($id_conversacion, $datosEditar);

        // Para el usuario destino se comporta como una conversación nueva en su bandeja.
        // Reutiliza el endpoint/socket existente: /emitir-conversacion -> nueva-conversacion.
        $this->emitirConversacionComoNuevaSocket($id_conversacion);

        return [
            'message' => 'Conversación transferida correctamente',
            'id' => $conversacion_actualizada->id,
            'id_estado' => $conversacion_actualizada->id_estado,
            'id_usuario_anterior' => $conversacion->id_usuario,
            'id_usuario_nuevo' => $id_usuario_destino
        ];
    }

    public function transferirConversacionesMasivo(array $ids_conversaciones, int $id_usuario_destino, ?int $id_usuario_origen = null): array
    {
        if (empty($ids_conversaciones)) {
            throw new \Exception('No se proporcionaron conversaciones para transferir');
        }

        // Validar usuario destino
        $usuario_destino = model('UsuarioModel')->find($id_usuario_destino);
        if (!$usuario_destino || $usuario_destino->activo != 1) {
            throw new \Exception('El usuario destino no existe o no está activo');
        }

        $resultados = [
            'exitosos' => 0,
            'errores' => 0,
            'detalles' => []
        ];

        foreach ($ids_conversaciones as $id_conversacion) {
            try {
                $resultado = $this->transferirConversacion((int) $id_conversacion, $id_usuario_destino, $id_usuario_origen);
                $resultados['exitosos']++;
                $resultados['detalles'][] = [
                    'id_conversacion' => $id_conversacion,
                    'estado' => 'exitoso',
                    'mensaje' => $resultado['message']
                ];
            } catch (\Exception $e) {
                $resultados['errores']++;
                $resultados['detalles'][] = [
                    'id_conversacion' => $id_conversacion,
                    'estado' => 'error',
                    'mensaje' => $e->getMessage()
                ];
            }
        }

        return $resultados;
    }

    public function obtenerUsuariosDisponiblesParaTransferencia(int $id_app): array
    {
        // Obtener todos los usuarios activos asignados a la app
        $usuarios = model('UsuarioModel')
            ->select('usuario.id, usuario.usuario, usuario_perfil.nombres, usuario_perfil.apellidos')
            ->join('usuario_perfil', 'usuario_perfil.id_usuario = usuario.id', 'left')
            ->join('usuario_app', 'usuario_app.id_usuario = usuario.id', 'inner')
            ->where('usuario_app.id_app', $id_app)
            ->where('usuario_app.deleted_at', null)
            ->where('usuario.activo', 1)
            ->where('usuario.deleted_at', null)
            ->orderBy('usuario_perfil.nombres', 'ASC')
            ->orderBy('usuario_perfil.apellidos', 'ASC')
            ->findAll();

        return array_map(function($usuario) {
            $nombreCompleto = trim(($usuario->nombres ?? '') . ' ' . ($usuario->apellidos ?? ''));
            return [
                'id' => $usuario->id,
                'usuario' => $usuario->usuario,
                'nombre_completo' => $nombreCompleto ?: $usuario->usuario
            ];
        }, $usuarios);
    }

    private function emitirConversacionComoNuevaSocket(int $id_conversacion): void
    {
        try {
            $conversacion_model = $this->getConversacionProvider();
            $conversacion = $conversacion_model->find($id_conversacion);
            if (!$conversacion) {
                return;
            }

            $cliente = clientes()->getClienteProvider()->find($conversacion->id_cliente);
            $cliente_perfil = clientes()->getClientePerfilProvider()
                ->where('id_cliente', $conversacion->id_cliente)
                ->first();

            $app = $conversacion->id_app ? model('AppModel')->find($conversacion->id_app) : null;

            $mensaje_model = model('MensajeModel');
            $mensajes_no_leidos = $mensaje_model
                ->where('id_conversacion', $conversacion->id)
                ->where('contesta', 'cliente')
                ->where('id_estado', 3)
                ->where('deleted_at', null)
                ->countAllResults(false);

            $ultimo_mensaje_no_leido = $mensaje_model
                ->select('created_at')
                ->where('id_conversacion', $conversacion->id)
                ->where('contesta', 'cliente')
                ->where('id_estado', 3)
                ->where('deleted_at', null)
                ->orderBy('created_at', 'DESC')
                ->limit(1)
                ->get()
                ->getRow();

            emitir_conversacion_socket([
                'id' => $conversacion->id,
                'id_cliente' => $conversacion->id_cliente,
                'id_usuario' => $conversacion->id_usuario,
                'id_estado' => $conversacion->id_estado,
                'id_app' => $conversacion->id_app,
                'id_motivo' => $conversacion->id_motivo,
                'id_etiqueta' => $conversacion->id_etiqueta ?? null,
                'temperatura' => $conversacion->temperatura,
                'tipo' => $conversacion->tipo,
                'registro' => $conversacion->registro,
                'origen' => $conversacion->origen,
                'id_asignado_por' => $conversacion->id_asignado_por,
                'servicio' => $conversacion->servicio,
                'telefono' => $cliente->telefono ?? null,
                'nombre_cliente' => $cliente_perfil->nombre ?? null,
                'apellido_cliente' => $cliente_perfil->apellido ?? null,
                'nombre_completo_cliente' => $cliente_perfil->nombre_completo ?? null,
                'nombre_app' => $app->nombre ?? null,
                'mensajes_no_leidos' => $mensajes_no_leidos,
                'ultimo_mensaje_no_leido_fecha' => $ultimo_mensaje_no_leido ? $ultimo_mensaje_no_leido->created_at : null,
                'ultimo_mensaje_contesta' => $this->obtenerUltimoMensajeContesta((int) $conversacion->id),
                'created_at' => $conversacion->created_at,
                'updated_at' => $conversacion->updated_at,
                'asignado_at' => $conversacion->asignado_at,
                'deleted_at' => $conversacion->deleted_at,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Socket error al emitir transferencia como nueva conversación: ' . $e->getMessage());
        }
    }

    /**
     * Lista campañas publicitarias directamente desde Meta Marketing API (Graph).
     *
     * @param array<string, mixed> $opciones id_app, ad_account_id, after, limit, effective_status, solo_cuentas
     *
     * @return array<string, mixed>
     */
    public function listarCampaniasMetaDesdeApi(int $idApp, array $opciones = []): array
    {
        $app = app()->getAppProvider()->find($idApp);
        if ($app === null) {
            throw new \Exception("No se encontró la app con ID: {$idApp}");
        }

        $accessToken = trim((string) ($app->access_token ?? ''));
        if ($accessToken === '') {
            throw new \Exception('La app no tiene access_token configurado');
        }

        $limit = max(1, min(100, (int) ($opciones['limit'] ?? 50)));
        $soloCuentas = ! empty($opciones['solo_cuentas']);

        $adAccounts = $this->listarAdAccountsMeta($accessToken);
        if ($soloCuentas) {
            return [
                'id_app' => $idApp,
                'app_nombre' => $app->nombre ?? null,
                'ad_accounts' => $adAccounts,
                'data' => [],
                'total' => 0,
            ];
        }

        $adAccountId = $this->normalizarAdAccountId((string) ($opciones['ad_account_id'] ?? ''));
        $campaignFields = 'id,name,status,effective_status,objective,start_time,stop_time,created_time,updated_time,buying_type';
        $campaigns = [];
        $paging = null;

        if ($adAccountId !== '') {
            $params = [
                'fields' => $campaignFields,
                'limit' => $limit,
            ];
            if (! empty($opciones['after'])) {
                $params['after'] = (string) $opciones['after'];
            }
            if (! empty($opciones['effective_status'])) {
                $params['effective_status'] = json_encode([(string) $opciones['effective_status']]);
            }

            $response = $this->metaGraphRequest("{$adAccountId}/campaigns", $accessToken, $params);
            $accountMap = $this->mapaAdAccountsPorId($adAccounts);
            $account = $accountMap[$adAccountId] ?? null;

            foreach ($response['data'] ?? [] as $row) {
                $campaigns[] = $this->formatearCampaniaMetaApi($row, $adAccountId, $account);
            }
            $paging = $response['paging'] ?? null;
        } else {
            $maxAccounts = max(1, min(20, (int) ($opciones['max_ad_accounts'] ?? 10)));
            $perAccountLimit = max(1, min(100, (int) ($opciones['limit_por_cuenta'] ?? 50)));

            foreach (array_slice($adAccounts, 0, $maxAccounts) as $account) {
                $accountId = (string) ($account['id'] ?? '');
                if ($accountId === '') {
                    continue;
                }

                $params = [
                    'fields' => $campaignFields,
                    'limit' => $perAccountLimit,
                ];
                if (! empty($opciones['effective_status'])) {
                    $params['effective_status'] = json_encode([(string) $opciones['effective_status']]);
                }

                try {
                    $response = $this->metaGraphRequest("{$accountId}/campaigns", $accessToken, $params);
                } catch (\Throwable $e) {
                    log_message('warning', "[META CAMPANIAS] No se pudieron leer campañas de {$accountId}: " . $e->getMessage());
                    continue;
                }

                foreach ($response['data'] ?? [] as $row) {
                    $campaigns[] = $this->formatearCampaniaMetaApi($row, $accountId, $account);
                }
            }
        }

        usort($campaigns, static function (array $a, array $b): int {
            return strcmp((string) ($b['updated_time'] ?? ''), (string) ($a['updated_time'] ?? ''));
        });

        return [
            'id_app' => $idApp,
            'app_nombre' => $app->nombre ?? null,
            'ad_accounts' => $adAccounts,
            'data' => $campaigns,
            'total' => count($campaigns),
            'paging' => $paging,
            'pagination' => [
                'per_page' => $limit,
                'current_count' => count($campaigns),
                'has_next' => ! empty($paging['cursors']['after'] ?? null),
                'has_prev' => ! empty($paging['cursors']['before'] ?? null),
                'next_cursor' => $paging['cursors']['after'] ?? null,
                'prev_cursor' => $paging['cursors']['before'] ?? null,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarAdAccountsMeta(string $accessToken): array
    {
        $fields = 'id,account_id,name,account_status,currency,timezone_name,business_name';
        $response = $this->metaGraphRequest('me/adaccounts', $accessToken, [
            'fields' => $fields,
            'limit' => 100,
        ]);

        $accounts = [];
        foreach ($response['data'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $accounts[] = [
                'id' => (string) ($row['id'] ?? ''),
                'account_id' => (string) ($row['account_id'] ?? ''),
                'name' => $row['name'] ?? null,
                'account_status' => isset($row['account_status']) ? (int) $row['account_status'] : null,
                'currency' => $row['currency'] ?? null,
                'timezone_name' => $row['timezone_name'] ?? null,
                'business_name' => $row['business_name'] ?? null,
            ];
        }

        return $accounts;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed>|null $account
     *
     * @return array<string, mixed>
     */
    private function formatearCampaniaMetaApi(array $row, string $adAccountId, ?array $account): array
    {
        return [
            'id' => (string) ($row['id'] ?? ''),
            'name' => $row['name'] ?? null,
            'status' => $row['status'] ?? null,
            'effective_status' => $row['effective_status'] ?? null,
            'objective' => $row['objective'] ?? null,
            'buying_type' => $row['buying_type'] ?? null,
            'start_time' => $row['start_time'] ?? null,
            'stop_time' => $row['stop_time'] ?? null,
            'created_time' => $row['created_time'] ?? null,
            'updated_time' => $row['updated_time'] ?? null,
            'ad_account_id' => $adAccountId,
            'ad_account_name' => $account['name'] ?? null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $adAccounts
     *
     * @return array<string, array<string, mixed>>
     */
    private function mapaAdAccountsPorId(array $adAccounts): array
    {
        $map = [];
        foreach ($adAccounts as $account) {
            $id = (string) ($account['id'] ?? '');
            if ($id !== '') {
                $map[$id] = $account;
            }
        }

        return $map;
    }

    private function normalizarAdAccountId(string $id): string
    {
        $id = trim($id);
        if ($id === '') {
            return '';
        }
        if (str_starts_with($id, 'act_')) {
            return $id;
        }

        $digits = preg_replace('/\D+/', '', $id);

        return $digits !== '' ? 'act_' . $digits : $id;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function metaGraphRequest(string $path, string $accessToken, array $params = []): array
    {
        $version = $this->config->meta_graph_api_version;
        $path = ltrim($path, '/');
        $endpoint = "https://graph.facebook.com/{$version}/{$path}";
        if ($params !== []) {
            $endpoint .= '?' . http_build_query($params);
        }

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            throw new \Exception('Error de conexión con Meta: ' . $curlError);
        }

        $responseData = json_decode((string) $response, true);
        if (! is_array($responseData)) {
            throw new \Exception('Respuesta inválida de Meta Graph API');
        }

        if ($httpCode !== 200) {
            $message = $responseData['error']['message'] ?? 'Error desconocido';
            $code = $responseData['error']['code'] ?? $httpCode;
            throw new \Exception("Meta Graph API: {$message} (código {$code})");
        }

        return $responseData;
    }

    private function resolverIdEmpresaConversacion(array $datos): int
    {
        $id = (int) ($datos['id_empresa'] ?? 0);
        if ($id > 0) {
            return $id;
        }

        if (! empty($datos['id_app'])) {
            $app = model('AppModel')->find((int) $datos['id_app']);
            $id = (int) ($app->id_empresa ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        helper('vip2system');
        $id = (int) (id_empresa_sesion() ?? 0);
        if ($id <= 0) {
            throw new \Exception('id_empresa es obligatorio para la conversación.');
        }

        return $id;
    }
}
