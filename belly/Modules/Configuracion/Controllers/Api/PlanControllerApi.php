<?php

namespace Belly\Configuracion\Controllers\Api;

use App\Controllers\BaseController;
use Belly\Configuracion\UseCases\PlanUseCase;

class PlanControllerApi extends BaseController
{
    public function listar()
    {
        try {
            return $this->response->setJSON([
                'success' => true,
                'data' => (new PlanUseCase())->listar(),
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
                'data' => (new PlanUseCase())->guardar($this->cuerpo()),
            ]);
        } catch (\Throwable $e) {
            return $this->error($e);
        }
    }

    public function actualizar(int $id)
    {
        try {
            return $this->response->setJSON([
                'success' => true,
                'data' => (new PlanUseCase())->guardar($this->cuerpo(), $id),
            ]);
        } catch (\Throwable $e) {
            return $this->error($e);
        }
    }

    public function eliminar(int $id)
    {
        try {
            (new PlanUseCase())->eliminar($id);

            return $this->response->setJSON(['success' => true]);
        } catch (\Throwable $e) {
            return $this->error($e);
        }
    }

    public function asignar(int $idEmpresa)
    {
        try {
            return $this->response->setJSON([
                'success' => true,
                'data' => (new PlanUseCase())->asignar($idEmpresa, $this->cuerpo()),
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
        $codigo = str_contains($e->getMessage(), 'No autorizado') ? 403 : 400;

        return $this->response->setStatusCode($codigo)->setJSON([
            'success' => false,
            'error' => $e->getMessage(),
        ]);
    }
}
