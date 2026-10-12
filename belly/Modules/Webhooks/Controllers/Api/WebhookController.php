<?php

namespace Belly\Webhooks\Controllers\Api;

use App\Controllers\BaseController;
use Belly\Configuracion\Entities\ConexionMetaEntity;
use Belly\Services\ContextoEmpresa;

/**
 * Webhook de WhatsApp Cloud API. Cada empresa tiene su URL: /v1/webhooks/meta/{clave}.
 * La clave identifica a la empresa; el token de verificación y el App Secret son los suyos.
 */
class WebhookController extends BaseController
{
    public function verificarMeta(string $clave = '')
    {
        $conexion = conexion_whatsapp()->conexionPorClave($clave);
        if ($conexion === null) {
            return $this->response->setStatusCode(404);
        }

        $modo = (string) ($this->request->getGet('hub_mode') ?? $this->request->getGet('hub.mode') ?? '');
        $token = (string) ($this->request->getGet('hub_verify_token') ?? $this->request->getGet('hub.verify_token') ?? '');
        $challenge = (string) ($this->request->getGet('hub_challenge') ?? $this->request->getGet('hub.challenge') ?? '');

        $respuesta = $challenge !== '' ? conexion_whatsapp()->verificarSuscripcion($conexion, $modo, $token, $challenge) : null;
        if ($respuesta === null) {
            log_message('warning', '[WEBHOOK WA] Verificación rechazada para la empresa ' . $conexion->id_empresa);

            return $this->response->setStatusCode(403);
        }

        return $this->response->setStatusCode(200)->setContentType('text/plain')->setBody($respuesta);
    }

    public function recibirMeta(string $clave = '')
    {
        $conexion = conexion_whatsapp()->conexionPorClave($clave);
        if ($conexion === null) {
            return $this->response->setStatusCode(404);
        }

        $cuerpo = (string) $this->request->getBody();
        $firma = trim($this->request->getHeaderLine('X-Hub-Signature-256'));
        if (! conexion_whatsapp()->firmaValida($conexion, $cuerpo, $firma)) {
            log_message('warning', '[WEBHOOK WA] Firma inválida para la empresa ' . $conexion->id_empresa);

            return $this->response->setStatusCode(403);
        }

        $payload = json_decode($cuerpo, true);
        if (! is_array($payload) || ($payload['object'] ?? '') !== 'whatsapp_business_account') {
            return $this->ack();
        }

        $this->procesar($conexion, $payload);

        return $this->ack();
    }

    /** @param array<string, mixed> $payload */
    private function procesar(ConexionMetaEntity $conexion, array $payload): void
    {
        try {
            conexion_whatsapp()->registrarEvento($conexion);
            // Todas las consultas quedan limitadas a esta empresa: un phone_number_id ajeno no se resuelve.
            ContextoEmpresa::ejecutarComo((int) $conexion->id_empresa, static fn () => webhook()->procesarEventoWhatsapp($payload));
        } catch (\Throwable $e) {
            log_message('error', '[WEBHOOK WA] ' . $e->getMessage());
        }
    }

    private function ack()
    {
        return $this->response->setStatusCode(200)->setJSON(['status' => 'ok']);
    }
}
