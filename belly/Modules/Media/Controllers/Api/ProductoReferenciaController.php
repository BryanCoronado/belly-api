<?php

namespace Belly\Media\Controllers\Api;

use App\Controllers\BaseController;

class ProductoReferenciaController extends BaseController{
    
    public function crear() {
        try {
            $data = $this->request->getJSON(true);
            $producto_referencia = producto_referencia()->crearProductoReferencia($data);
            return $this->response->setJSON([
                        'success' => true,
                        'data' => $producto_referencia
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

        $producto_referencias = producto_referencia()->listarProductoReferencias($page, $perPage, $search);

        return $this->response->setJSON([
            'success' => true,
            'data' => $producto_referencias['data'] ?? [],
            'pagination' => $producto_referencias['pagination'] ?? null,
        ]);
    }

    public function ver(int $id)
    {
        try {
            $producto_referencia = producto_referencia()->getById($id);

            if (!$producto_referencia) {
                return $this->response
                    ->setStatusCode(404)
                    ->setJSON([
                        'success' => false,
                        'message' => 'Producto referencia no encontrado',
                        'data' => null
                    ]);
            }

            return $this->response->setJSON([
                'success' => true,
                'data' => $producto_referencia
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $e->getMessage()
            ])->setStatusCode(404);
        }
    }

    public function listarPorProducto(int $id_producto)
    {
        try {
            $producto_referencias = producto_referencia()->listarPorProducto($id_producto);

            return $this->response->setJSON([
                'success' => true,
                'data' => $producto_referencias
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $e->getMessage()
            ])->setStatusCode(400);
        }
    }

    public function editar(int $id)
    {
        try {
            $data = $this->request->getJSON(true);
            $producto_referencia = producto_referencia()->editarProductoReferencia($data, $id);

            return $this->response->setJSON([
                'success' => true,
                'data' => $producto_referencia
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
            $resultado = producto_referencia()->eliminarProductoReferencia($id);

            return $this->response
                ->setStatusCode(200)
                ->setJSON([
                    'success' => true,
                    'data' => $resultado
                ]);
        } catch (\Exception $e) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
        }
    }

}
