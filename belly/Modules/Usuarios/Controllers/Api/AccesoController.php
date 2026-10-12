<?php

namespace Belly\Usuarios\Controllers\Api;

use App\Controllers\BaseController;

class AccesoController extends BaseController
{
    public function roles()
    {
        try {
            return $this->response->setJSON([
                'success' => true,
                'data' => acceso()->listarRoles(),
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'data' => $e->getMessage()]);
        }
    }

    public function verRol(int $id)
    {
        try {
            return $this->response->setJSON([
                'success' => true,
                'data' => acceso()->obtenerRol($id),
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'data' => $e->getMessage()]);
        }
    }

    public function crearRol()
    {
        try {
            $datos = $this->request->getJSON(true) ?? [];
            return $this->response->setJSON([
                'success' => true,
                'data' => acceso()->crearRol($datos),
            ], 201);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'data' => $e->getMessage()]);
        }
    }

    public function editarRol(int $id)
    {
        try {
            $datos = $this->request->getJSON(true) ?? [];
            return $this->response->setJSON([
                'success' => true,
                'data' => acceso()->editarRol($id, $datos),
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'data' => $e->getMessage()]);
        }
    }

    public function eliminarRol(int $id)
    {
        try {
            acceso()->eliminarRol($id);
            return $this->response->setJSON(['success' => true, 'data' => $id]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'data' => $e->getMessage()]);
        }
    }

    public function catalogo()
    {
        return $this->response->setJSON([
            'success' => true,
            'data' => acceso()->catalogo(),
        ]);
    }

    public function menu()
    {
        try {
            return $this->response->setJSON([
                'success' => true,
                'data' => acceso()->menu(),
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'data' => $e->getMessage()]);
        }
    }

    public function permisosRol(int $id)
    {
        try {
            return $this->response->setJSON([
                'success' => true,
                'data' => acceso()->permisosDeRol($id),
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'data' => $e->getMessage()]);
        }
    }

    public function guardarPermisosRol(int $id)
    {
        try {
            $datos = $this->request->getJSON(true) ?? [];
            $permisos = is_array($datos['permisos'] ?? null) ? $datos['permisos'] : [];
            return $this->response->setJSON([
                'success' => true,
                'data' => acceso()->guardarPermisosRol($id, $permisos),
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'data' => $e->getMessage()]);
        }
    }

    public function grupos()
    {
        try {
            return $this->response->setJSON([
                'success' => true,
                'data' => acceso()->listarGrupos(),
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'data' => $e->getMessage()]);
        }
    }

    public function verGrupo(int $id)
    {
        try {
            return $this->response->setJSON([
                'success' => true,
                'data' => acceso()->obtenerGrupo($id),
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'data' => $e->getMessage()]);
        }
    }

    public function crearGrupo()
    {
        try {
            $datos = $this->request->getJSON(true) ?? [];
            return $this->response->setJSON([
                'success' => true,
                'data' => acceso()->crearGrupo($datos),
            ], 201);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'data' => $e->getMessage()]);
        }
    }

    public function editarGrupo(int $id)
    {
        try {
            $datos = $this->request->getJSON(true) ?? [];
            return $this->response->setJSON([
                'success' => true,
                'data' => acceso()->editarGrupo($id, $datos),
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'data' => $e->getMessage()]);
        }
    }

    public function eliminarGrupo(int $id)
    {
        try {
            acceso()->eliminarGrupo($id);
            return $this->response->setJSON(['success' => true, 'data' => $id]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'data' => $e->getMessage()]);
        }
    }
}
