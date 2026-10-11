<?php

namespace Belly\Clientes\Controllers\Api;

use App\Controllers\BaseController;

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

        $clientes = clientes()->listarClientes($page, $perPage, $search);

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

    public function previewImportacion()
    {
        try {
            $file = $this->request->getFile('archivo_leads');
            
            if (!$file || !$file->isValid()) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'No se recibió un archivo válido'
                ])->setStatusCode(400);
            }
            
            // Validar extensión
            $extension = $file->getExtension();
            if ($extension !== 'xlsx') {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'Solo se permiten archivos .xlsx'
                ])->setStatusCode(400);
            }
            
            // Validar tamaño (5MB)
            $maxSize = 5 * 1024 * 1024; // 5MB en bytes
            if ($file->getSize() > $maxSize) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'El archivo excede el tamaño máximo de 5MB'
                ])->setStatusCode(400);
            }
            
            $resultado = clientes()->previewImportacionLeads($file);
            
            return $this->response->setJSON([
                'success' => true,
                'data' => $resultado
            ]);
        } catch (\Exception $e) {
            log_message('error', 'ClienteControllerApi::previewImportacion - Error: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ])->setStatusCode(400);
        }
    }

    public function importar()
    {
        try {
            $file = $this->request->getFile('archivo_leads');
            
            if (!$file || !$file->isValid()) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'No se recibió un archivo válido'
                ])->setStatusCode(400);
            }
            
            // Validar extensión
            $extension = $file->getExtension();
            if ($extension !== 'xlsx') {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'Solo se permiten archivos .xlsx'
                ])->setStatusCode(400);
            }
            
            // Validar tamaño (5MB)
            $maxSize = 5 * 1024 * 1024;
            if ($file->getSize() > $maxSize) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'El archivo excede el tamaño máximo de 5MB'
                ])->setStatusCode(400);
            }
            
            // Obtener parámetros
            $id_app = $this->request->getPost('id_app');
            if (empty($id_app)) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'La app es obligatoria'
                ])->setStatusCode(400);
            }
            
            $filas_seleccionadas_json = $this->request->getPost('filas_seleccionadas');
            $filas_seleccionadas = json_decode($filas_seleccionadas_json, true);
            if (empty($filas_seleccionadas) || !is_array($filas_seleccionadas)) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'Debe seleccionar al menos una fila para importar'
                ])->setStatusCode(400);
            }
            
            $id_usuario = $this->request->getPost('agente_id') ? (int) $this->request->getPost('agente_id') : null;
            $asignacion_aleatoria = $this->request->getPost('asignacion_aleatoria') === '1';
            
            $resultado = clientes()->importarLeadsDesdeExcel($file, (int) $id_app, $filas_seleccionadas, $id_usuario, $asignacion_aleatoria);
            
            return $this->response->setJSON([
                'success' => true,
                'total' => $resultado['total_importados'],
                'data' => $resultado['importados'],
                'warnings' => $resultado['warnings']
            ]);
        } catch (\Exception $e) {
            log_message('error', 'ClienteControllerApi::importar - Error: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
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
