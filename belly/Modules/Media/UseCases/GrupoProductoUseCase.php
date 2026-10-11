<?php
namespace Belly\Media\UseCases;

use Belly\Media\Config\MediaConfig;
use Belly\Media\Models\GrupoProductoModel;
use Belly\Media\Models\ProductoModel;
use Belly\Media\Models\ProductoPrecioModel;
use Belly\Media\Entities\GrupoProductoEntity;
use Belly\Media\Entities\ProductoPrecioEntity;

class GrupoProductoUseCase {
    protected MediaConfig $config;
    protected ?GrupoProductoModel $grupo_producto_provider = NULL;
    protected ?ProductoModel $producto_provider = NULL;
    protected ?ProductoPrecioModel $producto_precio_provider = NULL;

    public function __construct(MediaConfig $config)
    {
        $this->config = $config;
    }

    public function getGrupoProductoProvider(): GrupoProductoModel
    {
        if ($this->grupo_producto_provider !== null) {
            return $this->grupo_producto_provider;
        }

        $class_name = $this->config->grupo_producto_provider;
        $this->grupo_producto_provider = new $class_name();

        return $this->grupo_producto_provider;
    }

    public function getProductoProvider(): ProductoModel
    {
        if ($this->producto_provider !== null) {
            return $this->producto_provider;
        }

        $class_name = $this->config->producto_provider;
        $this->producto_provider = new $class_name();

        return $this->producto_provider;
    }

    public function getProductoPrecioProvider(): ProductoPrecioModel
    {
        if ($this->producto_precio_provider !== null) {
            return $this->producto_precio_provider;
        }

        $this->producto_precio_provider = new ProductoPrecioModel();

        return $this->producto_precio_provider;
    }


    public function getById(int $id)
    {
        $grupo_producto_model = $this->getGrupoProductoProvider();
        $grupo_producto = $grupo_producto_model->find($id);
        if (! isset($grupo_producto) || empty($grupo_producto)) {
            return null;
        }

        return $this->formatearGrupoConRelaciones($grupo_producto);
    }
   
    public function crearGrupoProducto(array $datos)
    {
        $datosValidados = $this->validarDatosGrupo($datos, true);

        $grupo_producto_model = $this->getGrupoProductoProvider();

        $grupo_producto_existente = $grupo_producto_model->where('nombre', $datosValidados['nombre'])->first();
        if ($grupo_producto_existente) {
            return [
                'id' => $grupo_producto_existente->id,
                'nombre' => $grupo_producto_existente->nombre,
                'mensaje' => 'El grupo producto ya existe.',
            ];
        }

        $grupo_producto_entity = $this->llenarGrupoProductoEntity(new GrupoProductoEntity(), $datosValidados);

        if (! $grupo_producto_model->save($grupo_producto_entity)) {
            throw new \Exception(json_encode($grupo_producto_model->errors()));
        }

        $idGrupo = (int) $grupo_producto_model->insertID;

        if (! empty($datos['precios']) && is_array($datos['precios'])) {
            $this->sincronizarPreciosGrupo($idGrupo, $datos['precios']);
        }

        $detalle = $this->getById($idGrupo);

        return array_merge($detalle ?? [], [
            'mensaje' => 'Grupo producto creado correctamente.',
        ]);
    }

    /**
     * Crea grupo + imagen de portada web (uploads/grupo_producto/…).
     *
     * @param array<string, mixed> $datosPost
     * @return array<string, mixed>
     */
    public function crearGrupoProductoConImagen($file, array $datosPost): array
    {
        $media = $this->guardarImagenPortadaGrupo($file);
        $datos = array_merge($datosPost, $media);

        try {
            return $this->crearGrupoProducto($datos);
        } catch (\Throwable $e) {
            $this->eliminarArchivoPortadaSiExiste($media['path'] ?? null);
            throw $e;
        }
    }

