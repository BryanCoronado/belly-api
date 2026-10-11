<?php
namespace Belly\Media\UseCases;

use Belly\Media\Config\MediaConfig;
use Belly\Media\Models\ProductoModel;
use Belly\Media\Models\ProductoPrecioModel;
use Belly\Media\Entities\ProductoEntity;
use Belly\Media\Entities\ProductoPrecioEntity;
use Config\App;

class ProductoUseCase {
    public const TIPOS_CALCULO = ['metro', 'fijo', 'unidad'];
    public const MONEDAS_PRECIO = ['PEN', 'USD'];

    protected MediaConfig $config;
    protected App $appConfig;
    protected ?ProductoModel $producto_provider = NULL;
    protected ?ProductoPrecioModel $producto_precio_provider = NULL;

    public function __construct(MediaConfig $config)
    {
        $this->config = $config;
        $this->appConfig = config('App');
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
        $producto_model = $this->getProductoProvider();
        $producto = $producto_model->find($id);

        if (isset($producto) && !empty($producto)) {
            return $this->formatearProductoConPrecios($producto);
        }

        return null;
    }
   
    public function crearProducto(array $datos)
    {
        $datosValidados = $this->validarDatosProducto($datos);

        $producto_model = $this->getProductoProvider();

        $producto_existente = $producto_model->where('nombre', $datosValidados['nombre'])->first();
        if ($producto_existente) {
            return [
                'id' => $producto_existente->id,
                'nombre' => $producto_existente->nombre,
                'mensaje' => 'El producto ya existe.'
            ];
        }

        $producto_entity = $this->llenarProductoEntity(new ProductoEntity(), $datosValidados);

        if (! $producto_model->save($producto_entity)) {
            throw new \Exception(json_encode($producto_model->errors()));
        }

        $idProducto = (int) $producto_model->insertID;

        if (array_key_exists('precios', $datos) && is_array($datos['precios'])) {
            $this->sincronizarPreciosProducto($idProducto, $datos['precios']);
        }

        return $this->formatearProductoConPrecios($producto_model->find($idProducto)) + [
            'mensaje' => 'Producto creado correctamente.',
        ];
    }





    public function editarProducto(array $datos, int $id)
    {
        $producto_model = $this->getProductoProvider();
        $producto = $producto_model->find($id);

        if (!$producto) {
            throw new \Exception('Producto no encontrado');
        }

        $datosValidados = $this->validarDatosProducto($datos);

        $this->actualizarRegistroProducto($id, $datosValidados);

        if (array_key_exists('precios', $datos) && is_array($datos['precios'])) {
            $this->sincronizarPreciosProducto($id, $datos['precios']);
        }

        return $this->formatearProductoConPrecios($producto_model->find($id)) + [
            'mensaje' => 'Producto editado correctamente.',
        ];
    }

