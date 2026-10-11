<?php

namespace Belly\Configuracion\Controllers\Api;

use App\Controllers\BaseController;

class ConfiguracionControllerApi extends BaseController
{

    private function jsonResponse(bool $success, $data = null, ?string $message = null, ?string $error = null, int $statusCode = 200)
    {
        $response = ['success' => $success];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        if ($message !== null) {
            $response['message'] = $message;
        }
        if ($error !== null) {
            $response['error'] = $error;
        }
        
        return $this->response->setJSON($response)->setStatusCode($statusCode);
    }

    public function crearApp()
    {
        try {
            $datos = $this->request->getJSON(true);
            
            if (empty($datos)) {
                return $this->jsonResponse(false, null, null, 'No se recibieron datos', 400);
            }
            
            $app_creada = app()->crearApp($datos);
            
            return $this->jsonResponse(true, $app_creada, 'App creada correctamente');
        } catch (\Exception $e) {
            log_message('error', 'Error al crear app: ' . $e->getMessage());
            return $this->jsonResponse(false, null, null, $e->getMessage(), 400);
        }
    }
    
    public function listarAppsDisponibles()
    {
        try {
            $apps = app()->obtenerAppsDisponibles();
            return $this->jsonResponse(true, $apps);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, null, null, $e->getMessage(), 500);
        }
    }

    public function listarApps()
    {
        try {
            $apps = app()->listarApps();
            return $this->jsonResponse(true, $apps);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, null, null, $e->getMessage(), 500);
        }
    }

    public function listarMisApps()
    {
        try {
            $apps = app()->listarMisApps();
            return $this->jsonResponse(true, $apps);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, null, null, $e->getMessage(), 500);
        }
    }

    public function listarPortafoliosConApps()
    {
        try {
            $portafolios = app()->listarPortafoliosConApps();
            return $this->jsonResponse(true, $portafolios);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, null, null, $e->getMessage(), 500);
        }
    }

    public function listarUsuariosPorApp(int $id_app)
    {
        try {
            $usuarios = app()->listarUsuariosPorApp($id_app);
            return $this->jsonResponse(true, $usuarios);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, null, null, $e->getMessage(), 500);
        }
    }


    public function asignarUsuarioAApp(int $id_app)
    {
        try {
            $datos = $this->request->getJSON(true);
            
            if (empty($datos) || !isset($datos['id_usuario'])) {
                return $this->jsonResponse(false, null, null, 'El id_usuario es requerido', 400);
            }
            
            $id_usuario = (int) $datos['id_usuario'];
            
            if ($id_usuario <= 0) {
                return $this->jsonResponse(false, null, null, 'El id_usuario debe ser un número válido', 400);
            }
            
            $resultado = app()->asignarUsuarioAApp($id_app, $id_usuario);
            
            return $this->jsonResponse(true, $resultado, 'Usuario asignado correctamente');
        } catch (\Exception $e) {
            log_message('error', "Error al asignar usuario a app: {$id_app} - {$e->getMessage()}");
            return $this->jsonResponse(false, null, null, $e->getMessage(), 400);
        }
    }

    public function desasignarUsuarioDeApp(int $id_app, int $id_usuario)
    {
        try {
            $resultado = app()->desasignarUsuarioDeApp($id_app, $id_usuario);
            return $this->jsonResponse(true, $resultado, 'Usuario desasignado correctamente');
        } catch (\Exception $e) {
            log_message('error', "Error al desasignar usuario de app: {$id_app}/{$id_usuario} - {$e->getMessage()}");
            return $this->jsonResponse(false, null, null, $e->getMessage(), 400);
        }
    }
}
