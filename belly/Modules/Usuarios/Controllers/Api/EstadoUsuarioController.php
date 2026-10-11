<?php

namespace Belly\Usuarios\Controllers\Api;

use App\Controllers\BaseController;
use Belly\Usuarios\Config\Services;

class EstadoUsuarioController extends BaseController
{
    public function listar()
    {
        try {
            $estado_usuario_use_case = Services::EstadoUsuarioUseCase();
            $estados = $estado_usuario_use_case->listarEstadosUsuarios();
            
            return $this->response->setJSON([
                'success' => true,
                'data' => $estados
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function ver(int $id = 0)
    {
        try {
            $estado_usuario_use_case = Services::EstadoUsuarioUseCase();
            $estado_usuario_model = $estado_usuario_use_case->getEstadoUsuarioProvider();
            $estado = $estado_usuario_model->find($id);
            
            if (!$estado) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Estado de usuario no encontrado',
                    'data' => null
                ], 404);
            }

            return $this->response->setJSON([
                'success' => true,
                'data' => $estado
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}

