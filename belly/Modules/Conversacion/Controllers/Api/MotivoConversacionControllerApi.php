<?php

namespace Belly\Conversacion\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;

class MotivoConversacionControllerApi extends BaseController
{
    use ResponseTrait;

    public function listar()
    {
        try {
            $motivos = motivo_conversacion()->listarMotivoConversacion();

            return $this->respond([
                'success' => true,
                'data' => $motivos
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function obtener(int $id)
    {
        try {
            $motivo = motivo_conversacion()->obtenerMotivoConversacion($id);

            return $this->respond([
                'success' => true,
                'data' => $motivo
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        }
    }

    public function crear()
    {
        try {
            $datos = $this->request->getJSON(true);
            
            if (!isset($datos['nombre']) || empty($datos['nombre'])) {
                return $this->respond([
                    'success' => false,
                    'message' => 'El campo nombre es obligatorio'
                ], 400);
            }

            $motivo = motivo_conversacion()->crearMotivoConversacion($datos);

            return $this->respond([
                'success' => true,
                'message' => 'Motivo de conversación creado correctamente',
                'data' => $motivo
            ], 201);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function editar(int $id)
    {
        try {
            $datos = $this->request->getJSON(true);
            
            $motivo = motivo_conversacion()->editarMotivoConversacion($id, $datos);

            return $this->respond([
                'success' => true,
                'message' => 'Motivo de conversación actualizado correctamente',
                'data' => $motivo
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function eliminar(int $id)
    {
        try {
            $resultado = motivo_conversacion()->eliminarMotivoConversacion($id);

            return $this->respond([
                'success' => true,
                'message' => $resultado['message'],
                'data' => $resultado
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}

