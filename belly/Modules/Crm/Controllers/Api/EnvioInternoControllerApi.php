<?php

namespace Belly\Crm\Controllers\Api;

use App\Controllers\BaseController;

/** Endpoint interno: el servidor de tiempo real (que conoce la clave) pide mandar la siguiente tanda de envíos masivos. */
class EnvioInternoControllerApi extends BaseController
{
    public function procesar()
    {
        $clave = trim((string) env('SOCKET_CLAVE', ''));
        $recibida = (string) $this->request->getHeaderLine('X-Socket-Clave');

        if ($clave === '' || ! hash_equals($clave, $recibida)) {
            return $this->response->setStatusCode(401)->setJSON(['ok' => false]);
        }

        try {
            helper('vip2system');

            return $this->response->setJSON(['ok' => true, 'enviados' => crm_envios()->procesarPendientes()]);
        } catch (\Throwable $e) {
            log_message('error', '[ENVIO MASIVO] Procesar pendientes: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON(['ok' => false]);
        }
    }
}
