<?php

namespace Belly\Configuracion\Controllers\Api;

use App\Controllers\BaseController;
use Belly\Configuracion\UseCases\PagoUseCase;

class PagoControllerApi extends BaseController
{
    public function listar()
    {
        try {
            return $this->response->setJSON([
                'success' => true,
                'data' => (new PagoUseCase())->listarOperador(),
            ]);
        } catch (\Throwable $e) {
            return $this->error($e);
        }
    }

    public function mios()
    {
        try {
            return $this->response->setJSON([
                'success' => true,
                'data' => (new PagoUseCase())->listarEmpresa(),
            ]);
        } catch (\Throwable $e) {
            return $this->error($e);
        }
    }

    public function crear()
    {
        try {
            return $this->response->setStatusCode(201)->setJSON([
                'success' => true,
                'data' => (new PagoUseCase())->registrar($this->cuerpo()),
            ]);
        } catch (\Throwable $e) {
            return $this->error($e);
        }
    }

    public function marcar(int $id)
    {
        try {
            return $this->response->setJSON([
                'success' => true,
                'data' => (new PagoUseCase())->marcar($id, $this->cuerpo()),
            ]);
        } catch (\Throwable $e) {
            return $this->error($e);
        }
    }

    /** @return array<string, mixed> */
    private function cuerpo(): array
    {
        $datos = $this->request->getJSON(true);

        return is_array($datos) ? $datos : [];
    }

    private function error(\Throwable $e)
    {
        $codigo = str_contains($e->getMessage(), 'No autorizado') || str_contains($e->getMessage(), 'Solo el administrador')
            ? 403
            : 400;

        return $this->response->setStatusCode($codigo)->setJSON([
            'success' => false,
            'error' => $e->getMessage(),
        ]);
    }
}
