<?php

namespace Belly\Permisos\Controllers\Api;

use App\Controllers\BaseController;

class GrupoPermisoController extends BaseController
{
    public function listar()
    {
        $grupo_permiso = grupo_permiso()->listar();

        return $this->response->setJSON([
            'success' => true,
            'data' => $grupo_permiso
        ]);
    }

    public function crear()
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $datos = $this->request->getJSON(true);

            $grupo_permiso = grupo_permiso()->crear($datos);

            $db->transComplete();
            return $this->response->setJSON([
                'success' => true,
                'data' => $grupo_permiso
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }
    
    public function ver(int $id)
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $grupo_permiso = grupo_permiso()->obtenerGrupo($id);

            $db->transComplete();
            return $this->response->setJSON([
                'success' => true,
                'data' => $grupo_permiso
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }

    public function actualizar(int $id)
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $datos = $this->request->getJSON(true);

            $grupo_permiso = grupo_permiso()->editar($datos, $id);

            $db->transComplete();
            return $this->response->setJSON([
                'success' => true,
                'data' => $grupo_permiso
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
            $grupo_permiso = grupo_permiso()->eliminar($id);
            return $this->response->setJSON([
                'success' => true,
                'message' => $grupo_permiso,
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
