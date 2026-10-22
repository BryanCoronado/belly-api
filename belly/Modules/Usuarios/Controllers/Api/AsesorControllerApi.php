<?php

namespace Belly\Usuarios\Controllers\Api;

use App\Controllers\BaseController;

class AsesorControllerApi extends BaseController
{
    public function listar()
    {
        return $this->ejecutar(static fn () => asesores()->listar());
    }

    public function crear()
    {
        return $this->ejecutar(fn () => asesores()->crear($this->datos()), 201);
    }

    public function cambiarEstado(int $id)
    {
        return $this->ejecutar(fn () => asesores()->cambiarEstado($id, (bool) ($this->datos()['activo'] ?? false)));
    }

    public function subirFoto(int $id)
    {
        return $this->ejecutar(fn () => asesores()->subirFoto($id, $this->request->getFile('foto')));
    }

    /** @return array<string, mixed> */
    private function datos(): array
    {
        $datos = $this->request->getJSON(true);

        return is_array($datos) ? $datos : [];
    }

    private function ejecutar(callable $accion, int $codigoOk = 200)
    {
        try {
            return $this->response->setStatusCode($codigoOk)->setJSON(['success' => true, 'data' => $accion()]);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            log_message('error', '[ASESORES] ' . $e->getMessage());

            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'No se pudo completar la acción.']);
        }
    }
}
