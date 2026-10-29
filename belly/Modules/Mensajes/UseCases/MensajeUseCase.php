<?php
namespace Belly\Mensajes\UseCases;

use Belly\Mensajes\Config\MensajesConfig;
use Belly\Mensajes\Models\MensajeModel;
use Belly\Mensajes\Entities\MensajeEntity;
use Belly\Conversacion\UseCases\ConversacionUseCase;
use Belly\Conversacion\Config\ConversacionConfig;

class MensajeUseCase {
    protected MensajesConfig $config;
    protected ?MensajeModel $mensaje_provider = NULL;

    public function __construct(MensajesConfig $config)
    {
        $this->config = $config;
    }

    public function getMensajeProvider(): MensajeModel
    {
        if ($this->mensaje_provider !== null) {
            return $this->mensaje_provider;
        }

        $class_name = $this->config->mensaje_provider;
        $this->mensaje_provider = new $class_name();

        return $this->mensaje_provider;
    }


    public function crearMensaje(array $datos)
    {
        $mensaje_model = $this->getMensajeProvider();

        // Idempotencia: wamid/mid vacío → null; si ya existe, no duplicar ni re-emitir socket.
        $wamid = trim((string) ($datos['id_mensaje_whatsapp'] ?? ''));
        if ($wamid === '') {
            $datos['id_mensaje_whatsapp'] = null;
        } else {
            $datos['id_mensaje_whatsapp'] = $wamid;
            $existente = $mensaje_model->where('id_mensaje_whatsapp', $wamid)->first();
            if ($existente) {
                return $this->serializarMensajeExistente($existente);
            }
        }

        $mensaje_entity = new MensajeEntity();
        $mensaje_entity->id_empresa = $this->resolverIdEmpresaMensaje($datos);
        $mensaje_entity->id_conversacion = $datos['id_conversacion'] ?? null;
        $mensaje_entity->id_estado = $datos['id_estado'] ?? null;
        $mensaje_entity->mensaje = $datos['mensaje'] ?? null;
        $mensaje_entity->contesta = $datos['contesta'] ?? null;
        $mensaje_entity->id_usuario = $this->resolverIdUsuarioMensaje($datos);
        $mensaje_entity->id_mensaje_whatsapp = $datos['id_mensaje_whatsapp'] ?? null;
        $mensaje_entity->id_mensaje_responde = $datos['id_mensaje_responde'] ?? null;
        $mensaje_entity->cod_error = $datos['cod_error'] ?? null;
        $mensaje_entity->media_type = $datos['media_type'] ?? null;
        $mensaje_entity->media_url = $datos['media_url'] ?? null;
        $mensaje_entity->media_path = $datos['media_path'] ?? null;
        $mensaje_entity->payload = $datos['payload'] ?? null;

        try {
            $saved = $mensaje_model->save($mensaje_entity);
        } catch (\Throwable $e) {
            // Carrera: otro request insertó el mismo wamid entre el SELECT y el INSERT.
            if ($wamid !== '' && $this->esErrorDuplicadoWamid($e)) {
                $existente = $mensaje_model->where('id_mensaje_whatsapp', $wamid)->first();
                if ($existente) {
                    log_message('debug', '[MENSAJE] Idempotencia por unique wamid=' . $wamid);
                    return $this->serializarMensajeExistente($existente);
                }
            }
            throw $e;
        }

        if ($saved) {
            $mensaje_entity->id = $mensaje_model->insertID;

            // Mantener conversacion.updated_at al día para ordenar el sidebar sin subqueries.
            try {
                $idConvTouch = (int) ($mensaje_entity->id_conversacion ?? 0);
                if ($idConvTouch > 0) {
                    \Config\Database::connect()
                        ->table('conversacion')
                        ->where('id', $idConvTouch)
                        ->update(['updated_at' => date('Y-m-d H:i:s')]);
                }
            } catch (\Throwable $e) {
                log_message('debug', '[MENSAJE] No se pudo tocar updated_at conversacion: ' . $e->getMessage());
            }
            
            $mensajeEntity = [
                'id' => $mensaje_entity->id,
                'id_conversacion' => $mensaje_entity->id_conversacion,
                'mensaje' => $mensaje_entity->mensaje,
                'id_estado' => $mensaje_entity->id_estado,
                'contesta' => $mensaje_entity->contesta,
                'id_usuario' => $mensaje_entity->id_usuario,
                'id_mensaje_whatsapp' => $mensaje_entity->id_mensaje_whatsapp,
                'id_mensaje_responde' => $mensaje_entity->id_mensaje_responde,
                'cod_error' => $mensaje_entity->cod_error,
                'media_type' => $mensaje_entity->media_type,
                'media_url' => $mensaje_entity->media_url,
                'media_path' => $mensaje_entity->media_path,
                'payload' => $mensaje_entity->payload,
            ];

            // Emitir socket automáticamente para todos los mensajes creados
            try {
                // Obtener id_usuario de la conversación
                $conversacion = model('ConversacionModel')->find($mensajeEntity['id_conversacion']);
                $id_usuario = $conversacion ? $conversacion->id_usuario : null;
                
                $mensajeData = [
                    'id' => $mensajeEntity['id'],
                    'mensaje' => $mensajeEntity['mensaje'],
                    'contesta' => $mensajeEntity['contesta'],
                    'media_url' => $mensajeEntity['media_url'],
                    'media_type' => $mensajeEntity['media_type'],
                    'id_conversacion' => $mensajeEntity['id_conversacion'],
                    'id_mensaje_whatsapp' => $mensajeEntity['id_mensaje_whatsapp'],
                    'id_mensaje_responde' => $mensajeEntity['id_mensaje_responde'],
                    'cod_error' => $mensajeEntity['cod_error'],
                    'id_estado' => $mensajeEntity['id_estado'],
                    'created_at' => [
                        'date' => date('Y-m-d H:i:s'),
                        'timezone_type' => 3,
                        'timezone' => 'America/Lima'
                    ],
                ];
                
                if (!empty($mensajeEntity['id_mensaje_responde'])) {
                    $mensaje_original = $this->getMensajeProvider()
                        ->where('id_mensaje_whatsapp', $mensajeEntity['id_mensaje_responde'])
                        ->first();
                    
                    if ($mensaje_original) {
                        $mensajeData['mensaje_responde_contenido'] = [
                            'id' => $mensaje_original->id,
                            'mensaje' => $mensaje_original->mensaje,
                            'contesta' => $mensaje_original->contesta,
                            'media_type' => $mensaje_original->media_type,
                            'media_url' => $mensaje_original->media_url,
                            'created_at' => $mensaje_original->created_at
                        ];
                    }
                }
                
                
                emitir_mensaje_socket($mensajeEntity['id_conversacion'], $mensajeData, $id_usuario);
                
                
                // Si es un mensaje del cliente en estado 3 (no leído), actualizar contadores
                if ($mensajeEntity['contesta'] === 'cliente' && $mensajeEntity['id_estado'] == 3) {
                    calcular_y_emitir_contadores_no_leidos($mensajeEntity['id_conversacion']);
                }
            } catch (\Exception $e) {
                log_message('error', 'Socket error: ' . $e->getMessage());
            }

            return $mensajeEntity;
        } else {
            // Por si el driver reporta error unique sin excepción.
            if ($wamid !== '') {
                $existente = $mensaje_model->where('id_mensaje_whatsapp', $wamid)->first();
                if ($existente) {
                    return $this->serializarMensajeExistente($existente);
                }
            }
            throw new \Exception(json_encode($mensaje_model->errors()));
        }
    }

