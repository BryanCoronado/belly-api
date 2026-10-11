<?php
namespace Belly\Configuracion\UseCases;

use Belly\Configuracion\Config\ConfiguracionConfig;
use Belly\Configuracion\Models\AppModel;
use Belly\Configuracion\Models\TipoDocumentoModel;
use Belly\Configuracion\Entities\AppEntity;

class AppUseCase {
    /** @see grupo_usuario_rol — rol «Personal de Contact Center» */
    private const ID_GRUPO_USUARIO_ROL_CONTACT_CENTER = 5;

    /** @see grupo_usuario_rol — sin recibir chats nuevos */
    private const ID_GRUPO_USUARIO_ROL_CONTACT_CENTER_OFF = 8;

    protected ConfiguracionConfig $config;
    protected ?AppModel $app_provider = NULL;
    protected ?TipoDocumentoModel $tipo_documento_provider = NULL;

    public function __construct(ConfiguracionConfig $config)
    {
        $this->config = $config;
    }

    public function getAppProvider(): AppModel
    {
        if ($this->app_provider !== null) {
            return $this->app_provider;
        }

        $class_name = $this->config->app_provider;
        $this->app_provider = new $class_name();

        return $this->app_provider;
    }

    public function getTipoDocumentoProvider(): TipoDocumentoModel
    {
        if ($this->tipo_documento_provider !== null) {
            return $this->tipo_documento_provider;
        }

        $class_name = $this->config->tipo_documento_provider;
        $this->tipo_documento_provider = new $class_name();

        return $this->tipo_documento_provider;
    }

    public function crearApp(array $datos)
    {
        $app_model = $this->getAppProvider();
        
        if (($datos['modo'] ?? null) === 'compartido') {
            if (empty($datos['max_usuarios']) || $datos['max_usuarios'] < 1) {
                throw new \Exception('Para apps compartidas, max_usuarios es obligatorio y debe ser mayor a 0');
            }
        }

        $app_entity = new AppEntity();
        $app_entity->id_empresa = $this->resolverIdEmpresaApp($datos);
        $app_entity->id_portafolio = $datos['id_portafolio'] ?? null;
        $app_entity->nombre = $datos['nombre'];
        $app_entity->telefono = $datos['telefono'];
        $app_entity->phone_number_id = $datos['phone_number_id'] ?? null;
        $app_entity->canal = $datos['canal'] ?? 'whatsapp';
        $app_entity->whatsapp_business_account_id = $datos['whatsapp_business_account_id'] ?? null;
        $app_entity->access_token = $datos['access_token'] ?? null;
        $app_entity->modo = $datos['modo'] ?? 'individual';
        $app_entity->tipo = $datos['tipo'] ?? 'NORMAL';
        $app_entity->max_usuarios = $datos['max_usuarios'] ?? null;
        $app_entity->estado = $datos['estado'] ?? 1;

        if (!$app_model->save($app_entity)) {
            throw new \Exception(json_encode($app_model->errors()));
        }

        $app_entity->id = $app_model->insertID;
        
        // Retornar la app creada con información de portafolio
        return $app_model
            ->select('app.*, portafolio.nombre as portafolio_nombre')
            ->join('portafolio', 'portafolio.id = app.id_portafolio', 'left')
            ->where('app.id', $app_entity->id)
            ->first();
    }

    public function listarUsuariosPorApp(int $id_app): array
    {
        $app_model = $this->getAppProvider();
        
        // Obtener información de la app
        $app = $app_model
            ->select('app.id, app.nombre, app.modo, app.max_usuarios')
            ->where('app.id', $id_app)
            ->first();
        
        if (!$app) {
            throw new \Exception('App no encontrada');
        }
        
        // Obtener usuarios asignados a la app
        $usuarios = $app_model
            ->select('usuario.id, usuario.usuario, usuario.activo, usuario_app.created_at as fecha_asignacion')
            ->join('usuario_app', 'usuario_app.id_app = app.id')
            ->join('usuario', 'usuario.id = usuario_app.id_usuario')
            ->where('app.id', $id_app)
            ->where('usuario_app.deleted_at', null)
            ->where('usuario.deleted_at', null)
            ->orderBy('usuario.usuario', 'ASC')
            ->findAll();
        
        // Retornar estructura con app y usuarios
        return [
            'id' => $app->id,
            'nombre' => $app->nombre,
            'modo' => $app->modo,
            'max_usuarios' => $app->max_usuarios,
            'usuarios' => $usuarios
        ];
    }

