<?php

namespace Belly\Catalogo\Controllers\Api;

use App\Controllers\BaseController;

class CatalogoControllerApi extends BaseController
{
    // ----- Familias -----

    public function listarFamilias()
    {
        return $this->ejecutar(static fn () => catalogo_familias()->listar());
    }

    public function crearFamilia()
    {
        return $this->ejecutar(fn () => catalogo_familias()->crear($this->datos()), 201);
    }

    public function actualizarFamilia(int $id)
    {
        return $this->ejecutar(fn () => catalogo_familias()->actualizar($id, $this->datos()));
    }

    public function eliminarFamilia(int $id)
    {
        return $this->ejecutar(static function () use ($id) {
            catalogo_familias()->eliminar($id);

            return ['eliminado' => true];
        });
    }

    // ----- Productos -----

    public function listarProductos()
    {
        return $this->ejecutar(fn () => catalogo_productos()->listar([
            'q' => $this->request->getGet('q'),
            'id_familia' => $this->request->getGet('id_familia'),
            'activo' => $this->request->getGet('activo'),
        ]));
    }

    public function verProducto(int $id)
    {
        return $this->ejecutar(static fn () => catalogo_productos()->obtener($id));
    }

    public function crearProducto()
    {
        return $this->ejecutar(fn () => catalogo_productos()->crear($this->datos()), 201);
    }

    public function actualizarProducto(int $id)
    {
        return $this->ejecutar(fn () => catalogo_productos()->actualizar($id, $this->datos()));
    }

    public function cambiarEstadoProducto(int $id)
    {
        return $this->ejecutar(fn () => catalogo_productos()->cambiarEstado($id, (bool) ($this->datos()['activo'] ?? false)));
    }

    public function eliminarProducto(int $id)
    {
        return $this->ejecutar(static function () use ($id) {
            catalogo_productos()->eliminar($id);

            return ['eliminado' => true];
        });
    }

    public function subirImagen()
    {
        return $this->ejecutar(fn () => catalogo_imagenes()->subir($this->request->getFile('imagen')), 201);
    }

    public function verVitrina()
    {
        return $this->ejecutar(static fn () => catalogo_vitrina()->deEmpresa());
    }

    public function guardarVitrina()
    {
        return $this->ejecutar(fn () => catalogo_vitrina()->guardar($this->datos()));
    }

    public function verLogoVitrina()
    {
        try {
            $logo = catalogo_vitrina()->logoBinario();

            return $this->response
                ->setHeader('Content-Type', $logo['mime'])
                ->setHeader('Cache-Control', 'private, max-age=60')
                ->setBody($logo['bytes']);
        } catch (\DomainException $e) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            log_message('error', '[CATALOGO] ' . $e->getMessage());

            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'No se pudo abrir el logo.']);
        }
    }

    public function publico(string $slug)
    {
        try {
            return $this->response->setJSON(['success' => true, 'data' => catalogo_vitrina()->publico($slug)]);
        } catch (\DomainException $e) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            log_message('error', '[CATALOGO] ' . $e->getMessage());

            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'No se pudo abrir el catálogo.']);
        }
    }

    public function listarPedidos()
    {
        return $this->ejecutar(static fn () => catalogo_pedidos()->listar((int) (request()->getGet('limite') ?? 50)));
    }

    public function cambiarEstadoPedido(int $id)
    {
        return $this->ejecutar(function () use ($id) {
            $estado = (string) ($this->datos()['estado'] ?? 'nuevo');

            return catalogo_pedidos()->cambiarEstado($id, $estado);
        });
    }

    public function seo(string $slug)
    {
        try {
            $producto = $this->request->getGet('producto');

            return $this->response->setJSON([
                'success' => true,
                'data' => catalogo_vitrina()->tarjeta($slug, $producto !== null && $producto !== '' ? (int) $producto : null),
            ]);
        } catch (\DomainException $e) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            log_message('error', '[CATALOGO] ' . $e->getMessage());

            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'No se pudo armar la ficha.']);
        }
    }

    /** @return array<string, mixed> */
    private function datos(): array
    {
        $datos = $this->request->getJSON(true);

        return is_array($datos) ? $datos : [];
    }

    private function ejecutar(callable $accion, int $codigoOk = 200)
    {
        try {
            return $this->response->setStatusCode($codigoOk)->setJSON(['success' => true, 'data' => $accion()]);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            log_message('error', '[CATALOGO] ' . $e->getMessage());

            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'No se pudo completar la acción.']);
        }
    }
}