    /**
     * Edita grupo; si hay archivo, reemplaza portada.
     *
     * @param array<string, mixed> $datosPost
     * @return array<string, mixed>
     */
    public function editarGrupoProductoConImagen(int $id, $file, array $datosPost): array
    {
        $grupo_producto_model = $this->getGrupoProductoProvider();
        $actual = $grupo_producto_model->find($id);
        if (! $actual) {
            throw new \Exception('Grupo producto no encontrado');
        }

        $pathAnterior = $actual->path ?? null;
        $media = null;

        if ($file !== null && $file->isValid() && (int) $file->getError() === UPLOAD_ERR_OK) {
            $media = $this->guardarImagenPortadaGrupo($file);
            $datosPost = array_merge($datosPost, $media);
        }

        try {
            $resultado = $this->editarGrupoProducto($datosPost, $id);
        } catch (\Throwable $e) {
            if ($media !== null) {
                $this->eliminarArchivoPortadaSiExiste($media['path'] ?? null);
            }
            throw $e;
        }

        if ($media !== null && $pathAnterior && $pathAnterior !== ($media['path'] ?? null)) {
            $this->eliminarArchivoPortadaSiExiste((string) $pathAnterior);
        }

        return $resultado;
    }

    /**
     * @return array{filename:string,path:string,url:string,original_name:string,mime_type:string,size:int}
     */
    private function guardarImagenPortadaGrupo($file): array
    {
        if (! $file || ! $file->isValid()) {
            throw new \Exception('Archivo no válido');
        }
        $mimeType = (string) $file->getMimeType();
        if (! str_starts_with($mimeType, 'image/')) {
            throw new \Exception('La portada web debe ser una imagen (JPG, PNG, WEBP…)');
        }

        $extension = $file->getClientExtension() ?: match ($mimeType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };
        $nombreUnico = uniqid('grp_', true) . '.' . $extension;
        $relativePath = 'grupo_producto/' . date('Y-m-d') . '/' . $nombreUnico;
        $rutaCompleta = WRITEPATH . 'uploads/' . $relativePath;
        $directorioDestino = dirname($rutaCompleta);

        if (! is_dir($directorioDestino)) {
            mkdir($directorioDestino, 0777, true);
        }

        if (! $file->move($directorioDestino, $nombreUnico)) {
            throw new \Exception('Error al guardar archivo: ' . implode(', ', $file->getErrors()));
        }

        return [
            'filename' => $nombreUnico,
            'path' => $relativePath,
            'url' => $this->construirUrlPublicaGrupo($relativePath),
            'original_name' => (string) $file->getClientName(),
            'mime_type' => $mimeType,
            'size' => (int) $file->getSize(),
        ];
    }

    private function construirUrlPublicaGrupo(string $path): string
    {
        $path = ltrim(preg_replace('#^uploads/#', '', ltrim($path, '/')), '/');
        $baseUploadsUrl = trim((string) env('MEDIA_UPLOADS_BASE_URL', ''));
        if ($baseUploadsUrl !== '') {
            return rtrim($baseUploadsUrl, '/') . '/uploads/' . $path;
        }

        return rtrim((string) config('App')->baseURL, '/') . '/uploads/' . $path;
    }

