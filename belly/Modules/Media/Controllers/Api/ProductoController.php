<?php

namespace Belly\Media\Controllers\Api;

use App\Controllers\BaseController;

class ProductoController extends BaseController{
    
    public function crear() {
        try {
            $data = $this->request->getJSON(true);
            $producto = producto()->crearProducto($data);
            return $this->response->setJSON([
                        'success' => true,
                        'data' => $producto
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                        'success' => false,
                        'error' => $e->getMessage()
                    ])->setStatusCode(400);
        }
    }


    public function crearConCatalogo()
    {
        try {
            $file = $this->request->getFile('archivo');

            if (!$file || !$file->isValid()) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'No se recibió un archivo válido. Usa el campo "archivo" en form-data.',
                ])->setStatusCode(400);
            }

            $datosPost = $this->datosProductoDesdePost();
            $ficha = $this->request->getFile('ficha');

            $resultado = producto()->crearProductoConCatalogo($file, $datosPost, $ficha);

            if (empty($resultado['success'])) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => $resultado['error'] ?? 'Error al crear el producto.',
                    'data' => isset($resultado['id']) ? ['id' => $resultado['id'], 'nombre' => $resultado['nombre'] ?? null] : null,
                ])->setStatusCode(400);
            }

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

    public function listar(){
        $page = $this->request-> getGet('page') ?? 1;
        $perPage = $this->request->getGet('perPage') ?? 10;
        $search = $this->request->getGet('search') ?? null;
        $search = ($search && strlen($search) > 2) ? $search : null;
        $mostrarWeb = $this->request->getGet('mostrar_web');
        $mostrarWeb = ($mostrarWeb === null || $mostrarWeb === '') ? null : (int) $mostrarWeb;
        $idGrupo = $this->request->getGet('id_grupo_producto');
        $idGrupo = ($idGrupo === null || $idGrupo === '') ? null : (int) $idGrupo;

        $productos = producto()->listarProductos($page, $perPage, $search, $mostrarWeb, $idGrupo);

        return $this->response->setJSON([
            'success' => true,
            'data' => $productos['data'] ?? [],
            'pagination' => $productos['pagination'] ?? null,
        ]);
    }

    public function ver(int $id)
    {
        try {
            $producto = producto()->getById($id);

            if (!$producto) {
                return $this->response
                    ->setStatusCode(404)
                    ->setJSON([
                        'success' => false,
                        'message' => 'Producto no encontrado',
                        'data' => null
                    ]);
            }

            return $this->response->setJSON([
                'success' => true,
                'data' => $producto
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $e->getMessage()
            ])->setStatusCode(404);
        }
    }

    public function listarPorGrupo(int $id_grupo_producto)
    {
        try {
            $productos = producto()->listarPorGrupo($id_grupo_producto);

            return $this->response->setJSON([
                'success' => true,
                'data' => $productos
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
            $producto = producto()->editarProducto($data, $id);

            return $this->response->setJSON([
                'success' => true,
                'data' => $producto
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage()
            ])->setStatusCode(400);
        }
    }

    public function editarConCatalogo(int $id)
    {
        try {
            $id = (int) $id;
            if ($id <= 0) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'ID inválido',
                ])->setStatusCode(400);
            }

            $file = $this->request->getFile('archivo');
            $tieneArchivoValido = $file && $file->isValid() && !$file->hasMoved();

            $datosPost = $this->datosProductoDesdePost();
            $ficha = $this->request->getFile('ficha');
            $hero = $this->request->getFile('archivo_hero');
            $heroMobile = $this->request->getFile('archivo_hero_mobile');
            $diagrama = $this->request->getFile('archivo_diagrama');

            $resultado = producto()->editarProductoConCatalogo(
                $id,
                $tieneArchivoValido ? $file : null,
                $datosPost,
                $ficha,
                $hero && $hero->isValid() && ! $hero->hasMoved() ? $hero : null,
                $heroMobile && $heroMobile->isValid() && ! $heroMobile->hasMoved() ? $heroMobile : null,
                $diagrama && $diagrama->isValid() && ! $diagrama->hasMoved() ? $diagrama : null,
            );

            if (empty($resultado['success'])) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => $resultado['error'] ?? 'Error al actualizar el producto.',
                ])->setStatusCode(400);
            }

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

    /**
     * POST multipart: fondo del hero de ficha pública (escritorio y/o móvil).
     */
    public function editarHeroMedia(int $id)
    {
        try {
            $id = (int) $id;
            if ($id <= 0) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'ID inválido',
                ])->setStatusCode(400);
            }

            $hero = $this->request->getFile('archivo_hero');
            $heroMobile = $this->request->getFile('archivo_hero_mobile');
            $diagrama = $this->request->getFile('archivo_diagrama');
            $quitarHero = (string) ($this->request->getPost('quitar_hero') ?? '') === '1';
            $quitarHeroMobile = (string) ($this->request->getPost('quitar_hero_mobile') ?? '') === '1';
            $quitarDiagrama = (string) ($this->request->getPost('quitar_diagrama') ?? '') === '1';

            $resultado = producto()->editarHeroMedia(
                $id,
                $hero && $hero->isValid() && ! $hero->hasMoved() ? $hero : null,
                $heroMobile && $heroMobile->isValid() && ! $heroMobile->hasMoved() ? $heroMobile : null,
                $quitarHero,
                $quitarHeroMobile,
                $diagrama && $diagrama->isValid() && ! $diagrama->hasMoved() ? $diagrama : null,
                $quitarDiagrama
            );

            if (empty($resultado['success'])) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => $resultado['error'] ?? 'Error al actualizar el hero.',
                ])->setStatusCode(400);
            }

            return $this->response->setJSON([
                'success' => true,
                'data' => $resultado['data'] ?? null,
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage(),
            ])->setStatusCode(400);
        }
    }

    /**
     * Campos multipart del catálogo. Solo incluye los enviados para no
     * sobrescribir con vacío lo que el formulario no manda.
     *
     * @return array<string, mixed>
     */
    private function datosProductoDesdePost(): array
    {
        $post = $this->request->getPost();
        if (! is_array($post)) {
            $post = [];
        }

        $campos = [
            'nombre', 'descripcion', 'tipo_calculo', 'activo', 'id_grupo_producto',
            'especificaciones_tecnicas', 'color', 'precios',
            'slug', 'mostrar_web', 'descripcion_web', 'seo_title', 'seo_description',
            'especificaciones_web', 'caracteristicas_web',
            'subcategoria', 'subcategoria_orden', 'prioridad',
            'oferta', 'oferta_texto', 'mensaje_whatsapp', 'colores', 'atributos', 'relacionados',
        ];

        $datos = [];
        foreach ($campos as $campo) {
            if (array_key_exists($campo, $post)) {
                $datos[$campo] = $post[$campo];
            }
        }

        return $datos;
    }

    public function eliminar(int $id)
    {
        try {
            $resultado = producto()->eliminarProducto($id);

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
