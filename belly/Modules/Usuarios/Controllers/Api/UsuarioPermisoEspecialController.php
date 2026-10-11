<?php

namespace Belly\Usuarios\Controllers\Api;

use App\Controllers\BaseController;

class UsuarioPermisoEspecialController extends BaseController
{

    public function crear()
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $datos = $this->request->getJSON(true);

            $usuario_permiso_esp = usuario_permiso_especial()->crear($datos);

            $db->transComplete();
            return $this->response->setJSON([
                'success' => true,
                'data' => $usuario_permiso_esp
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }

    public function actualizar()
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $datos = $this->request->getJSON(true);

            $usuario_permiso_esp = usuario_permiso_especial()->editar($datos);

            $db->transComplete();
            return $this->response->setJSON([
                'success' => true,
                'data' => $usuario_permiso_esp
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }

    public function eliminar(int $id)
    {
        try {
            $usuario_permiso_esp = usuario_permiso_especial()->eliminar($id);
            return $this->response->setJSON([
                'success' => true,
                'message' => $usuario_permiso_esp,
                'data' => $id
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }

    public function obtenerpermisoEspecialPorUsuario(int $id_usuario)
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $usuario_permiso_esp = usuario_permiso_especial()->obtenerpermisoEspecialPorUsuario($id_usuario);
            $db->transComplete();
            return $this->response->setJSON([
                'success' => true,
                'data' => $usuario_permiso_esp
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }
}
