<?php
namespace Belly\Clientes\UseCases;

use Belly\Clientes\Config\ClientesConfig;
use Belly\Clientes\Models\ClienteModel;
use Belly\Clientes\Entities\ClienteEntity;
use Belly\Clientes\Models\ClientePerfilModel;
use Belly\Clientes\Entities\ClientePerfilEntity;

class ClienteUseCase {
    protected ClientesConfig $config;
    protected ?ClienteModel $cliente_provider = NULL;
    protected ?ClientePerfilModel $cliente_perfil_provider = NULL;

    public function __construct(ClientesConfig $config)
    {
        $this->config = $config;
    }

    public function getClienteProvider(): ClienteModel
    {
        if ($this->cliente_provider !== null) {
            return $this->cliente_provider;
        }

        $class_name = $this->config->cliente_provider;
        $this->cliente_provider = new $class_name();

        return $this->cliente_provider;
    }

    public function getClientePerfilProvider(): ClientePerfilModel
    {
        if ($this->cliente_perfil_provider !== null) {
            return $this->cliente_perfil_provider;
        }

        $class_name = $this->config->cliente_perfil_provider;
        $this->cliente_perfil_provider = new $class_name();

        return $this->cliente_perfil_provider;
    }




    public function getById(int $id)
    {
        $cliente_model = $this->getClienteProvider();
        $cliente_model->select([
            'cliente.id',
            'cliente.telefono', 
            'cliente.created_at',
            'cliente.updated_at',
            'cp.nombre',
            'cp.apellido', 
            'cp.nombre_completo',
            'cp.marca',
            'cp.modelo',
            'cp.placa',
            'cp.correo',
            'cp.distrito',
            'cp.tipo'
        ]);
        $cliente_model->join('cliente_perfil as cp', 'cp.id_cliente = cliente.id', 'left');
        $cliente = $cliente_model->find($id);
        if (isset($cliente) && !empty($cliente)) {
            return $cliente;
        }
        return null;
    }
   
    /**
     * Cliente WhatsApp: match por teléfono y/o BSUID (wa_user_id), con backfill.
     *
     * @param array{telefono?:?string,wa_user_id?:?string,wa_parent_user_id?:?string,nombre?:?string} $datos
     * @return array{id:int,telefono:?string,wa_user_id:?string,mensaje:string}
     */
    public function crearOActualizarClienteWhatsapp(array $datos): array
    {
        helper('whatsapp');

        $telefono = normalizar_telefono_whatsapp($datos['telefono'] ?? null);
        $waUserId = trim((string) ($datos['wa_user_id'] ?? ''));
        if (! es_whatsapp_bsuid($waUserId)) {
            $waUserId = '';
        }
        $waParent = trim((string) ($datos['wa_parent_user_id'] ?? ''));
        if (! es_whatsapp_parent_bsuid($waParent)) {
            $waParent = '';
        }

        if ($telefono === null && $waUserId === '') {
            throw new \Exception('Se requiere teléfono o wa_user_id (BSUID) para cliente WhatsApp.');
        }

        $cliente_model = $this->getClienteProvider();
        $existente = null;

        if ($telefono !== null) {
            $existente = $cliente_model->where('telefono', $telefono)->first();
        }
        if ($existente === null && $waUserId !== '') {
            $existente = $cliente_model->where('wa_user_id', $waUserId)->first();
        }

        if ($existente !== null) {
            $updates = [];
            if ($telefono !== null && empty($existente->telefono)) {
                if (! $this->otroClienteTieneCampo($cliente_model, 'telefono', $telefono, (int) $existente->id)) {
                    $updates['telefono'] = $telefono;
                }
            }
            if ($waUserId !== '' && (string) ($existente->wa_user_id ?? '') !== $waUserId) {
                $this->liberarCampoUnicoCliente($cliente_model, 'wa_user_id', $waUserId, (int) $existente->id);
                $updates['wa_user_id'] = $waUserId;
            }
            if ($waParent !== '') {
                $updates['wa_parent_user_id'] = $waParent;
            }

            if ($updates !== []) {
                $this->actualizarClienteWhatsappSeguro($cliente_model, (int) $existente->id, $updates);
            }

            if (! empty($datos['nombre'])) {
                $this->actualizarNombreSiVacio((int) $existente->id, $datos);
            }

            $fresh = $cliente_model->find((int) $existente->id);

            return [
                'id' => (int) $existente->id,
                'telefono' => $fresh->telefono ?? $telefono,
                'wa_user_id' => $fresh->wa_user_id ?? ($waUserId !== '' ? $waUserId : null),
                'mensaje' => 'El cliente WhatsApp ya existe.',
            ];
        }

        $cliente_entity = new ClienteEntity();
        if ($telefono !== null) {
            $cliente_entity->telefono = $telefono;
        }
        if ($waUserId !== '') {
            $cliente_entity->wa_user_id = $waUserId;
        }
        if ($waParent !== '') {
            $cliente_entity->wa_parent_user_id = $waParent;
        }

        $cliente_model->skipValidation(true);
        try {
            if (! $cliente_model->save($cliente_entity)) {
                throw new \Exception(json_encode($cliente_model->errors()));
            }
        } catch (\Throwable $e) {
            // Carrera / unique: reutilizar el registro que ganó el insert
            $recuperado = $this->buscarClienteWhatsappExistente($cliente_model, $telefono, $waUserId);
            if ($recuperado !== null) {
                return $this->crearOActualizarClienteWhatsapp([
                    'telefono' => $telefono,
                    'wa_user_id' => $waUserId !== '' ? $waUserId : null,
                    'wa_parent_user_id' => $waParent !== '' ? $waParent : null,
                    'nombre' => $datos['nombre'] ?? null,
                ]);
            }
            throw $e;
        } finally {
            $cliente_model->skipValidation(false);
        }

        $cliente_entity->id = $cliente_model->insertID;

        $perfil_model = $this->getClientePerfilProvider();
        $perfil_entity = new ClientePerfilEntity();
        $perfil_entity->id_cliente = $cliente_entity->id;
        $nombre = trim((string) ($datos['nombre'] ?? '')) ?: 'Sin nombre';
        $perfil_entity->nombre = $nombre;
        $perfil_entity->nombre_completo = $nombre;
        if (! $perfil_model->save($perfil_entity)) {
            log_message('warning', 'Error al crear perfil WA cliente ' . $cliente_entity->id);
        }

        return [
            'id' => (int) $cliente_entity->id,
            'telefono' => $telefono,
            'wa_user_id' => $waUserId !== '' ? $waUserId : null,
            'mensaje' => 'Cliente WhatsApp creado correctamente.',
        ];
    }