    public function listarProductos($page = 1, $perPage = 10, $search = null, $mostrarWeb = null, $idGrupo = null)
    {
        $producto_model = $this->getProductoProvider();

        if (! empty($search)) {
            $producto_model->groupStart()
                ->like('nombre', $search)
                ->orLike('descripcion', $search)
                ->orLike('especificaciones_tecnicas', $search)
            ->groupEnd();
        }

        if ($mostrarWeb !== null) {
            $producto_model->where('mostrar_web', (int) $mostrarWeb ? 1 : 0);
        }

        if ($idGrupo !== null && $idGrupo > 0) {
            $producto_model->where('id_grupo_producto', (int) $idGrupo);
        }

        $producto_model->orderBy('prioridad', 'ASC')->orderBy('nombre', 'ASC');

        if ($perPage === 'all') {
            $productos = $producto_model->findAll();
            $data = array_map([$this, 'formatearProductoLista'], $productos);
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

        $result = $producto_model->paginate($perPage, 'default', $page);
        $pager = $producto_model->pager;
        $total = $pager->getTotal('default');
        $currentPage = $pager->getCurrentPage('default');
        $perPageResult = $pager->getPerPage('default');
        $lastPage = $pager->getPageCount('default');
        $from = $total > 0 ? (($currentPage - 1) * $perPageResult) + 1 : 0;
        $to = $from + count($result) - 1;

        return [
            'data' => array_map([$this, 'formatearProductoLista'], $result),
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
     * @return array<string, mixed>
     */
    private function formatearProductoLista($producto): array
    {
        return [
            'id' => (int) $producto->id,
            'id_grupo_producto' => isset($producto->id_grupo_producto) ? (int) $producto->id_grupo_producto : null,
            'nombre' => $producto->nombre,
            'slug' => $producto->slug ?? null,
            'mostrar_web' => isset($producto->mostrar_web) ? (int) $producto->mostrar_web : 0,
            'seo_title' => $producto->seo_title ?? null,
            'subcategoria' => $producto->subcategoria ?? null,
            'prioridad' => isset($producto->prioridad) ? (int) $producto->prioridad : null,
            'oferta' => isset($producto->oferta) ? (int) $producto->oferta : 0,
            'activo' => isset($producto->activo) ? (int) $producto->activo : null,
            'url' => ! empty($producto->path)
                ? $this->construirUrlPublicaProducto((string) $producto->path)
                : ($producto->url ?? null),
        ];
    }

    public function listarPorGrupo(int $id_grupo_producto)
    {
        $producto_model = $this->getProductoProvider();

        $productos = $producto_model
            ->where('id_grupo_producto', $id_grupo_producto)
            ->where('deleted_at', null)
            ->findAll();

        return array_map(fn ($producto) => $this->formatearProductoConPrecios($producto), $productos);
    }

    public function eliminarProducto(int $id)
    {
        $producto_model = $this->getProductoProvider();
        $producto = $producto_model->find($id);

        if (!$producto) {
            throw new \Exception('Producto no encontrado');
        }

        $eliminado = $producto_model->delete($id);

        if (!$eliminado) {
            throw new \Exception('No se pudo eliminar el producto');
        }

        return [
            'id' => $id,
            'message' => 'Producto eliminado correctamente'
        ];
    }

    private function llenarProductoEntity(ProductoEntity $producto_entity, array $datos): ProductoEntity
    {
        $campos = [
            'id_grupo_producto',
            'nombre',
            'descripcion',
            'slug',
            'descripcion_web',
            'mostrar_web',
            'seo_title',
            'seo_description',
            'tipo_calculo',
            'prioridad',
            'sinonimos',
            'activo',
            'especificaciones_tecnicas',
            'especificaciones_web',
            'caracteristicas_web',
            'subcategoria',
            'subcategoria_orden',
            'oferta',
            'oferta_texto',
            'mensaje_whatsapp',
            'colores',
            'atributos',
            'relacionados',
            'color',
            'filename',
            'path',
            'url',
            'original_name',
            'mime_type',
            'size',
            'ficha_filename',
            'ficha_path',
            'ficha_url',
            'ficha_mime_type',
            'ficha_size',
        ];

        foreach ($campos as $campo) {
            if (array_key_exists($campo, $datos)) {
                $producto_entity->{$campo} = $datos[$campo];
            }
        }

        return $producto_entity;
    }

    /**
     * @param array<string, mixed> $datosPost
     * @return array<string, mixed>
     */
    public function parsePreciosDesdeRequest(mixed $preciosRaw): array
    {
        if ($preciosRaw === null || $preciosRaw === '') {
            return [];
        }

        if (is_array($preciosRaw)) {
            return $preciosRaw;
        }

        if (is_string($preciosRaw)) {
            $decoded = json_decode($preciosRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                throw new \Exception('El campo "precios" debe ser un JSON válido.');
            }

            return $decoded;
        }

        throw new \Exception('Formato inválido en "precios".');
    }

    public function crearProductoConCatalogo($file, array $datosPost, $fichaFile = null): array
    {
        try {
            $datosValidados = $this->validarDatosProducto($datosPost, true);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $producto_model = $this->getProductoProvider();
        $producto_existente = $producto_model->where('nombre', $datosValidados['nombre'])->first();
        if ($producto_existente) {
            return [
                'success' => false,
                'error' => 'El producto ya existe.',
                'id' => $producto_existente->id,
                'nombre' => $producto_existente->nombre,
            ];
        }

        $this->validarArchivoCatalogo($file);

        $mimeType = (string) $file->getMimeType();
        $extension = $file->getClientExtension() ?: $this->obtenerExtensionImagenProducto($mimeType);
        $nombreUnico = uniqid('cat_', true) . '.' . $extension;
        $relativePath = 'producto/' . date('Y-m-d') . '/' . $nombreUnico;
        $rutaCompleta = WRITEPATH . 'uploads/' . $relativePath;
        $directorioDestino = dirname($rutaCompleta);

        if (!is_dir($directorioDestino)) {
            mkdir($directorioDestino, 0777, true);
        }

        if (!$file->move($directorioDestino, $nombreUnico)) {
            return [
                'success' => false,
                'error' => 'Error al guardar archivo: ' . implode(', ', $file->getErrors()),
            ];
        }

        $publicUrl = $this->construirUrlPublicaProducto($relativePath);

        $datos = array_merge($datosValidados, [
            'filename' => $nombreUnico,
            'path' => $relativePath,
            'url' => $publicUrl,
            'original_name' => $file->getClientName(),
            'mime_type' => $mimeType,
            'size' => (int) $file->getSize(),
        ]);

        $fichaMedia = null;
        if ($this->archivoUtilizable($fichaFile)) {
            try {
                $fichaMedia = $this->guardarFichaTecnica($fichaFile);
                $datos = array_merge($datos, $fichaMedia);
            } catch (\Exception $e) {
                @unlink($directorioDestino . DIRECTORY_SEPARATOR . $nombreUnico);

                return ['success' => false, 'error' => $e->getMessage()];
            }
        }

        $producto_entity = $this->llenarProductoEntity(new ProductoEntity(), $datos);

        if (!$producto_model->save($producto_entity)) {
            @unlink($directorioDestino . DIRECTORY_SEPARATOR . $nombreUnico);
            $this->eliminarArchivoUploads($fichaMedia['ficha_path'] ?? null);

            return [
                'success' => false,
                'error' => json_encode($producto_model->errors()),
            ];
        }

        $idProducto = (int) $producto_model->insertID;

        if (array_key_exists('precios', $datosPost)) {
            try {
                $precios = $this->parsePreciosDesdeRequest($datosPost['precios']);
                $this->sincronizarPreciosProducto($idProducto, $precios);
            } catch (\Exception $e) {
                @unlink($directorioDestino . DIRECTORY_SEPARATOR . $nombreUnico);

                return ['success' => false, 'error' => $e->getMessage()];
            }
        }

        $detalle = $this->formatearProductoConPrecios($producto_model->find($idProducto));

        return array_merge($detalle, [
            'success' => true,
            'mensaje' => 'Producto creado correctamente.',
            'filename' => $nombreUnico,
            'path' => $relativePath,
            'url' => $publicUrl,
        ]);
    }

    /**
     * Actualiza producto (catálogo). Si se envía archivo válido, reemplaza la imagen en uploads/producto/…
     * y elimina el archivo anterior cuando corresponde.
     *
     * @param array<string, mixed> $datosPost
     * @return array<string, mixed>
     */
    public function editarProductoConCatalogo(int $id, $file, array $datosPost, $fichaFile = null, $heroFile = null, $heroMobileFile = null, $diagramaFile = null): array
    {
        $producto_model = $this->getProductoProvider();
        $producto = $producto_model->find($id);

        if (!$producto) {
            return ['success' => false, 'error' => 'Producto no encontrado'];
        }

        try {
            $datosValidados = $this->validarDatosProducto($datosPost, true);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $otroMismoNombre = $producto_model->where('nombre', $datosValidados['nombre'])->where('id !=', $id)->first();
        if ($otroMismoNombre) {
            return ['success' => false, 'error' => 'Ya existe otro producto con ese nombre.'];
        }

        $pathAnterior = trim((string) ($producto->path ?? ''));
        $fichaPathAnterior = trim((string) ($producto->ficha_path ?? ''));
        $datos = $datosValidados;

        $nuevoArchivoRutaRelativa = null;
        $rutaCompletaNuevo = null;
        $fichaMedia = null;

        if ($file !== null && $file->isValid() && !$file->hasMoved()) {
            $this->validarArchivoCatalogo($file);

            $mimeType = (string) $file->getMimeType();
            $extension = $file->getClientExtension() ?: $this->obtenerExtensionImagenProducto($mimeType);
            $nombreUnico = uniqid('cat_', true) . '.' . $extension;
            $relativePath = 'producto/' . date('Y-m-d') . '/' . $nombreUnico;
            $rutaCompleta = WRITEPATH . 'uploads/' . $relativePath;
            $directorioDestino = dirname($rutaCompleta);

            if (!is_dir($directorioDestino)) {
                mkdir($directorioDestino, 0777, true);
            }

            if (!$file->move($directorioDestino, $nombreUnico)) {
                return [
                    'success' => false,
                    'error' => 'Error al guardar archivo: ' . implode(', ', $file->getErrors()),
                ];
            }

            $publicUrl = $this->construirUrlPublicaProducto($relativePath);
            $nuevoArchivoRutaRelativa = $relativePath;
            $rutaCompletaNuevo = $directorioDestino . DIRECTORY_SEPARATOR . $nombreUnico;

            $datos['filename'] = $nombreUnico;
            $datos['path'] = $relativePath;
            $datos['url'] = $publicUrl;
            $datos['original_name'] = $file->getClientName();
            $datos['mime_type'] = $mimeType;
            $datos['size'] = (int) $file->getSize();
        }

        if ($this->archivoUtilizable($fichaFile)) {
            try {
                $fichaMedia = $this->guardarFichaTecnica($fichaFile);
                $datos = array_merge($datos, $fichaMedia);
            } catch (\Exception $e) {
                if ($rutaCompletaNuevo !== null && is_file($rutaCompletaNuevo)) {
                    @unlink($rutaCompletaNuevo);
                }

                return ['success' => false, 'error' => $e->getMessage()];
            }
        }

        try {
            $this->actualizarRegistroProducto($id, $datos);
        } catch (\Exception $e) {
            if ($rutaCompletaNuevo !== null && is_file($rutaCompletaNuevo)) {
                @unlink($rutaCompletaNuevo);
            }
            $this->eliminarArchivoUploads($fichaMedia['ficha_path'] ?? null);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        if ($nuevoArchivoRutaRelativa !== null && $pathAnterior !== '' && $pathAnterior !== $nuevoArchivoRutaRelativa) {
            $this->eliminarArchivoUploads($pathAnterior);
        }

        if ($fichaMedia !== null && $fichaPathAnterior !== '' && $fichaPathAnterior !== ($fichaMedia['ficha_path'] ?? null)) {
            $this->eliminarArchivoUploads($fichaPathAnterior);
        }

        $actualizado = $producto_model->find($id);

        if (array_key_exists('precios', $datosPost)) {
            try {
                $precios = $this->parsePreciosDesdeRequest($datosPost['precios']);
                $this->sincronizarPreciosProducto($id, $precios);
            } catch (\Exception $e) {
                return ['success' => false, 'error' => $e->getMessage()];
            }
        }

        if ($this->archivoUtilizable($heroFile) || $this->archivoUtilizable($heroMobileFile) || $this->archivoUtilizable($diagramaFile)) {
            $heroResult = $this->editarHeroMedia(
                $id,
                $heroFile,
                $heroMobileFile,
                false,
                false,
                $diagramaFile,
                false
            );
            if (empty($heroResult['success'])) {
                return [
                    'success' => false,
                    'error' => $heroResult['error'] ?? 'Error al guardar imágenes del hero.',
                ];
            }
        }

        $detalle = $this->formatearProductoConPrecios($producto_model->find($id));

        return array_merge($detalle, [
            'success' => true,
            'mensaje' => 'Producto editado correctamente.',
            'filename' => $actualizado->filename ?? null,
            'path' => $actualizado->path ?? null,
            'url' => $actualizado->url ?? null,
        ]);
    }

    /**
     * Sube o quita imágenes del hero de la ficha pública:
     * fondo escritorio, fondo móvil y diagrama de dimensiones.
     *
     * @return array{success:bool,error?:string,data?:array<string,mixed>}
     */
    public function editarHeroMedia(
        int $id,
        $heroFile = null,
        $heroMobileFile = null,
        bool $quitarHero = false,
        bool $quitarHeroMobile = false,
        $diagramaFile = null,
        bool $quitarDiagrama = false
    ): array {
        $producto_model = $this->getProductoProvider();
        $producto = $producto_model->find($id);

        if (! $producto) {
            return ['success' => false, 'error' => 'Producto no encontrado'];
        }

        $datos = [];
        $heroPathAnterior = trim((string) ($producto->hero_path ?? ''));
        $heroMobilePathAnterior = trim((string) ($producto->hero_path_mobile ?? ''));
        $diagramaPathAnterior = trim((string) ($producto->diagrama_path ?? ''));
        $nuevosPaths = [];

        if ($quitarHero) {
            $datos['hero_path'] = null;
            $datos['hero_url'] = null;
        } elseif ($this->archivoUtilizable($heroFile)) {
            try {
                $heroMedia = $this->guardarHeroImagen($heroFile, 'hero');
                $datos = array_merge($datos, $heroMedia);
                $nuevosPaths[] = $heroMedia['hero_path'];
            } catch (\Exception $e) {
                return ['success' => false, 'error' => $e->getMessage()];
            }
        }

        if ($quitarHeroMobile) {
            $datos['hero_path_mobile'] = null;
            $datos['hero_url_mobile'] = null;
        } elseif ($this->archivoUtilizable($heroMobileFile)) {
            try {
                $heroMobileMedia = $this->guardarHeroImagen($heroMobileFile, 'hero_mobile');
                $datos['hero_path_mobile'] = $heroMobileMedia['hero_path'];
                $datos['hero_url_mobile'] = $heroMobileMedia['hero_url'];
                $nuevosPaths[] = $heroMobileMedia['hero_path'];
            } catch (\Exception $e) {
                foreach ($nuevosPaths as $p) {
                    $this->eliminarArchivoUploads($p);
                }

                return ['success' => false, 'error' => $e->getMessage()];
            }
        }

        if ($quitarDiagrama) {
            $datos['diagrama_path'] = null;
            $datos['diagrama_url'] = null;
        } elseif ($this->archivoUtilizable($diagramaFile)) {
            try {
                $diagramaMedia = $this->guardarHeroImagen($diagramaFile, 'diagrama');
                $datos['diagrama_path'] = $diagramaMedia['hero_path'];
                $datos['diagrama_url'] = $diagramaMedia['hero_url'];
                $nuevosPaths[] = $diagramaMedia['hero_path'];
            } catch (\Exception $e) {
                foreach ($nuevosPaths as $p) {
                    $this->eliminarArchivoUploads($p);
                }

                return ['success' => false, 'error' => $e->getMessage()];
            }
        }

        if ($datos === []) {
            return [
                'success' => true,
                'data' => $this->formatearProductoConPrecios($producto),
            ];
        }

        try {
            $this->actualizarRegistroProducto($id, $datos);
        } catch (\Exception $e) {
            foreach ($nuevosPaths as $p) {
                $this->eliminarArchivoUploads($p);
            }

            return ['success' => false, 'error' => $e->getMessage()];
        }

        if ($quitarHero && $heroPathAnterior !== '') {
            $this->eliminarArchivoUploads($heroPathAnterior);
        } elseif (! empty($datos['hero_path']) && $heroPathAnterior !== '' && $heroPathAnterior !== $datos['hero_path']) {
            $this->eliminarArchivoUploads($heroPathAnterior);
        }

        if ($quitarHeroMobile && $heroMobilePathAnterior !== '') {
            $this->eliminarArchivoUploads($heroMobilePathAnterior);
        } elseif (! empty($datos['hero_path_mobile']) && $heroMobilePathAnterior !== '' && $heroMobilePathAnterior !== $datos['hero_path_mobile']) {
            $this->eliminarArchivoUploads($heroMobilePathAnterior);
        }

        if ($quitarDiagrama && $diagramaPathAnterior !== '') {
            $this->eliminarArchivoUploads($diagramaPathAnterior);
        } elseif (! empty($datos['diagrama_path']) && $diagramaPathAnterior !== '' && $diagramaPathAnterior !== $datos['diagrama_path']) {
            $this->eliminarArchivoUploads($diagramaPathAnterior);
        }

        $actualizado = $producto_model->find($id);

        return [
            'success' => true,
            'data' => $this->formatearProductoConPrecios($actualizado),
        ];
    }

    private function archivoUtilizable($file): bool
    {
        return $file !== null
            && $file->isValid()
            && ! $file->hasMoved()
            && (int) $file->getError() === UPLOAD_ERR_OK;
    }

    /**
     * Ficha técnica descargable (PDF o imagen) en uploads/producto/ficha/…
     *
     * @return array{ficha_filename:string,ficha_path:string,ficha_url:string,ficha_mime_type:string,ficha_size:int}
     */
    private function guardarFichaTecnica($file): array
    {
        $mimeType = (string) $file->getMimeType();
        $permitidos = ['application/pdf', 'application/octet-stream'];
        if (! in_array($mimeType, $permitidos, true) && ! str_starts_with($mimeType, 'image/')) {
            throw new \Exception('La ficha técnica debe ser un PDF o una imagen.');
        }

        $extension = strtolower($file->getClientExtension() ?: 'pdf');
        if (! in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'webp'], true)) {
            $extension = $mimeType === 'application/pdf' ? 'pdf' : 'jpg';
        }

        $nombreUnico = uniqid('ficha_', true) . '.' . $extension;
        $relativePath = 'producto/ficha/' . date('Y-m-d') . '/' . $nombreUnico;
        $directorioDestino = dirname(WRITEPATH . 'uploads/' . $relativePath);

        if (! is_dir($directorioDestino)) {
            mkdir($directorioDestino, 0777, true);
        }

        if (! $file->move($directorioDestino, $nombreUnico)) {
            throw new \Exception('Error al guardar la ficha técnica: ' . implode(', ', $file->getErrors()));
        }

        return [
            'ficha_filename' => $nombreUnico,
            'ficha_path' => $relativePath,
            'ficha_url' => $this->construirUrlPublicaProducto($relativePath),
            'ficha_mime_type' => $mimeType,
            'ficha_size' => (int) $file->getSize(),
        ];
    }

    /**
     * Imagen de fondo del hero de ficha pública.
     *
     * @return array{hero_path:string,hero_url:string}
     */
    private function guardarHeroImagen($file, string $prefijo = 'hero'): array
    {
        $mimeType = (string) $file->getMimeType();
        if (! str_starts_with($mimeType, 'image/')) {
            throw new \Exception('La imagen del hero debe ser JPG, PNG o WebP.');
        }

        $extension = strtolower($file->getClientExtension() ?: $this->obtenerExtensionImagenProducto($mimeType));
        $nombreUnico = uniqid($prefijo . '_', true) . '.' . $extension;
        $relativePath = 'producto/hero/' . date('Y-m-d') . '/' . $nombreUnico;
        $rutaCompleta = WRITEPATH . 'uploads/' . $relativePath;
        $directorioDestino = dirname($rutaCompleta);

        if (! is_dir($directorioDestino)) {
            mkdir($directorioDestino, 0777, true);
        }

        if (! $file->move($directorioDestino, $nombreUnico)) {
            throw new \Exception('Error al guardar imagen del hero: ' . implode(', ', $file->getErrors()));
        }

        return [
            'hero_path' => $relativePath,
            'hero_url' => $this->construirUrlPublicaProducto($relativePath),
        ];
    }

    private function eliminarArchivoUploads(?string $path): void
    {
        $path = $path !== null ? trim($path) : '';
        if ($path === '') {
            return;
        }

        $rel = ltrim(str_replace('\\', '/', $path), '/');
        $full = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (is_file($full)) {
            @unlink($full);
        }
    }

    private function validarArchivoCatalogo($file): void
    {
        if (!$file || !$file->isValid()) {
            throw new \Exception('Archivo no válido');
        }

        $mimeType = $file->getMimeType();
        if (!str_starts_with((string) $mimeType, 'image/')) {
            throw new \Exception('El archivo debe ser una imagen');
        }
    }

    private function obtenerExtensionImagenProducto(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'image/bmp' => 'bmp',
            'image/x-icon' => 'ico',
            default => 'jpg',
        };
    }

    private function construirUrlPublicaProducto(string $path): string
    {
        $path = ltrim(preg_replace('#^uploads/#', '', ltrim(str_replace('\\', '/', $path), '/')), '/');
        $baseUploadsUrl = trim((string) env('MEDIA_UPLOADS_BASE_URL', ''));
        if ($baseUploadsUrl !== '') {
            return rtrim($baseUploadsUrl, '/') . '/uploads/' . ltrim($path, '/');
        }

        return rtrim($this->appConfig->baseURL, '/') . '/uploads/' . ltrim($path, '/');
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    private function validarDatosProducto(array $datos, bool $requiereGrupo = false): array
    {
        $nombre = isset($datos['nombre']) ? trim((string) $datos['nombre']) : '';
        if ($nombre === '') {
            throw new \Exception('El campo "nombre" es obligatorio.');
        }

        $descripcion = isset($datos['descripcion']) ? trim((string) $datos['descripcion']) : '';
        if ($descripcion === '') {
            throw new \Exception('El campo "descripcion" es obligatorio (máx. 500 caracteres).');
        }
        if (mb_strlen($descripcion) > 500) {
            throw new \Exception('El campo "descripcion" admite como máximo 500 caracteres.');
        }

        $especificaciones = isset($datos['especificaciones_tecnicas']) ? trim((string) $datos['especificaciones_tecnicas']) : '';
        if ($especificaciones === '') {
            throw new \Exception('El campo "especificaciones_tecnicas" es obligatorio.');
        }
        if (mb_strlen($especificaciones) > 500) {
            throw new \Exception('El campo "especificaciones_tecnicas" admite como máximo 500 caracteres.');
        }

        $tipoCalculo = isset($datos['tipo_calculo']) ? strtolower(trim((string) $datos['tipo_calculo'])) : '';
        if ($tipoCalculo === '') {
            throw new \Exception('El campo "tipo_calculo" es obligatorio.');
        }
        if (! in_array($tipoCalculo, self::TIPOS_CALCULO, true)) {
            throw new \Exception('El campo "tipo_calculo" debe ser: metro, fijo o unidad.');
        }

        $activo = $datos['activo'] ?? 1;
        if ($activo === '' || $activo === null) {
            $activo = 1;
        }
        $activo = (int) $activo;
        if (! in_array($activo, [0, 1], true)) {
            throw new \Exception('El campo "activo" debe ser 0 o 1.');
        }

        $resultado = [
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'especificaciones_tecnicas' => $especificaciones,
            'tipo_calculo' => $tipoCalculo,
            'activo' => $activo,
        ];

        if (isset($datos['id_grupo_producto']) && $datos['id_grupo_producto'] !== '' && $datos['id_grupo_producto'] !== null) {
            $resultado['id_grupo_producto'] = (int) $datos['id_grupo_producto'];
        } elseif ($requiereGrupo) {
            throw new \Exception('El campo "id_grupo_producto" es obligatorio.');
        }

        if (array_key_exists('color', $datos)) {
            $resultado['color'] = trim((string) $datos['color']);
        }

        if (array_key_exists('slug', $datos)) {
            $resultado['slug'] = trim((string) $datos['slug']) ?: null;
        }
        if (array_key_exists('descripcion_web', $datos)) {
            $resultado['descripcion_web'] = (string) $datos['descripcion_web'];
        }
        if (array_key_exists('mostrar_web', $datos)) {
            $resultado['mostrar_web'] = (int) $datos['mostrar_web'] ? 1 : 0;
        }
        if (array_key_exists('seo_title', $datos)) {
            $resultado['seo_title'] = trim((string) $datos['seo_title']) ?: null;
        }
        if (array_key_exists('seo_description', $datos)) {
            $resultado['seo_description'] = trim((string) $datos['seo_description']) ?: null;
        }
        if (array_key_exists('especificaciones_web', $datos)) {
            $resultado['especificaciones_web'] = (string) $datos['especificaciones_web'];
        }
        if (array_key_exists('caracteristicas_web', $datos)) {
            $resultado['caracteristicas_web'] = (string) $datos['caracteristicas_web'];
        }
        if (array_key_exists('subcategoria', $datos)) {
            $sub = trim((string) $datos['subcategoria']);
            if (mb_strlen($sub) > 120) {
                throw new \Exception('El campo "subcategoria" admite como máximo 120 caracteres.');
            }
            $resultado['subcategoria'] = $sub !== '' ? $sub : null;
        }
        if (array_key_exists('subcategoria_orden', $datos)) {
            $orden = $datos['subcategoria_orden'];
            $resultado['subcategoria_orden'] = ($orden === '' || $orden === null) ? null : max(0, (int) $orden);
        }
        if (array_key_exists('prioridad', $datos)) {
            $prioridad = $datos['prioridad'];
            $resultado['prioridad'] = ($prioridad === '' || $prioridad === null) ? null : max(0, (int) $prioridad);
        }
        if (array_key_exists('oferta', $datos)) {
            $resultado['oferta'] = (int) $datos['oferta'] ? 1 : 0;
        }
        if (array_key_exists('oferta_texto', $datos)) {
            $ofertaTexto = trim((string) $datos['oferta_texto']);
            $resultado['oferta_texto'] = $ofertaTexto !== '' ? mb_substr($ofertaTexto, 0, 80) : null;
        }
        if (array_key_exists('mensaje_whatsapp', $datos)) {
            $mensaje = trim((string) $datos['mensaje_whatsapp']);
            if (mb_strlen($mensaje) > 1000) {
                throw new \Exception('El campo "mensaje_whatsapp" admite como máximo 1000 caracteres.');
            }
            $resultado['mensaje_whatsapp'] = $mensaje !== '' ? $mensaje : null;
        }
        if (array_key_exists('colores', $datos)) {
            $resultado['colores'] = $this->normalizarColoresProducto($datos['colores']);
        }
        if (array_key_exists('atributos', $datos)) {
            $resultado['atributos'] = $this->normalizarAtributosProducto($datos['atributos']);
        }
        if (array_key_exists('relacionados', $datos)) {
            $resultado['relacionados'] = $this->normalizarRelacionadosProducto($datos['relacionados']);
        }

        return $resultado;
    }

    /**
     * Paleta de colores del producto. Acepta array o JSON string.
     * Formato normalizado: [{nombre, codigo, hex, url}]
     */
    private function normalizarColoresProducto(mixed $valor): ?string
    {
        $lista = $this->decodificarListaJson($valor, 'colores');
        if ($lista === null) {
            return null;
        }

        $out = [];
        foreach ($lista as $item) {
            if (is_string($item)) {
                $item = ['nombre' => $item];
            }
            if (! is_array($item)) {
                continue;
            }

            $nombre = trim((string) ($item['nombre'] ?? ''));
            $codigo = trim((string) ($item['codigo'] ?? ''));
            $hex = strtoupper(trim((string) ($item['hex'] ?? '')));
            $url = trim((string) ($item['url'] ?? ''));

            if ($hex !== '' && ! str_starts_with($hex, '#')) {
                $hex = '#' . $hex;
            }
            if ($hex !== '' && ! preg_match('/^#[0-9A-F]{6}$/', $hex)) {
                $hex = '';
            }

            if ($nombre === '' && $codigo === '' && $hex === '' && $url === '') {
                continue;
            }

            $out[] = [
                'nombre' => $nombre !== '' ? mb_substr($nombre, 0, 80) : null,
                'codigo' => $codigo !== '' ? mb_substr($codigo, 0, 40) : null,
                'hex' => $hex !== '' ? $hex : null,
                'url' => $url !== '' ? $url : null,
            ];
        }

        return $out === [] ? null : json_encode($out, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Ficha de atributos técnicos. Formato normalizado: [{label, valor}]
     */
    private function normalizarAtributosProducto(mixed $valor): ?string
    {
        $lista = $this->decodificarListaJson($valor, 'atributos');
        if ($lista === null) {
            return null;
        }

        $out = [];
        foreach ($lista as $item) {
            if (! is_array($item)) {
                continue;
            }

            $label = trim((string) ($item['label'] ?? $item['nombre'] ?? ''));
            $val = trim((string) ($item['valor'] ?? $item['value'] ?? ''));

            if ($label === '' && $val === '') {
                continue;
            }

            $out[] = [
                'label' => mb_substr($label, 0, 120),
                'valor' => mb_substr($val, 0, 240),
            ];
        }

        return $out === [] ? null : json_encode($out, JSON_UNESCAPED_UNICODE);
    }

    /**
     * IDs de productos recomendados.
     */
    private function normalizarRelacionadosProducto(mixed $valor): ?string
    {
        $lista = $this->decodificarListaJson($valor, 'relacionados');
        if ($lista === null) {
            return null;
        }

        $ids = [];
        foreach ($lista as $item) {
            $id = is_array($item) ? (int) ($item['id'] ?? 0) : (int) $item;
            if ($id > 0 && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids === [] ? null : json_encode(array_slice($ids, 0, 12));
    }

    /**
     * @return list<mixed>|null null cuando el campo se envía vacío (limpiar).
     */
    private function decodificarListaJson(mixed $valor, string $campo): ?array
    {
        if ($valor === null || $valor === '' || $valor === '[]') {
            return null;
        }

        if (is_string($valor)) {
            $decoded = json_decode($valor, true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                throw new \Exception("El campo \"{$campo}\" debe ser un JSON válido (lista).");
            }
            $valor = $decoded;
        }

        if (! is_array($valor)) {
            throw new \Exception("El campo \"{$campo}\" debe ser una lista.");
        }

        return array_values($valor);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatearProductoConPrecios(ProductoEntity $producto): array
    {
        $precios = $this->getProductoPrecioProvider()
            ->where('id_producto', (int) $producto->id)
            ->where('deleted_at', null)
            ->orderBy('prioridad', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();

        return [
            'id' => (int) $producto->id,
            'id_grupo_producto' => isset($producto->id_grupo_producto) ? (int) $producto->id_grupo_producto : null,
            'nombre' => $producto->nombre,
            'descripcion' => $producto->descripcion ?? null,
            'slug' => $producto->slug ?? null,
            'descripcion_web' => $producto->descripcion_web ?? null,
            'mostrar_web' => isset($producto->mostrar_web) ? (int) $producto->mostrar_web : 0,
            'seo_title' => $producto->seo_title ?? null,
            'seo_description' => $producto->seo_description ?? null,
            'tipo_calculo' => $producto->tipo_calculo ?? null,
            'prioridad' => isset($producto->prioridad) ? (int) $producto->prioridad : null,
            'especificaciones_tecnicas' => $producto->especificaciones_tecnicas ?? null,
            'especificaciones_web' => $producto->especificaciones_web ?? null,
            'caracteristicas_web' => $producto->caracteristicas_web ?? null,
            'subcategoria' => $producto->subcategoria ?? null,
            'subcategoria_orden' => isset($producto->subcategoria_orden) ? (int) $producto->subcategoria_orden : null,
            'oferta' => isset($producto->oferta) ? (int) $producto->oferta : 0,
            'oferta_texto' => $producto->oferta_texto ?? null,
            'mensaje_whatsapp' => $producto->mensaje_whatsapp ?? null,
            'colores' => $this->decodificarJsonCampo($producto->colores ?? null),
            'atributos' => $this->decodificarJsonCampo($producto->atributos ?? null),
            'relacionados' => $this->decodificarJsonCampo($producto->relacionados ?? null),
            'activo' => isset($producto->activo) ? (int) $producto->activo : null,
            'color' => $producto->color ?? null,
            'url' => ! empty($producto->path)
                ? $this->construirUrlPublicaProducto((string) $producto->path)
                : ($producto->url ?? null),
            'filename' => $producto->filename ?? null,
            'path' => $producto->path ?? null,
            'ficha_url' => ! empty($producto->ficha_path)
                ? $this->construirUrlPublicaProducto((string) $producto->ficha_path)
                : ($producto->ficha_url ?? null),
            'ficha_filename' => $producto->ficha_filename ?? null,
            'ficha_mime_type' => $producto->ficha_mime_type ?? null,
            'hero_url' => ! empty($producto->hero_path)
                ? $this->construirUrlPublicaProducto((string) $producto->hero_path)
                : ($producto->hero_url ?? null),
            'hero_path' => $producto->hero_path ?? null,
            'hero_url_mobile' => ! empty($producto->hero_path_mobile)
                ? $this->construirUrlPublicaProducto((string) $producto->hero_path_mobile)
                : ($producto->hero_url_mobile ?? null),
            'hero_path_mobile' => $producto->hero_path_mobile ?? null,
            'diagrama_url' => ! empty($producto->diagrama_path)
                ? $this->construirUrlPublicaProducto((string) $producto->diagrama_path)
                : ($producto->diagrama_url ?? null),
            'diagrama_path' => $producto->diagrama_path ?? null,
            'precios' => array_map(fn ($p) => $this->formatearPrecio($p), $precios),
        ];
    }

    /**
     * @return list<mixed>
     */
    private function decodificarJsonCampo(mixed $valor): array
    {
        if ($valor === null || $valor === '') {
            return [];
        }

        if (is_array($valor)) {
            return array_values($valor);
        }

        $decoded = json_decode((string) $valor, true);

        return is_array($decoded) ? array_values($decoded) : [];
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
     * Crea o actualiza una fila de precio del producto.
     *
     * @param array<string, mixed> $item
     */
    public function persistirPrecioProducto(int $idProducto, array $item): void
    {
        if (! $this->precioTieneDatos($item)) {
            return;
        }

        $moneda = strtoupper(trim((string) ($item['moneda'] ?? '')));
        if ($moneda === '') {
            throw new \Exception('Cada fila de precio debe indicar "moneda" (PEN o USD).');
        }
        if (! in_array($moneda, self::MONEDAS_PRECIO, true)) {
            throw new \Exception('La moneda debe ser PEN o USD.');
        }

        $data = [
            'id_producto' => $idProducto,
            'espesor' => $this->valorDecimalOpcional($item, 'espesor'),
            'largo' => $this->valorDecimalOpcional($item, 'largo'),
            'desarrollo' => $this->valorDecimalOpcional($item, 'desarrollo'),
            'relleno' => $this->valorDecimalOpcional($item, 'relleno'),
            'tipo' => $this->valorTextoOpcional($item, 'tipo'),
            'color' => $this->valorTextoOpcional($item, 'color'),
            'moneda' => $moneda,
            'precio' => array_key_exists('precio', $item) && $item['precio'] !== '' && $item['precio'] !== null
                ? (float) $item['precio']
                : 0.0,
            'prioridad' => array_key_exists('prioridad', $item) && $item['prioridad'] !== '' && $item['prioridad'] !== null
                ? (int) $item['prioridad']
                : null,
        ];

        $precioModel = $this->getProductoPrecioProvider();
        $idPrecio = (int) ($item['id'] ?? 0);

        if ($idPrecio > 0) {
            $existente = $precioModel->find($idPrecio);
            if (! $existente || (int) $existente->id_producto !== $idProducto) {
                throw new \Exception("Precio {$idPrecio} no encontrado para el producto {$idProducto}.");
            }

            unset($data['id_producto']);
            $precioModel->builder()->where('id', $idPrecio)->update($data);

            return;
        }

        if (! $precioModel->insert($data)) {
            $errores = $precioModel->errors();
            throw new \Exception(! empty($errores) ? json_encode($errores) : 'No se pudo crear el precio.');
        }
    }

    /**
     * Actualiza campos del producto sin fallar cuando solo cambian los precios.
     *
     * @param array<string, mixed> $datos
     */
    private function actualizarRegistroProducto(int $id, array $datos): void
    {
        if ($datos === []) {
            return;
        }

        $producto_model = $this->getProductoProvider();
        $datos['updated_at'] = date('Y-m-d H:i:s');

        $producto_model->builder()->where('id', $id)->update($datos);
    }

    /**
     * @param list<array<string, mixed>> $precios
     */
    private function sincronizarPreciosProducto(int $idProducto, array $precios): void
    {
        if ($precios === []) {
            return;
        }

        foreach ($precios as $item) {
            if (! is_array($item)) {
                throw new \Exception('Cada elemento de "precios" debe ser un objeto.');
            }

            $this->persistirPrecioProducto($idProducto, $item);
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    private function precioTieneDatos(array $item): bool
    {
        $campos = ['espesor', 'largo', 'desarrollo', 'relleno', 'tipo', 'color', 'precio', 'prioridad'];
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
