<?php

namespace Belly\Usuarios\Services;

use Belly\Usuarios\UseCases\DispositivoUseCase;

/**
 * Envía notificaciones Expo Push a la app de asesores.
 * Nunca debe frenar el flujo de mensajes: si falla, solo se registra.
 */
class PushAsesor
{
    private const URL = 'https://exp.host/--/api/v2/push/send';

    public static function avisoMensaje(array $aviso): void
    {
        try {
            $motivo = (string) ($aviso['motivo'] ?? 'mensaje');
            if ($motivo !== 'mensaje' && $motivo !== 'asignada') {
                return;
            }

            $contesta = (string) ($aviso['contesta'] ?? '');
            if ($motivo === 'mensaje' && $contesta !== '' && $contesta !== 'cliente') {
                return;
            }

            $destinos = array_values(array_filter(array_map('intval', $aviso['para'] ?? [])));
            if ($destinos === []) {
                $idUsuario = (int) ($aviso['id_usuario'] ?? 0);
                if ($idUsuario > 0) {
                    $destinos = [$idUsuario];
                }
            }
            if ($destinos === []) {
                return;
            }

            $idConversacion = (int) ($aviso['id_conversacion'] ?? 0);
            $titulo = trim((string) ($aviso['nombre'] ?? '')) ?: 'WhatsApp';
            $cuerpo = trim((string) ($aviso['preview'] ?? ''));
            if ($cuerpo === '') {
                $cuerpo = $motivo === 'asignada' ? 'Nueva conversación asignada' : 'Nuevo mensaje';
            }

            $casos = new DispositivoUseCase();
            $tokens = [];
            foreach ($destinos as $idUsuario) {
                foreach ($casos->tokensParaAviso($idUsuario, $idConversacion > 0 ? $idConversacion : null) as $token) {
                    $tokens[] = $token;
                }
            }
            $tokens = array_values(array_unique($tokens));
            if ($tokens === []) {
                return;
            }

            self::enviar($tokens, $titulo, $cuerpo, [
                'id_conversacion' => $idConversacion,
                'motivo' => $motivo,
            ]);
        } catch (\Throwable $e) {
            log_message('warning', '[PUSH] ' . $e->getMessage());
        }
    }

    /**
     * @param list<string> $tokens
     * @param array<string, mixed> $datos
     */
    private static function enviar(array $tokens, string $titulo, string $cuerpo, array $datos): void
    {
        $mensajes = [];
        foreach ($tokens as $token) {
            $mensajes[] = [
                'to' => $token,
                'title' => mb_substr($titulo, 0, 80),
                'body' => mb_substr($cuerpo, 0, 180),
                'sound' => 'default',
                'priority' => 'high',
                'channelId' => 'chat',
                'data' => $datos,
            ];
        }

        foreach (array_chunk($mensajes, 100) as $lote) {
            $ch = curl_init(self::URL);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($lote),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Accept-Encoding: gzip, deflate',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT_MS => 400,
                CURLOPT_TIMEOUT_MS => 1500,
                CURLOPT_NOSIGNAL => true,
            ]);
            $respuesta = curl_exec($ch);
            $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($codigo < 200 || $codigo >= 300) {
                log_message('warning', "[PUSH] Expo HTTP {$codigo} {$error} " . substr((string) $respuesta, 0, 200));
            }
        }
    }
}