    /**
     * Reconcilia identidad tras system user_changed_number / user_changed_user_id.
     *
     * @param array<string, mixed> $message
     * @param array<string, mixed> $value
     * @return array{ok:bool,id_cliente:?int,accion:string}
     */
    public function reconciliarIdentidadWhatsappSistema(array $message, array $value = []): array
    {
        helper('whatsapp');

        $system = is_array($message['system'] ?? null) ? $message['system'] : [];
        $tipo = strtolower(trim((string) ($system['type'] ?? '')));

        // previous: Meta usa previous_user_id; algunos integradores envían customer
        $prevUserId = trim((string) (
            $system['previous_user_id']
            ?? $system['previous_wa_user_id']
            ?? $system['customer']
            ?? ''
        ));
        $prevParent = trim((string) ($system['previous_parent_user_id'] ?? ''));
        $nuevoWaId = normalizar_telefono_whatsapp($system['wa_id'] ?? null);
        $identidad = extraer_identidad_whatsapp_entrante($message, $value);

        $nuevoUserId = null;
        if (es_whatsapp_bsuid((string) ($system['user_id'] ?? ''))) {
            $nuevoUserId = (string) $system['user_id'];
        } elseif (! empty($identidad['wa_user_id'])) {
            $nuevoUserId = $identidad['wa_user_id'];
        }

        $nuevoParent = null;
        if (es_whatsapp_parent_bsuid((string) ($system['parent_user_id'] ?? ''))) {
            $nuevoParent = (string) $system['parent_user_id'];
        } elseif (! empty($identidad['wa_parent_user_id'])) {
            $nuevoParent = $identidad['wa_parent_user_id'];
        }

        if ($nuevoWaId === null && ! empty($identidad['telefono'])) {
            $nuevoWaId = $identidad['telefono'];
        }

        // Sin datos nuevos útiles: no inventar cliente
        if ($nuevoUserId === null && $nuevoWaId === null && ! es_whatsapp_bsuid($prevUserId)) {
            return ['ok' => false, 'id_cliente' => null, 'accion' => 'sin_datos_' . ($tipo !== '' ? $tipo : 'system')];
        }

        $cliente_model = $this->getClienteProvider();
        $cliente = null;

        if (es_whatsapp_bsuid($prevUserId)) {
            $cliente = $cliente_model->where('wa_user_id', $prevUserId)->first();
        }
        if ($cliente === null && $nuevoUserId !== null) {
            $cliente = $cliente_model->where('wa_user_id', $nuevoUserId)->first();
        }
        if ($cliente === null && $nuevoWaId !== null) {
            $cliente = $cliente_model->where('telefono', $nuevoWaId)->first();
        }
        // Fallback: from del mensaje (tel o BSUID previo)
        $from = trim((string) ($message['from'] ?? ''));
        if ($cliente === null && es_whatsapp_bsuid($from)) {
            $cliente = $cliente_model->where('wa_user_id', $from)->first();
        }
        if ($cliente === null) {
            $fromTel = normalizar_telefono_whatsapp($from);
            if ($fromTel !== null) {
                $cliente = $cliente_model->where('telefono', $fromTel)->first();
            }
        }

        if ($cliente === null) {
            if ($nuevoUserId === null && $nuevoWaId === null) {
                return ['ok' => false, 'id_cliente' => null, 'accion' => 'no_encontrado_' . ($tipo !== '' ? $tipo : 'system')];
            }
            $creado = $this->crearOActualizarClienteWhatsapp([
                'telefono' => $nuevoWaId,
                'wa_user_id' => $nuevoUserId,
                'wa_parent_user_id' => $nuevoParent,
                'nombre' => $identidad['nombre'] ?? null,
            ]);

            return [
                'ok' => true,
                'id_cliente' => (int) $creado['id'],
                'accion' => 'creado_' . ($tipo !== '' ? $tipo : 'system'),
            ];
        }

        $updates = [];
        if ($nuevoUserId !== null && (string) ($cliente->wa_user_id ?? '') !== $nuevoUserId) {
            $this->liberarCampoUnicoCliente($cliente_model, 'wa_user_id', $nuevoUserId, (int) $cliente->id);
            $updates['wa_user_id'] = $nuevoUserId;
        }
        if ($nuevoWaId !== null && (string) ($cliente->telefono ?? '') !== $nuevoWaId) {
            if (! $this->otroClienteTieneCampo($cliente_model, 'telefono', $nuevoWaId, (int) $cliente->id)) {
                $updates['telefono'] = $nuevoWaId;
            } else {
                log_message(
                    'warning',
                    '[WA BSUID] Teléfono ' . $nuevoWaId . ' ya pertenece a otro cliente; no se pisa (cliente ' . $cliente->id . ')'
                );
            }
        }
        if ($nuevoParent !== null) {
            $updates['wa_parent_user_id'] = $nuevoParent;
        }

        if ($updates !== []) {
            $this->actualizarClienteWhatsappSeguro($cliente_model, (int) $cliente->id, $updates);
        }

        log_message(
            'info',
            '[WA BSUID] Reconciliación tipo=' . $tipo
            . ' cliente=' . $cliente->id
            . ' prev=' . $prevUserId
            . ' nuevo_user=' . ($nuevoUserId ?? '')
            . ' nuevo_tel=' . ($nuevoWaId ?? '')
            . (es_whatsapp_parent_bsuid($prevParent) ? ' prev_parent=' . $prevParent : '')
        );

        return [
            'ok' => true,
            'id_cliente' => (int) $cliente->id,
            'accion' => 'actualizado_' . ($tipo !== '' ? $tipo : 'system'),
        ];
    }

    /**
     * Solo actualiza clientes ya existentes (no crea) desde status webhooks.
     *
     * @param array<string, mixed> $status
     */
    public function backfillIdentidadWhatsappDesdeStatus(array $status): void
    {
        helper('whatsapp');

        $waUserId = trim((string) ($status['recipient_user_id'] ?? ''));
        if (! es_whatsapp_bsuid($waUserId)) {
            $waUserId = '';
        }
        $telefono = normalizar_telefono_whatsapp($status['recipient_id'] ?? null);
        $parent = trim((string) ($status['recipient_parent_user_id'] ?? ''));
        if (! es_whatsapp_parent_bsuid($parent)) {
            $parent = '';
        }

        if ($telefono === null && $waUserId === '') {
            return;
        }

        $cliente_model = $this->getClienteProvider();
        $cliente = null;
        if ($telefono !== null) {
            $cliente = $cliente_model->where('telefono', $telefono)->first();
        }
        if ($cliente === null && $waUserId !== '') {
            $cliente = $cliente_model->where('wa_user_id', $waUserId)->first();
        }
        if ($cliente === null) {
            return;
        }

        $updates = [];
        if ($waUserId !== '' && empty($cliente->wa_user_id)) {
            if (! $this->otroClienteTieneCampo($cliente_model, 'wa_user_id', $waUserId, (int) $cliente->id)) {
                $updates['wa_user_id'] = $waUserId;
            }
        }
        if ($parent !== '' && empty($cliente->wa_parent_user_id)) {
            $updates['wa_parent_user_id'] = $parent;
        }
        if ($updates === []) {
            return;
        }

        $this->actualizarClienteWhatsappSeguro($cliente_model, (int) $cliente->id, $updates);
    }

    /**
     * @param \CodeIgniter\Model $cliente_model
     */
    private function buscarClienteWhatsappExistente($cliente_model, ?string $telefono, string $waUserId): ?object
    {
        if ($telefono !== null) {
            $c = $cliente_model->where('telefono', $telefono)->first();
            if ($c !== null) {
                return $c;
            }
        }
        if ($waUserId !== '') {
            return $cliente_model->where('wa_user_id', $waUserId)->first();
        }

        return null;
    }

