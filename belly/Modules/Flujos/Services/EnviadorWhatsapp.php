<?php

namespace Belly\Flujos\Services;

use Belly\Flujos\Domain\EnviadorMensajes;

/** Entrega los mensajes del bot por el mismo camino que usa el chat (queda guardado como "automático"). */
class EnviadorWhatsapp implements EnviadorMensajes
{
    public function enviar(int $idConversacion, array $mensaje): bool
    {
        $datos = $this->aDatosDeEnvio($idConversacion, $mensaje);
        if ($datos === null) {
            return true;
        }

        $resultado = mensaje()->crearMensajeYEnviarWhatsapp($datos);
        if (! ($resultado['success'] ?? false)) {
            log_message('error', '[BOT] No se pudo enviar mensaje automático conv=' . $idConversacion . ': ' . ($resultado['error'] ?? 'sin detalle'));

            return false;
        }

        return true;
    }

    /** @return array<string, mixed>|null */
    private function aDatosDeEnvio(int $idConversacion, array $m): ?array
    {
        $base = ['id_conversacion' => $idConversacion, 'contesta' => 'bot'];

        return match ($m['tipo'] ?? '') {
            'texto' => ($m['texto'] ?? '') === '' ? null : $base + ['media_type' => 'text', 'mensaje' => $m['texto']],
            'imagen' => $base + ['media_type' => 'image', 'media_url' => $m['url'], 'mensaje' => $m['texto'] ?? ''],
            'video' => $base + ['media_type' => 'video', 'media_url' => $m['url'], 'mensaje' => $m['texto'] ?? ''],
            'audio' => $base + ['media_type' => 'audio', 'media_url' => $m['url']],
            'ubicacion' => $base + [
                'media_type' => 'location',
                'latitude' => (string) $m['latitud'],
                'longitude' => (string) $m['longitud'],
                'name' => $m['nombre'] ?? '',
                'address' => $m['direccion'] ?? '',
            ],
            'documento' => $base + ['media_type' => 'document', 'media_url' => $m['url'], 'filename' => $m['nombre'] ?? 'documento'],
            'botones' => $base + ['media_type' => 'text', 'mensaje' => $m['texto'], 'interactivo' => [
                'tipo' => 'botones', 'texto' => $m['texto'], 'botones' => $m['botones'],
            ]],
            'lista' => $base + ['media_type' => 'text', 'mensaje' => $m['texto'], 'interactivo' => [
                'tipo' => 'lista', 'texto' => $m['texto'], 'boton' => $m['boton'], 'opciones' => $m['opciones'],
            ]],
            default => null,
        };
    }
}
