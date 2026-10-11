<?php

namespace Belly\Conversacion\UseCases;

use Belly\Conversacion\Config\ConversacionConfig;
use Belly\Conversacion\Entities\ConversacionVentaEntity;
use Belly\Conversacion\Models\ConversacionVentaModel;

class ConversacionVentaUseCase
{
    protected ConversacionConfig $config;
    protected ?ConversacionVentaModel $provider = null;

    public function __construct(ConversacionConfig $config)
    {
        $this->config = $config;
    }

    public function getProvider(): ConversacionVentaModel
    {
        if ($this->provider !== null) {
            return $this->provider;
        }

        $class = $this->config->conversacion_venta_provider;
        $this->provider = new $class();

        return $this->provider;
    }

    /**
     * Registra la venta al marcar temperatura "venta". Idempotente por conversación.
     */
    public function registrarDesdeConversacion(
        int $idConversacion,
        int $idCliente,
        int $idUsuario,
        ?int $idUsuarioRegistro,
        ?string $temperaturaAnterior = null,
    ): ConversacionVentaEntity {
        $model = $this->getProvider();

        $existente = $model->where('id_conversacion', $idConversacion)->first();
        if ($existente !== null) {
            return $existente;
        }

        $ahora = date('Y-m-d H:i:s');
        $entity = new ConversacionVentaEntity([
            'id_conversacion'      => $idConversacion,
            'id_cliente'           => $idCliente,
            'id_usuario'           => $idUsuario,
            'id_usuario_registro'  => $idUsuarioRegistro,
            'temperatura_anterior' => $temperaturaAnterior !== null && $temperaturaAnterior !== ''
                ? trim(strtolower($temperaturaAnterior))
                : null,
            'registrada_at'        => $ahora,
        ]);

        if (! $model->save($entity)) {
            throw new \Exception(json_encode($model->errors()) ?: 'No se pudo registrar la venta');
        }

        $entity->id = (int) $model->getInsertID();

        return $entity;
    }

    /**
     * @param array<string, mixed> $filtros
     *
     * @return array{data: list<mixed>, pagination: array<string, mixed>}
     */
    public function listarVentas(int $page = 1, $perPage = 25, array $filtros = []): array
    {
        $model = $this->getProvider();

        $model
            ->select('conversacion_venta.*,
                conversacion.id_estado,
                conversacion.temperatura,
                conversacion.id_app,
                cliente.telefono,
                cliente_perfil.nombre as nombre_cliente,
                cliente_perfil.apellido as apellido_cliente,
                cliente_perfil.nombre_completo as nombre_completo_cliente,
                usuario.usuario as nombre_usuario,
                usuario_perfil.nombres as nombres_usuario,
                usuario_perfil.apellidos as apellidos_usuario,
                app.nombre as nombre_app')
            ->join('conversacion', 'conversacion.id = conversacion_venta.id_conversacion')
            ->join('cliente', 'cliente.id = conversacion_venta.id_cliente')
            ->join('cliente_perfil', 'cliente_perfil.id_cliente = cliente.id', 'left')
            ->join('usuario', 'usuario.id = conversacion_venta.id_usuario')
            ->join('usuario_perfil', 'usuario_perfil.id_usuario = usuario.id', 'left')
            ->join('app', 'app.id = conversacion.id_app', 'left');

        if (! empty($filtros['id_usuario'])) {
            $model->where('conversacion_venta.id_usuario', (int) $filtros['id_usuario']);
        }

        if (! empty($filtros['search'])) {
            $search = trim((string) $filtros['search']);
            $model->groupStart();
            $model->like('cliente.telefono', $search);
            $model->like('cliente_perfil.nombre', $search);
            $model->like('cliente_perfil.apellido', $search);
            $model->like('cliente_perfil.nombre_completo', $search);
            $model->like('usuario.usuario', $search);
            $model->like('usuario_perfil.nombres', $search);
            $model->like('usuario_perfil.apellidos', $search);
            $model->groupEnd();
        }

        if (! empty($filtros['fechaInicio'])) {
            $model->where('DATE(conversacion_venta.registrada_at) >=', $filtros['fechaInicio']);
        }
        if (! empty($filtros['fechaFin'])) {
            $model->where('DATE(conversacion_venta.registrada_at) <=', $filtros['fechaFin']);
        }

        if (! empty($filtros['temperatura_anterior'])) {
            $model->where('conversacion_venta.temperatura_anterior', trim((string) $filtros['temperatura_anterior']));
        }

        if (isset($filtros['id_estado']) && $filtros['id_estado'] !== '' && $filtros['id_estado'] !== null) {
            $model->where('conversacion.id_estado', (int) $filtros['id_estado']);
        }

        $model->orderBy('conversacion_venta.registrada_at', 'DESC');

        return $this->ejecutarListadoPaginado($model, $page, $perPage);
    }

    /**
     * @return array{data: list<mixed>, pagination: array<string, mixed>}
     */
    private function ejecutarListadoPaginado($model, int $page, $perPage): array
    {
        if ($perPage === 'all') {
            $result = $model->findAll();
            $total = count($result);

            return [
                'data' => $result,
                'pagination' => [
                    'total' => $total,
                    'currentPage' => 1,
                    'perPage' => 'all',
                    'from' => $total > 0 ? 1 : 0,
                    'to' => $total,
                    'hasNext' => false,
                    'hasPrev' => false,
                ],
            ];
        }

        $perPage = max(5, min(100, (int) $perPage));
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $total = (int) $model->countAllResults(false);
        $result = $model->findAll($perPage, $offset);
        $lastPage = max(1, (int) ceil($total / $perPage));

        return [
            'data' => $result,
            'pagination' => [
                'total' => $total,
                'currentPage' => $page,
                'perPage' => $perPage,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => $total > 0 ? min($total, $offset + count($result)) : 0,
                'hasNext' => $page < $lastPage,
                'hasPrev' => $page > 1,
            ],
        ];
    }
}
