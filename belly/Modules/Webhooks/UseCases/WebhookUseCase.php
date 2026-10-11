<?php

namespace Belly\Webhooks\UseCases;

class WebhookUseCase
{
    /**
     * @param array<string, mixed> $payload
     */
    public function procesarEventoWhatsapp(array $payload): void
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach ($entry['changes'] ?? [] as $change) {
                if (! is_array($change) || ($change['field'] ?? '') !== 'messages') {
                    continue;
                }

                $value = $change['value'] ?? null;
                if (! is_array($value)) {
                    continue;
                }

                foreach ($value['messages'] ?? [] as $message) {
                    if (! is_array($message)) {
                        continue;
                    }

                    try {
                        $this->procesarMensajeWhatsapp($message, $value, $payload);
                    } catch (\Throwable $e) {
                        log_message('error', '[WEBHOOK WA] Mensaje: ' . $e->getMessage());
                    }
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    if (! is_array($status)) {
                        continue;
                    }

                    try {
                        $this->procesarEstadoMensajeWhatsapp($status);
                    } catch (\Throwable $e) {
                        log_message('error', '[WEBHOOK WA] Estado: ' . $e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $message
     * @param array<string, mixed> $value
     * @param array<string, mixed>|null $payload
     */
    public function procesarMensajeWhatsapp(array $message, array $value, ?array $payload = null): void
    {
        helper('whatsapp');

        if (($message['type'] ?? '') === 'system') {
            $this->reconciliarMensajeSistema($message, $value);

            return;
        }

        $identidad = extraer_identidad_whatsapp_entrante($message, $value);
        $telefono = $identidad['telefono'];
        $waUserId = $identidad['wa_user_id'];
        $phoneNumberId = trim((string) ($value['metadata']['phone_number_id'] ?? ''));
        $wamid = trim((string) ($message['id'] ?? ''));

        if ($telefono === null && empty($waUserId)) {
            log_message('warning', '[WEBHOOK WA] Mensaje sin teléfono ni identificador. wamid=' . ($wamid !== '' ? $wamid : '-'));

            return;
        }

        if ($phoneNumberId === '') {
            log_message('error', '[WEBHOOK WA] Falta phone_number_id');

            return;
        }

        $app = app()->obtenerApp('phone_number_id', $phoneNumberId);
        if (! $app) {
            log_message('error', '[WEBHOOK WA] App no encontrada para phone_number_id=' . $phoneNumberId);

            return;
        }

        $idApp = (int) $app->id;
        if ($wamid !== '' && $this->mensajeYaExiste($wamid)) {
            log_message('debug', '[WEBHOOK WA] Duplicado wamid=' . $wamid);

            return;
        }

        try {
            $cliente = clientes()->crearOActualizarClienteWhatsapp([
                'telefono' => $telefono,
                'wa_user_id' => $waUserId,
                'wa_parent_user_id' => $identidad['wa_parent_user_id'] ?? null,
                'nombre' => $identidad['nombre'] ?? null,
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[WEBHOOK WA] Cliente: ' . $e->getMessage());

            return;
        }

        $idCliente = (int) ($cliente['id'] ?? 0);
        if ($idCliente <= 0) {
            log_message('error', '[WEBHOOK WA] Cliente sin id');

            return;
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $conversacion = $this->obtenerConversacionActivaWhatsapp($idCliente, $idApp, $db);
            $conversacionNueva = false;

            if ($conversacion) {
                $idConversacion = (int) $conversacion->id;
                $idUsuario = (int) $conversacion->id_usuario;
            } else {
                $idUsuario = $this->resolverAsignacion($app, $idApp, $idCliente);
                if ($idUsuario === null) {
                    $db->transRollback();
                    log_message('error', '[WEBHOOK WA] Sin asesor disponible. app=' . $idApp . ' cliente=' . $idCliente);

                    return;
                }

                $referral = $this->referralDelMensaje($message);
                $creada = conversaciones()->crearConversacion([
                    'id_cliente' => $idCliente,
                    'id_usuario' => $idUsuario,
                    'origen' => 'api-crm',
                    'registro' => $referral['source_id'] !== null ? 'anuncio_meta' : null,
                    'id_app' => $idApp,
                    'id_estado' => 3,
                    'asignado_at' => date('Y-m-d H:i:s'),
                    'source_id' => $referral['source_id'],
                    'campaign_id' => null,
                ]);
                $idConversacion = (int) ($creada['id'] ?? 0);
                $conversacionNueva = true;
            }

            if ($idConversacion <= 0) {
                $db->transRollback();
                log_message('error', '[WEBHOOK WA] Conversación sin id');

                return;
            }

            $db->transComplete();
            if ($db->transStatus() === false) {
                log_message('error', '[WEBHOOK WA] Transacción de conversación fallida');

                return;
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', '[WEBHOOK WA] Conversación: ' . $e->getMessage());

            return;
        }

        $mensajeData = procesarTipoMensajeWhatsappEntrante($message, $idApp, $idConversacion);
        $guardado = $this->guardarMensajeEntrante($idConversacion, $message, $payload, $mensajeData);

        if (! empty($guardado['_idempotent'])) {
            return;
        }

        if ($conversacionNueva) {
            log_message('info', '[WEBHOOK WA] Conversación nueva id=' . $idConversacion . ' usuario=' . $idUsuario);
        }
    }

    /**
     * @param array<string, mixed> $status
     */
    public function procesarEstadoMensajeWhatsapp(array $status): void
    {
        $wamid = trim((string) ($status['id'] ?? ''));
        if ($wamid === '') {
            return;
        }

        $estado = (string) ($status['status'] ?? '');
        $nuevoEstado = match ($estado) {
            'sent' => 2,
            'delivered' => 3,
            'read' => 4,
            'failed' => 5,
            default => null,
        };

        $codError = null;
        if ($estado === 'failed') {
            $codError = $status['errors'][0]['code'] ?? null;
            $this->registrarFalloWhatsapp($wamid, $status, $codError);
        }

        if ($nuevoEstado !== null && function_exists('mensaje')) {
            mensaje()->actualizarEstadoMensajeWhatsapp($wamid, $nuevoEstado, null, $codError);
        }

        try {
            if (function_exists('clientes')) {
                clientes()->backfillIdentidadWhatsappDesdeStatus($status);
            }
        } catch (\Throwable $e) {
            log_message('debug', '[WEBHOOK WA] Identidad en status omitida: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $message
     * @param array<string, mixed> $value
     */
    private function reconciliarMensajeSistema(array $message, array $value): void
    {
        try {
            $result = clientes()->reconciliarIdentidadWhatsappSistema($message, $value);
            log_message(
                'info',
                '[WEBHOOK WA] system=' . (string) ($message['system']['type'] ?? '')
                . ' accion=' . (string) ($result['accion'] ?? '')
                . ' cliente=' . (string) ($result['id_cliente'] ?? '')
            );
        } catch (\Throwable $e) {
            log_message('error', '[WEBHOOK WA] Sistema: ' . $e->getMessage());
        }
    }

    /**
     * Cliente recurrente conserva su asesor si sigue disponible.
     * Si no, reparto equitativo del día entre el personal de la app.
     */
    private function resolverAsignacion(object $app, int $idApp, int $idCliente): ?int
    {
        $asignacion = conversaciones()->resolverAsignacionClienteRecurrente($idCliente, $idApp, $app);
        if (! empty($asignacion['id_usuario'])) {
            return (int) $asignacion['id_usuario'];
        }

        $idEquitativo = app()->obtenerUsuarioParaApp($app);

        return $idEquitativo ? (int) $idEquitativo : null;
    }

    private function obtenerConversacionActivaWhatsapp(int $idCliente, int $idApp, $db): ?object
    {
        $filtroOrigen = sql_filtro_origen_whatsapp('origen');
        $sql = "SELECT * FROM conversacion
                WHERE id_cliente = ?
                AND id_app = ?
                AND id_estado IN (1, 3)
                AND deleted_at IS NULL
                AND {$filtroOrigen}
                ORDER BY updated_at DESC
                LIMIT 1
                FOR UPDATE";

        $result = $db->query($sql, [$idCliente, $idApp])->getRow();

        return $result ?: null;
    }

    private function mensajeYaExiste(string $wamid): bool
    {
        $existe = mensaje()->getMensajeProvider()
            ->select('id')
            ->where('id_mensaje_whatsapp', $wamid)
            ->first();

        return $existe !== null;
    }

    /**
     * @param array<string, mixed> $message
     *
     * @return array{source_id: ?string}
     */
    private function referralDelMensaje(array $message): array
    {
        $referral = $message['referral'] ?? null;
        $sourceId = is_array($referral) ? trim((string) ($referral['source_id'] ?? '')) : '';

        return [
            'source_id' => $sourceId !== '' ? $sourceId : null,
        ];
    }

    /**
     * @param array<string, mixed> $message
     * @param array<string, mixed>|null $payload
     * @param array<string, mixed> $mensajeData
     *
     * @return array<string, mixed>
     */
    private function guardarMensajeEntrante(int $idConversacion, array $message, ?array $payload, array $mensajeData): array
    {
        $wamid = trim((string) ($message['id'] ?? ''));
        if ($wamid !== '' && $this->mensajeYaExiste($wamid)) {
            $existente = mensaje()->getMensajeProvider()
                ->where('id_mensaje_whatsapp', $wamid)
                ->first();

            if ($existente) {
                return [
                    'id' => $existente->id,
                    '_idempotent' => true,
                ];
            }
        }

        $referral = is_array($message['referral'] ?? null) ? $message['referral'] : null;
        if (! empty($mensajeData['referral_mensaje'])) {
            mensaje()->crearMensaje([
                'id_conversacion' => $idConversacion,
                'id_estado' => 3,
                'mensaje' => $mensajeData['referral_mensaje'],
                'contesta' => 'cliente',
                'media_path' => $mensajeData['referral_media_path'] ?? null,
                'media_url' => $mensajeData['referral_media_url'] ?? null,
                'media_type' => $mensajeData['referral_media_type'] ?? null,
                'payload' => json_encode(['referral' => $referral], JSON_UNESCAPED_UNICODE),
            ]);
        }

        return mensaje()->crearMensaje([
            'id_conversacion' => $idConversacion,
            'id_estado' => 3,
            'mensaje' => $mensajeData['mensaje'] ?? '',
            'contesta' => 'cliente',
            'id_mensaje_whatsapp' => $wamid !== '' ? $wamid : null,
            'id_mensaje_responde' => $mensajeData['id_mensaje_responde'] ?? null,
            'media_path' => $mensajeData['media_path'] ?? null,
            'media_url' => $mensajeData['media_url'] ?? null,
            'media_type' => $mensajeData['media_type'] ?? null,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * @param array<string, mixed> $status
     */
    private function registrarFalloWhatsapp(string $wamid, array $status, mixed $codError): void
    {
        $descripcion = (string) ($status['errors'][0]['message'] ?? 'Error desconocido');
        $detalle = $status['errors'][0]['error_data']['details'] ?? null;

        $mensajeOriginal = mensaje()->getMensajeProvider()
            ->where('id_mensaje_whatsapp', $wamid)
            ->first();

        if (! $mensajeOriginal) {
            log_message('warning', '[WEBHOOK WA] Fallo sin mensaje local wamid=' . $wamid);

            return;
        }

        $idConversacion = (int) ($mensajeOriginal->id_conversacion ?? 0);
        $ventana = mensaje()->obtenerDiagnosticoVentana24hPublico($idConversacion);

        log_message(
            'error',
            '[WEBHOOK WA] status=failed wamid=' . $wamid
            . ' codigo=' . (string) $codError
            . ' titulo=' . $descripcion
            . ' detalle=' . ($detalle ?? 'n/a')
            . ' id_conversacion=' . $idConversacion
            . ' ventana_24h=' . json_encode($ventana, JSON_UNESCAPED_UNICODE)
        );

        mensaje()->crearMensajeError(
            $mensajeOriginal->id_conversacion,
            $wamid,
            $mensajeOriginal->mensaje,
            $codError,
            $descripcion,
            null
        );
    }
}
