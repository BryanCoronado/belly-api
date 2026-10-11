<?php

namespace Belly\Conversacion\Controllers\Api;

use App\Controllers\BaseController;

class ConversacionMetricaController extends BaseController {

    /**
     * GET /v1/conversacion-metricas/dashboard
     * Métricas del dashboard según el rol del usuario logueado
     */
    public function dashboard()
    {
        try {
            $resultado = conversacion_metrica()->conversacionMetrica();
            return $this->response->setJSON([
                'success' => true,
                'data' => $resultado
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ])->setStatusCode(400);
        }
    }

}