    public function asignarUsuarioAApp(int $id_app, int $id_usuario): array
    {
        $app_model = $this->getAppProvider();
        
        // Verificar que la app existe
        $app = $app_model->find($id_app);
        if (!$app) {
            throw new \Exception('App no encontrada');
        }
        
        // Verificar que el usuario existe
        $usuario_model = model('UsuarioModel');
        $usuario = $usuario_model->find($id_usuario);
        if (!$usuario) {
            throw new \Exception('Usuario no encontrado');
        }
        
        // Verificar que el usuario no esté ya asignado
        $usuario_app_model = model('UsuarioAppModel');
        $ya_asignado = $usuario_app_model
            ->where('id_app', $id_app)
            ->where('id_usuario', $id_usuario)
            ->where('deleted_at', null)
            ->first();
        
        if ($ya_asignado) {
            throw new \Exception('El usuario ya está asignado a esta app');
        }
        
        // Verificar si existe un registro soft deleted para restaurarlo
        $registro_existente = $usuario_app_model
            ->where('id_app', $id_app)
            ->where('id_usuario', $id_usuario)
            ->withDeleted() // Incluir registros soft deleted
            ->first();
        
        $registro_soft_deleted = $registro_existente && $registro_existente->deleted_at !== null ? $registro_existente : null;
        
        // Verificar límite de usuarios según el modo de la app
        if ($app->modo === 'individual') {
            // En modo individual, solo puede haber un usuario
            $usuarios_asignados = $usuario_app_model
                ->where('id_app', $id_app)
                ->where('deleted_at', null)
                ->countAllResults();
            
            if ($usuarios_asignados >= 1) {
                throw new \Exception('La app en modo individual ya tiene un usuario asignado');
            }
        } else {
            // En modo compartido, verificar el límite max_usuarios
            $usuarios_asignados = $usuario_app_model
                ->where('id_app', $id_app)
                ->where('deleted_at', null)
                ->countAllResults();
            
            if ($usuarios_asignados >= $app->max_usuarios) {
                throw new \Exception("La app ha alcanzado el límite de {$app->max_usuarios} usuarios");
            }
        }
        
        // Si existe un registro soft deleted, restaurarlo
        if ($registro_soft_deleted) {
            // Usar query builder directamente para evitar restricciones de soft deletes
            // Ya verificamos que el registro existe y tiene deleted_at no nulo arriba
            $db = \Config\Database::connect();
            $resultado = $db->table('usuario_app')
                ->where('id_app', $id_app)
                ->where('id_usuario', $id_usuario)
                ->set('deleted_at', null)
                ->update();
            
            if ($resultado === false) {
                throw new \Exception('Error al restaurar la asignación del usuario a la app');
            }
            
            // Si no se afectó ninguna fila, el registro pudo no existir o ya estar restaurado
            if ($resultado === 0) {
                throw new \Exception('No se pudo restaurar la asignación. El registro podría no existir o ya estar activo');
            }
        } else {
            // Si no existe, crear nuevo registro
            $resultado = $usuario_app_model->db->table('usuario_app')->insert([
                'id_app' => $id_app,
                'id_usuario' => $id_usuario,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            if (!$resultado) {
                throw new \Exception('Error al asignar el usuario a la app');
            }
        }
        
        return [
            'message' => 'Usuario asignado correctamente',
            'id_app' => $id_app,
            'id_usuario' => $id_usuario,
            'usuario' => $usuario->usuario
        ];
    }

    public function desasignarUsuarioDeApp(int $id_app, int $id_usuario): array
    {
        $app_model = $this->getAppProvider();
        
        // Verificar que la app existe
        $app = $app_model->find($id_app);
        if (!$app) {
            throw new \Exception('App no encontrada');
        }
        
        // Verificar que el usuario existe
        $usuario_model = model('UsuarioModel');
        $usuario = $usuario_model->find($id_usuario);
        if (!$usuario) {
            throw new \Exception('Usuario no encontrado');
        }
        
        // Verificar que el usuario está asignado a la app
        $usuario_app_model = model('UsuarioAppModel');
        $asignacion = $usuario_app_model
            ->where('id_app', $id_app)
            ->where('id_usuario', $id_usuario)
            ->where('deleted_at', null)
            ->first();
        
        if (!$asignacion) {
            throw new \Exception('El usuario no está asignado a esta app');
        }
        
        // Verificar si el usuario tiene conversaciones activas
        $conversaciones_activas = model('ConversacionModel')
            ->where('id_usuario', $id_usuario)
            ->where('id_estado', 1)
            ->where('deleted_at', null)
            ->countAllResults();
        

        
        // Realizar soft delete de la asignación usando where
        $usuario_app_model
            ->where('id_app', $id_app)
            ->where('id_usuario', $id_usuario)
            ->delete();
        
        return [
            'message' => 'Usuario desasignado correctamente',
            'id_app' => $id_app,
            'id_usuario' => $id_usuario,
            'usuario' => $usuario->usuario
        ];
    }

    public function obtenerApp($campo, $valor)
    {
        $class_name = $this->config->app_provider;
        $app_model = new $class_name();
        
        $app = $app_model
            ->where($campo, $valor)
            ->where('estado', 1)
            ->where('deleted_at', null)
            ->first();
        
        if (!$app) {
            log_message('debug', "App no encontrada con $campo = $valor (activa y no eliminada)");
        } else {
            log_message('debug', "App encontrada: ID={$app->id}, nombre={$app->nombre}, phone_number_id={$app->phone_number_id}");
        }
        
        return $app;
    }

    public function listarApps(): array
    {
        $app_model = $this->getAppProvider();
        
        $apps = $app_model->select('app.*, portafolio.nombre as portafolio_nombre')
            ->join('portafolio', 'portafolio.id = app.id_portafolio', 'left')
            ->orderBy('app.nombre', 'ASC')
            ->findAll();
        
        return $apps;
    }


    public function listarPortafoliosConApps(): array
    {
        $portafolio_model = model('Belly\Configuracion\Models\PortafolioModel');
        $app_model = $this->getAppProvider();
        
        $portafolios = $portafolio_model
            ->where('deleted_at', null)
            ->orderBy('nombre', 'ASC')
            ->findAll();
        
        foreach ($portafolios as $portafolio) {
            $apps = $app_model
                ->where('id_portafolio', $portafolio->id)
                ->where('deleted_at', null)
                ->orderBy('nombre', 'ASC')
                ->findAll();
            
            $portafolio->apps = $apps;
        }
        
        return $portafolios;
    }

    public function obtenerListadoDocumentos(): array
    {
        $tipo_documento_model = $this->getTipoDocumentoProvider();
        $documentos = $tipo_documento_model->findAll();
        return $documentos;
    }

    public function obtenerAppsDisponibles(): array
    {
        $app_model = $this->getAppProvider();
        $apps = $app_model->select('app.*, portafolio.nombre as portafolio_nombre')
            ->join('portafolio', 'portafolio.id = app.id_portafolio', 'left')
            ->where('app.estado', 1)
            ->groupStart()
                ->where('app.modo', 'individual')
                ->whereNotIn('app.id', function($builder) {
                    return $builder->select('id_app')
                        ->from('usuario_app')
                        ->where('usuario_app.deleted_at', null);
                })
                ->groupEnd()
            ->orGroupStart()
                ->where('app.modo', 'compartido')
                ->where('app.max_usuarios > (SELECT COUNT(*) FROM usuario_app WHERE id_app = app.id AND deleted_at IS NULL)', null, false)
                ->groupEnd()
            ->findAll();
        
        // Agregar información de usuarios asignados para cada app
        foreach ($apps as $app) {
            $usuarios_asignados = model('UsuarioAppModel')
                ->where('id_app', $app->id)
                ->where('deleted_at', null)
                ->countAllResults();
            
            $app->usuarios_asignados = $usuarios_asignados;
        }
        
        return $apps;
    }

    public function obtenerUsuarioParaApp($app, ?int $excluir_usuario = null): ?int
    {
        if ($app->modo === 'individual') {
            $usuario = usuario()->obtenerUsuarioPorApp($app->id);
            return $usuario ? $usuario->id : null;
        }

        return $this->seleccionarUsuarioDisponibleEnApp((int) $app->id, $excluir_usuario);
    }

    /**
     * Puede recibir chats nuevos: usuario_app + rol 5 (Contact Center) y sin rol 8 (off).
     */
    public function usuarioPuedeRecibirChatsEnApp(int $id_usuario, int $id_app): bool
    {
        if ($id_usuario <= 0 || $id_app <= 0) {
            return false;
        }

        $usuario = model('UsuarioModel')->find($id_usuario);
        if ($usuario === null
            || (int) ($usuario->activo ?? 0) !== 1
            || (int) ($usuario->cuenta_bloqueada ?? 0) === 1
            || (int) ($usuario->es_bot ?? 0) === 1
            || $usuario->deleted_at !== null) {
            return false;
        }

        $usuarioApp = model('UsuarioAppModel')
            ->where('id_app', $id_app)
            ->where('id_usuario', $id_usuario)
            ->where('deleted_at', null)
            ->first();

        if ($usuarioApp === null) {
            return false;
        }

        $tieneRolCc = model('GrupoUsuarioUsuarioModel')
            ->where('id_usuario', $id_usuario)
            ->where('id_grupo_usuario_rol', self::ID_GRUPO_USUARIO_ROL_CONTACT_CENTER)
            ->where('deleted_at', null)
            ->countAllResults() > 0;

        if (! $tieneRolCc) {
            return false;
        }

        $tieneRolOff = model('GrupoUsuarioUsuarioModel')
            ->where('id_usuario', $id_usuario)
            ->where('id_grupo_usuario_rol', self::ID_GRUPO_USUARIO_ROL_CONTACT_CENTER_OFF)
            ->where('deleted_at', null)
            ->countAllResults() > 0;

        return ! $tieneRolOff;
    }

    private function sqlExcluirUsuario(?int $excluir_usuario): string
    {
        if ($excluir_usuario === null || $excluir_usuario <= 0) {
            return '';
        }

        return ' AND q1.id != ' . (int) $excluir_usuario;
    }

    private function sqlExcluirRolContactCenterOff(int $idRolOff): string
    {
        return ' AND NOT EXISTS (
                            SELECT 1 FROM grupo_usuario_usuario guu_off
                            WHERE guu_off.id_usuario = q1.id
                              AND guu_off.id_grupo_usuario_rol = ' . $idRolOff . '
                              AND guu_off.deleted_at IS NULL
                        )';
    }

    /**
     * Selecciona un usuario disponible de forma equitativa para una app compartida.
     * Solo participan usuarios con rol Personal de Contact Center (`grupo_usuario_rol.id` = 5)
     * y sin rol «off» (id 8, sin recibir chats).
     *
     * Reparto por día calendario (fecha del servidor MySQL, CURDATE):
     * - Gana quien tenga menos conversaciones con asignado_at hoy para esta app.
     * - Empate: quien lleve más tiempo sin una asignación hoy (última asignación hoy más antigua).
     * - Así las asesoras nuevas y las antiguas compiten en igualdad cada día, sin “penalizar”
     *   al histórico de conversaciones de meses atrás.
     *
     * Horarios (horario_grupo_usuario_rol): desactivados en SQL hasta nuevo aviso.
     *
     * Usa SELECT FOR UPDATE para bloquear filas y reducir carreras entre procesos.
     */
    private function seleccionarUsuarioDisponibleEnApp(int $id_app, ?int $excluir_usuario = null): ?int
    {
        $db = $this->getAppProvider()->db;
        $idRolCc = self::ID_GRUPO_USUARIO_ROL_CONTACT_CENTER;
        $idRolOff = self::ID_GRUPO_USUARIO_ROL_CONTACT_CENTER_OFF;
        $id_app = (int) $id_app;
        $excluirSql = $this->sqlExcluirUsuario($excluir_usuario);
        $excluirOffSql = $this->sqlExcluirRolContactCenterOff($idRolOff);

        // Iniciar transacción para asegurar consistencia
        $db->transStart();

        try {
            $stmt = "SELECT 
                        q1.id AS id_usuario,
                        q1.usuario,
                        COALESCE(q2.cnt_hoy, 0) AS asignaciones_hoy,
                        q2.ult_hoy AS ultima_asignacion_hoy
                    FROM usuario AS q1
                    INNER JOIN usuario_app AS ua ON (ua.id_usuario = q1.id AND ua.id_app = {$id_app} AND ua.deleted_at IS NULL)
                    LEFT JOIN (
                        SELECT 
                            s1.id_usuario,
                            COUNT(*) AS cnt_hoy,
                            MAX(s1.asignado_at) AS ult_hoy
                        FROM conversacion AS s1
                        WHERE 
                            s1.id_usuario IS NOT NULL
                            AND s1.id_app = {$id_app}
                            AND DATE(s1.asignado_at) = CURDATE()
                            AND s1.deleted_at IS NULL
                        GROUP BY s1.id_usuario
                    ) AS q2 ON (q2.id_usuario = q1.id)
                    WHERE 
                        q1.activo = 1
                        AND q1.cuenta_bloqueada = 0
                        AND q1.es_bot = 0
                        AND q1.deleted_at IS NULL
                        AND EXISTS (
                            SELECT 1 FROM grupo_usuario_usuario guu_cc
                            WHERE guu_cc.id_usuario = q1.id
                              AND guu_cc.id_grupo_usuario_rol = {$idRolCc}
                              AND guu_cc.deleted_at IS NULL
                        ){$excluirOffSql}{$excluirSql}
                    ORDER BY asignaciones_hoy ASC, q2.ult_hoy ASC, q1.id ASC
                    LIMIT 1
                    FOR UPDATE";
            
            $query = $db->query($stmt);
            $row = $query->getRowArray();
            
            // Completar la transacción
            $db->transComplete();
            
            if ($row && isset($row['id_usuario'])) {
                log_message(
                    'info',
                    "Usuario equitativo (por día) para app {$id_app}: {$row['usuario']} (ID: {$row['id_usuario']}) "
                    . "asignaciones_hoy={$row['asignaciones_hoy']}"
                );
                return (int) $row['id_usuario'];
            }
            
            log_message('warning', "No se encontró usuario equitativo (rol Contact Center, id_grupo_usuario_rol={$idRolCc}) para app: $id_app");
            return null;
            
        } catch (\Exception $e) {
            $db->transRollback();
            log_message('error', "Error al seleccionar usuario equitativo: " . $e->getMessage());
            return null;
        }
    }

    public function listarMisApps(): array
    {
        $usuario_logueado = auth()->getUsuario();
        if (!$usuario_logueado) {
            throw new \Exception('No se pudo obtener el usuario autenticado');
        }

        $id_usuario = $usuario_logueado->id;
        $app_model = $this->getAppProvider();

        return $app_model
            ->select('app.id, app.nombre, app.telefono, app.modo, app.tipo, app.estado, app.id_portafolio, portafolio.nombre as portafolio_nombre')
            ->join('usuario_app', 'usuario_app.id_app = app.id')
            ->join('portafolio', 'portafolio.id = app.id_portafolio', 'left')
            ->where('usuario_app.id_usuario', $id_usuario)
            ->where('usuario_app.deleted_at', null)
            ->where('app.estado', 1)
            ->where('app.deleted_at', null)
            ->orderBy('app.nombre', 'ASC')
            ->findAll();
    }

    private function resolverIdEmpresaApp(array $datos): int
    {
        $id = (int) ($datos['id_empresa'] ?? 0);
        if ($id > 0) {
            return $id;
        }

        if (! empty($datos['id_portafolio'])) {
            $portafolio = model('PortafolioModel')->find((int) $datos['id_portafolio']);
            $id = (int) ($portafolio->id_empresa ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        helper('vip2system');
        $id = (int) (id_empresa_sesion() ?? 0);
        if ($id <= 0) {
            throw new \Exception('id_empresa es obligatorio para la app.');
        }

        return $id;
    }
}
