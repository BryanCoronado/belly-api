<?php

namespace Belly\Flujos\Controllers\Api;

use App\Controllers\BaseController;
use Belly\Flujos\UseCases\ArchivoFlujoUseCase;

class FlujoControllerApi extends BaseController
{
    public function listar()
    {
        return $this->ejecutar(static fn () => flujos()->listar());
    }

    public function asesores()
    {
        return $this->ejecutar(static fn () => flujos()->asesores());
    }

    public function ver(int $id)
    {
        return $this->ejecutar(static fn () => flujos()->obtener($id));
    }

    public function crear()
    {
        return $this->ejecutar(fn () => flujos()->crear($this->datos()), 201);
    }

    public function actualizar(int $id)
    {
        return $this->ejecutar(fn () => flujos()->actualizar($id, $this->datos()));
    }

    public function cambiarEstado(int $id)
    {
        $datos = $this->datos();

        return $this->ejecutar(fn () => flujos()->cambiarEstado(
            $id,
            (bool) ($datos['activo'] ?? false),
            filter_var($datos['forzar'] ?? false, FILTER_VALIDATE_BOOLEAN)
        ));
    }

    public function duplicar(int $id)
    {
        return $this->ejecutar(static fn () => flujos()->duplicar($id), 201);
    }

    public function eliminar(int $id)
    {
        return $this->ejecutar(static function () use ($id) {
            flujos()->eliminar($id);

            return ['eliminado' => true];
        });
    }

    public function validar()
    {
        return $this->ejecutar(fn () => flujos()->validar($this->datos()['definicion'] ?? null));
    }

    public function simular()
    {
        return $this->ejecutar(fn () => flujos()->simular($this->datos()));
    }

    public function subirArchivo()
    {
        return $this->ejecutar(fn () => (new ArchivoFlujoUseCase())->subir(
            $this->request->getFile('archivo'),
            (string) $this->request->getPost('tipo')
        ), 201);
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
            log_message('error', '[FLUJOS] ' . $e->getMessage());

            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'No se pudo completar la acción.']);
        }
    }
}