    /**
     * @param \CodeIgniter\Model $cliente_model
     */
    private function otroClienteTieneCampo($cliente_model, string $campo, string $valor, int $exceptoId): bool
    {
        return $cliente_model
            ->where($campo, $valor)
            ->where('id !=', $exceptoId)
            ->first() !== null;
    }

    /**
     * Libera unique de otro cliente (pone NULL) para poder reasignar sin 1062.
     *
     * @param \CodeIgniter\Model $cliente_model
     */
    private function liberarCampoUnicoCliente($cliente_model, string $campo, string $valor, int $exceptoId): void
    {
        $otro = $cliente_model
            ->where($campo, $valor)
            ->where('id !=', $exceptoId)
            ->first();
        if ($otro === null) {
            return;
        }

        log_message(
            'warning',
            '[WA BSUID] Liberando ' . $campo . '=' . $valor
            . ' del cliente ' . $otro->id . ' para asignarlo a ' . $exceptoId
        );
        $cliente_model->skipValidation(true);
        try {
            $cliente_model->update((int) $otro->id, [$campo => null]);
        } finally {
            $cliente_model->skipValidation(false);
        }
    }

    /**
     * @param \CodeIgniter\Model $cliente_model
     * @param array<string, mixed> $updates
     */
    private function actualizarClienteWhatsappSeguro($cliente_model, int $id, array $updates): void
    {
        $cliente_model->skipValidation(true);
        try {
            $cliente_model->update($id, $updates);
        } catch (\Throwable $e) {
            log_message('error', '[WA BSUID] Update cliente ' . $id . ' falló: ' . $e->getMessage());
            throw $e;
        } finally {
            $cliente_model->skipValidation(false);
        }
    }

    public function crearCliente(array $datos)
    {
        $cliente_model = $this->getClienteProvider();

        // Buscar si ya existe el cliente
        $cliente_existente = $cliente_model->where('telefono', $datos['telefono'])->first();
        if ($cliente_existente) {
            return [
                'id' => $cliente_existente->id,
                'telefono' => $cliente_existente->telefono,
                'mensaje' => 'El cliente ya existe.'
            ];
        }

        // Crear nuevo cliente
        $cliente_entity = new ClienteEntity();
        $cliente_entity->telefono = $datos['telefono'];
        $cliente_entity->nombre = $datos['nombre'] ?? null;

        if ($cliente_model->save($cliente_entity)) {
            $cliente_entity->id = $cliente_model->insertID;
            
            // Crear perfil del cliente automáticamente
            $perfil_model = $this->getClientePerfilProvider();
            $perfil_entity = new ClientePerfilEntity();
            $perfil_entity->id_cliente = $cliente_entity->id;
            
            // Usar el nombre del webhook o un valor por defecto
            $nombre = $datos['nombre'] ?? 'Sin nombre';
            $perfil_entity->nombre = $nombre;
            
            // Si viene nombre completo, usarlo; si no, construir con el nombre
            if (isset($datos['nombre_completo'])) {
                $perfil_entity->nombre_completo = $datos['nombre_completo'];
            } else {
                $perfil_entity->nombre_completo = $nombre;
            }
            
            // Campos opcionales del perfil
            if (isset($datos['apellido'])) {
                $perfil_entity->apellido = $datos['apellido'];
            }
            if (isset($datos['tipo'])) {
                $perfil_entity->tipo = $datos['tipo'];
            }
         
            if (!$perfil_model->save($perfil_entity)) {
                log_message('warning', 'Error al crear perfil del cliente ' . $cliente_entity->id . ': ' . json_encode($perfil_model->errors()));
            }
            
            return [
                'id' => $cliente_entity->id,
                'telefono' => $cliente_entity->telefono,
                'mensaje' => 'Cliente creado correctamente.'
            ];
        } else {
            throw new \Exception(json_encode($cliente_model->errors()));
        }
    }

    /**
     * @param array<string, mixed> $datos
     */
    private function actualizarNombreSiVacio(int $idCliente, array $datos): void
    {
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($nombre === '') {
            return;
        }

        $perfilModel = $this->getClientePerfilProvider();
        $perfil = $perfilModel->where('id_cliente', $idCliente)->first();
        if ($perfil === null) {
            $perfilEntity = new ClientePerfilEntity();
            $perfilEntity->id_cliente = $idCliente;
            $perfilEntity->nombre = $nombre;
            $perfilEntity->nombre_completo = trim((string) ($datos['nombre_completo'] ?? $nombre));
            $perfilModel->save($perfilEntity);

            return;
        }

        $nombreActual = trim((string) ($perfil->nombre ?? ''));
        if ($nombreActual !== '') {
            return;
        }

        $perfilModel->update($perfil->id, [
            'nombre' => $nombre,
            'nombre_completo' => trim((string) ($datos['nombre_completo'] ?? $nombre)),
        ]);
    }

