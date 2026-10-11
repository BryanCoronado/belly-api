<?php

namespace Belly\Usuarios\Controllers\Api;

use App\Controllers\BaseController;
// use CodeIgniter\API\ResponseTrait;

class GrupoUsuarioRolController extends BaseController
{
    // use ResponseTrait;

    public function listar() {
        $page = $this->request->getGet('page') ?? 1;
        $perPage = $this->request->getGet('perPage') ?? 10;
        $search = $this->request->getGet('search') ?? null;
        $search = ($search && strlen($search) > 2) ? $search : null;
        $grupo_usuario_rol = grupo_usuario_rol()->listar_usuarios_rol($page, $perPage, $search);

        return $this->response->setJSON([
            'success' => true,
            'data' => $grupo_usuario_rol['data'] ?? [],
            'pagination' => $grupo_usuario_rol['pagination'] ?? null,
        ]);
    }

    public function crear()
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $datos = $this->request->getJSON(true);

            $grupo_usuario_rol = grupo_usuario_rol()->crear($datos);

            $db->transComplete();
            return $this->response->setJSON([
                'success' => true,
                'data' => $grupo_usuario_rol
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }

    public function ver(int $id = 0)
    {
        $grupo_usuario_rol = grupo_usuario_rol()->obtenerRol($id);
        if (!$grupo_usuario_rol) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Rol no encontrado',
                'data' => null
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $grupo_usuario_rol
        ]);
    }

    public function editar(int $id)
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $datos = $this->request->getJSON(true);

            $grupo_usuario_rol = grupo_usuario_rol()->editar($datos, $id);

            $db->transComplete();
            return $this->response->setJSON([
                'success' => true,
                'data' => $grupo_usuario_rol
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
            $grupo_usuario_rol = grupo_usuario_rol()->eliminar($id); 
            return $this->response->setJSON([
                'success' => true,
                'message' => $grupo_usuario_rol,
                'data' => $id
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }
}