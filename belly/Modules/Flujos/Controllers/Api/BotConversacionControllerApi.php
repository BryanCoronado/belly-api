<?php

namespace Belly\Flujos\Controllers\Api;

use App\Controllers\BaseController;

/** Lo que el bot recogió en una conversación; lo ve cualquier asesor con acceso al chat. */
class BotConversacionControllerApi extends BaseController
{
    public function resumen(int $idConversacion)
    {
        try {
            return $this->response->setJSON([
                'success' => true,
                'data' => bot_flujos()->resumenDeConversacion($idConversacion),
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[FLUJOS] resumen: ' . $e->getMessage());

            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'No se pudo cargar el resumen.']);
        }
    }
}
