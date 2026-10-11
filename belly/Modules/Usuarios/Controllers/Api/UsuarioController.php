<?php

namespace Belly\Usuarios\Controllers\Api;

use App\Controllers\BaseController;

class UsuarioController extends BaseController {



    public function crear()
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $datos = $this->request->getJSON(true);

            $usuario = usuario()->crearUsuario($datos);

            $db->transComplete();
            return $this->response->setJSON([
                'success' => true,
                'data' => $usuario
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }

    public function ver(int $id = 0) {
        $usuario = usuario()->getById($id);
        if (!$usuario) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Usuario no encontrado',
                'data' => null
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $usuario
        ]);
    }


    public function listar()
    {
        $page = (int) ($this->request->getGet('page') ?? 1);
        $perPage = $this->request->getGet('perPage') ?? 10;
        $search = $this->request->getGet('search') ?? null;
        $search = ($search && strlen($search) > 2) ? $search : null;

        $usuarios = usuario()->listar_usuarios($page, $perPage, $search);
        return $this->response->setJSON([
            'success' => true,
            'data' => $usuarios['data'] ?? [],
            'pagination' => $usuarios['pagination'] ?? null,
        ]);
    }

  

    public function editar(int $id)
    {
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $datos = $this->request->getJSON(true);

            $usuario = usuario()->editarUsuario($datos, $id);

            $db->transComplete();
            return $this->response->setJSON([
                'success' => true,
                'data' => $usuario
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
        try {
            $datos = $this->request->getJSON(true);
            $usuario = usuario()->cambiarEstadoUsuario($datos,$id);
            return $this->response->setJSON([
                'success' => true,
                'message' => $usuario,
                'data' => $id
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }

    /**
     * Endpoint para actualizar `id_estado_usuario` del usuario.
     */
    public function actualizarEstadoUsuario(int $id)
    {
        try {
            $datos = $this->request->getJSON(true);
            $usuario = usuario()->cambiarIdEstadoUsuario($datos, $id);
            return $this->response->setJSON([
                'success' => true,
                'message' => $usuario,
                'data' => $id
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
            $usuario = usuario()->eliminar_usuario($id); 
            return $this->response->setJSON([
                'success' => true,
                'message' => $usuario,
                'data' => $id
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }

    public function listarPorRol(int $id_grupo_usuario_rol)
    {
        try {
            $page = (int) ($this->request->getGet('page') ?? 1);
            $perPage = $this->request->getGet('perPage') ?? 10;
            $search = $this->request->getGet('search') ?? null;
            $search = ($search && strlen($search) > 2) ? $search : null;

            $usuarios = usuario()->listarUsuariosPorRol($id_grupo_usuario_rol, $page, $perPage, $search);
            return $this->response->setJSON([
                'success' => true,
                'data' => $usuarios['data'] ?? [],
                'pagination' => $usuarios['pagination'] ?? null
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function historialEstadosAsesores()
    {
        try {
            $fecha = $this->request->getGet('fecha') ?? date('Y-m-d');
            $respuesta = usuario()->obtenerHistorialEstadosAsesores($fecha);
            
            return $this->response->setJSON([
                'success' => true,
                'data' => $respuesta
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}


