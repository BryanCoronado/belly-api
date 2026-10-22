<?php

namespace Belly\Reparto\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\Exceptions\HTTPException;

class RepartoControllerApi extends BaseController
{
    public function obtener()
    {
        return $this->ejecutar(static fn () => reparto()->obtener());
    }

    public function guardar()
    {
        return $this->ejecutar(fn () => reparto()->guardar($this->datos()['definicion'] ?? null));
    }

    public function revisar()
    {
        return $this->ejecutar(fn () => reparto()->revisar($this->datos()['definicion'] ?? null));
    }

    public function probar()
    {
        return $this->ejecutar(fn () => reparto()->probar($this->datos()));
    }

    public function listarGrupos()
    {
        return $this->ejecutar(static fn () => grupos_atencion()->listar());
    }

    public function crearGrupo()
    {
        return $this->ejecutar(fn () => grupos_atencion()->crear($this->datos()), 201);
    }

    public function actualizarGrupo(int $id)
    {
        return $this->ejecutar(fn () => grupos_atencion()->actualizar($id, $this->datos()));
    }

    public function eliminarGrupo(int $id)
    {
        return $this->ejecutar(static function () use ($id) {
            grupos_atencion()->eliminar($id);

            return ['eliminado' => true];
        });
    }

    /** @return array<string, mixed> */
    private function datos(): array
    {
        try {
            $datos = $this->request->getJSON(true);
        } catch (HTTPException) {
            throw new \InvalidArgumentException('Los datos llegaron incompletos. Recarga la página e inténtalo de nuevo.');
        }

        return is_array($datos) ? $datos : [];
    }

    private function ejecutar(callable $accion, int $codigoOk = 200)
    {
        try {
            return $this->response->setStatusCode($codigoOk)->setJSON(['success' => true, 'data' => $accion()]);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            log_message('error', '[REPARTO] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'No se pudo completar la acción.']);
        }
    }
}
