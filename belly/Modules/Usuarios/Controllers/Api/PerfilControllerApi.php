<?php

namespace Belly\Usuarios\Controllers\Api;

use App\Controllers\BaseController;
use Belly\Usuarios\UseCases\PerfilUseCase;

class PerfilControllerApi extends BaseController
{
    public function ver()
    {
        return $this->ejecutar(fn () => $this->caso()->ver());
    }

    public function actualizar()
    {
        return $this->ejecutar(fn () => $this->caso()->actualizar($this->datos()));
    }

    public function foto()
    {
        return $this->ejecutar(fn () => $this->caso()->subirFoto($this->request->getFile('foto')));
    }

    public function empresa()
    {
        return $this->ejecutar(fn () => $this->caso()->actualizarEmpresa($this->datos()));
    }

    /** @return array<string, mixed> */
    private function datos(): array
    {
        $datos = $this->request->getJSON(true);

        return is_array($datos) ? $datos : [];
    }

    private function caso(): PerfilUseCase
    {
        return new PerfilUseCase();
    }

    private function ejecutar(callable $accion)
    {
        try {
            return $this->response->setStatusCode(200)->setJSON(['success' => true, 'data' => $accion()]);
        } catch (\DomainException $e) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            log_message('error', '[PERFIL] ' . $e->getMessage());

            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'No se pudo guardar.']);
        }
    }
}
