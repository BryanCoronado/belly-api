<?php

namespace Belly\Configuracion\Controllers\Api;

use App\Controllers\BaseController;

class EmpresaControllerApi extends BaseController
{
    public function listar()
    {
        try {
            return $this->response->setJSON([
                'success' => true,
                'data' => empresa()->listar(),
            ]);
        } catch (\Throwable $e) {
            return $this->responderError($e);
        }
    }

    public function crear()
    {
        try {
            $datos = $this->request->getJSON(true);
            if (! is_array($datos)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'error' => 'No se recibieron datos.',
                ]);
            }

            return $this->response->setStatusCode(201)->setJSON([
                'success' => true,
                'data' => empresa()->crear($datos),
            ]);
        } catch (\Throwable $e) {
            return $this->responderError($e);
        }
    }

    private function responderError(\Throwable $e)
    {
        $codigo = $e->getMessage() === 'No autorizado para administrar empresas.' ? 403 : 400;

        return $this->response->setStatusCode($codigo)->setJSON([
            'success' => false,
            'error' => $e->getMessage(),
        ]);
    }
}