    private function eliminarArchivoPortadaSiExiste(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }
        $full = WRITEPATH . 'uploads/' . ltrim($path, '/');
        if (is_file($full)) {
            @unlink($full);
        }
    }

    public function editarGrupoProducto(array $datos, int $id)
    {
        $grupo_producto_model = $this->getGrupoProductoProvider();
        $grupo_producto = $grupo_producto_model->find($id);

        if (! $grupo_producto) {
            throw new \Exception('Grupo producto no encontrado');
        }

        $datosValidados = $this->validarDatosGrupo($datos, false);

        $grupo_producto_entity = $this->llenarGrupoProductoEntity($grupo_producto, $datosValidados);
        $grupo_producto_entity->id = $id;

        if (! $grupo_producto_model->save($grupo_producto_entity)) {
            throw new \Exception(json_encode($grupo_producto_model->errors()));
        }

        if (array_key_exists('precios', $datos) && is_array($datos['precios'])) {
            $this->sincronizarPreciosGrupo($id, $datos['precios']);
        }

        $detalle = $this->getById($id);

        return array_merge($detalle ?? [], [
            'mensaje' => 'Grupo producto editado correctamente.',
        ]);
    }

    public function listarGrupoProductos($page = 1, $perPage = 10, $search = null, $mostrarWeb = null)
    {
        $grupo_producto_model = $this->getGrupoProductoProvider();

        // Busqueda
        if (! empty($search)) {
            $grupo_producto_model->groupStart()
                ->like('nombre', $search)
            ->groupEnd();
        }

        if ($mostrarWeb !== null) {
            $grupo_producto_model->where('mostrar_web', (int) $mostrarWeb ? 1 : 0);
        }

        $grupo_producto_model->orderBy('prioridad', 'ASC')->orderBy('nombre', 'ASC');

        // Obtener todos
        if ($perPage === 'all') {
            $grupo_productos = $grupo_producto_model->findAll();
            $data = array_map([$this, 'formatearGrupoLista'], $grupo_productos);
            $total = count($data);

            return [
                'data' => $data,
                'pagination' => [
                    'total' => $total,
                    'currentPage' => 1,
                    'from' => $total > 0 ? 1 : 0,
                    'to' => $total,
                    'hasNext' => false,
                    'hasPrev' => false,
                ],
            ];
        }

        // Paginacion
        $result = $grupo_producto_model->paginate($perPage, 'default', $page);
        $pager = $grupo_producto_model->pager;
        $total = $pager->getTotal('default');
        $currentPage = $pager->getCurrentPage('default');
        $perPageResult = $pager->getPerPage('default');
        $lastPage = $pager->getPageCount('default');
        $from = $total > 0 ? (($currentPage - 1) * $perPageResult) + 1 : 0;
        $to = $from + count($result) - 1;

        return [
            'data' => array_map([$this, 'formatearGrupoLista'], $result),
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

    /**
     * Lista ligera (sin productos/precios anidados).
     *
     * @return array<string, mixed>
     */
    private function formatearGrupoLista($grupo): array
    {
        return [
            'id' => (int) $grupo->id,
            'nombre' => $grupo->nombre,
            'descripcion' => $grupo->descripcion,
            'slug' => $grupo->slug ?? null,
            'descripcion_web' => $grupo->descripcion_web ?? null,
            'mostrar_web' => isset($grupo->mostrar_web) ? (int) $grupo->mostrar_web : 0,
            'seo_title' => $grupo->seo_title ?? null,
            'seo_description' => $grupo->seo_description ?? null,
            'mensaje_whatsapp' => $grupo->mensaje_whatsapp ?? null,
            'subcategorias' => $this->decodificarSinonimos($grupo->subcategorias ?? null),
            'prioridad' => isset($grupo->prioridad) ? (int) $grupo->prioridad : null,
            'activo' => (int) $grupo->activo,
            'color' => $grupo->color,
            'url' => ! empty($grupo->path)
                ? $this->construirUrlPublicaGrupo((string) $grupo->path)
                : ($grupo->url ?? null),
            'created_at' => $grupo->created_at ?? null,
            'updated_at' => $grupo->updated_at ?? null,
        ];
    }

   

    public function eliminarGrupoProducto(int $id)
    {
        $grupo_producto_model = $this->getGrupoProductoProvider();
    
        // Buscar registro
        $grupo_producto = $grupo_producto_model->find($id);
    
        if (!$grupo_producto) {
            throw new \Exception('Grupo producto no encontrado');
        }
    
        $eliminado = $grupo_producto_model->delete($id);
    
        if (!$eliminado) {
            throw new \Exception('No se pudo eliminar el grupo producto');
        }
    
        return [
            'id' => $id,
            'message' => 'Grupo producto eliminado correctamente'
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatearGrupoConRelaciones(GrupoProductoEntity $grupo): array
    {
        $productos = $this->obtenerProductosConPrecios((int) $grupo->id);
        $precios = [];

        foreach ($productos as $producto) {
            foreach ($producto['precios'] as $precio) {
                $precios[] = array_merge($precio, [
                    'id_producto' => $producto['id'],
                    'producto_nombre' => $producto['nombre'],
                ]);
            }
        }

        return [
            'id' => (int) $grupo->id,
            'nombre' => $grupo->nombre,
            'descripcion' => $grupo->descripcion,
            'slug' => $grupo->slug ?? null,
            'descripcion_web' => $grupo->descripcion_web ?? null,
            'mostrar_web' => isset($grupo->mostrar_web) ? (int) $grupo->mostrar_web : 0,
            'seo_title' => $grupo->seo_title ?? null,
            'seo_description' => $grupo->seo_description ?? null,
            'mensaje_whatsapp' => $grupo->mensaje_whatsapp ?? null,
            'sinonimos' => $this->decodificarSinonimos($grupo->sinonimos ?? null),
            'subcategorias' => $this->decodificarSinonimos($grupo->subcategorias ?? null),
            'prioridad' => isset($grupo->prioridad) ? (int) $grupo->prioridad : null,
            'activo' => (int) $grupo->activo,
            'color' => $grupo->color,
            'filename' => $grupo->filename ?? null,
            'path' => $grupo->path ?? null,
            'url' => ! empty($grupo->path)
                ? $this->construirUrlPublicaGrupo((string) $grupo->path)
                : ($grupo->url ?? null),
            'original_name' => $grupo->original_name ?? null,
            'mime_type' => $grupo->mime_type ?? null,
            'size' => isset($grupo->size) ? (int) $grupo->size : null,
            'created_at' => $grupo->created_at ?? null,
            'updated_at' => $grupo->updated_at ?? null,
            'productos' => $productos,
            'precios' => $precios,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function obtenerProductosConPrecios(int $idGrupo): array
    {
        $productos = $this->getProductoProvider()
            ->where('id_grupo_producto', $idGrupo)
            ->where('deleted_at', null)
            ->findAll();

        if ($productos === []) {
            return [];
        }

        $idsProducto = array_map(static fn ($p) => (int) $p->id, $productos);
        $preciosPorProducto = $this->obtenerPreciosPorProductos($idsProducto);
        $resultado = [];

        foreach ($productos as $producto) {
            $idProducto = (int) $producto->id;
            $resultado[] = [
                'id' => $idProducto,
                'nombre' => $producto->nombre,
                'descripcion' => $producto->descripcion ?? null,
                'activo' => isset($producto->activo) ? (int) $producto->activo : null,
                'sinonimos' => $this->decodificarSinonimos($producto->sinonimos ?? null),
                'precios' => $preciosPorProducto[$idProducto] ?? [],
            ];
        }

        return $resultado;
    }

    /**
     * @param list<int> $idsProducto
     * @return array<int, list<array<string, mixed>>>
     */
    private function obtenerPreciosPorProductos(array $idsProducto): array
    {
        if ($idsProducto === []) {
            return [];
        }

        $filas = $this->getProductoPrecioProvider()
            ->whereIn('id_producto', $idsProducto)
            ->where('deleted_at', null)
            ->orderBy('prioridad', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();

        $agrupados = [];
        foreach ($filas as $fila) {
            $idProducto = (int) $fila->id_producto;
            $agrupados[$idProducto][] = $this->formatearPrecio($fila);
        }

        return $agrupados;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatearPrecio(ProductoPrecioEntity $precio): array
    {
        return [
            'id' => (int) $precio->id,
            'id_producto' => (int) $precio->id_producto,
            'espesor' => $precio->espesor !== null ? (float) $precio->espesor : null,
            'largo' => $precio->largo !== null ? (float) $precio->largo : null,
            'desarrollo' => $precio->desarrollo !== null ? (float) $precio->desarrollo : null,
            'relleno' => $precio->relleno !== null ? (float) $precio->relleno : null,
            'tipo' => $precio->tipo ?? null,
            'color' => $precio->color ?? null,
            'moneda' => $precio->moneda ?? null,
            'precio' => $precio->precio !== null ? (float) $precio->precio : null,
            'prioridad' => isset($precio->prioridad) ? (int) $precio->prioridad : null,
        ];
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    private function validarDatosGrupo(array $datos, bool $esCreacion): array
    {
        $nombre = isset($datos['nombre']) ? trim((string) $datos['nombre']) : '';
        if ($nombre === '') {
            throw new \Exception('El campo "nombre" es obligatorio.');
        }

        $descripcion = isset($datos['descripcion']) ? trim((string) $datos['descripcion']) : '';
        if ($descripcion === '') {
            throw new \Exception('El campo "descripcion" es obligatorio.');
        }
        if (mb_strlen($descripcion) > 50) {
            throw new \Exception('El campo "descripcion" admite como máximo 50 caracteres.');
        }

        $activo = $datos['activo'] ?? 1;
        if ($activo === '' || $activo === null) {
            $activo = 1;
        }
        $activo = (int) $activo;
        if (! in_array($activo, [0, 1], true)) {
            throw new \Exception('El campo "activo" debe ser 0 o 1.');
        }

        $color = isset($datos['color']) ? trim((string) $datos['color']) : '';
        if ($color === '') {
            throw new \Exception('El campo "color" es obligatorio.');
        }
        if (mb_strlen($color) > 50) {
            throw new \Exception('El campo "color" admite como máximo 50 caracteres.');
        }

        $prioridad = null;
        $incluirPrioridad = false;
        if (array_key_exists('prioridad', $datos) && $datos['prioridad'] !== '' && $datos['prioridad'] !== null) {
            if (! is_numeric($datos['prioridad'])) {
                throw new \Exception('El campo "prioridad" debe ser un número entero.');
            }
            $prioridad = (int) $datos['prioridad'];
            $incluirPrioridad = true;
        }

        $sinonimos = null;
        $incluirSinonimos = false;
        if (array_key_exists('sinonimos', $datos)) {
            $sinonimos = $this->normalizarSinonimos($datos['sinonimos']);
            $incluirSinonimos = true;
        }

        if ($esCreacion && array_key_exists('precios', $datos) && is_array($datos['precios']) && $datos['precios'] !== []) {
            foreach ($datos['precios'] as $idx => $precio) {
                if (! is_array($precio)) {
                    throw new \Exception('Cada elemento de "precios" debe ser un objeto.');
                }
                if (empty($precio['id_producto'])) {
                    throw new \Exception('En creación, cada precio debe incluir "id_producto" de un producto existente.');
                }
            }
        }

        return [
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'activo' => $activo,
            'color' => $color,
            'incluir_prioridad' => $incluirPrioridad,
            'prioridad' => $prioridad,
            'incluir_sinonimos' => $incluirSinonimos,
            'sinonimos' => $sinonimos,
            'slug' => array_key_exists('slug', $datos) ? trim((string) $datos['slug']) : null,
            'incluir_slug' => array_key_exists('slug', $datos),
            'descripcion_web' => array_key_exists('descripcion_web', $datos) ? (string) $datos['descripcion_web'] : null,
            'incluir_descripcion_web' => array_key_exists('descripcion_web', $datos),
            'mostrar_web' => array_key_exists('mostrar_web', $datos) ? (int) $datos['mostrar_web'] : null,
            'incluir_mostrar_web' => array_key_exists('mostrar_web', $datos),
            'seo_title' => array_key_exists('seo_title', $datos) ? trim((string) $datos['seo_title']) : null,
            'incluir_seo_title' => array_key_exists('seo_title', $datos),
            'seo_description' => array_key_exists('seo_description', $datos) ? trim((string) $datos['seo_description']) : null,
            'incluir_seo_description' => array_key_exists('seo_description', $datos),
            'mensaje_whatsapp' => array_key_exists('mensaje_whatsapp', $datos) ? trim((string) $datos['mensaje_whatsapp']) : null,
            'incluir_mensaje_whatsapp' => array_key_exists('mensaje_whatsapp', $datos),
            'subcategorias' => array_key_exists('subcategorias', $datos) ? $this->normalizarSubcategorias($datos['subcategorias']) : null,
            'incluir_subcategorias' => array_key_exists('subcategorias', $datos),
            'filename' => array_key_exists('filename', $datos) ? $datos['filename'] : null,
            'path' => array_key_exists('path', $datos) ? $datos['path'] : null,
            'url' => array_key_exists('url', $datos) ? $datos['url'] : null,
            'original_name' => array_key_exists('original_name', $datos) ? $datos['original_name'] : null,
            'mime_type' => array_key_exists('mime_type', $datos) ? $datos['mime_type'] : null,
            'size' => array_key_exists('size', $datos) ? $datos['size'] : null,
            'incluir_imagen' => array_key_exists('url', $datos) || array_key_exists('filename', $datos),
        ];
    }

    /**
     * @param array<string, mixed> $datos
     */
    private function llenarGrupoProductoEntity(GrupoProductoEntity $entity, array $datos): GrupoProductoEntity
    {
        $entity->nombre = $datos['nombre'];
        $entity->descripcion = $datos['descripcion'];
        $entity->activo = $datos['activo'];
        $entity->color = $datos['color'];

        if (! empty($datos['incluir_prioridad'])) {
            $entity->prioridad = $datos['prioridad'];
        }

        if (! empty($datos['incluir_sinonimos'])) {
            $entity->sinonimos = $datos['sinonimos'];
        }

        if (! empty($datos['incluir_slug'])) {
            $entity->slug = $datos['slug'] !== '' ? $datos['slug'] : null;
        }

        if (! empty($datos['incluir_descripcion_web'])) {
            $entity->descripcion_web = $datos['descripcion_web'];
        }

        if (! empty($datos['incluir_mostrar_web'])) {
            $entity->mostrar_web = in_array((int) $datos['mostrar_web'], [0, 1], true) ? (int) $datos['mostrar_web'] : 0;
        }

        if (! empty($datos['incluir_seo_title'])) {
            $entity->seo_title = $datos['seo_title'] !== '' ? $datos['seo_title'] : null;
        }

        if (! empty($datos['incluir_seo_description'])) {
            $entity->seo_description = $datos['seo_description'] !== '' ? $datos['seo_description'] : null;
        }

        if (! empty($datos['incluir_mensaje_whatsapp'])) {
            $mensaje = $datos['mensaje_whatsapp'] ?? null;
            if (is_string($mensaje) && mb_strlen($mensaje) > 1000) {
                throw new \Exception('El campo "mensaje_whatsapp" admite como máximo 1000 caracteres.');
            }
            $entity->mensaje_whatsapp = ($mensaje !== null && $mensaje !== '') ? $mensaje : null;
        }

        if (! empty($datos['incluir_subcategorias'])) {
            $entity->subcategorias = $datos['subcategorias'];
        }

        if (! empty($datos['incluir_imagen'])) {
            foreach (['filename', 'path', 'url', 'original_name', 'mime_type', 'size'] as $campo) {
                if (array_key_exists($campo, $datos) && $datos[$campo] !== null) {
                    $entity->{$campo} = $datos[$campo];
                }
            }
        }

        return $entity;
    }

    /**
     * @param list<array<string, mixed>> $precios
     */
    private function sincronizarPreciosGrupo(int $idGrupo, array $precios): void
    {
        if ($precios === []) {
            return;
        }

        $idsProductosGrupo = $this->obtenerIdsProductosGrupo($idGrupo);

        foreach ($precios as $idx => $item) {
            if (! is_array($item)) {
                throw new \Exception('Cada elemento de "precios" debe ser un objeto.');
            }

            $idProducto = (int) ($item['id_producto'] ?? 0);
            if ($idProducto <= 0) {
                throw new \Exception('Cada precio debe incluir "id_producto".');
            }

            if (! in_array($idProducto, $idsProductosGrupo, true)) {
                throw new \Exception("El producto {$idProducto} no pertenece al grupo {$idGrupo}.");
            }

            if (! $this->precioTieneDatos($item)) {
                continue;
            }

            producto()->persistirPrecioProducto($idProducto, $item);
        }
    }

    /**
     * @return list<int>
     */
    private function obtenerIdsProductosGrupo(int $idGrupo): array
    {
        $productos = $this->getProductoProvider()
            ->select('id')
            ->where('id_grupo_producto', $idGrupo)
            ->where('deleted_at', null)
            ->findAll();

        return array_map(static fn ($p) => (int) $p->id, $productos);
    }

    /**
     * Orden de subcategorías de la familia (ej. Rectos, Curvos, Cumbreras).
     * Acepta array, JSON string o texto separado por comas / saltos de línea.
     */
    private function normalizarSubcategorias(mixed $valor): ?string
    {
        if ($valor === null || $valor === '' || $valor === '[]') {
            return null;
        }

        if (is_string($valor)) {
            $trim = trim($valor);
            $decoded = json_decode($trim, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $valor = $decoded;
            } else {
                $valor = preg_split('/[\r\n,;]+/', $trim) ?: [];
            }
        }

        if (! is_array($valor)) {
            throw new \Exception('El campo "subcategorias" debe ser una lista.');
        }

        $out = [];
        foreach ($valor as $item) {
            $nombre = is_array($item) ? (string) ($item['nombre'] ?? '') : (string) $item;
            $nombre = trim($nombre);
            if ($nombre === '') {
                continue;
            }
            $nombre = mb_substr($nombre, 0, 120);
            if (! in_array($nombre, $out, true)) {
                $out[] = $nombre;
            }
        }

        return $out === [] ? null : json_encode(array_slice($out, 0, 30), JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return list<string>
     */
    private function decodificarSinonimos(mixed $sinonimos): array
    {
        if ($sinonimos === null || $sinonimos === '') {
            return [];
        }

        if (is_array($sinonimos)) {
            return array_values(array_filter(array_map(static fn ($s) => trim((string) $s), $sinonimos)));
        }

        $decoded = json_decode((string) $sinonimos, true);

        return is_array($decoded)
            ? array_values(array_filter(array_map(static fn ($s) => trim((string) $s), $decoded)))
            : [];
    }

    private function normalizarSinonimos(mixed $sinonimos): ?string
    {
        if ($sinonimos === null || $sinonimos === '') {
            return null;
        }

        if (is_array($sinonimos)) {
            $lista = array_values(array_filter(array_map(static fn ($s) => trim((string) $s), $sinonimos)));

            return $lista === [] ? null : json_encode($lista, JSON_UNESCAPED_UNICODE);
        }

        $texto = trim((string) $sinonimos);
        if ($texto === '') {
            return null;
        }

        if (str_starts_with($texto, '[')) {
            $decoded = json_decode($texto, true);
            if (! is_array($decoded)) {
                throw new \Exception('El campo "sinonimos" tiene un JSON inválido.');
            }
            $lista = array_values(array_filter(array_map(static fn ($s) => trim((string) $s), $decoded)));

            return $lista === [] ? null : json_encode($lista, JSON_UNESCAPED_UNICODE);
        }

        $lista = array_values(array_filter(array_map('trim', explode(',', $texto))));

        return $lista === [] ? null : json_encode($lista, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function precioTieneDatos(array $item): bool
    {
        $campos = ['espesor', 'largo', 'desarrollo', 'relleno', 'tipo', 'color', 'moneda', 'precio', 'prioridad'];
        foreach ($campos as $campo) {
            if (! array_key_exists($campo, $item)) {
                continue;
            }
            $valor = $item[$campo];
            if ($valor !== null && $valor !== '') {
                return true;
            }
        }

        return ! empty($item['id']);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function valorDecimalOpcional(array $item, string $campo): ?float
    {
        if (! array_key_exists($campo, $item) || $item[$campo] === '' || $item[$campo] === null) {
            return null;
        }

        return (float) $item[$campo];
    }

    /**
     * @param array<string, mixed> $item
     */
    private function valorTextoOpcional(array $item, string $campo): ?string
    {
        if (! array_key_exists($campo, $item) || $item[$campo] === null) {
            return null;
        }

        $valor = trim((string) $item[$campo]);

        return $valor === '' ? null : $valor;
    }

}
