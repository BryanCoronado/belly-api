<?php

namespace Belly\Permisos\Controllers\Api;

use App\Controllers\BaseController;

class PermisoController extends BaseController
{
    public function listar()
    {
        $page = $this->request->getGet('page') ?? 1;
        $perPage = $this->request->getGet('perPage') ?? 10;
        $search = $this->request->getGet('search') ?? null;
        $search = ($search && strlen($search) > 2) ? $search : null;

        $permisos = permiso()->listar($page, $perPage, $search);

        return $this->response->setJSON([
            'success' => true,
            'data' => $permisos['data'] ?? [],
            'pagination' => $permisos['pagination'] ?? null,
        ]);
    }

    public function crear()
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $datos = $this->request->getJSON(true);

            $permiso = permiso()->crear($datos);

            $db->transComplete();
            return $this->response->setJSON([
                'success' => true,
                'data' => $permiso
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
            $permiso = permiso()->obtenerPermiso($id);

            $db->transComplete();
            return $this->response->setJSON([
                'success' => true,
                'data' => $permiso
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

            $permiso = permiso()->editar($datos, $id);

            $db->transComplete();
            return $this->response->setJSON([
                'success' => true,
                'data' => $permiso
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }

    public function actualizarEstado(int $id)
    {
        error_log("🚀 LLEGÓ AL CONTROLADOR - ID: " . $id);
        
        try {
            $datos = $this->request->getJSON(true);
            error_log("📦 Datos recibidos en controlador: " . json_encode($datos));
            
            $permiso = permiso()->cambiarEstadoPermiso($datos, $id);
            return $this->response->setJSON([
                'success' => true,
                'message' => $permiso,
                'data' => $id
            ]);
        } catch (\Exception $e) {
            error_log("❌ Error en controlador: " . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }

    public function eliminar(int $id)
    {
        try {
            $permiso = permiso()->eliminar($id);
            return $this->response->setJSON([
                'success' => true,
                'message' => $permiso,
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