    public function listarClientes($page = 1, $perPage = 10, $search = null){
        $cliente_model = $this->getClienteProvider();
        
        $cliente_model->select([
            'cliente.id',
            'cliente.telefono', 
            'cliente.created_at',
            'cliente.updated_at',
            'cp.nombre',
            'cp.apellido', 
            'cp.nombre_completo',
            'cp.marca',
            'cp.modelo',
            'cp.placa',
            'cp.correo',
            'cp.distrito',
            'cp.tipo'
        ]);
        
        $cliente_model->join('cliente_perfil as cp', 'cp.id_cliente = cliente.id', 'left');
        
        $cliente_model->orderBy('cliente.id', 'ASC');
        
        if (!empty($search)) {
            $cliente_model->groupStart();
            $cliente_model->like('cliente.id', $search);
            $cliente_model->orLike('cliente.telefono', $search);
            $cliente_model->orLike('cp.nombre', $search);
            $cliente_model->orLike('cp.apellido', $search);
            $cliente_model->orLike('cp.nombre_completo', $search);
            $cliente_model->orLike('cp.correo', $search);
            $cliente_model->groupEnd();
        }

        if ($perPage == 'all') {
            $clientes = $cliente_model->findAll();
            $total = count($clientes);
            return [
                'data' => $clientes,
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

        $result = $cliente_model->paginate($perPage, 'default', $page);
        $pager = $cliente_model->pager;
        $total = $pager->getTotal('default');
        $currentPage = $pager->getCurrentPage('default');
        $perPage = $pager->getPerPage('default');
        $lastPage = $pager->getPageCount('default');
        $from = ($total > 0) ? (($currentPage - 1) * $perPage) + 1 : 0;
        $to = ($from + count($result)) - 1;

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

    public function listarMisClientes($page = 1, $perPage = 10, $search = null)
    {
        $usuario = auth()->getUsuario();
        if (!$usuario) {
            throw new \Exception('No se pudo obtener el usuario autenticado');
        }

        $cliente_model = $this->getClienteProvider();
        $cliente_model->select([
            'cliente.id',
            'cliente.telefono',
            'cliente.created_at',
            'cliente.updated_at',
            'cp.nombre',
            'cp.apellido',
            'cp.nombre_completo',
            'cp.marca',
            'cp.modelo',
            'cp.placa',
            'cp.correo',
            'cp.distrito',
            'cp.tipo'
        ]);
        $cliente_model->join('cliente_perfil as cp', 'cp.id_cliente = cliente.id', 'left');
        // Unir con conversacion para filtrar por usuario
        $cliente_model->join('conversacion as conv', 'conv.id_cliente = cliente.id AND conv.deleted_at IS NULL', 'inner');
        $cliente_model->where('conv.id_usuario', $usuario->id);
        $cliente_model->orderBy('cliente.id', 'ASC');

        if (!empty($search)) {
            $cliente_model->groupStart();
            $cliente_model->like('cliente.id', $search);
            $cliente_model->orLike('cliente.telefono', $search);
            $cliente_model->orLike('cp.nombre', $search);
            $cliente_model->orLike('cp.apellido', $search);
            $cliente_model->orLike('cp.nombre_completo', $search);
            $cliente_model->orLike('cp.correo', $search);
            $cliente_model->groupEnd();
        }

        $cliente_model->groupBy('cliente.id'); // Evitar duplicados si hay varias conversaciones

        if ($perPage == 'all') {
            $clientes = $cliente_model->findAll();
            $total = count($clientes);
            return [
                'data' => $clientes,
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

        $result = $cliente_model->paginate($perPage, 'default', $page);
        $pager = $cliente_model->pager;
        $total = $pager->getTotal('default');
        $currentPage = $pager->getCurrentPage('default');
        $perPage = $pager->getPerPage('default');
        $lastPage = $pager->getPageCount('default');
        $from = ($total > 0) ? (($currentPage - 1) * $perPage) + 1 : 0;
        $to = ($from + count($result)) - 1;

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

    /**
     * Búsqueda rápida por teléfono: cliente, asesor asignado y estado de conversación.
     */
    public function buscarClientePorTelefono(string $telefonoRaw): array
    {
        $telefonoRaw = trim($telefonoRaw);
        if ($telefonoRaw === '') {
            throw new \Exception('El teléfono es obligatorio');
        }

        $telefonoValidado = pretty_phone_number($telefonoRaw);
        if (! ($telefonoValidado['success'] ?? false) || empty($telefonoValidado['telefono'])) {
            throw new \Exception(
                $telefonoValidado['message'] ?? 'El teléfono debe tener formato peruano con código 51 (ej: 51954058887)'
            );
        }

        $telefono = (string) $telefonoValidado['telefono'];

        $cliente = $this->getClienteProvider()
            ->select([
                'cliente.id',
                'cliente.telefono',
                'cp.nombre',
                'cp.apellido',
                'cp.nombre_completo',
            ])
            ->join('cliente_perfil as cp', 'cp.id_cliente = cliente.id', 'left')
            ->where('cliente.telefono', $telefono)
            ->where('cliente.deleted_at', null)
            ->first();

        if (! $cliente) {
            return [
                'encontrado' => false,
                'telefono' => $telefono,
                'mensaje' => 'No se encontró cliente con ese teléfono',
            ];
        }

        $nombreCompleto = trim((string) ($cliente->nombre_completo ?? ''));
        if ($nombreCompleto === '') {
            $nombreCompleto = trim(((string) ($cliente->nombre ?? '')) . ' ' . ((string) ($cliente->apellido ?? '')));
        }
        if ($nombreCompleto === '' || strcasecmp($nombreCompleto, 'Sin nombre') === 0) {
            $nombreCompleto = null;
        }

        $conversacion = model('ConversacionModel')
            ->asArray()
            ->select('
                conversacion.id,
                conversacion.id_estado,
                conversacion.id_usuario,
                conversacion.id_app,
                conversacion.temperatura,
                conversacion.updated_at,
                estado_conversacion.nombre as estado_nombre,
                estado_conversacion.descripcion as estado_descripcion,
                usuario_perfil.nombres as asesor_nombres,
                usuario_perfil.apellidos as asesor_apellidos,
                usuario.es_bot as asesor_es_bot,
                app.nombre as nombre_app
            ')
            ->join('estado_conversacion', 'estado_conversacion.id = conversacion.id_estado', 'left')
            ->join('usuario', 'usuario.id = conversacion.id_usuario', 'left')
            ->join('usuario_perfil', 'usuario_perfil.id_usuario = usuario.id', 'left')
            ->join('app', 'app.id = conversacion.id_app', 'left')
            ->where('conversacion.id_cliente', $cliente->id)
            ->where('conversacion.deleted_at', null)
            ->orderBy('conversacion.updated_at', 'DESC')
            ->first();

        $resultado = [
            'encontrado' => true,
            'telefono' => $telefono,
            'cliente' => [
                'id' => (int) $cliente->id,
                'nombre' => $cliente->nombre ?? null,
                'apellido' => $cliente->apellido ?? null,
                'nombre_completo' => $nombreCompleto,
            ],
            'conversacion' => null,
        ];

        if ($conversacion) {
            $asesorNombre = trim(((string) ($conversacion['asesor_nombres'] ?? '')) . ' ' . ((string) ($conversacion['asesor_apellidos'] ?? '')));
            if ($asesorNombre === '') {
                $asesorNombre = null;
            } elseif (! empty($conversacion['asesor_es_bot'])) {
                $asesorNombre .= ' (Bot)';
            }

            $resultado['conversacion'] = [
                'id' => (int) $conversacion['id'],
                'id_estado' => $conversacion['id_estado'] ? (int) $conversacion['id_estado'] : null,
                'estado' => $conversacion['estado_nombre'] ?? null,
                'estado_descripcion' => $conversacion['estado_descripcion'] ?? null,
                'temperatura' => $conversacion['temperatura'] ?? null,
                'asesor' => [
                    'id' => $conversacion['id_usuario'] ? (int) $conversacion['id_usuario'] : null,
                    'nombre' => $asesorNombre,
                ],
                'app' => $conversacion['nombre_app'] ?? null,
                'updated_at' => $conversacion['updated_at'] ?? null,
            ];
        }

        return $resultado;
    }

    public function actualizarCliente(int $id, array $datos)
    {
        $cliente_model = $this->getClienteProvider();
        
        $cliente_existente = $cliente_model->find($id);
        if (!$cliente_existente) {
            throw new \Exception('Cliente no encontrado.');
        }

        // Separar datos del cliente y del perfil
        $datos_cliente = [];
        $datos_perfil = [];
        
        // Campos del cliente
        if (array_key_exists('telefono', $datos)) {
            helper('whatsapp');
            $telRaw = trim((string) ($datos['telefono'] ?? ''));
            if ($telRaw === '') {
                // No borrar teléfono existente con campo vacío desde el formulario.
            } else {
                $telefonoNorm = normalizar_telefono_whatsapp($telRaw);
                if ($telefonoNorm === null) {
                    throw new \Exception(
                        'Teléfono inválido. Use formato internacional, por ejemplo 51987654321 o +51 987 654 321.'
                    );
                }
                if ($this->otroClienteTieneCampo($cliente_model, 'telefono', $telefonoNorm, $id)) {
                    throw new \Exception('El número de teléfono ya está registrado en otro cliente.');
                }
                $datos_cliente['telefono'] = $telefonoNorm;
            }
        }
        
        // Campos del perfil
        $campos_perfil = ['nombre', 'apellido', 'nombre_completo', 'marca', 'modelo', 'placa', 'correo', 'distrito', 'tipo'];
        foreach ($campos_perfil as $campo) {
            if (isset($datos[$campo])) {
                $datos_perfil[$campo] = $datos[$campo];
            }
        }

        // Actualizar cliente si hay datos
        if (!empty($datos_cliente)) {
            $cliente_entity = new ClienteEntity();
            $cliente_entity->id = $id;
            foreach ($datos_cliente as $key => $value) {
                $cliente_entity->$key = $value;
            }
            if (!$cliente_model->save($cliente_entity)) {
                throw new \Exception(json_encode($cliente_model->errors()));
            }
        }

        // Actualizar perfil si hay datos
        if (!empty($datos_perfil)) {
            $perfil_model = $this->getClientePerfilProvider();
            $perfil_existente = $perfil_model->where('id_cliente', $id)->first();

            // Recalcular nombre_completo si cambió nombre o apellido
            if (isset($datos_perfil['nombre']) || isset($datos_perfil['apellido'])) {
                $nombre = $datos_perfil['nombre'] ?? ($perfil_existente->nombre ?? '');
                $apellido = $datos_perfil['apellido'] ?? ($perfil_existente->apellido ?? '');
                $datos_perfil['nombre_completo'] = trim($nombre . ' ' . $apellido);
            }

            if ($perfil_existente) {
                // Actualizar perfil existente
                $datos_perfil['id_cliente'] = $id;
                if (!$perfil_model->update($perfil_existente->id, $datos_perfil)) {
                    throw new \Exception(json_encode($perfil_model->errors()));
                }
            } else {
                // Crear nuevo perfil
                $datos_perfil['id_cliente'] = $id;
                $perfil_entity = new ClientePerfilEntity();
                foreach ($datos_perfil as $key => $value) {
                    $perfil_entity->$key = $value;
                }
                if (!$perfil_model->save($perfil_entity)) {
                    throw new \Exception(json_encode($perfil_model->errors()));
                }
            }
        }
        return $this->getById($id);
    }

    public function crearClienteManualAsesor(array $data)
    {
        if (! isset($data['id_app']) || empty($data['id_app'])) {
            throw new \Exception('La app es obligatoria para crear la conversación');
        }

        $usuarioAuth = auth()->getUsuario();
        if ($usuarioAuth === null) {
            throw new \Exception('Usuario no autenticado');
        }
        $id_usuario_creador = (int) $usuarioAuth->id;

        $telefonoRaw = trim((string) ($data['telefono'] ?? ''));
        if ($telefonoRaw === '') {
            throw new \Exception('El teléfono es obligatorio');
        }

        $telefonoValidado = pretty_phone_number($telefonoRaw);
        if (! ($telefonoValidado['success'] ?? false) || empty($telefonoValidado['telefono'])) {
            throw new \Exception(
                $telefonoValidado['message'] ?? 'El teléfono debe tener formato peruano con código 51 (ej: 51954058887)'
            );
        }

        $telefono = (string) $telefonoValidado['telefono'];
        if (! str_starts_with($telefono, '51') || strlen($telefono) !== 11) {
            throw new \Exception('El teléfono debe iniciar con 51 y tener 11 dígitos (ej: 51954058887)');
        }

        $id_app = (int) $data['id_app'];
        $app = app()->obtenerApp('id', $id_app);
        if (! $app) {
            throw new \Exception('App no encontrada o inactiva');
        }

        $usuario_app = model('UsuarioAppModel')
            ->where('id_app', $id_app)
            ->where('id_usuario', $id_usuario_creador)
            ->where('deleted_at', null)
            ->first();
        if (! $usuario_app) {
            throw new \Exception('No tienes acceso a esta app para crear el contacto');
        }

        $cliente_existente = $this->getClienteProvider()
            ->where('telefono', $telefono)
            ->where('deleted_at', null)
            ->first();

        if ($cliente_existente) {
            $conversacion_cliente = model('ConversacionModel')
                ->where('id_cliente', $cliente_existente->id)
                ->where('deleted_at', null)
                ->first();

            if ($conversacion_cliente) {
                throw new \Exception('El cliente ya existe y tiene una conversación registrada');
            }

            throw new \Exception('El cliente ya existe. La creación manual solo aplica para contactos nuevos');
        }

        $datos_cliente = [
            'telefono' => $telefono,
            'nombre' => $data['nombres'] ?? $data['nombre'] ?? null,
            'apellido' => $data['apellidos'] ?? $data['apellido'] ?? null,
            'nombre_completo' => trim(($data['nombres'] ?? $data['nombre'] ?? '') . ' ' . ($data['apellidos'] ?? $data['apellido'] ?? '')),
        ];

        $cliente = $this->crearCliente($datos_cliente);
        if (($cliente['mensaje'] ?? '') === 'El cliente ya existe.') {
            throw new \Exception('El cliente ya existe. La creación manual solo aplica para contactos nuevos');
        }

        $id_cliente = (int) $cliente['id'];

        $conversacion_existente = model('ConversacionModel')
            ->where('id_cliente', $id_cliente)
            ->where('deleted_at', null)
            ->first();

        if ($conversacion_existente) {
            throw new \Exception('El cliente ya tiene una conversación registrada');
        }

        $conversacion_result = conversaciones()->crearConversacion([
            'id_cliente' => $id_cliente,
            'id_usuario' => $id_usuario_creador,
            'origen' => 'llamada',
            'registro' => 'manual',
            'id_app' => $id_app,
            'id_estado' => 3,
            'id_etiqueta' => 9,
            'temperatura' => 'caliente',
            'id_asignado_por' => $id_usuario_creador,
            'asignado_at' => date('Y-m-d H:i:s'),
        ]);

        try {
            mensaje()->crearMensaje([
                'id_conversacion' => $conversacion_result['id'],
                'id_estado' => 4,
                'mensaje' => 'Creaste este contacto, por favor enviar la plantilla.',
                'contesta' => 'bot',
                'id_mensaje_whatsapp' => null,
                'id_mensaje_responde' => null,
                'cod_error' => null,
                'media_path' => null,
                'media_url' => null,
                'media_type' => null,
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Error al crear mensaje inicial en creación manual: ' . $e->getMessage());
        }

        return [
            'cliente' => $cliente,
            'conversacion' => [
                'id' => $conversacion_result['id'],
                'mensaje' => 'Conversación creada correctamente',
            ],
        ];
    }

    // public function previewImportacionLeads($file)
    // {
    //     // Verificar que PhpSpreadsheet esté disponible
    //     if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
    //         throw new \Exception('La librería PhpSpreadsheet no está instalada. Ejecuta: composer require phpoffice/phpspreadsheet');
    //     }
        
    //     $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
    //     $spreadsheet = $reader->load($file->getTempName());
    //     $worksheet = $spreadsheet->getActiveSheet();
        
    //     $preview = [];
    //     $header = [];
    //     $totalFilas = 0;
        
    //     // Leer encabezados de la primera fila (fila 1)
    //     $headerRow = 1;
    //     $header['A'] = $worksheet->getCell('A' . $headerRow)->getValue() ?? 'Nombre';
    //     $header['B'] = $worksheet->getCell('B' . $headerRow)->getValue() ?? 'Apellido';
    //     $header['C'] = $worksheet->getCell('C' . $headerRow)->getValue() ?? 'Teléfono';
    //     $header['D'] = $worksheet->getCell('D' . $headerRow)->getValue() ?? 'Marca';
    //     $header['E'] = $worksheet->getCell('E' . $headerRow)->getValue() ?? 'Modelo';
    //     $header['F'] = $worksheet->getCell('F' . $headerRow)->getValue() ?? 'Placa';
    //     $header['G'] = $worksheet->getCell('G' . $headerRow)->getValue() ?? 'Origen';
    //     $header['H'] = $worksheet->getCell('H' . $headerRow)->getValue() ?? 'Servicio';
        
    //     // Leer datos desde la fila 2 en adelante
    //     $highestRow = $worksheet->getHighestRow();
    //     $numeroFila = 2; // Empezar desde la fila 2 (después del encabezado)
        
    //     for ($row = 2; $row <= $highestRow; $row++) {
    //         $data = [
    //             'A' => trim($worksheet->getCell('A' . $row)->getValue() ?? ''),
    //             'B' => trim($worksheet->getCell('B' . $row)->getValue() ?? ''),
    //             'C' => trim($worksheet->getCell('C' . $row)->getValue() ?? ''),
    //             'D' => trim($worksheet->getCell('D' . $row)->getValue() ?? ''),
    //             'E' => trim($worksheet->getCell('E' . $row)->getValue() ?? ''),
    //             'F' => trim($worksheet->getCell('F' . $row)->getValue() ?? ''),
    //             'G' => trim($worksheet->getCell('G' . $row)->getValue() ?? ''),
    //             'H' => trim($worksheet->getCell('H' . $row)->getValue() ?? ''),
    //         ];
            
    //         // Si la fila está completamente vacía, saltarla
    //         $filaVacia = true;
    //         foreach ($data as $valor) {
    //             if (!empty($valor)) {
    //                 $filaVacia = false;
    //                 break;
    //             }
    //         }
            
    //         if ($filaVacia) {
    //             continue;
    //         }
            
    //         $totalFilas++;
            
    //         // Validar datos
    //         $validacion = $this->validarFilaLead($data, $numeroFila);
            
    //         $preview[] = [
    //             'numero_fila' => $numeroFila,
    //             'data' => $data,
    //             'validacion' => $validacion
    //         ];
            
    //         $numeroFila++;
    //     }
        
    //     return [
    //         'total_filas' => $totalFilas,
    //         'preview' => $preview,
    //         'header' => $header
    //     ];
    // }
    
    // private function validarFilaLead(array $data, int $numeroFila): array
    // {
    //     $errores = [];
    //     $warnings = [];
        
    //     // Validar teléfono (obligatorio)
    //     $telefono = $data['C'] ?? '';
    //     if (empty($telefono)) {
    //         $errores[] = 'Teléfono requerido';
    //     } else {
    //         // Limpiar teléfono (quitar espacios, guiones, etc.)
    //         $telefonoLimpio = preg_replace('/[^0-9]/', '', $telefono);
    //         if (strlen($telefonoLimpio) < 9) {
    //             $errores[] = 'Teléfono inválido (mínimo 9 dígitos)';
    //         }
    //     }
        
    //     // Validar nombre (obligatorio)
    //     $nombre = $data['A'] ?? '';
    //     if (empty($nombre)) {
    //         $errores[] = 'Nombre requerido';
    //     }
        
    //     // Validar que el cliente no exista ya
    //     if (!empty($telefono)) {
    //         $telefonoLimpio = preg_replace('/[^0-9]/', '', $telefono);
    //         $clienteExistente = $this->getClienteProvider()
    //             ->where('telefono', $telefonoLimpio)
    //             ->first();
            
    //         if ($clienteExistente) {
    //             $warnings[] = 'Cliente ya existe';
    //         }
    //     }
        
    //     // Validar origen (si está presente)
    //     $origen = $data['G'] ?? '';
    //     if (!empty($origen)) {
    //         $origenesValidos = [
    //             'Facebook', 'Instagram', 'TikTok', 'Snapchat', 'WhatsApp',
    //             'LinkedIn', 'Telegram', 'Pinterest', 'YouTube', 'Twitter',
    //             'Google', 'X', 'Yavendió', 'formulario web'
    //         ];
            
    //         if (!in_array($origen, $origenesValidos)) {
    //             $warnings[] = 'Origen no reconocido';
    //         }
    //     }
        
    //     return [
    //         'valida' => empty($errores),
    //         'tiene_warnings' => !empty($warnings),
    //         'errores' => $errores,
    //         'warnings' => $warnings
    //     ];
    // }

    // public function importarLeadsDesdeExcel($file, int $id_app, array $filas_seleccionadas, ?int $id_usuario = null, bool $asignacion_aleatoria = false)
    // {
    //     // Obtener y validar la app
    //     $app = app()->obtenerApp('id', $id_app);
    //     if (!$app) {
    //         throw new \Exception('App no encontrada o inactiva');
    //     }
        
    //     // Validar que la app tenga usuarios disponibles (reutilizar lógica de crearClienteManual)
    //     $usuarios_app = model('UsuarioAppModel')
    //         ->where('usuario_app.id_app', $id_app)
    //         ->where('usuario_app.deleted_at', null)
    //         ->join('usuario', 'usuario.id = usuario_app.id_usuario')
    //         ->where('usuario.activo', 1)
    //         ->where('usuario.deleted_at', null)
    //         ->findAll();
        
    //     if (empty($usuarios_app)) {
    //         throw new \Exception('La app seleccionada no tiene usuarios disponibles. Debe asignar al menos un usuario activo a la app antes de importar.');
    //     }
        
    //     // Si es modo compartido y se seleccionó un usuario específico, validar que pertenece a la app
    //     if ($app->modo === 'compartido' && !$asignacion_aleatoria && $id_usuario) {
    //         $usuario_valido = model('UsuarioAppModel')
    //             ->where('id_app', $id_app)
    //             ->where('id_usuario', $id_usuario)
    //             ->where('deleted_at', null)
    //             ->first();
            
    //         if (!$usuario_valido) {
    //             throw new \Exception('El usuario seleccionado no pertenece a esta app o no está activo');
    //         }
    //     }
        
        
    //     if (!function_exists('subirArchivoSFTP')) {
    //         \helper('file_helper');
    //     }
    //     $originalName = $file->getClientName();
    //     $extension = pathinfo($originalName, PATHINFO_EXTENSION) ?: 'xlsx';
    //     $uniqueName = 'import_' . date('Ymd_His') . '_' . uniqid() . '.' . $extension;
    //     $sftpPath = 'importacion/' . $uniqueName;

    //     // Guardar temporalmente el archivo para subirlo a SFTP
    //     $tmpDir = sys_get_temp_dir();
    //     $tmpFile = $tmpDir . DIRECTORY_SEPARATOR . $uniqueName;
    //     if (!$file->move($tmpDir, $uniqueName)) {
    //         throw new \Exception('No se pudo guardar el archivo temporal para SFTP.');
    //     }

    //     $resultadoSFTP = \subirArchivoSFTP($tmpFile, $sftpPath);
    //     $sftpUrl = $resultadoSFTP['success'] ? $resultadoSFTP['url'] : null;
    //     if (!$resultadoSFTP['success']) {
    //         // Limpiar archivo temporal
    //         if (file_exists($tmpFile)) @unlink($tmpFile);
    //         throw new \Exception('No se pudo subir el archivo de importación a SFTP: ' . ($resultadoSFTP['error'] ?? 'Error desconocido'));
    //     }

    //     // Leer el Excel desde el archivo temporal local
    //     $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
    //     $spreadsheet = $reader->load($tmpFile);
    //     $worksheet = $spreadsheet->getActiveSheet();
    //     $highestRow = $worksheet->getHighestRow();

    //     // Eliminar el archivo temporal después de procesar
    //     if (file_exists($tmpFile)) @unlink($tmpFile);
        
    //     $importados = [];
    //     $warnings = [];
    //     $total_importados = 0;
        
    //     // Procesar solo las filas seleccionadas
    //     foreach ($filas_seleccionadas as $numero_fila) {
    //         if ($numero_fila < 2 || $numero_fila > $highestRow) {
    //             continue;
    //         }
            
    //         try {
    //             // Leer datos de la fila
    //             $data = [
    //                 'nombres' => trim($worksheet->getCell('A' . $numero_fila)->getValue() ?? ''),
    //                 'apellidos' => trim($worksheet->getCell('B' . $numero_fila)->getValue() ?? ''),
    //                 'telefono' => trim($worksheet->getCell('C' . $numero_fila)->getValue() ?? ''),
    //                 'marca' => trim($worksheet->getCell('D' . $numero_fila)->getValue() ?? ''),
    //                 'modelo' => trim($worksheet->getCell('E' . $numero_fila)->getValue() ?? ''),
    //                 'placa' => trim($worksheet->getCell('F' . $numero_fila)->getValue() ?? ''),
    //                 'origen' => trim($worksheet->getCell('G' . $numero_fila)->getValue() ?? ''),
    //                 'servicio' => trim($worksheet->getCell('H' . $numero_fila)->getValue() ?? ''),
    //             ];
                
    //             // Validar datos mínimos
    //             if (empty($data['telefono']) || empty($data['nombres'])) {
    //                 $warnings[] = "Fila {$numero_fila}: Faltan datos obligatorios (teléfono o nombre)";
    //                 continue;
    //             }
                
    //             // Limpiar teléfono
    //             $telefono_limpio = preg_replace('/[^0-9]/', '', $data['telefono']);
    //             if (strlen($telefono_limpio) < 9) {
    //                 $warnings[] = "Fila {$numero_fila}: Teléfono inválido";
    //                 continue;
    //             }
                
    //             // Preparar datos para reutilizar crearClienteManual
    //             $datos_importacion = [
    //                 'id_app' => $id_app,
    //                 'telefono' => $telefono_limpio,
    //                 'nombres' => $data['nombres'],
    //                 'apellidos' => $data['apellidos'],
    //                 'marca' => $data['marca'] ?: null,
    //                 'modelo' => $data['modelo'] ?: null,
    //                 'placa' => $data['placa'] ?: null,
    //                 'servicio' => $data['servicio'] ?: null,
    //                 'origen' => !empty($data['origen']) ? $data['origen'] : 'importacion',
    //             ];
                
    //             // Determinar usuario según el modo (reutilizar lógica de crearClienteManual)
    //             if ($app->modo === 'individual') {
    //                 // Modo individual: se asigna automáticamente en crearClienteManual
    //                 $datos_importacion['asignacion_aleatoria'] = false;
    //             } else {
    //                 // Modo compartido
    //                 if ($asignacion_aleatoria) {
    //                     $datos_importacion['asignacion_aleatoria'] = true;
    //                 } elseif ($id_usuario) {
    //                     $datos_importacion['id_usuario'] = $id_usuario;
    //                     $datos_importacion['asignacion_aleatoria'] = false;
    //                 } else {
    //                     $warnings[] = "Fila {$numero_fila}: Para app compartida debe seleccionar un usuario o usar asignación aleatoria";
    //                     continue;
    //                 }
    //             }
                
    //             // Reutilizar crearClienteManual pero adaptado para importación
    //             // Usar transacción para asegurar que cada asignación vea el estado actualizado
    //             // y se complete antes de la siguiente
    //             $db = \Config\Database::connect();
    //             $db->transStart();
    //             try {
    //                 $resultado = $this->crearClienteYConversacionParaImportacion($datos_importacion, $app);
    //                 $db->transComplete();
                    
    //                 if ($db->transStatus() === false) {
    //                     throw new \Exception('Error en la transacción de base de datos');
    //                 }
    //             } catch (\Exception $e) {
    //                 $db->transRollback();
    //                 throw $e;
    //             }
                
    //             if ($resultado['exito']) {
    //                 $importados[] = [
    //                     'fila' => $numero_fila,
    //                     'cliente_id' => $resultado['cliente_id'],
    //                     'conversacion_id' => $resultado['conversacion_id'],
    //                     'telefono' => $telefono_limpio,
    //                     'nombre' => trim($data['nombres'] . ' ' . $data['apellidos'])
    //                 ];
    //                 $total_importados++;
    //             } else {
    //                 $warnings[] = "Fila {$numero_fila}: " . $resultado['error'];
    //             }
                
    //         } catch (\Exception $e) {
    //             $warnings[] = "Fila {$numero_fila}: " . $e->getMessage();
    //             log_message('error', "Error importando fila {$numero_fila}: " . $e->getMessage());
    //         }
    //     }
        
    //     return [
    //         'total_importados' => $total_importados,
    //         'importados' => $importados,
    //         'warnings' => !empty($warnings) ? implode("\n", $warnings) : '',
    //         'archivo_sftp_url' => $sftpUrl,
    //         'archivo_sftp_path' => $sftpPath
    //     ];
    // }
    

    // private function crearClienteYConversacionParaImportacion(array $data, $app): array
    // {
    //     try {
    //         $id_app = (int) $data['id_app'];
            
    //         // Preparar datos del cliente (misma lógica que crearClienteManual)
    //         $datos_cliente = [
    //             'telefono' => $data['telefono'] ?? null,
    //             'nombre' => $data['nombres'] ?? $data['nombre'] ?? null,
    //             'apellido' => $data['apellidos'] ?? null,
    //             'nombre_completo' => trim(($data['nombres'] ?? '') . ' ' . ($data['apellidos'] ?? '')),
    //             'marca' => $data['marca'] ?? null,
    //             'modelo' => $data['modelo'] ?? null,
    //             'placa' => $data['placa'] ?? null,
    //             'tipo' => $data['tipo'] ?? null,
    //         ];
            
    //         // Verificar si el cliente ya existe (misma lógica)
    //         $cliente_existente = $this->getClienteProvider()
    //             ->where('telefono', $datos_cliente['telefono'])
    //             ->first();
            
    //         if ($cliente_existente) {
    //             $id_cliente = $cliente_existente->id;
    //         } else {
    //             // Crear nuevo cliente
    //             $cliente = $this->crearCliente($datos_cliente);
    //             $id_cliente = $cliente['id'];
                
    //             // Actualizar perfil si hay datos adicionales
    //             if (isset($data['marca']) || isset($data['modelo']) || isset($data['placa']) || isset($data['tipo'])) {
    //                 $datos_perfil = [];
    //                 if (isset($data['marca'])) $datos_perfil['marca'] = $data['marca'];
    //                 if (isset($data['modelo'])) $datos_perfil['modelo'] = $data['modelo'];
    //                 if (isset($data['placa'])) $datos_perfil['placa'] = $data['placa'];
    //                 if (isset($data['tipo'])) $datos_perfil['tipo'] = $data['tipo'];
                    
    //                 if (!empty($datos_perfil)) {
    //                     $this->actualizarCliente($id_cliente, $datos_perfil);
    //                 }
    //             }
    //         }
            
    //         // Determinar usuario según el modo de la app (reutilizar lógica de crearClienteManual)
    //         $id_usuario = null;
    //         if ($app->modo === 'individual') {
    //             $id_usuario = app()->obtenerUsuarioParaApp($app);
    //             if (!$id_usuario) {
    //                 return ['exito' => false, 'error' => 'No hay usuario disponible para esta app individual'];
    //             }
    //         } else {
    //             if (isset($data['asignacion_aleatoria']) && $data['asignacion_aleatoria'] === true) {
    //                 $id_usuario = app()->obtenerUsuarioParaApp($app);
    //                 if (!$id_usuario) {
    //                     return ['exito' => false, 'error' => 'No hay usuarios disponibles para asignación aleatoria'];
    //                 }
    //             } elseif (isset($data['id_usuario']) && !empty($data['id_usuario'])) {
    //                 $id_usuario = (int) $data['id_usuario'];
    //                 $usuario_app = model('UsuarioAppModel')
    //                     ->where('id_app', $id_app)
    //                     ->where('id_usuario', $id_usuario)
    //                     ->where('deleted_at', null)
    //                     ->first();
    //                 if (!$usuario_app) {
    //                     return ['exito' => false, 'error' => 'El usuario seleccionado no pertenece a esta app'];
    //                 }
    //             } else {
    //                 return ['exito' => false, 'error' => 'Para app compartida debe seleccionar un usuario o usar asignación aleatoria'];
    //             }
    //         }
            
    //         // Verificar si ya existe conversación activa (misma lógica)
    //         $conversacion_activa = model('ConversacionModel')
    //             ->where('id_cliente', $id_cliente)
    //             ->where('id_app', $id_app)
    //             ->whereIn('id_estado', [1, 3])
    //             ->first();
            
    //         if ($conversacion_activa) {
    //             return ['exito' => false, 'error' => 'Ya existe una conversación activa para este cliente en esta app'];
    //         }
            
    //         // Crear conversación (misma lógica pero con registro='importacion')
    //         $origen = $data['origen'] ?? 'importacion';
    //         $conversacion_result = conversaciones()->crearConversacion([
    //             'id_cliente' => $id_cliente,
    //             'id_usuario' => $id_usuario,
    //             'origen' => $origen,
    //             'servicio' => $data['servicio'] ?? null,
    //             'registro' => 'importacion',
    //             'id_app' => $id_app,
    //             'id_estado' => 3,
    //             'id_asignado_por' => null,
    //             'asignado_at' => date('Y-m-d H:i:s'),
    //         ]);
            
    //         // Crear mensaje inicial con los datos del cliente (reutilizar mensaje()->crearMensaje)
    //         $cliente_perfil = $this->getClientePerfilProvider()->where('id_cliente', $id_cliente)->first();
    //         $nombre_cliente = $cliente_perfil ? trim(($cliente_perfil->nombre ?? '') . ' ' . ($cliente_perfil->apellido ?? '')) : 'Cliente';
    //         $telefono_cliente = $datos_cliente['telefono'] ?? '';
            
    //         // Construir mensaje con datos del cliente
    //         $mensaje_texto = "Cliente: {$nombre_cliente}\n";
    //         $mensaje_texto .= "Teléfono: {$telefono_cliente}\n";
    //         if (!empty($datos_cliente['marca'])) {
    //             $mensaje_texto .= "Marca: {$datos_cliente['marca']}\n";
    //         }
    //         if (!empty($datos_cliente['modelo'])) {
    //             $mensaje_texto .= "Modelo: {$datos_cliente['modelo']}\n";
    //         }
    //         if (!empty($datos_cliente['placa'])) {
    //             $mensaje_texto .= "Placa: {$datos_cliente['placa']}\n";
    //         }
    //         if (!empty($data['servicio'])) {
    //             $mensaje_texto .= "Servicio: {$data['servicio']}\n";
    //         }
    //         if (!empty($origen) && $origen !== 'importacion') {
    //             $mensaje_texto .= "Origen: {$origen}\n";
    //         }
    //         $mensaje_texto .= "Lead importado desde Excel";
            
    //         try {
    //             mensaje()->crearMensaje([
    //                 'id_conversacion' => $conversacion_result['id'],
    //                 'id_estado' => 4, // Leído (mensaje del sistema)
    //                 'mensaje' => $mensaje_texto,
    //                 'contesta' => 'bot', // Mensaje del sistema/bot
    //                 'id_mensaje_whatsapp' => null,
    //                 'id_mensaje_responde' => null,
    //                 'cod_error' => null,
    //                 'media_path' => null,
    //                 'media_url' => null,
    //                 'media_type' => null,
    //                 'payload' => json_encode(['tipo' => 'importacion', 'origen' => $origen]),
    //             ]);
    //         } catch (\Exception $e) {
    //             log_message('error', "Error al crear mensaje inicial en importación: " . $e->getMessage());
    //             // No fallar la importación si falla el mensaje
    //         }
            
    //         return [
    //             'exito' => true,
    //             'cliente_id' => $id_cliente,
    //             'conversacion_id' => $conversacion_result['id']
    //         ];
            
    //     } catch (\Exception $e) {
    //         return ['exito' => false, 'error' => $e->getMessage()];
    //     }
    // }

    public function eliminarCliente(int $id)
    {
        $cliente_model = $this->getClienteProvider();
        
        $cliente_existente = $cliente_model->find($id);
        if (!$cliente_existente) {
            throw new \Exception('Cliente no encontrado.');
        }

        // Soft delete: usar el método delete() que respeta useSoftDeletes
        if ($cliente_model->delete($id)) {
            return [
                'success' => true,
                'message' => 'Cliente eliminado correctamente',
                'id' => $id
            ];
        } else {
            throw new \Exception('Error al eliminar el cliente');
        }
    }

    public function eliminarMultiplesClientes(array $ids)
    {
        $cliente_model = $this->getClienteProvider();
        
        if (empty($ids)) {
            throw new \Exception('Debe proporcionar al menos un ID de cliente');
        }

        // Validar que todos los IDs existan
        $ids_validos = [];
        $ids_no_encontrados = [];
        
        foreach ($ids as $id) {
            $cliente_existente = $cliente_model->find($id);
            if ($cliente_existente) {
                $ids_validos[] = $id;
            } else {
                $ids_no_encontrados[] = $id;
            }
        }
        
        if (empty($ids_validos)) {
            throw new \Exception('Ninguno de los clientes fue encontrado');
        }

        // Soft delete: eliminar todos los válidos
        $eliminados = [];
        foreach ($ids_validos as $id) {
            if ($cliente_model->delete($id)) {
                $eliminados[] = $id;
            }
        }
        
        return [
            'success' => true,
            'total_solicitados' => count($ids),
            'total_eliminados' => count($eliminados),
            'ids_eliminados' => $eliminados,
            'ids_no_encontrados' => $ids_no_encontrados,
            'message' => count($eliminados) . ' cliente(s) eliminado(s) correctamente'
        ];
    }

}
