<?php

namespace Belly\Media\Controllers\Api;

use App\Controllers\BaseController;

class GrupoProductoController extends BaseController
{
    public function crear()
    {
        try {
            $file = $this->request->getFile('archivo');
            $tieneArchivo = $file && $file->isValid() && (int) $file->getError() === UPLOAD_ERR_OK;

            if ($tieneArchivo || $this->request->getPost() !== []) {
                $datos = $this->datosGrupoDesdePost();
                $grupo_producto = $tieneArchivo
                    ? grupo_producto()->crearGrupoProductoConImagen($file, $datos)
                    : grupo_producto()->crearGrupoProducto($datos);
            } else {
                $data = $this->request->getJSON(true) ?? [];
                $grupo_producto = grupo_producto()->crearGrupoProducto(is_array($data) ? $data : []);
            }

            return $this->response->setJSON([
                'success' => true,
                'data' => $grupo_producto,
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage(),
            ])->setStatusCode(400);
        }
    }

    public function listar()
    {
        $page = $this->request->getGet('page') ?? 1;
        $perPage = $this->request->getGet('perPage') ?? 10;
        $search = $this->request->getGet('search') ?? null;
        $search = ($search && strlen($search) > 2) ? $search : null;
        $mostrarWeb = $this->request->getGet('mostrar_web');
        $mostrarWeb = ($mostrarWeb === null || $mostrarWeb === '') ? null : (int) $mostrarWeb;

        $grupo_productos = grupo_producto()->listarGrupoProductos($page, $perPage, $search, $mostrarWeb);

        return $this->response->setJSON([
            'success' => true,
            'data' => $grupo_productos['data'] ?? [],
            'pagination' => $grupo_productos['pagination'] ?? null,
        ]);
    }

    public function editar(int $id)
    {
        try {
            $data = $this->request->getJSON(true);
            $grupo_producto = grupo_producto()->editarGrupoProducto(is_array($data) ? $data : [], $id);

            return $this->response->setJSON([
                'success' => true,
                'data' => $grupo_producto,
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => $e->getMessage(),
            ])->setStatusCode(400);
        }
    }

    /**
     * POST multipart: datos + archivo opcional (portada web).
     */
    public function editarMedia(int $id)
    {
        try {
            $file = $this->request->getFile('archivo');
            $tieneArchivo = $file && $file->isValid() && (int) $file->getError() === UPLOAD_ERR_OK;
            $datos = $this->datosGrupoDesdePost();

            $grupo_producto = grupo_producto()->editarGrupoProductoConImagen(
                $id,
                $tieneArchivo ? $file : null,
                $datos
            );

            return $this->response->setJSON([
                'success' => true,
                'data' => $grupo_producto,
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
            $resultado = grupo_producto()->eliminarGrupoProducto($id);

            return $this->response
                ->setStatusCode(200)
                ->setJSON([
                    'success' => true,
                    'data' => $resultado,
                ]);
        } catch (\Exception $e) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
        }
    }

    public function ver(int $id)
    {
        try {
            $grupo_producto = grupo_producto()->getById($id);

            return $this->response
                ->setStatusCode(200)
                ->setJSON([
                    'success' => true,
                    'data' => $grupo_producto,
                ]);
        } catch (\Exception $e) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function datosGrupoDesdePost(): array
    {
        $post = $this->request->getPost();
        if (! is_array($post)) {
            $post = [];
        }

        $out = [];
        foreach ([
            'nombre', 'descripcion', 'color', 'slug', 'descripcion_web',
            'seo_title', 'seo_description', 'mensaje_whatsapp', 'activo', 'mostrar_web', 'prioridad',
            'subcategorias',
        ] as $key) {
            if (array_key_exists($key, $post)) {
                $out[$key] = $post[$key];
            }
        }

        return $out;
    }
}
