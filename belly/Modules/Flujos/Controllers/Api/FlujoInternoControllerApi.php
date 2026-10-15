<?php

namespace Belly\Flujos\Controllers\Api;

use App\Controllers\BaseController;

/** Endpoint interno: solo el servidor de tiempo real (que conoce la clave) puede pedir retomar los flujos en espera. */
class FlujoInternoControllerApi extends BaseController
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

            return $this->response->setJSON(['ok' => true, 'retomadas' => bot_flujos()->procesarPendientes()]);
        } catch (\Throwable $e) {
            log_message('error', '[FLUJOS] Procesar pendientes: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON(['ok' => false]);
        }
    }
}
