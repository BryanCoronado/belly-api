<?php
namespace Belly\Usuarios\Controllers\Api;
use App\Controllers\BaseController;

class GrupoUsuarioPermisoRolController extends BaseController
{
    public function obtenerPermisosPorGrupoUsuarioYGrupoUsuarioRol($grupoUsuarioId = null, $grupoUsuarioRolId = null)
    {
        if (empty($grupoUsuarioId) || empty($grupoUsuarioRolId)) {
            return $this->response->setJson(['status' => 400, 'error' => true, 'messages' => 'Grupo de usuario y rol son requeridos.'], 400);
        }

        $permisos = grupo_usuario_permiso_rol()->obtenerPermisosPorGrupoUsuarioYGrupoUsuarioRol($grupoUsuarioId, $grupoUsuarioRolId);

        return $this->response->setJson([
            'success' => true,
            'data' => $permisos
        ]);
    }

    public function actualizarPermisosPorGrupoUsuarioYGrupoUsuarioRol()
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $datos = $this->request->getJSON(true);

            if (empty($datos['id_grupo_usuario']) || empty($datos['id_grupo_usuario_rol']) || empty($datos['id_permiso'])) {
                throw new \Exception('ID de grupo de usuario, rol y permiso no proporcionados.');
            }

            $permisos = grupo_usuario_permiso_rol()->actualizarPermisosPorGrupoUsuarioYGrupoUsuarioRol($datos);

            $db->transComplete();
            return $this->response->setJson([
                'success' => true,
                'data' => $permisos
            ]);
        } catch (\Exception $e) {
            return $this->response->setJson([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    } 

    public function eliminar(int $id_grupo_usuario, int $id_grupo_usuario_rol, int $id_permiso)
    {
        if (empty($id_grupo_usuario) || empty($id_grupo_usuario_rol) || empty($id_permiso)) {
            return $this->response->setJSON(['status' => 400, 'error' => true, 'messages' => 'Grupo de usuario, rol y permiso son requeridos.'], 400);
        }

        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $grupo_usuario_permiso_rol = grupo_usuario_permiso_rol()->eliminar($id_grupo_usuario, $id_grupo_usuario_rol, $id_permiso);
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
    
}