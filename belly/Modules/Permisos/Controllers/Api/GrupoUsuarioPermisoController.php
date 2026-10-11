<?php
namespace Belly\Permisos\Controllers\Api;
use App\Controllers\BaseController;

class GrupoUsuarioPermisoController extends BaseController
{
    public function obtenerGruposUsuariosPermisos(int $id)
    {
        $grupo_usuario_permiso = grupo_usuario_permiso()->obtenerGruposUsuariosPermisos($id);
        return $this->response->setJSON([
            'success' => true,
            'data' => $grupo_usuario_permiso
        ]);
    }

    public function actualizarGruposUsuariosPermisos()
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $datos = $this->request->getJSON(true);

            if (!$datos['grupo_usuario_id']) {
                throw new \Exception('ID de grupo de usuario no proporcionado.');
            }

            $grupo_usuario_permiso = grupo_usuario_permiso()->actualizarGruposUsuariosPermisos($datos);

            $db->transComplete();
            return $this->response->setJSON([
                'success' => true,
                'data' => $grupo_usuario_permiso
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }
}