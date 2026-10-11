<?php

namespace Belly\Usuarios\Controllers\Api;

use App\Controllers\BaseController;

class UsuarioMetricaController extends BaseController {

    /**
     * GET /v1/usuario-metricas/dashboard
     * Métricas del dashboard según el rol del usuario logueado
     */
    public function dashboard()
    {
        try {
            $resultado = usuario_metrica()->obtenerMetricasHoy();
            
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


