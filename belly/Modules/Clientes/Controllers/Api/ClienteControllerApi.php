<?php

namespace Belly\Clientes\Controllers\Api;

use App\Controllers\BaseController;
use Belly\Services\Autorizacion;

class ClienteControllerApi extends BaseController{
    
    public function crear() {
        try {
            $data = $this->request->getJSON(true);
            $cliente = clientes()->crearCliente($data);
            return $this->response->setJSON([
                        'success' => true,
                        'data' => $cliente
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                        'success' => false,
                        'error' => $e->getMessage()
                    ])->setStatusCode(400);
        }
    }

    public function listar(){
        $page = $this->request-> getGet('page') ?? 1;
        $perPage = $this->request->getGet('perPage') ?? 10;
        $search = $this->request->getGet('search') ?? null;
        $search = ($search && strlen($search) > 2) ? $search : null;

        $clientes = Autorizacion::esAdministradorEmpresa()
            ? clientes()->listarClientes($page, $perPage, $search)
            : clientes()->listarMisClientes($page, $perPage, $search);

        return $this->response->setJSON([
            'success' => true,
            'data' => $clientes['data'] ?? [],
            'pagination' => $clientes['pagination'] ?? null,
        ]);
    }
    public function listarMisClientes(){
        $page = $this->request-> getGet('page') ?? 1;
        $perPage = $this->request->getGet('perPage') ?? 10;
        $search = $this->request->getGet('search') ?? null;
        $search = ($search && strlen($search) > 2) ? $search : null;

        $clientes = clientes()->listarMisClientes($page, $perPage, $search);

        return $this->response->setJSON([
            'success' => true,
            'data' => $clientes['data'] ?? [],
            'pagination' => $clientes['pagination'] ?? null,
        ]);
    }

    public function ver(int $id)
    {
        try {
            $cliente = clientes()->getById($id);
            return $this->response->setJSON([
                'success' => true,
                'data' => $cliente
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
        try {
            $data = $this->request->getJSON(true);
            $cliente = clientes()->actualizarCliente($id, $data);
            return $this->response->setJSON([
                'success' => true,
                'data' => $cliente
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ])->setStatusCode(400);
        }
    }

    public function crearManualAsesor()
    {
        try {
            $data = $this->request->getJSON(true);
            $resultado = clientes()->crearClienteManualAsesor($data);
            
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

    public function buscarPorTelefono()
    {
        try {
            $telefono = $this->request->getGet('telefono');

            if (empty($telefono)) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'El parámetro telefono es requerido',
                ])->setStatusCode(400);
            }

            $resultado = clientes()->buscarClientePorTelefono((string) $telefono);

            return $this->response->setJSON([
                'success' => true,
                'data' => $resultado,
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage(),
            ])->setStatusCode(400);
        }
    }


    public function eliminar(int $id)
    {
        try {
            $resultado = clientes()->eliminarCliente($id);
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
            
            $resultado = clientes()->eliminarMultiplesClientes($data['ids']);
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

}
