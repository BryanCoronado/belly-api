<?php

namespace Belly\Usuarios\Controllers\Api;

use App\Controllers\BaseController;

class GrupoUsuarioUsuarioController extends BaseController
{
    public function listar()
    {
        $grupo_usuario_usuario = grupo_usuario_usuario()->listar();

        return $this->response->setJSON([
            'success' => true,
            'data' => $grupo_usuario_usuario
        ]);
    }

    public function listarPorGrupoUsuario(int $id_grupo_usuario)
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $grupo_usuario_usuario = grupo_usuario_usuario()->listarPorGrupoUsuario($id_grupo_usuario);
            $db->transComplete();
            return $this->response->setJSON([
                'success' => true,
                'data' => $grupo_usuario_usuario
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
        
    }

    public function crear()
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $datos = $this->request->getJSON(true);

            $grupo_usuario_usuario = grupo_usuario_usuario()->crear($datos);
            $permisos = grupo_usuario_permiso_rol()->encontrarPermisosPorGrupoUsuarioYGrupoUsuarioRol($datos['id_grupo_usuario'], $datos['id_grupo_usuario_rol']);
            $grupo_usuario_usuario->permisos = $permisos;
            $db->transComplete();
            return $this->response->setJSON([
                'success' => true,
                'data' => $grupo_usuario_usuario
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }

    
    public function obtenerPermisosPorGrupoUsuarioYGrupoUsuarioRol()
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $datos = $this->request->getJSON(true);

            $grupo_usuario_permiso_rol = grupo_usuario_permiso_rol()->obtenerPermisosPorGrupoUsuarioYGrupoUsuarioRol($datos['id_grupo_usuario'], $datos['id_grupo_usuario_rol']);
            $db->transComplete();
            return $this->response->setJSON([
                'success' => true,
                'data' => $grupo_usuario_permiso_rol
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }

    public function update(int $id)
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $datos = $this->request->getJSON(true);

            $grupo_usuario_usuario = grupo_usuario_usuario()->editar($datos, $id);

            $db->transComplete();
            return $this->response->setJSON([
                'success' => true,
                'data' => $grupo_usuario_usuario
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }

    public function delete(int $id)
    {
        try {
            $grupo_usuario_usuario = grupo_usuario_usuario()->eliminar($id);
            return $this->response->setJSON([
                'success' => true,
                'message' => $grupo_usuario_usuario,
                'data' => $id
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }

    public function listBySelectByUser()
    {
        $grupo_usuario_usuario = grupo_usuario_usuario()->listBySelectUser();
        if (!$grupo_usuario_usuario) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Grupo de usuario no encontrado',
                'data' => null
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $grupo_usuario_usuario
        ]);
    }

    public function listByOperaciones()
    {
        $grupo_usuario_usuario = grupo_usuario_usuario()->listByOperaciones();
        if (!$grupo_usuario_usuario) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Grupo de usuario no encontrado',
                'data' => null
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $grupo_usuario_usuario
        ]);
    }
}
