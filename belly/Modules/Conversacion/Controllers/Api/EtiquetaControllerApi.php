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

    public function crear()
    {
        try {
            $datos = $this->request->getJSON(true);
            if (! is_array($datos)) {
                return $this->respond(['success' => false, 'message' => 'No se recibieron datos.'], 400);
            }

            return $this->respond([
                'success' => true,
                'data' => etiquetas()->crear($datos),
            ], 201);
        } catch (\Throwable $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function editar(int $id)
    {
        try {
            $datos = $this->request->getJSON(true);
            if (! is_array($datos)) {
                return $this->respond(['success' => false, 'message' => 'No se recibieron datos.'], 400);
            }

            return $this->respond([
                'success' => true,
                'data' => etiquetas()->editar($id, $datos),
            ]);
        } catch (\Throwable $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function eliminar(int $id)
    {
        try {
            etiquetas()->eliminar($id);

            return $this->respond([
                'success' => true,
                'message' => 'Etiqueta eliminada correctamente',
            ]);
        } catch (\Throwable $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
