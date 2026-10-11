<?php

namespace Belly\Conversacion\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;

class ConversacionControllerApi extends BaseController

   
{
    use ResponseTrait;

    /** @return array<string, mixed> */
    private function filtrosRegistroVenta(): array
    {
        return [
            'registro_venta' => $this->request->getGet('registro_venta'),
            'fecha_venta_inicio' => $this->request->getGet('fecha_venta_inicio'),
            'fecha_venta_fin' => $this->request->getGet('fecha_venta_fin'),
        ];
    }

    /** @return array<string, mixed> */
    private function filtroBusquedaMensaje(): array
    {
        return [
            'search_mensaje' => $this->request->getGet('search_mensaje'),
        ];
    }

    public function listar()
    {
        try {
            $usuario = auth()->getUsuario();
            $filtros = array_merge([
                'search' => $this->request->getGet('search'),
                'fechaInicio' => $this->request->getGet('fechaInicio'),
                'fechaFin' => $this->request->getGet('fechaFin'),
                'id_etiqueta' => $this->request->getGet('id_etiqueta'),
                'id_estado' => $this->request->getGet('id_estado'),
                'temperatura' => $this->request->getGet('temperatura'),
                'ventana_24h' => $this->request->getGet('ventana_24h'),
                'origen_canal' => $this->request->getGet('origen_canal'),
            ], $this->filtroBusquedaMensaje(), $this->filtrosRegistroVenta());

            $page = max(1, (int) ($this->request->getGet('page') ?? 1));
            $result = conversaciones()->listarConversacion($usuario->id, $filtros, $page, 'sidebar');

            return $this->respond([
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination'],
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function ver(int $id)
    {
        try {
            $data = conversaciones()->obtenerDatosConversacionCompleta($id);

            return $this->respond([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            $statusCode = stripos($e->getMessage(), 'no encontrada') !== false ? 404 : 400;

            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
    
    public function listarMisConversaciones()
    {
        try {
            $page = (int) ($this->request->getGet('page') ?? 1);
            $perPage = $this->request->getGet('perPage') ?? 10;
            if ($perPage !== 'all') {
                $perPage = (int) $perPage;
            }
            $search = $this->request->getGet('search') ?? null;
            $filtros = array_merge([
                'search'        => $search,
                'id_estado'     => $this->request->getGet('id_estado'),
                'temperatura'   => $this->request->getGet('temperatura'),
                'id_etiqueta'   => $this->request->getGet('id_etiqueta'),
                'fechaInicio'   => $this->request->getGet('fechaInicio'),
                'fechaFin'      => $this->request->getGet('fechaFin'),
                'campaign_id'   => $this->request->getGet('campaign_id'),
                'ventana_24h'   => $this->request->getGet('ventana_24h'),
                'origen_canal'  => $this->request->getGet('origen_canal'),
            ], $this->filtroBusquedaMensaje(), $this->filtrosRegistroVenta());
            $result = conversaciones()->listarMisConversaciones($page, $perPage, null, $filtros);
            return $this->respond([
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination']
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function listarFinalizadas()
    {
        $usuario = auth()->getUsuario();
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $filtros = [
            'origen_canal' => $this->request->getGet('origen_canal'),
            'search' => $this->request->getGet('search'),
        ];
        $result = conversaciones()->listarConversacionFinalizada($usuario->id, $page, $filtros);

        return $this->respond([
            'success' => true,
            'data' => $result['data'],
            'pagination' => $result['pagination'],
        ]);
    }


    public function listarNoLeidos()
    {
        $usuario = auth()->getUsuario();
        $filtros = array_merge([
            'search'        => $this->request->getGet('search'),
            'fechaInicio'   => $this->request->getGet('fechaInicio'),
            'fechaFin'      => $this->request->getGet('fechaFin'),
            'id_etiqueta'   => $this->request->getGet('id_etiqueta'),
            'temperatura'   => $this->request->getGet('temperatura'),
            'ventana_24h'   => $this->request->getGet('ventana_24h'),
            'origen_canal'  => $this->request->getGet('origen_canal'),
        ], $this->filtroBusquedaMensaje(), $this->filtrosRegistroVenta());

        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $result = conversaciones()->conversacionNoLeido($usuario->id, $filtros, $page);

        return $this->respond([
            'success' => true,
            'data' => $result['data'],
            'pagination' => $result['pagination'],
        ]);
    }

    public function listarFaltaResponder()
    {
        $usuario = auth()->getUsuario();
        $filtros = array_merge([
            'search'        => $this->request->getGet('search'),
            'fechaInicio'   => $this->request->getGet('fechaInicio'),
            'fechaFin'      => $this->request->getGet('fechaFin'),
            'id_etiqueta'   => $this->request->getGet('id_etiqueta'),
            'temperatura'   => $this->request->getGet('temperatura'),
            'ventana_24h'   => $this->request->getGet('ventana_24h'),
            'origen_canal'  => $this->request->getGet('origen_canal'),
        ], $this->filtroBusquedaMensaje(), $this->filtrosRegistroVenta());

        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $result = conversaciones()->conversacionFaltaResponder($usuario->id, $filtros, $page);

        return $this->respond([
            'success' => true,
            'data' => $result['data'],
            'pagination' => $result['pagination'],
        ]);
    }

    /**
     * Contadores livianos para badge del menú (sin listar conversaciones).
     */
    public function contadoresSidebar()
    {
        try {
            $usuario = auth()->getUsuario();
            $data = conversaciones()->contadoresSidebarChat((int) $usuario->id);

            return $this->respond([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }


    public function editar(int $id)
    {
        try {
            $datos = $this->request->getJSON(true);
            
            $conversacion = conversaciones()->editarConversacion($id, $datos);
            
            return $this->respond([
                'success' => true,
                'data' => $conversacion
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }

    public function abrir(int $id)
    {
        try {
            $resultado = conversaciones()->abrirConversacion($id);

            return $this->respond([
                'success' => true,
                'data' => $resultado
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function actualizarTemperatura(int $id)
    {
        try {
            $datos = $this->request->getJSON(true);
            $temperatura = $datos['temperatura'] ?? null;

            if (!is_string($temperatura) || trim($temperatura) === '') {
                return $this->respond([
                    'success' => false,
                    'message' => 'El campo temperatura es obligatorio'
                ], 400);
            }

            $resultado = conversaciones()->actualizarTemperaturaConversacion($id, $temperatura);

            return $this->respond([
                'success' => true,
                'data' => $resultado
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function listarVentas()
    {
        try {
            $page = (int) ($this->request->getGet('page') ?? 1);
            $perPage = $this->request->getGet('perPage') ?? 25;
            if ($perPage !== 'all') {
                $perPage = (int) $perPage;
            }

            $filtros = [
                'search' => $this->request->getGet('search'),
                'fechaInicio' => $this->request->getGet('fechaInicio'),
                'fechaFin' => $this->request->getGet('fechaFin'),
                'id_usuario' => $this->request->getGet('id_usuario'),
                'temperatura_anterior' => $this->request->getGet('temperatura_anterior'),
                'id_estado' => $this->request->getGet('id_estado'),
            ];

            $result = conversacion_venta()->listarVentas($page, $perPage, $filtros);

            return $this->respond([
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination'],
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function actualizarEtiqueta(int $id)
    {
        try {
            $datos = $this->request->getJSON(true) ?? [];
            if (!array_key_exists('id_etiqueta', $datos)) {
                return $this->respond([
                    'success' => false,
                    'message' => 'El campo id_etiqueta es requerido (número o null para quitar)',
                ], 400);
            }

            $idEtiqueta = $datos['id_etiqueta'];
            if ($idEtiqueta !== null && $idEtiqueta !== '') {
                $idEtiqueta = (int) $idEtiqueta;
            } else {
                $idEtiqueta = null;
            }

            $opcionesTarea = [];
            if (! empty($datos['fecha_programada'])) {
                $opcionesTarea['fecha_programada'] = trim((string) $datos['fecha_programada']);
            }
            if (! empty($datos['fecha_limite'])) {
                $opcionesTarea['fecha_limite'] = trim((string) $datos['fecha_limite']);
            }
            if (! empty($datos['contexto'])) {
                $opcionesTarea['contexto'] = trim((string) $datos['contexto']);
            }
            if (array_key_exists('valor_monetario', $datos)) {
                $opcionesTarea['valor_monetario'] = $datos['valor_monetario'];
            }
            if (array_key_exists('area_terreno_m2', $datos)) {
                $opcionesTarea['area_terreno_m2'] = $datos['area_terreno_m2'];
            }
            if (! empty($datos['origen_ia'])) {
                $opcionesTarea['origen_ia'] = true;
            }

            $resultado = conversaciones()->actualizarEtiquetaConversacion($id, $idEtiqueta, true, $opcionesTarea);

            return $this->respond([
                'success' => true,
                'data' => $resultado,
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function finalizar(int $id)
    {
        try {
            $datos = $this->request->getJSON(true);
            
            if (!isset($datos['id_motivo']) || empty($datos['id_motivo'])) {
                return $this->respond([
                    'success' => false,
                    'message' => 'Necesita seleccionar un motivo de finalización'
                ], 400);
            }

            $resultado = conversaciones()->finalizarConversacion($id, $datos);
            
            return $this->respond([
                'success' => true,
                'data' => $resultado
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function listarFinalizadasPorCliente(int $id_cliente)
    {
        try {
            $conversaciones = conversaciones()->listarConversacionesFinalizadasPorCliente($id_cliente);
            
            return $this->respond([
                'success' => true,
                'data' => $conversaciones
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'data' => $e->getMessage()
            ]);
        }
    }

    public function listarCampaniasMetaFiltro()
    {
        try {
            $data = conversaciones()->listarCampaniasMetaFiltroContactCenter();

            return $this->respond([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function listarCampaniasMeta()
    {
        try {
            $idApp = (int) ($this->request->getGet('id_app') ?? 0);
            if ($idApp <= 0) {
                throw new \Exception('El parámetro id_app es obligatorio');
            }

            $resultado = conversaciones()->listarCampaniasMetaDesdeApi($idApp, [
                'ad_account_id' => $this->request->getGet('ad_account_id'),
                'after' => $this->request->getGet('after'),
                'limit' => $this->request->getGet('limit'),
                'limit_por_cuenta' => $this->request->getGet('limit_por_cuenta'),
                'max_ad_accounts' => $this->request->getGet('max_ad_accounts'),
                'effective_status' => $this->request->getGet('effective_status'),
                'solo_cuentas' => filter_var($this->request->getGet('solo_cuentas'), FILTER_VALIDATE_BOOLEAN),
            ]);

            return $this->respond([
                'success' => true,
                'data' => $resultado['data'],
                'ad_accounts' => $resultado['ad_accounts'] ?? [],
                'pagination' => $resultado['pagination'] ?? null,
                'paging' => $resultado['paging'] ?? null,
                'meta' => [
                    'id_app' => $resultado['id_app'] ?? $idApp,
                    'app_nombre' => $resultado['app_nombre'] ?? null,
                    'total' => $resultado['total'] ?? 0,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function listarAsesoresFiltro()
    {
        try {
            $tipo = $this->request->getGet('tipo');
            $data = conversaciones()->listarAsesoresParaFiltroListado(
                is_string($tipo) && $tipo !== '' ? $tipo : null
            );

            return $this->respond([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function listarContactCenter()
    {
        try {
            $page = (int) ($this->request->getGet('page') ?? 1);
            $perPage = $this->request->getGet('perPage') ?? 10;
            
            if ($perPage !== 'all') {
                $perPage = (int) $perPage;
            }
            
            $filtros = array_merge([
                'id_estado' => $this->request->getGet('id_estado'),
                'temperatura' => $this->request->getGet('temperatura'),
                'id_etiqueta' => $this->request->getGet('id_etiqueta'),
                'campaign_id' => $this->request->getGet('campaign_id'),
                'search' => $this->request->getGet('search'),
                'fechaInicio' => $this->request->getGet('fechaInicio'),
                'fechaFin' => $this->request->getGet('fechaFin'),
                'excluir_id_usuario' => $this->request->getGet('excluir_id_usuario'),
                'ventana_24h' => $this->request->getGet('ventana_24h'),
                'origen_canal' => $this->request->getGet('origen_canal'),
            ], $this->filtroBusquedaMensaje(), $this->filtrosRegistroVenta());
            
            $conversaciones = conversaciones()->listarConversacionesContactCenter($page, $perPage, $filtros);
            
            return $this->respond([
                'success' => true,
                'data' => $conversaciones['data'],
                'pagination' => $conversaciones['pagination']
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function listarCustomerSuccess()
    {
        try {
            $page = (int) ($this->request->getGet('page') ?? 1);
            $perPage = $this->request->getGet('perPage') ?? 10;
            
            if ($perPage !== 'all') {
                $perPage = (int) $perPage;
            }
            
            $filtros = array_merge([
                'id_estado' => $this->request->getGet('id_estado'),
                'id_etiqueta' => $this->request->getGet('id_etiqueta'),
                'search' => $this->request->getGet('search'),
                'fechaInicio' => $this->request->getGet('fechaInicio'),
                'fechaFin' => $this->request->getGet('fechaFin'),
                'excluir_id_usuario' => $this->request->getGet('excluir_id_usuario'),
                'ventana_24h' => $this->request->getGet('ventana_24h'),
                'temperatura' => $this->request->getGet('temperatura'),
                'origen_canal' => $this->request->getGet('origen_canal'),
            ], $this->filtroBusquedaMensaje(), $this->filtrosRegistroVenta());
            
            $conversaciones = conversaciones()->listarConversacionesCustomerSuccess($page, $perPage, $filtros);
            
            return $this->respond([
                'success' => true,
                'data' => $conversaciones['data'],
                'pagination' => $conversaciones['pagination']
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }


    public function transferir(int $id)
    {
        try {
            $data = $this->request->getJSON(true);
            
            if (!isset($data['id_usuario_destino']) || empty($data['id_usuario_destino'])) {
                return $this->respond([
                    'success' => false,
                    'error' => 'El id_usuario_destino es obligatorio'
                ], 400);
            }

            $id_usuario_destino = (int) $data['id_usuario_destino'];
            $id_usuario_origen = isset($data['id_usuario_origen']) ? (int) $data['id_usuario_origen'] : null;

            $resultado = conversaciones()->transferirConversacion($id, $id_usuario_destino, $id_usuario_origen);

            return $this->respond([
                'success' => true,
                'data' => $resultado
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function transferirMasivo()
    {
        try {
            $data = $this->request->getJSON(true);
            
            if (!isset($data['ids_conversaciones']) || !is_array($data['ids_conversaciones']) || empty($data['ids_conversaciones'])) {
                return $this->respond([
                    'success' => false,
                    'error' => 'Debe proporcionar un array de ids_conversaciones'
                ], 400);
            }

            if (!isset($data['id_usuario_destino']) || empty($data['id_usuario_destino'])) {
                return $this->respond([
                    'success' => false,
                    'error' => 'El id_usuario_destino es obligatorio'
                ], 400);
            }

            $ids_conversaciones = array_map('intval', $data['ids_conversaciones']);
            $id_usuario_destino = (int) $data['id_usuario_destino'];
            $id_usuario_origen = isset($data['id_usuario_origen']) ? (int) $data['id_usuario_origen'] : null;

            $resultado = conversaciones()->transferirConversacionesMasivo($ids_conversaciones, $id_usuario_destino, $id_usuario_origen);

            return $this->respond([
                'success' => true,
                'data' => $resultado,
                'message' => "Transferencia completada: {$resultado['exitosos']} exitosos, {$resultado['errores']} errores"
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function obtenerUsuariosDisponibles(int $id_app)
    {
        try {
            $usuarios = conversaciones()->obtenerUsuariosDisponiblesParaTransferencia($id_app);
            return $this->respond([
                'success' => true,
                'data' => $usuarios
            ]);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function exportar()
    {
        try {
            $usuario = auth()->getUsuario();
            $tipo = trim((string) ($this->request->getGet('tipo') ?? 'listar'));
            $alcance = trim((string) ($this->request->getGet('alcance') ?? 'filtrados'));
            $columnasRaw = trim((string) ($this->request->getGet('columnas') ?? ''));
            $idsRaw = trim((string) ($this->request->getGet('ids_conversaciones') ?? ''));

            $filtros = array_merge([
                'id_estado' => $this->request->getGet('id_estado'),
                'temperatura' => $this->request->getGet('temperatura'),
                'id_etiqueta' => $this->request->getGet('id_etiqueta'),
                'campaign_id' => $this->request->getGet('campaign_id'),
                'search' => $this->request->getGet('search'),
                'fechaInicio' => $this->request->getGet('fechaInicio'),
                'fechaFin' => $this->request->getGet('fechaFin'),
                'excluir_id_usuario' => $this->request->getGet('excluir_id_usuario'),
                'ventana_24h' => $this->request->getGet('ventana_24h'),
                'origen_canal' => $this->request->getGet('origen_canal'),
            ], $this->filtroBusquedaMensaje(), $this->filtrosRegistroVenta());

            $columnKeys = $columnasRaw !== ''
                ? array_values(array_filter(array_map('trim', explode(',', $columnasRaw))))
                : [];

            $idsSeleccionados = [];
            if ($idsRaw !== '') {
                $idsSeleccionados = array_values(array_unique(array_filter(
                    array_map('intval', explode(',', $idsRaw)),
                    static fn ($id) => $id > 0
                )));
            }

            $resultado = conversacion_export()->exportar(
                $tipo,
                (int) $usuario->id,
                $filtros,
                $alcance,
                $idsSeleccionados,
                $columnKeys
            );

            return $this->response
                ->download($resultado['filename'], $resultado['content'], true)
                ->setHeader('X-Export-Total', (string) $resultado['total']);
        } catch (\Exception $e) {
            return $this->respond([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

}