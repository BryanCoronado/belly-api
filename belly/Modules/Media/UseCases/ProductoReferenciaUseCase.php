<?php
namespace Belly\Media\UseCases;

use Belly\Media\Config\MediaConfig;
use Belly\Media\Models\ProductoReferenciaModel;
use Belly\Media\Entities\ProductoReferenciaEntity;

class ProductoReferenciaUseCase {
    protected MediaConfig $config;
    protected ?ProductoReferenciaModel $producto_referencia_provider = NULL;

    public function __construct(MediaConfig $config)
    {
        $this->config = $config;
    }

    public function getProductoReferenciaProvider(): ProductoReferenciaModel
    {
        if ($this->producto_referencia_provider !== null) {
            return $this->producto_referencia_provider;
        }

        $class_name = $this->config->producto_referencia_provider;
        $this->producto_referencia_provider = new $class_name();

        return $this->producto_referencia_provider;
    }

    public function getById(int $id)
    {
        $producto_referencia_model = $this->getProductoReferenciaProvider();
        $producto_referencia = $producto_referencia_model->find($id);

        if (isset($producto_referencia) && !empty($producto_referencia)) {
            return $producto_referencia;
        }

        return null;
    }
   
    public function crearProductoReferencia(array $datos)
    {
        $producto_referencia_model = $this->getProductoReferenciaProvider();

        $producto_referencia_entity = $this->llenarProductoReferenciaEntity(new ProductoReferenciaEntity(), $datos);

        if ($producto_referencia_model->save($producto_referencia_entity)) {
            $producto_referencia_entity->id = $producto_referencia_model->insertID;

            return [
                'id' => $producto_referencia_entity->id,
                'filename' => $producto_referencia_entity->filename,
                'mensaje' => 'Producto referencia creado correctamente.'
            ];
        } else {
            throw new \Exception(json_encode($producto_referencia_model->errors()));
        }
    }

    public function editarProductoReferencia(array $datos, int $id)
    {
        $producto_referencia_model = $this->getProductoReferenciaProvider();
        $producto_referencia = $producto_referencia_model->find($id);

        if (!$producto_referencia) {
            throw new \Exception('Producto referencia no encontrado');
        }

        $producto_referencia_entity = $this->llenarProductoReferenciaEntity($producto_referencia, $datos);
        $producto_referencia_entity->id = $id;

        if (!$producto_referencia_model->save($producto_referencia_entity)) {
            throw new \Exception(json_encode($producto_referencia_model->errors()));
        }

        return [
            'id' => $producto_referencia_entity->id,
            'filename' => $producto_referencia_entity->filename,
            'mensaje' => 'Producto referencia editado correctamente.'
        ];
    }

    public function listarProductoReferencias($page = 1, $perPage = 10, $search = null)
    {
        $producto_referencia_model = $this->getProductoReferenciaProvider();

        if (!empty($search)) {
            $producto_referencia_model->groupStart()
                ->like('filename', $search)
                ->orLike('original_name', $search)
                ->orLike('mime_type', $search)
                ->orLike('url', $search)
            ->groupEnd();
        }

        if ($perPage === 'all') {
            $producto_referencias = $producto_referencia_model->findAll();
            $total = count($producto_referencias);

            return [
                'data' => $producto_referencias,
                'pagination' => [
                    'total' => $total,
                    'currentPage' => 1,
                    'from' => $total > 0 ? 1 : 0,
                    'to' => $total,
                    'hasNext' => false,
                    'hasPrev' => false,
                ]
            ];
        }

        $result = $producto_referencia_model->paginate($perPage, 'default', $page);
        $pager = $producto_referencia_model->pager;
        $total = $pager->getTotal('default');
        $currentPage = $pager->getCurrentPage('default');
        $perPageResult = $pager->getPerPage('default');
        $lastPage = $pager->getPageCount('default');
        $from = $total > 0 ? (($currentPage - 1) * $perPageResult) + 1 : 0;
        $to = $from + count($result) - 1;

        return [
            'data' => $result,
            'pagination' => [
                'total' => $total,
                'currentPage' => $currentPage,
                'from' => $from,
                'to' => $to,
                'hasNext' => $currentPage < $lastPage,
                'hasPrev' => $currentPage > 1,
            ],
        ];
    }

    public function listarPorProducto(int $id_producto)
    {
        $producto_referencia_model = $this->getProductoReferenciaProvider();

        return $producto_referencia_model
            ->where('id_producto', $id_producto)
            ->findAll();
    }

    public function eliminarProductoReferencia(int $id)
    {
        $producto_referencia_model = $this->getProductoReferenciaProvider();
        $producto_referencia = $producto_referencia_model->find($id);

        if (!$producto_referencia) {
            throw new \Exception('Producto referencia no encontrado');
        }

        $eliminado = $producto_referencia_model->delete($id);

        if (!$eliminado) {
            throw new \Exception('No se pudo eliminar el producto referencia');
        }

        return [
            'id' => $id,
            'message' => 'Producto referencia eliminado correctamente'
        ];
    }

    private function llenarProductoReferenciaEntity(ProductoReferenciaEntity $producto_referencia_entity, array $datos): ProductoReferenciaEntity
    {
        $campos = [
            'id_producto',
            'activo',
            'filename',
            'path',
            'url',
            'original_name',
            'mime_type',
            'size',
        ];

        foreach ($campos as $campo) {
            if (array_key_exists($campo, $datos)) {
                $producto_referencia_entity->{$campo} = $datos[$campo];
            }
        }

        return $producto_referencia_entity;
    }

}
