<?php

namespace Belly\Clientes\Controllers\Api;


use App\Controllers\BaseController;

class ClienteMetricaControllerApi extends BaseController{
    
    public function obtenerMetricasAtencion()
    {
        try {
            $fecha_inicio = $this->request->getGet('fecha_inicio');
            $fecha_fin = $this->request->getGet('fecha_fin');
            
            $metricas = clientes_metrica()->obtenerMetricasAtencion($fecha_inicio, $fecha_fin);
            
            return $this->response->setJSON([
                'success' => true,
                'data' => $metricas
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

}
