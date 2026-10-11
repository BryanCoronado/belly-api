<?php

namespace Belly\Webhooks\Controllers\Api;

use App\Controllers\BaseController;

class WebhookController extends BaseController
{
    public function conectarMeta()
    {
        $method = strtoupper((string) $this->request->getMethod(true));

        if ($method === 'GET') {
            return $this->verificarSuscripcion();
        }

        if ($method === 'POST') {
            return $this->recibirEvento();
        }

        return $this->response->setStatusCode(405);
    }

    private function verificarSuscripcion()
    {
        $verifyToken = trim((string) config('WebhooksConfig')->meta_verify_token);
        $mode = (string) ($this->request->getGet('hub.mode') ?? $this->request->getGet('hub_mode') ?? '');
        $token = (string) ($this->request->getGet('hub.verify_token') ?? $this->request->getGet('hub_verify_token') ?? '');
        $challenge = (string) ($this->request->getGet('hub.challenge') ?? $this->request->getGet('hub_challenge') ?? '');

        if ($verifyToken === '') {
            log_message('error', '[WEBHOOK WA] Falta META_WEBHOOK_VERIFY_TOKEN');

            return $this->response->setStatusCode(403);
        }

        if ($mode === 'subscribe' && hash_equals($verifyToken, $token) && $challenge !== '') {
            return $this->response
                ->setStatusCode(200)
                ->setContentType('text/plain')
                ->setBody($challenge);
        }

        log_message('warning', '[WEBHOOK WA] Verificación rechazada');

        return $this->response->setStatusCode(403);
    }

    private function recibirEvento()
    {
        try {
            $payload = $this->leerPayload();
            if ($payload === null) {
                return $this->ack();
            }

            $object = (string) ($payload['object'] ?? '');
            if ($object !== 'whatsapp_business_account') {
                log_message('warning', '[WEBHOOK WA] Objeto ignorado: ' . ($object !== '' ? $object : '(vacío)'));

                return $this->ack();
            }

            webhook()->procesarEventoWhatsapp($payload);
        } catch (\Throwable $e) {
            log_message('error', '[WEBHOOK WA] ' . $e->getMessage());
        }

        return $this->ack();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function leerPayload(): ?array
    {
        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            $raw = (string) $this->request->getBody();
            $payload = json_decode($raw, true);
        }

        if (! is_array($payload)) {
            log_message('warning', '[WEBHOOK WA] Body no es JSON');

            return null;
        }

        return $payload;
    }

    private function ack()
    {
        return $this->response->setStatusCode(200)->setJSON(['status' => 'ok']);
    }
}
