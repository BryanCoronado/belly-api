<?php

namespace Belly\Crm\Controllers\Api;

use App\Controllers\BaseController;
use Belly\Crm\Services\ConsultaClientes;

class CrmControllerApi extends BaseController
{
    // ----- Clientes -----

    public function listarClientes()
    {
        return $this->ejecutar(fn () => crm_clientes()->listar(
            ConsultaClientes::filtrosDe($this->request->getGet()),
            (int) ($this->request->getGet('page') ?? 1),
            (int) ($this->request->getGet('perPage') ?? 25)
        ), conPaginacion: true);
    }

    public function tablero()
    {
        return $this->ejecutar(fn () => crm_clientes()->tablero(ConsultaClientes::filtrosDe($this->request->getGet())));
    }

    public function verCliente(int $id)
    {
        return $this->ejecutar(static fn () => crm_clientes()->ficha($id));
    }

    public function actualizarCliente(int $id)
    {
        return $this->ejecutar(fn () => crm_clientes()->actualizar($id, $this->datos()));
    }

    public function actualizarVarios()
    {
        $datos = $this->datos();

        return $this->ejecutar(static fn () => ['actualizados' => crm_clientes()->actualizarVarios((array) ($datos['ids'] ?? []), (array) ($datos['cambios'] ?? []))]);
    }

    public function crearCliente()
    {
        return $this->ejecutar(fn () => crm_alta()->crear($this->datos()), 201);
    }

    public function iniciarChat(int $id)
    {
        return $this->ejecutar(static fn () => crm_alta()->iniciarChat($id));
    }

    public function importarClientes()
    {
        $datos = $this->datos();

        return $this->ejecutar(static fn () => crm_alta()->importar(array_values((array) ($datos['filas'] ?? [])), (array) ($datos['opciones'] ?? [])));
    }

    // ----- Ajustes -----

    public function ajustes()
    {
        return $this->ejecutar(static fn () => crm_ajustes()->todo());
    }

    public function dashboard()
    {
        return $this->ejecutar(fn () => crm_dashboard()->resumen((int) ($this->request->getGet('dias') ?? 14)));
    }

    public function crearEtapa()
    {
        return $this->ejecutar(fn () => crm_ajustes()->guardarEtapa(null, $this->datos()), 201);
    }

    public function actualizarEtapa(int $id)
    {
        return $this->ejecutar(fn () => crm_ajustes()->guardarEtapa($id, $this->datos()));
    }

    public function ordenarEtapas()
    {
        return $this->ejecutar(fn () => crm_ajustes()->ordenarEtapas((array) ($this->datos()['ids'] ?? [])));
    }

    public function eliminarEtapa(int $id)
    {
        $moverA = $this->request->getGet('mover_a');

        return $this->ejecutar(static fn () => crm_ajustes()->eliminarEtapa($id, $moverA !== null ? (int) $moverA : null));
    }

    public function crearTipo()
    {
        return $this->ejecutar(fn () => crm_ajustes()->guardarTipo(null, $this->datos()), 201);
    }

    public function actualizarTipo(int $id)
    {
        return $this->ejecutar(fn () => crm_ajustes()->guardarTipo($id, $this->datos()));
    }

    public function ordenarTipos()
    {
        return $this->ejecutar(fn () => crm_ajustes()->ordenarTipos((array) ($this->datos()['ids'] ?? [])));
    }

    public function eliminarTipo(int $id)
    {
        return $this->ejecutar(static fn () => crm_ajustes()->eliminarTipo($id));
    }

    public function crearEtiqueta()
    {
        return $this->ejecutar(fn () => crm_ajustes()->guardarEtiqueta(null, $this->datos()), 201);
    }

    public function actualizarEtiqueta(int $id)
    {
        return $this->ejecutar(fn () => crm_ajustes()->guardarEtiqueta($id, $this->datos()));
    }

    public function eliminarEtiqueta(int $id)
    {
        return $this->ejecutar(static fn () => crm_ajustes()->eliminarEtiqueta($id));
    }

    public function crearMotivo()
    {
        return $this->ejecutar(fn () => crm_ajustes()->guardarMotivo(null, $this->datos()), 201);
    }

    public function actualizarMotivo(int $id)
    {
        return $this->ejecutar(fn () => crm_ajustes()->guardarMotivo($id, $this->datos()));
    }

    public function ordenarMotivos()
    {
        return $this->ejecutar(fn () => crm_ajustes()->ordenarMotivos((array) ($this->datos()['ids'] ?? [])));
    }

    public function eliminarMotivo(int $id)
    {
        return $this->ejecutar(static fn () => crm_ajustes()->eliminarMotivo($id));
    }

    // ----- Envíos masivos -----

    public function audiencia()
    {
        return $this->ejecutar(fn () => crm_envios()->audiencia((array) ($this->datos()['filtros'] ?? [])));
    }

    public function plantillas()
    {
        return $this->ejecutar(static fn () => crm_envios()->plantillas());
    }

    public function listarEnvios()
    {
        return $this->ejecutar(static fn () => crm_envios()->listar());
    }

    public function verEnvio(int $id)
    {
        return $this->ejecutar(static fn () => crm_envios()->ver($id));
    }

    public function crearEnvio()
    {
        return $this->ejecutar(fn () => crm_envios()->crear($this->datos()), 201);
    }

    public function cancelarEnvio(int $id)
    {
        return $this->ejecutar(static fn () => crm_envios()->cancelar($id));
    }

    /** @return array<string, mixed> */
    private function datos(): array
    {
        $datos = $this->request->getJSON(true);

        return is_array($datos) ? $datos : [];
    }

    private function ejecutar(callable $accion, int $codigoOk = 200, bool $conPaginacion = false)
    {
        try {
            $resultado = $accion();
            $cuerpo = $conPaginacion
                ? ['success' => true, 'data' => $resultado['data'], 'pagination' => $resultado['pagination']]
                : ['success' => true, 'data' => $resultado];

            return $this->response->setStatusCode($codigoOk)->setJSON($cuerpo);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            log_message('error', '[CRM] ' . $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine());

            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'No se pudo completar la acción.']);
        }
    }
}