    /**
     * @param object $existente
     * @return array<string, mixed>
     */
    private function serializarMensajeExistente($existente): array
    {
        return [
            'id' => $existente->id,
            'id_conversacion' => $existente->id_conversacion,
            'mensaje' => $existente->mensaje,
            'id_estado' => $existente->id_estado,
            'contesta' => $existente->contesta,
            'id_mensaje_whatsapp' => $existente->id_mensaje_whatsapp,
            'id_mensaje_responde' => $existente->id_mensaje_responde ?? null,
            'cod_error' => $existente->cod_error ?? null,
            'media_type' => $existente->media_type ?? null,
            'media_url' => $existente->media_url ?? null,
            'media_path' => $existente->media_path ?? null,
            'payload' => $existente->payload ?? null,
            '_idempotent' => true,
        ];
    }

    private function esErrorDuplicadoWamid(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());
        return str_contains($msg, 'duplicate')
            || str_contains($msg, 'uq_mensaje_wamid')
            || str_contains($msg, '1062');
    }

    public function crearMensajeYEnviarWhatsapp(array $datos)
    {
        $idConversacion = (int) ($datos['id_conversacion'] ?? 0);
        $requestId = (string) ($datos['request_id'] ?? uniqid('wa_', true));

        try {
            $conversacionUseCase = new ConversacionUseCase(new ConversacionConfig());
            $datosConversacion = $conversacionUseCase->obtenerDatosConversacionCompleta($datos['id_conversacion'], true);

            if (!in_array($datosConversacion['conversacion']->id_estado, [1,3])) {
                throw new \Exception('Solo se pueden enviar mensajes a conversaciones en estado 1.');
            }

            $appApi = $datosConversacion['app_entidad'] ?? null;
            if (!$appApi || empty($appApi->access_token) || empty($appApi->phone_number_id)) {
                throw new \Exception('No se pudo cargar la app o faltan credenciales (access_token / phone_number_id).');
            }

            $telefono = $datosConversacion['telefono'] ?? null;
            $waUserId = $datosConversacion['wa_user_id'] ?? null;
            helper('whatsapp');
            $destino = resolver_destino_whatsapp($telefono, $waUserId);
            if ($destino === '') {
                throw new \Exception('Cliente sin teléfono ni wa_user_id (BSUID) para enviar WhatsApp.');
            }
            $access_token = $appApi->access_token;
            $phone_number_id = $appApi->phone_number_id;
            $idConversacion = (int) ($datos['id_conversacion'] ?? 0);

            $this->logDiagnosticoWhatsappSaliente($requestId, $idConversacion, $datos, [
                'telefono_destino' => $destino,
                'destino_tipo' => es_whatsapp_bsuid($destino) ? 'bsuid' : 'telefono',
                'id_app' => (int) ($datosConversacion['conversacion']->id_app ?? 0),
                'nombre_app' => $appApi->nombre ?? null,
                'phone_number_id' => $phone_number_id,
                'whatsapp_business_account_id' => $appApi->whatsapp_business_account_id ?? null,
                'id_estado_conversacion' => (int) ($datosConversacion['conversacion']->id_estado ?? 0),
            ]);

            $endpoint = "https://graph.facebook.com/v19.0/{$phone_number_id}/messages";
            $headers = [
                "Authorization: Bearer $access_token",
                "Content-Type: application/json"
            ];

            $tipo = $datos['media_type'] ?? 'text';
            if ($tipo === 'sticker' || $tipo === 'image') {
                $this->asegurarImagenCompatibleWhatsapp($datos);
                $tipo = $datos['media_type'] ?? $tipo;
            }

            if (in_array($tipo, ['image', 'video', 'document', 'audio', 'sticker'], true)) {
                $this->prepararMediaIdWhatsapp($datos, $phone_number_id, $access_token);
            }

            $payload = procesarMensajeWhatsappSaliente($datos, $destino);

            $datos['mensaje'] = $payload['mensaje_bd'] ?? '[Sin texto]';
            $datos['id_mensaje_responde'] = $datos['id_mensaje_responde'] ?? null;

            $payloadGraph = $payload;
            unset($payloadGraph['mensaje_bd']);

            log_message('debug', '[WA ENVIAR] Payload Graph API request_id=' . $requestId
                . ' endpoint=' . $endpoint
                . ' payload=' . json_encode($payloadGraph, JSON_UNESCAPED_UNICODE));

            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payloadGraph));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $responseData = json_decode($response, true);

            log_message('debug', '[WA ENVIAR] Respuesta Graph API request_id=' . $requestId
                . ' http_code=' . $http_code
                . ' curl_error=' . ($error ?: 'none')
                . ' response=' . json_encode($responseData, JSON_UNESCAPED_UNICODE));

            if ($http_code === 200 || $http_code === 201) {
                $datos['id_mensaje_whatsapp'] = $responseData['messages'][0]['id'] ?? null;
                $datos['payload'] = json_encode([
                    'request' => $payload,
                    'response' => $responseData
                ]);
                // Graph ya aceptó el envío → "enviado" (2). El reloj (1) solo confundía
                // cuando el webhook de status tarda o no llega.
                $datos['id_estado'] = 2;
                $datos['contesta'] = $datos['contesta'] ?? 'usuario';

                try {
                    $mensajeEntity = $this->crearMensaje($datos);

                    log_message('info', '[WA ENVIAR] Mensaje aceptado por Graph request_id=' . $requestId
                        . ' id_conversacion=' . $idConversacion
                        . ' id_mensaje_local=' . ($mensajeEntity['id'] ?? 'null')
                        . ' id_mensaje_whatsapp=' . ($datos['id_mensaje_whatsapp'] ?? 'null')
                        . ' (si luego falla con 131047, revisar webhook [WA FALLO])');

                    return ['success' => true, 'data' => $mensajeEntity];
                } catch (\Throwable $persistError) {
                    // WhatsApp ya aceptó el envío, pero falló el guardado local.
                    log_message(
                        'error',
                        'MensajeUseCase::crearMensajeYEnviarWhatsapp - enviado a WhatsApp pero falló persistencia local: '
                        . $persistError->getMessage()
                    );

                    return [
                        'success' => true,
                        'warning' => 'Mensaje enviado a WhatsApp, pero no se pudo guardar localmente.',
                        'data' => [
                            'id' => null,
                            'id_conversacion' => $datos['id_conversacion'] ?? null,
                            'id_mensaje_whatsapp' => $datos['id_mensaje_whatsapp'] ?? null,
                            'mensaje' => $datos['mensaje'] ?? null,
                            'media_type' => $datos['media_type'] ?? null,
                            'media_url' => $datos['media_url'] ?? null,
                            'media_path' => $datos['media_path'] ?? null,
                            'id_estado' => 2,
                            'contesta' => 'usuario',
                        ]
                    ];
                }
            } else {
                log_message('error', '[WA ENVIAR] Graph rechazó en HTTP request_id=' . $requestId
                    . ' id_conversacion=' . $idConversacion
                    . ' http_code=' . $http_code
                    . ' response=' . json_encode($responseData, JSON_UNESCAPED_UNICODE));
                throw new \Exception('Error al enviar mensaje a WhatsApp: ' . json_encode($responseData) . ' - ' . $error);
            }

        } catch (\Exception $e) {
            log_message('error', '[WA ENVIAR] Excepción request_id=' . $requestId
                . ' id_conversacion=' . ($datos['id_conversacion'] ?? 'null')
                . ' msg=' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function logDiagnosticoWhatsappSaliente(string $requestId, int $idConversacion, array $datos, array $contexto): void
    {
        $ventana = $this->obtenerDiagnosticoVentana24h($idConversacion);
        $reply = null;

        if (!empty($datos['id_mensaje_responde'])) {
            $reply = $this->getMensajeProvider()
                ->where('id_mensaje_whatsapp', $datos['id_mensaje_responde'])
                ->first();
        }

        $replyInfo = null;
        if ($reply) {
            $replyInfo = [
                'id' => (int) ($reply->id ?? 0),
                'contesta' => (string) ($reply->contesta ?? ''),
                'created_at' => (string) ($reply->created_at ?? ''),
                'id_mensaje_whatsapp' => (string) ($reply->id_mensaje_whatsapp ?? ''),
                'mensaje_preview' => mb_substr((string) ($reply->mensaje ?? ''), 0, 80),
            ];
        }

        log_message('debug', '[WA ENVIAR] Diagnóstico pre-envío request_id=' . $requestId . ' '
            . json_encode([
                'id_conversacion' => $idConversacion,
                'contexto_envio' => $contexto,
                'ventana_24h' => $ventana,
                'mensaje_responde_a' => $replyInfo,
                'id_mensaje_responde_wamid' => $datos['id_mensaje_responde'] ?? null,
                'media_type' => $datos['media_type'] ?? 'text',
                'mensaje_preview' => mb_substr((string) ($datos['mensaje'] ?? ''), 0, 120),
            ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, mixed>
     */
    private function obtenerDiagnosticoVentana24h(int $idConversacion): array
    {
        $ultimoCliente = $this->getMensajeProvider()
            ->where('id_conversacion', $idConversacion)
            ->where('contesta', 'cliente')
            ->where('deleted_at', null)
            ->orderBy('created_at', 'DESC')
            ->first();

        $ahora = time();
        $ultimoAtStr = $ultimoCliente ? (string) ($ultimoCliente->created_at ?? '') : '';
        $ultimoAt = $ultimoAtStr !== '' ? strtotime($ultimoAtStr) : false;
        $segundosDesde = ($ultimoAt !== false) ? max(0, $ahora - $ultimoAt) : null;

        return [
            'hora_servidor' => date('Y-m-d H:i:s'),
            'timezone_servidor' => date_default_timezone_get(),
            'ultimo_mensaje_cliente_id' => $ultimoCliente ? (int) ($ultimoCliente->id ?? 0) : null,
            'ultimo_mensaje_cliente_at' => $ultimoAtStr ?: null,
            'ultimo_mensaje_cliente_wamid' => $ultimoCliente->id_mensaje_whatsapp ?? null,
            'ultimo_mensaje_cliente_preview' => $ultimoCliente
                ? mb_substr((string) ($ultimoCliente->mensaje ?? ''), 0, 80)
                : null,
            'segundos_desde_ultimo_cliente' => $segundosDesde,
            'minutos_desde_ultimo_cliente' => $segundosDesde !== null ? round($segundosDesde / 60, 1) : null,
            'dentro_ventana_24h_crm' => $segundosDesde !== null && $segundosDesde < 86400,
            'expira_ventana_crm_at' => ($ultimoAt !== false)
                ? date('Y-m-d H:i:s', $ultimoAt + 86400)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function obtenerDiagnosticoVentana24hPublico(int $idConversacion): array
    {
        if ($idConversacion <= 0) {
            return [];
        }

        return $this->obtenerDiagnosticoVentana24h($idConversacion);
    }

    public function actualizarEstadoMensajeWhatsapp($id_mensaje_whatsapp, $nuevo_estado, $payload = null, $cod_error = null)
    {
        $mensaje_model = $this->getMensajeProvider();
        $mensaje = $mensaje_model->where('id_mensaje_whatsapp', $id_mensaje_whatsapp)->first();

        if ($mensaje) {
            // Guardar el estado anterior antes de actualizar
            $estado_anterior = (int) ($mensaje->id_estado ?? 0);
            $nuevo_estado_int = (int) $nuevo_estado;
            $es_mensaje_cliente = $mensaje->contesta === 'cliente';

            // Evitar que un status tardío (sent/delivered) baje un mensaje ya leído.
            // failed (5) siempre se aplica.
            if (
                $nuevo_estado_int !== 5
                && $estado_anterior > 0
                && $nuevo_estado_int > 0
                && $nuevo_estado_int < $estado_anterior
            ) {
                return true;
            }

            $updateData = ['id_estado' => $nuevo_estado_int];
            if ($payload) {
                $updateData['payload'] = $payload;
            }
            if ($cod_error) {
                $updateData['cod_error'] = $cod_error;
            }

            if ($mensaje_model->update($mensaje->id, $updateData)) {
                try {
                    $datosMensaje = [
                        'id' => $mensaje->id,
                        'id_conversacion' => $mensaje->id_conversacion,
                        'mensaje' => $mensaje->mensaje,
                        'contesta' => $mensaje->contesta,
                        'id_estado' => $nuevo_estado_int,
                        'cod_error' => $cod_error,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ];
                    emitir_estado_mensaje_socket($mensaje->id, $nuevo_estado_int, $datosMensaje);

                    if ($es_mensaje_cliente && ($estado_anterior == 3 || $nuevo_estado_int == 3 || $estado_anterior == 4 || $nuevo_estado_int == 4)) {
                        calcular_y_emitir_contadores_no_leidos($mensaje->id_conversacion);
                    }
                } catch (\Exception $e) {
                    log_message('error', 'Socket error: ' . $e->getMessage());
                }

                return true;
            }
        }
        return false;
    }


    public function obtenerMensajesPorConversacion($id_conversacion)
    {
        $id_conversacion = (int) $id_conversacion;
        if ($id_conversacion <= 0) {
            return [];
        }

        $mensaje_model = $this->getMensajeProvider();
        $mensajes = $mensaje_model
            ->where('id_conversacion', $id_conversacion)
            ->orderBy('id', 'ASC')
            ->findAll();

        $wamidsRespuesta = [];
        foreach ($mensajes as $mensaje) {
            $wamid = trim((string) ($mensaje->id_mensaje_responde ?? ''));
            if ($wamid !== '') {
                $wamidsRespuesta[$wamid] = true;
            }
        }

        $respuestasPorWamid = [];
        if ($wamidsRespuesta !== []) {
            $citados = $mensaje_model
                ->where('id_conversacion', $id_conversacion)
                ->whereIn('id_mensaje_whatsapp', array_keys($wamidsRespuesta))
                ->findAll();

            foreach ($citados as $citado) {
                $key = trim((string) ($citado->id_mensaje_whatsapp ?? ''));
                if ($key === '') {
                    continue;
                }
                $respuestasPorWamid[$key] = [
                    'id' => $citado->id,
                    'mensaje' => $citado->mensaje,
                    'contesta' => $citado->contesta,
                    'media_type' => $citado->media_type,
                    'media_url' => $citado->media_url,
                    'created_at' => $citado->created_at,
                ];
            }
        }

        $mensajes_procesados = [];
        foreach ($mensajes as $mensaje) {
            if ((int) ($mensaje->id_conversacion ?? 0) !== $id_conversacion) {
                log_message(
                    'error',
                    '[MENSAJE] Fuga de contexto: mensaje id=' . (int) ($mensaje->id ?? 0)
                    . ' no pertenece a conv=' . $id_conversacion,
                );

                continue;
            }

            $mensaje_data = $mensaje->toArray();
            $wamid = trim((string) ($mensaje->id_mensaje_responde ?? ''));
            if ($wamid !== '') {
                $mensaje_data['mensaje_responde_contenido'] = $respuestasPorWamid[$wamid] ?? null;
            }

            $interactivo = $this->extraerInteractivo($mensaje_data['payload'] ?? null);
            if ($interactivo !== null) {
                $mensaje_data['interactivo'] = $interactivo;
            }

            $mensajes_procesados[] = $mensaje_data;
        }

        return $this->enriquecerAutoresMensajes($mensajes_procesados);
    }

    /**
     * Botones o lista que envió el bot, para dibujarlos en el chat como los ve el cliente en WhatsApp.
     *
     * @return array{tipo:string, boton?:string, opciones:list<array{titulo:string, descripcion?:string}>}|null
     */
    private function extraerInteractivo(mixed $payload): ?array
    {
        $datos = is_string($payload) ? json_decode($payload, true) : $payload;
        $bloque = is_array($datos) ? ($datos['request']['interactive'] ?? null) : null;
        if (! is_array($bloque)) {
            return null;
        }

        if (($bloque['type'] ?? '') === 'button') {
            $opciones = [];
            foreach ((array) ($bloque['action']['buttons'] ?? []) as $boton) {
                $titulo = (string) ($boton['reply']['title'] ?? '');
                if ($titulo !== '') {
                    $opciones[] = ['titulo' => $titulo];
                }
            }

            return $opciones === [] ? null : ['tipo' => 'botones', 'opciones' => $opciones];
        }

        if (($bloque['type'] ?? '') === 'list') {
            $opciones = [];
            foreach ((array) ($bloque['action']['sections'] ?? []) as $seccion) {
                foreach ((array) ($seccion['rows'] ?? []) as $fila) {
                    $titulo = (string) ($fila['title'] ?? '');
                    if ($titulo !== '') {
                        $opcion = ['titulo' => $titulo];
                        if (! empty($fila['description'])) {
                            $opcion['descripcion'] = (string) $fila['description'];
                        }
                        $opciones[] = $opcion;
                    }
                }
            }

            return $opciones === [] ? null : [
                'tipo' => 'lista',
                'boton' => (string) ($bloque['action']['button'] ?? 'Ver opciones'),
                'opciones' => $opciones,
            ];
        }

        return null;
    }

    public function crearMensajeError($id_conversacion, $id_mensaje_whatsapp, $texto_original, $cod_error, $descripcion_error, $payload = null)
    {
        $mensaje = "No se pudo enviar el mensaje: \"$texto_original\"\n";
        $mensaje .= "Motivo: $descripcion_error (Código: $cod_error)";
        return $this->crearMensaje([
            'id_conversacion'      => $id_conversacion,
            'id_estado'            => 5,
            'mensaje'              => $mensaje,
            'contesta'             => 'bot',
            'id_mensaje_whatsapp'  => $id_mensaje_whatsapp,
            'cod_error'            => $cod_error,
            'payload'              => $payload ? json_encode($payload) : null,
        ]);
    }

    public function enviarPlantillaWhatsapp(array $datos)
    {
        try {
            $conversacionUseCase = new ConversacionUseCase(new ConversacionConfig());
            $datosConversacion = $conversacionUseCase->obtenerDatosConversacionCompleta($datos['id_conversacion'], true);

            if (!in_array((int) $datosConversacion['conversacion']->id_estado, [1, 3], true)) {
                throw new \Exception('Solo se pueden enviar plantillas a conversaciones en atención o sin abrir.');
            }

            $appApi = $datosConversacion['app_entidad'] ?? null;
            if (!$appApi || empty($appApi->access_token) || empty($appApi->phone_number_id)) {
                throw new \Exception('No se pudo cargar la app o faltan credenciales (access_token / phone_number_id).');
            }

            $telefono = $datosConversacion['telefono'] ?? null;
            $waUserId = $datosConversacion['wa_user_id'] ?? null;
            helper('whatsapp');
            $destino = resolver_destino_whatsapp($telefono, $waUserId);
            if ($destino === '') {
                throw new \Exception('Cliente sin teléfono ni wa_user_id (BSUID) para enviar plantilla WhatsApp.');
            }
            $access_token = $appApi->access_token;
            $phone_number_id = $appApi->phone_number_id;

            if (empty($datos['template_id']) || empty($datos['template_name'])) {
                throw new \Exception('El template_id y template_name son obligatorios');
            }

            $plantillaInfo = $this->obtenerInformacionPlantilla($datos['template_id'], $access_token);
            $idiomaPlantilla = trim((string) ($plantillaInfo['language'] ?? ''));
            $idiomaEnvio = $idiomaPlantilla !== ''
                ? $idiomaPlantilla
                : trim((string) ($datos['language'] ?? 'es'));

            log_message(
                'debug',
                '[WA PLANTILLA] conv=' . ($datos['id_conversacion'] ?? 0)
                . ' name=' . ($datos['template_name'] ?? '')
                . ' template_id=' . ($datos['template_id'] ?? '')
                . ' language=' . ($idiomaEnvio !== '' ? $idiomaEnvio : 'es'),
            );

            $endpoint = "https://graph.facebook.com/v19.0/{$phone_number_id}/messages";
            $headers = [
                "Authorization: Bearer $access_token",
                "Content-Type: application/json"
            ];

            // Construir el payload para plantilla (BSUID → recipient; teléfono → to)
            $payload = array_merge(construir_encabezado_destinatario_whatsapp($destino), [
                'type' => 'template',
                'template' => [
                    'name' => $datos['template_name'],
                    'language' => [
                        'code' => $idiomaEnvio !== '' ? $idiomaEnvio : 'es',
                    ],
                ],
            ]);

            // Inicializar array de componentes
            $components = [];

            // Procesar HEADER si la plantilla lo requiere
            $headerType = $plantillaInfo['header_type'] ?? null;
            
            if (($plantillaInfo['header_requerido'] ?? false) && in_array($headerType, ['IMAGE', 'VIDEO', 'DOCUMENT'])) {
                // Usar imagen de ejemplo si no se proporciona ninguna
                if (empty($datos['header_handle']) && 
                    empty($datos['header_image_handle']) && 
                    empty($datos['header_image_url']) && 
                    empty($datos['image_url']) && 
                    empty($datos['header_image_path']) && 
                    empty($datos['image_path'])) {
                    
                    $headerExampleUrl = $plantillaInfo['header_example_url'] ?? null;
                    if ($headerExampleUrl) {
                        log_message('debug', "Usando imagen de ejemplo de la plantilla");
                        $datos['header_image_url'] = $headerExampleUrl;
                    }
                }
                
                $headerHandle = $this->procesarHeaderMedia($datos, $phone_number_id, $headerType, $access_token);
                
                if (!$headerHandle) {
                    throw new \Exception("La plantilla requiere un HEADER de tipo {$headerType}, pero no se pudo procesar la imagen.");
                }
                
                // Construir parámetro del HEADER según el tipo
                $headerParam = ['type' => strtolower($headerType)];
                if ($headerType === 'IMAGE') {
                    $headerParam['image'] = ['id' => $headerHandle];
                } elseif ($headerType === 'VIDEO') {
                    $headerParam['video'] = ['id' => $headerHandle];
                } elseif ($headerType === 'DOCUMENT') {
                    $headerParam['document'] = ['id' => $headerHandle];
                }
                
                $components[] = [
                    'type' => 'header',
                    'parameters' => [$headerParam]
                ];
            }

            $variablesHeader = $this->contarVariablesTextoPlantilla($plantillaInfo['header_text_template'] ?? null);
            $variablesBody = $this->contarVariablesTextoPlantilla($plantillaInfo['body_text_template'] ?? null);

            // Parámetros del HEADER de texto ({{1}}, {{2}}, …)
            if ($variablesHeader > 0 && ! empty($datos['parameters']) && is_array($datos['parameters'])) {
                $headerTextParams = [];
                foreach (array_slice($datos['parameters'], 0, $variablesHeader) as $param) {
                    if (isset($param['type']) && $param['type'] === 'text') {
                        $headerTextParams[] = [
                            'type' => 'text',
                            'text' => trim((string) ($param['text'] ?? '')),
                        ];
                    }
                }

                while (count($headerTextParams) < $variablesHeader) {
                    $headerTextParams[] = ['type' => 'text', 'text' => '-'];
                }

                if ($headerTextParams !== []) {
                    $components[] = [
                        'type' => 'header',
                        'parameters' => $headerTextParams,
                    ];
                }
            }

            // Parámetros del BODY
            if ($variablesBody > 0 && ! empty($datos['parameters']) && is_array($datos['parameters'])) {
                $bodyParams = [];
                $offsetBody = $variablesHeader;
                foreach (array_slice($datos['parameters'], $offsetBody, $variablesBody) as $param) {
                    if (isset($param['type']) && $param['type'] === 'text') {
                        $bodyParams[] = [
                            'type' => 'text',
                            'text' => trim((string) ($param['text'] ?? '')),
                        ];
                    }
                }

                while (count($bodyParams) < $variablesBody) {
                    $bodyParams[] = ['type' => 'text', 'text' => '-'];
                }

                if ($bodyParams !== []) {
                    $components[] = [
                        'type' => 'body',
                        'parameters' => $bodyParams,
                    ];
                }
            }

            log_message(
                'debug',
                '[WA PLANTILLA] vars header=' . $variablesHeader
                . ' body=' . $variablesBody
                . ' components=' . count($components),
            );
            
            if (!empty($components)) {
                $payload['template']['components'] = $components;
            }

            // Agregar contexto si existe
            if (!empty($datos['id_mensaje_responde'])) {
                $payload['context'] = [
                    'message_id' => $datos['id_mensaje_responde']
                ];
            }

            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $responseData = json_decode($response, true);

            if ($http_code === 200 || $http_code === 201) {
                $bodyParamTexts = [];
                if (!empty($datos['parameters']) && is_array($datos['parameters'])) {
                    foreach ($datos['parameters'] as $param) {
                        if (isset($param['type']) && $param['type'] === 'text' && array_key_exists('text', $param)) {
                            $bodyParamTexts[] = (string) $param['text'];
                        }
                    }
                }

                $mensaje_bd = $this->componerTextoVistaPlantilla(
                    $datos['template_name'],
                    $plantillaInfo,
                    $bodyParamTexts
                );

                $headerType = $plantillaInfo['header_type'] ?? null;
                $headerMediaUrl =
                    $datos['header_image_url']
                    ?? $datos['image_url']
                    ?? $plantillaInfo['header_example_url']
                    ?? null;
                $headerMediaUrl = is_string($headerMediaUrl) && $headerMediaUrl !== '' ? $headerMediaUrl : null;

                $headerMediaPath =
                    $datos['header_image_path']
                    ?? $datos['image_path']
                    ?? null;
                $headerMediaPath = is_string($headerMediaPath) && $headerMediaPath !== '' ? $headerMediaPath : null;

                $mediaTipoGuardar = 'template';
                if (($plantillaInfo['header_requerido'] ?? false) && $headerType) {
                    if ($headerType === 'IMAGE') {
                        $mediaTipoGuardar = 'image';
                    } elseif ($headerType === 'VIDEO') {
                        $mediaTipoGuardar = 'video';
                    } elseif ($headerType === 'DOCUMENT') {
                        $mediaTipoGuardar = 'document';
                    }
                }

                $datos_mensaje = [
                    'id_conversacion' => $datos['id_conversacion'],
                    'id_mensaje_whatsapp' => $responseData['messages'][0]['id'] ?? null,
                    'mensaje' => $mensaje_bd,

                    'payload' => json_encode([
                        'request' => $payload,
                        'response' => $responseData
                    ]),
                    'id_estado' => 2,
                    'media_url' => $headerMediaUrl,
                    'media_path' => $headerMediaPath,
                    'cod_error' => $responseData['error']['code'] ?? null,
                    'id_mensaje_responde' => $datos['id_mensaje_responde'] ?? null,
                    'contesta' => 'usuario',
                    'id_usuario' => $datos['id_usuario'] ?? null,
                    'media_type' => $mediaTipoGuardar,
                ];
                
                $mensajeEntity = $this->crearMensaje($datos_mensaje);

                log_message('debug', "Mensaje creado exitosamente: " . json_encode($datos_mensaje));

                return ['success' => true, 'data' => $mensajeEntity];
            } else {
                $error_message = $responseData['error']['message'] ?? 'Error desconocido';
                throw new \Exception('Error al enviar plantilla a WhatsApp: ' . $error_message);
            }

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }


    private function obtenerInformacionPlantilla($template_id, $access_token)
    {
        $info = [
            'language' => null,
            'header_requerido' => false,
            'header_type' => null,
            'header_example_url' => null,
            'header_text_template' => null,
            'body_text_template' => null,
            'footer_text' => null,
        ];

        try {
            $endpoint = "https://graph.facebook.com/v19.0/{$template_id}?" . http_build_query([
                'fields' => 'id,name,language,components',
                'access_token' => $access_token
            ]);

            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 200) {
                $data = json_decode($response, true);
                $info['language'] = $this->normalizarIdiomaPlantillaMeta($data['language'] ?? null);

                foreach ($data['components'] ?? [] as $component) {
                    $type = $component['type'] ?? '';

                    if ($type === 'HEADER') {
                        $info['header_requerido'] = true;
                        $format = $component['format'] ?? 'TEXT';
                        $info['header_type'] = $format;

                        if ($format === 'TEXT') {
                            $info['header_text_template'] = $component['text'] ?? null;
                            continue;
                        }

                        $exampleHandle = $component['example']['header_handle'] ?? null;
                        if ($exampleHandle) {
                            $url = is_array($exampleHandle) ? ($exampleHandle[0] ?? null) : $exampleHandle;
                            if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
                                $info['header_example_url'] = $url;
                            }
                        }
                        continue;
                    }

                    if ($type === 'BODY') {
                        $info['body_text_template'] = $component['text'] ?? null;
                        continue;
                    }

                    if ($type === 'FOOTER') {
                        $info['footer_text'] = $component['text'] ?? null;
                    }
                }
            }
        } catch (\Exception $e) {
            log_message('warning', "Error al obtener información de plantilla: " . $e->getMessage());
        }

        return $info;
    }

    /**
     * Meta puede devolver "en", "en_US" o {"code":"en_US"} según el endpoint.
     */
    private function normalizarIdiomaPlantillaMeta(mixed $language): ?string
    {
        if (is_array($language)) {
            $code = trim((string) ($language['code'] ?? ''));

            return $code !== '' ? $code : null;
        }

        $code = trim((string) $language);

        return $code !== '' ? $code : null;
    }

    private function contarVariablesTextoPlantilla(?string $textoPlantilla): int
    {
        if ($textoPlantilla === null || trim($textoPlantilla) === '') {
            return 0;
        }

        if (preg_match_all('/\{\{(\d+)\}\}/', $textoPlantilla, $coincidencias) < 1 || empty($coincidencias[1])) {
            return 0;
        }

        return (int) max(array_map('intval', $coincidencias[1]));
    }

    /**
     * Sustituye {{1}}, {{2}}, … en el texto de plantilla Meta por valores en orden (índice 1-based).
     */
    private function aplicarVariablesTextoPlantilla(?string $textoPlantilla, array $valoresEnOrden): string
    {
        if ($textoPlantilla === null || trim($textoPlantilla) === '') {
            return '';
        }

        return (string) preg_replace_callback('/\{\{(\d+)\}\}/', function (array $m) use ($valoresEnOrden) {
            $idx = (int) $m[1];
            if ($idx >= 1 && array_key_exists($idx - 1, $valoresEnOrden)) {
                return (string) $valoresEnOrden[$idx - 1];
            }
            return $m[0];
        }, $textoPlantilla);
    }

    /**
     * Texto legible para historial/UI: cabecera (si es texto), cuerpo con variables resueltas, pie y nombre interno de plantilla.
     */
    private function componerTextoVistaPlantilla(string $templateName, array $plantillaInfo, array $bodyParamTexts): string
    {
        $bloques = [];

        $headerType = $plantillaInfo['header_type'] ?? null;
        $headerTpl = $plantillaInfo['header_text_template'] ?? null;
        if ($headerType === 'TEXT' && $headerTpl !== null && trim($headerTpl) !== '') {
            // Las variables {{n}} del HEADER pueden usar índices distintos del BODY en Meta; este flujo solo recibe params de BODY.
            $headerTieneVars = preg_match('/\{\{\d+\}\}/', (string) $headerTpl) === 1;
            $valorHeaderVars = $headerTieneVars ? [] : $bodyParamTexts;
            $bloques[] = trim($this->aplicarVariablesTextoPlantilla($headerTpl, $valorHeaderVars));
        }

        $cuerpo = $this->aplicarVariablesTextoPlantilla($plantillaInfo['body_text_template'] ?? '', $bodyParamTexts);
        $cuerpo = trim($cuerpo);
        if ($cuerpo !== '') {
            $bloques[] = $cuerpo;
        } elseif (!empty($bodyParamTexts)) {
            $bloques[] = implode(' ', $bodyParamTexts);
        }

        $pie = isset($plantillaInfo['footer_text']) ? trim((string) $plantillaInfo['footer_text']) : '';
        if ($pie !== '') {
            $bloques[] = $pie;
        }

        $principal = implode("\n\n", array_filter($bloques, static fn ($b) => $b !== ''));

        if ($principal === '') {
            return "Plantilla: {$templateName}";
        }

        return $principal . "\n\n— Plantilla: {$templateName}";
    }

    /**
     * WhatsApp Cloud solo entrega JPEG/PNG como imagen, y WebP estático 512×512 ≤100 KB
     * como sticker. Giphy casi nunca cumple: lo pasamos a JPEG para que sí llegue.
     *
     * @param array<string, mixed> $datos
     */
    private function asegurarImagenCompatibleWhatsapp(array &$datos): void
    {
        $referencia = (string) ($datos['media_path'] ?? $datos['media_url'] ?? '');
        $rel = media()->rutaMediaDesdeAlmacenado($referencia);
        if ($rel === null) {
            return;
        }

        $disco = media()->rutaDiscoUploads($rel);
        if (! is_file($disco)) {
            return;
        }

        $tipo = (string) ($datos['media_type'] ?? 'image');
        $mime = $this->mimeArchivoLocal($disco);
        $peso = (int) filesize($disco);
        $info = @getimagesize($disco);
        $ancho = (int) ($info[0] ?? 0);
        $alto = (int) ($info[1] ?? 0);

        if (
            $tipo === 'sticker'
            && $mime === 'image/webp'
            && $ancho === 512
            && $alto === 512
            && $peso > 0
            && $peso <= 100 * 1024
        ) {
            return;
        }

        if ($tipo === 'image' && in_array($mime, ['image/jpeg', 'image/jpg', 'image/png'], true)) {
            return;
        }

        $jpeg = $this->convertirImagenJpegWhatsapp($disco);
        if ($jpeg === null) {
            log_message('error', '[WhatsApp] No se pudo convertir media a JPEG: ' . $disco);
            $datos['media_type'] = 'image';

            return;
        }

        $this->actualizarRutaMediaLocal($datos, $jpeg);
        $datos['media_type'] = 'image';
        if (trim((string) ($datos['mensaje'] ?? '')) === '' || preg_match('/^(\[Sticker\]|Se envió sticker)$/i', (string) $datos['mensaje'])) {
            $datos['mensaje'] = '';
        }
        unset($datos['whatsapp_media_id']);
    }

    private function mimeArchivoLocal(string $ruta): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, $ruta) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        return strtolower($mime);
    }

    private function actualizarRutaMediaLocal(array &$datos, string $disco): void
    {
        $raiz = WRITEPATH . 'uploads/';
        $rel = ltrim(str_replace('\\', '/', substr($disco, strlen($raiz))), '/');
        $datos['media_path'] = 'uploads/' . $rel;
        $datos['media_url'] = media()->construirUrlPublicaUploads($rel);
    }

    private function convertirImagenJpegWhatsapp(string $origen): ?string
    {
        $fuente = $this->abrirImagenGd($origen);
        if ($fuente === false) {
            return null;
        }

        $ancho = imagesx($fuente);
        $alto = imagesy($fuente);
        if ($ancho < 1 || $alto < 1) {
            imagedestroy($fuente);

            return null;
        }

        $max = 1600;
        $escala = min(1, $max / max($ancho, $alto));
        $nuevoAncho = max(1, (int) round($ancho * $escala));
        $nuevoAlto = max(1, (int) round($alto * $escala));

        $lienzo = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        $blanco = imagecolorallocate($lienzo, 255, 255, 255);
        imagefilledrectangle($lienzo, 0, 0, $nuevoAncho, $nuevoAlto, $blanco);
        imagecopyresampled($lienzo, $fuente, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
        imagedestroy($fuente);

        $destino = preg_replace('/\.[^.]+$/', '-wa.jpg', $origen) ?: ($origen . '-wa.jpg');
        $ok = @imagejpeg($lienzo, $destino, 82);
        imagedestroy($lienzo);
        if (! $ok || ! is_file($destino) || filesize($destino) <= 0) {
            return null;
        }

        return $destino;
    }

    /**
     * @return \GdImage|false
     */
    private function abrirImagenGd(string $ruta)
    {
        $crudo = @file_get_contents($ruta);
        if (is_string($crudo) && $crudo !== '') {
            $desdeString = @imagecreatefromstring($crudo);
            if ($desdeString !== false) {
                return $desdeString;
            }
        }

        foreach (['imagecreatefromwebp', 'imagecreatefromgif', 'imagecreatefrompng', 'imagecreatefromjpeg'] as $fn) {
            if (! function_exists($fn)) {
                continue;
            }
            $img = @$fn($ruta);
            if ($img !== false) {
                return $img;
            }
        }

        if (class_exists(\Imagick::class)) {
            try {
                $im = new \Imagick();
                $im->readImage($ruta);
                $im->setIteratorIndex(0);
                $im->setImageFormat('png');
                $png = $im->getImageBlob();
                $im->clear();
                $im->destroy();
                $desdeImagick = @imagecreatefromstring($png);
                if ($desdeImagick !== false) {
                    return $desdeImagick;
                }
            } catch (\Throwable $e) {
                log_message('warning', '[WhatsApp] Imagick no pudo leer ' . $ruta . ': ' . $e->getMessage());
            }
        }

        return false;
    }

    /**
     * Sube imagen/video/documento a Meta (media ID) en lugar de confiar en link público.
     * Meta a menudo falla con error 131053 si no puede descargar la URL externa.
     *
     * @param array<string, mixed> $datos
     */
    private function prepararMediaIdWhatsapp(array &$datos, string $phone_number_id, string $access_token): void
    {
        if (! empty($datos['whatsapp_media_id'])) {
            return;
        }

        $tipo = (string) ($datos['media_type'] ?? '');
        if (! in_array($tipo, ['image', 'video', 'document', 'audio', 'sticker'], true)) {
            return;
        }

        $filePath = null;
        $isTempFile = false;

        foreach ([$datos['media_path'] ?? null, $datos['media_url'] ?? null] as $referencia) {
            if (! is_string($referencia) || trim($referencia) === '') {
                continue;
            }

            $relPath = media()->rutaMediaDesdeAlmacenado($referencia);
            if ($relPath === null) {
                continue;
            }

            $localPath = media()->rutaDiscoUploads($relPath);
            if (is_file($localPath) && filesize($localPath) > 0) {
                $filePath = $localPath;
                break;
            }
        }

        if ($filePath === null && ! empty($datos['media_url']) && filter_var($datos['media_url'], FILTER_VALIDATE_URL)) {
            log_message('debug', '[WhatsApp] Descargando ' . $tipo . ' para subir a Meta: ' . $datos['media_url']);
            $filePath = $this->descargarMediaDesdeURL($datos['media_url'], $access_token, $tipo);
            $isTempFile = ($filePath !== null);
        }

        if ($filePath === null || ! is_file($filePath) || filesize($filePath) <= 0) {
            log_message('warning', '[WhatsApp] No se pudo resolver archivo local para ' . $tipo . ', se usará link: ' . ($datos['media_url'] ?? ''));

            return;
        }

        try {
            $mediaId = $this->subirMediaAWhatsapp($filePath, $phone_number_id, $access_token);
            if ($mediaId) {
                $datos['whatsapp_media_id'] = $mediaId;
                log_message('info', '[WhatsApp] ' . $tipo . ' subido a Meta. media_id=' . $mediaId);
            }
        } catch (\Throwable $e) {
            log_message('error', '[WhatsApp] Error al subir ' . $tipo . ' a Meta: ' . $e->getMessage());
        } finally {
            if ($isTempFile && is_file($filePath)) {
                @unlink($filePath);
            }
        }
    }

    /**
     * Procesa el HEADER de tipo media (imagen, video, documento)
     * Acepta: handle directo (media ID), URL de imagen, o ruta de archivo local
     */
    private function procesarHeaderMedia($datos, $phone_number_id, $headerType, $access_token)
    {
        // 1. Si ya se proporciona un handle (media ID), usarlo directamente
        $headerHandle = $datos['header_handle'] ?? $datos['header_image_handle'] ?? null;
        if (!empty($headerHandle)) {
            return $headerHandle;
        }

        // 2. Obtener archivo desde URL o ruta local
        $filePath = null;
        $isTempFile = false;

        // Intentar desde URL
        $headerImageUrl = $datos['header_image_url'] ?? $datos['image_url'] ?? null;
        if (!empty($headerImageUrl) && filter_var($headerImageUrl, FILTER_VALIDATE_URL)) {
            log_message('debug', "Descargando imagen desde URL: {$headerImageUrl}");
            $filePath = $this->descargarMediaDesdeURL($headerImageUrl, $access_token, 'image');
            $isTempFile = ($filePath !== null);
        }

        // Si no hay URL, intentar desde ruta local
        if (!$filePath) {
            $headerImagePath = $datos['header_image_path'] ?? $datos['image_path'] ?? null;
            if (!empty($headerImagePath) && file_exists($headerImagePath)) {
                $filePath = $headerImagePath;
            }
        }

        // 3. Subir archivo si existe
        if ($filePath && file_exists($filePath) && filesize($filePath) > 0) {
            try {
                $mediaId = $this->subirMediaAWhatsapp($filePath, $phone_number_id, $access_token);
                
                // Limpiar archivo temporal si fue descargado
                if ($isTempFile && file_exists($filePath)) {
                    @unlink($filePath);
                }
                
                if ($mediaId) {
                    log_message('debug', "Media subido exitosamente. Media ID: {$mediaId}");
                    return $mediaId;
                }
            } catch (\Exception $e) {
                log_message('error', "Error al subir media: " . $e->getMessage());
                if ($isTempFile && file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
        }

        return null;
    }

    /**
     * Sube un archivo directamente a WhatsApp Business Media API
     * Devuelve el media attachment ID válido para usar en plantillas
     */
    private function subirMediaAWhatsapp($filePath, $phone_number_id, $access_token)
    {
        if (!file_exists($filePath)) {
            log_message('error', "El archivo no existe: {$filePath}");
            return null;
        }

        // Detectar tipo MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $filePath) ?: 'image/jpeg';
        finfo_close($finfo);

        $endpoint = "https://graph.facebook.com/v19.0/{$phone_number_id}/media";
        $cfile = new \CURLFile($filePath, $mime_type, basename($filePath));
        
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'messaging_product' => 'whatsapp',
                'type' => $mime_type,
                'file' => $cfile
            ],
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$access_token}"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            log_message('error', "Error cURL al subir media: {$error}");
            return null;
        }

        if ($http_code !== 200 && $http_code !== 201) {
            $responseData = json_decode($response, true);
            $error_message = $responseData['error']['message'] ?? 'Error desconocido';
            log_message('error', "Error HTTP al subir media: HTTP {$http_code} - {$error_message}");
            return null;
        }

        $responseData = json_decode($response, true);
        
        if (!isset($responseData['id'])) {
            log_message('error', "Respuesta inválida de WhatsApp: " . json_encode($responseData));
            return null;
        }

        return $responseData['id'];
    }

    /**
     * Descarga media desde URL y la guarda temporalmente para subir a Meta.
     */
    private function descargarMediaDesdeURL(string $url, ?string $access_token = null, string $tipoMedia = 'image'): ?string
    {
        $tempDir = WRITEPATH . 'temp/';
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $extension = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
        if ($extension === '' || $extension === false) {
            $extension = match ($tipoMedia) {
                'video' => 'mp4',
                'document' => 'pdf',
                default => 'jpg',
            };
        }

        $tempFile = $tempDir . 'wa_media_' . uniqid('', true) . '.' . $extension;

        $ch = curl_init($url);
        $headers = [];

        if ($access_token && (str_contains($url, 'scontent.whatsapp.net') || str_contains($url, 'graph.facebook.com'))) {
            $headers[] = "Authorization: Bearer {$access_token}";
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => $tipoMedia === 'video' ? 180 : 60,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $fileData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200 || empty($fileData)) {
            log_message('error', '[WhatsApp] Error al descargar media (' . $tipoMedia . '): ' . ($error ?: "HTTP {$httpCode}"));

            return null;
        }

        if (file_put_contents($tempFile, $fileData) === false) {
            log_message('error', '[WhatsApp] No se pudo guardar media temporal');

            return null;
        }

        log_message('debug', '[WhatsApp] Media descargada: ' . $tempFile . ' (' . strlen($fileData) . ' bytes)');

        return $tempFile;
    }

    private const MIN_PALABRA_BUSQUEDA = 3;

    private const MAX_PALABRAS_BUSQUEDA = 5;

    /**
     * Extrae palabras clave para búsqueda (mín. 3 caracteres, máx. 5 palabras).
     *
     * @return list<string>
     */
    public function extraerPalabrasClaveBusqueda(?string $texto): array
    {
        if ($texto === null || trim($texto) === '') {
            return [];
        }

        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $partes = preg_split('/\s+/u', $texto, -1, PREG_SPLIT_NO_EMPTY);
        $palabras = [];

        foreach ($partes as $parte) {
            $parte = trim($parte);
            if (mb_strlen($parte, 'UTF-8') < self::MIN_PALABRA_BUSQUEDA) {
                continue;
            }
            $palabras[] = $parte;
            if (count($palabras) >= self::MAX_PALABRAS_BUSQUEDA) {
                break;
            }
        }

        return array_values(array_unique($palabras));
    }

    /**
     * SQL EXISTS para filtrar conversaciones que contengan todas las palabras en sus mensajes.
     * Case-insensitive; se aplica sobre el conjunto ya filtrado del listado.
     */
    public function construirExistsSqlBusquedaMensaje(array $palabras): ?string
    {
        if ($palabras === []) {
            return null;
        }

        $db = \Config\Database::connect();
        $condiciones = [
            'm.id_conversacion = conversacion.id',
            'm.deleted_at IS NULL',
            'm.mensaje IS NOT NULL',
            "TRIM(m.mensaje) <> ''",
        ];

        foreach ($palabras as $palabra) {
            $like = '%' . $db->escapeLikeString($palabra) . '%';
            $condiciones[] = 'LOWER(m.mensaje) LIKE ' . $db->escape($like);
        }

        return 'EXISTS (SELECT 1 FROM mensaje m WHERE ' . implode(' AND ', $condiciones) . ')';
    }

    private function resolverIdEmpresaMensaje(array $datos): int
    {
        $id = (int) ($datos['id_empresa'] ?? 0);
        if ($id > 0) {
            return $id;
        }

        if (! empty($datos['id_conversacion'])) {
            $conversacion = model('ConversacionModel')->find((int) $datos['id_conversacion']);
            $id = (int) ($conversacion->id_empresa ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        helper('vip2system');
        $id = (int) (id_empresa_sesion() ?? 0);
        if ($id <= 0) {
            throw new \Exception('id_empresa es obligatorio para el mensaje.');
        }

        return $id;
    }

    /** Quién del equipo escribió el mensaje (solo cuando contesta = usuario). */
    private function resolverIdUsuarioMensaje(array $datos): ?int
    {
        $contesta = (string) ($datos['contesta'] ?? '');
        if ($contesta !== 'usuario') {
            return null;
        }

        $id = (int) ($datos['id_usuario'] ?? 0);
        if ($id > 0) {
            return $id;
        }

        try {
            $usuario = auth()->getUsuario();
            $id = (int) ($usuario->id ?? 0);

            return $id > 0 ? $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param list<array<string, mixed>> $mensajes
     * @return list<array<string, mixed>>
     */
    private function enriquecerAutoresMensajes(array $mensajes): array
    {
        $ids = [];
        foreach ($mensajes as $mensaje) {
            $id = (int) ($mensaje['id_usuario'] ?? 0);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
        if ($ids === []) {
            return $mensajes;
        }

        $filas = db_connect()->table('usuario u')
            ->select('u.id, u.usuario, p.nombres, p.apellidos, p.foto_url')
            ->join('usuario_perfil p', 'p.id_usuario = u.id AND p.deleted_at IS NULL', 'left')
            ->whereIn('u.id', array_keys($ids))
            ->get()
            ->getResultArray();

        $porId = [];
        foreach ($filas as $fila) {
            $nombre = trim(trim((string) ($fila['nombres'] ?? '')) . ' ' . trim((string) ($fila['apellidos'] ?? '')));
            if ($nombre === '') {
                $nombre = (string) $fila['usuario'];
            }
            $porId[(int) $fila['id']] = [
                'id' => (int) $fila['id'],
                'nombre' => $nombre,
                'foto' => $fila['foto_url'] ?: null,
            ];
        }

        foreach ($mensajes as &$mensaje) {
            $id = (int) ($mensaje['id_usuario'] ?? 0);
            if ($id > 0 && isset($porId[$id])) {
                $mensaje['autor'] = $porId[$id];
            }
        }
        unset($mensaje);

        return $mensajes;
    }
}
