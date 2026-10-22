<?php

namespace Belly\AgenteIa\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\Exceptions\HTTPException;

class AgenteIaControllerApi extends BaseController
{
    public function estado()
    {
        return $this->ejecutar(static fn () => agentes_ia()->estado());
    }

    public function conectar()
    {
        return $this->ejecutar(fn () => agentes_ia()->conectar($this->datos()['api_key'] ?? ''));
    }

    public function desconectar()
    {
        return $this->ejecutar(static function () {
            agentes_ia()->desconectar();

            return ['conectada' => false];
        });
    }

    public function obtener(int $id)
    {
        return $this->ejecutar(static fn () => agentes_ia()->obtener($id));
    }

    public function crear()
    {
        return $this->ejecutar(fn () => agentes_ia()->crear($this->datos()), 201);
    }

    public function actualizar(int $id)
    {
        return $this->ejecutar(fn () => agentes_ia()->actualizar($id, $this->datos()));
    }

    public function eliminar(int $id)
    {
        return $this->ejecutar(static function () use ($id) {
            agentes_ia()->eliminar($id);

            return ['eliminado' => true];
        });
    }

    public function probar(int $id)
    {
        return $this->ejecutar(fn () => agentes_ia()->probar($id, $this->datos()['charla'] ?? []));
    }

    public function agregarConocimiento(int $idAgente)
    {
        return $this->ejecutar(fn () => agentes_ia()->agregarConocimiento($idAgente, $this->datos()), 201);
    }

    public function actualizarConocimiento(int $idAgente, int $id)
    {
        return $this->ejecutar(fn () => agentes_ia()->actualizarConocimiento($idAgente, $id, $this->datos()));
    }

    public function eliminarConocimiento(int $idAgente, int $id)
    {
        return $this->ejecutar(static fn () => agentes_ia()->eliminarConocimiento($idAgente, $id));
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
            log_message('error', '[IA] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'No se pudo completar la acción.']);
        }
    }
}
