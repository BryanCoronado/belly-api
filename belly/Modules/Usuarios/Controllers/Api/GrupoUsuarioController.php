<?php

namespace Belly\Usuarios\Controllers\Api;

use App\Controllers\BaseController;

class GrupoUsuarioController extends BaseController
{
    // use ResponseTrait;

    public function listar()
    {
        $page = $this->request->getGet('page') ?? 1;
        $perPage = $this->request->getGet('perPage') ?? 10;
        $search = $this->request->getGet('search') ?? null;
        $search = ($search && strlen($search) > 2) ? $search : null;


        $grupo_usuario = grupo_usuario()->listar($page, $perPage, $search);
        $grupo_usuario['success'] = true;
        return $this->response->setJSON($grupo_usuario);
    }
    public function ver(int $id = 0)
    {
        $grupo_usuario = grupo_usuario()->getById($id);
        if (!$grupo_usuario) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Grupo de usuario no encontrado',
                'data' => null
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $grupo_usuario
        ]);
    }
    public function crear()
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $datos = $this->request->getJSON(true);

            $grupo_usuario = grupo_usuario()->crear($datos);

            $db->transComplete();
            return $this->response->setJSON([
                'success' => true,
                'data' => $grupo_usuario
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }
    public function editar(int $id)
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $datos = $this->request->getJSON(true);

            $grupo_usuario = grupo_usuario()->editar($datos, $id);

            $db->transComplete();
            return $this->response->setJSON([
                'success' => true,
                'data' => $grupo_usuario
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
            $grupo_usuario = grupo_usuario()->eliminar($id);
            return $this->response->setJSON([
                'success' => true,
                'message' => $grupo_usuario,
                'data' => $id
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }

    public function listBySelect()
    {
        $grupo_usuario = grupo_usuario()->listBySelect();
        if (!$grupo_usuario) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Grupo de usuario no encontrado',
                'data' => null
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $grupo_usuario
        ]);
    }
}
