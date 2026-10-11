<?php

namespace Belly\Clientes\Controllers\Api;

use App\Controllers\BaseController;

class ListaNegraControllerApi extends BaseController{
    
    public function crear()
    {
        try {
            $data = $this->request->getJSON(true);
            $resultado = lista_negra()->crear($data);
            return $this->response->setJSON([
                'success' => true,
                'data' => $resultado
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ])->setStatusCode(400);
        }
    }

    public function listar()
    {
        $page = $this->request->getGet('page') ?? 1;
        $perPage = $this->request->getGet('perPage') ?? 10;
        $search = $this->request->getGet('search') ?? null;
        $search = ($search && strlen($search) > 2) ? $search : null;

        $resultado = lista_negra()->listar($page, $perPage, $search);

        return $this->response->setJSON([
            'success' => true,
            'data' => $resultado['data'] ?? [],
            'pagination' => $resultado['pagination'] ?? null,
        ]);
    }

    public function ver(int $id)
    {
        try {
            $registro = lista_negra()->getById($id);
            return $this->response->setJSON([
                'success' => true,
                'data' => $registro
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ])->setStatusCode(404);
        }
    }

    public function actualizar(int $id)
    {
        try {
            $data = $this->request->getJSON(true);
            $resultado = lista_negra()->actualizar($id, $data);
            return $this->response->setJSON([
                'success' => true,
                'data' => $resultado
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ])->setStatusCode(400);
        }
    }

    public function eliminar(int $id)
    {
        try {
            $resultado = lista_negra()->eliminar($id);
            return $this->response->setJSON([
                'success' => true,
                'data' => $resultado
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ])->setStatusCode(400);
        }
    }

    public function eliminarMultiples()
    {
        try {
            $data = $this->request->getJSON(true);
            
            if (!isset($data['ids']) || !is_array($data['ids'])) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'Debe proporcionar un array de ids'
                ])->setStatusCode(400);
            }
            
            $resultado = lista_negra()->eliminarMultiples($data['ids']);
            return $this->response->setJSON([
                'success' => true,
                'data' => $resultado
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ])->setStatusCode(400);
        }
    }

    public function verificar()
    {
        try {
            $telefono = $this->request->getGet('telefono');
            
            if (empty($telefono)) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'El parámetro telefono es requerido'
                ])->setStatusCode(400);
            }
            
            $esta_bloqueado = lista_negra()->verificarTelefono($telefono);
            
            return $this->response->setJSON([
                'success' => true,
                'bloqueado' => $esta_bloqueado,
                'telefono' => $telefono
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ])->setStatusCode(400);
        }
    }
}
  