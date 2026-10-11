<?php

namespace Belly\Usuarios\Controllers\Api;

use App\Controllers\BaseController;

class GrupoUsuarioPermisoUsuarioController extends BaseController
{
    public function listar()
    {
        $grupo_usuario_permi_usuario = grupo_usuario_permiso_usuario()->listar();

        return $this->response->setJSON([
            'success' => true,
            'data' => $grupo_usuario_permi_usuario
        ]);
    }
    public function crear()
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $datos = $this->request->getJSON(true);

            $grupo_usuario_permi_usuario = grupo_usuario_permiso_usuario()->crear($datos);

            $db->transComplete();
            return $this->response->setJSON([
                'success' => true,
                'data' => $grupo_usuario_permi_usuario
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }

    public function ediatr(int $id)
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $datos = $this->request->getJSON(true);

            $grupo_usuario_permi_usuario = grupo_usuario_permiso_usuario()->editar($datos, $id);

            $db->transComplete();
            return $this->response->setJSON([
                'success' => true,
                'data' => $grupo_usuario_permi_usuario
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }

    public function eliminar(int $id_grupo_usuario, int $id_usuario, int $id_permiso)
    {
        if (empty($id_grupo_usuario) || empty($id_usuario) || empty($id_permiso)) {
            return $this->response->setJSON(['status' => 400, 'error' => true, 'messages' => 'Grupo de usuario, usuario y permiso son requeridos.'], 400);
        }

        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $grupo_usuario_permi_usuario = grupo_usuario_permiso_usuario()->eliminar($id_grupo_usuario, $id_usuario, $id_permiso);
            $db->transComplete();

            return $this->response->setJSON([
                'success' => true,
                'data' => $grupo_usuario_permi_usuario
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }




    public function obtenerGrupoUsuarioPermisoUsuario($grupoUsuarioId,$usuarioId){
        if (empty($grupoUsuarioId) || empty($usuarioId)) {
            return $this->response->setJSON(['status' => 400, 'error' => true, 'messages' => 'Grupo de usuario y usuario son requeridos.'], 400);
        }

        $permisos = grupo_usuario_permiso_usuario()->obtenerGrupoUsuarioPermisoUsuario($grupoUsuarioId, $usuarioId);

        return $this->response->setJSON([
            'success' => true,
            'data' => $permisos
        ]);
    }

    public function actualizarPorGrupoUsuarioIDUsuarioID(){
        $db = \Config\Database::connect();
        $db->transStart();
        try{
            $datos = $this->request->getJSON(true);

            if(empty($datos['id_grupo_usuario']) || empty($datos['id_usuario'])) {
                throw new \Exception('ID de grupo de usuario, usuario y permisos no proporcionados.');
            }

            $result = grupo_usuario_permiso_usuario()->actualizarPorGrupoUsuarioIDUsuarioID($datos);
            $db->transComplete();

            return $this->response->setJSON([
                'success' => true,
                'data' => $result
            ]);
        }catch(\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }
}