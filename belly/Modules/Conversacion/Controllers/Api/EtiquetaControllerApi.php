<?php

namespace Belly\Conversacion\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;

class EtiquetaControllerApi extends BaseController
{
    use ResponseTrait;

    public function listar()
    {
        try {
            $etiquetas = etiquetas()->listarEtiquetas();

            return $this->respond([
                'success' => true,
                'data' => $etiquetas,
            ]);
        } catch (\Throwable $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
