<?php
namespace Belly\Usuarios\UseCases;

use Belly\Services\Autorizacion;
use Belly\Usuarios\Config\UsuarioConfig;
use Belly\Usuarios\Models\UsuarioModel;
use Belly\Usuarios\Entities\UsuarioEntity;
use Belly\Usuarios\Entities\UsuarioPerfilEntity;
use Belly\Usuarios\Models\UsuarioPerfilModel;

class UsuarioUseCase {
    private const TIPO_DOCUMENTO_POR_DEFECTO = 1;

    protected UsuarioConfig $config;
    protected ?UsuarioModel $usuario_provider = NULL;
    protected ?UsuarioPerfilModel $perfil_provider = NULL;


    public function __construct(UsuarioConfig $config)
    {
        $this->config = $config;
    }

    public function getUsuarioProvider(): UsuarioModel
    {
        if ($this->usuario_provider !== null) {
            return $this->usuario_provider;
        }

        $class_name = $this->config->usuario_provider;
        $this->usuario_provider = new $class_name();

        return $this->usuario_provider;
    }

    public function getPerfilProvider(): UsuarioPerfilModel
    {
        if ($this->perfil_provider !== null) {
            return $this->perfil_provider;
        }

        $className = $this->config->perfil_provider;
        $this->perfil_provider = new $className();

        return $this->perfil_provider;
    }


    public function getById($id=0)
    {
        $usuario_model = $this->getUsuarioProvider();
        $usuario_model->select(['usuario.id as id_usuario','usuario.id_estado_usuario', 'es.nombre as nombre_estado_usuario', 'usuario.created_at', 'usuario.usuario', 'usuario.activo', 'up.nombres', 'up.apellidos', 'up.id_tipo_documento', 'up.nro_documento','doc.tipo as nombre_documento', 'up.correo', 'up.celular', 'up.celular_secundario', 'usuario.cuenta_bloqueada']);
        $usuario_model->join('usuario_perfil as up', 'up.id_usuario = usuario.id');
        $usuario_model->join('estado_usuario as es','es.id = usuario.id_estado_usuario');
        $usuario_model->join('tipo_documento as doc', 'doc.id = up.id_tipo_documento', 'left');
        $usuario = $usuario_model->find($id);

        if (isset($usuario) && !empty($usuario)) {
            return $usuario;
        }

        return ['errors' => lang('Usuarios.userNoExist')];
    }



    public function crearUsuario(array $datos)
    {
        $this->exigirAdministradorEmpresa();
        $this->validarNuevoUsuario($datos);

        $idEmpresa = $this->resolverIdEmpresaUsuario($datos);
        $usuario = strtolower(trim((string) $datos['usuario']));
        $ocupado = db_connect()->table('usuario')
            ->where('id_empresa', $idEmpresa)
            ->where('usuario', $usuario)
            ->where('deleted_at', null)
            ->countAllResults();
        if ($ocupado > 0) {
            throw new \Exception('Ese usuario ya existe en la empresa.');
        }

        $idRol = (int) ($datos['id_rol'] ?? 0);
        $rol = $idRol > 0 ? acceso()->rolDeEmpresa($idRol, $idEmpresa) : acceso()->rolPorDefecto($idEmpresa);
        $idRol = (int) $rol['id'];

        $datos['usuario'] = $usuario;

        $password = $this->encriptarContrasenia($datos);

        $usuario_entity = new UsuarioEntity();
        $usuario_entity->id_empresa = $idEmpresa;
        $usuario_entity->usuario = $usuario;
        $usuario_entity->password = $password;
        $usuario_entity->login_fallidos = 0;
        $usuario_entity->cuenta_bloqueada = 0;
        $usuario_entity->id_estado_usuario = 6;
        $usuario_entity->activo = 1;
        $usuario_entity->sudo = (int) ($rol['es_admin'] ?? 0) === 1 ? 1 : 0;

        $usuario_model = $this->getUsuarioProvider();
        if (! $usuario_model->save($usuario_entity)) {
            throw new \Exception(json_encode($usuario_model->errors()));
        }

        $usuario_entity->id = (int) $usuario_model->insertID;
        $perfil = $this->crearPerfilUsuario($datos, $usuario_entity->id);
        $idGrupo = (int) ($datos['id_grupo_usuario'] ?? 0);
        acceso()->asignarUsuarioARol($usuario_entity->id, $idRol, $idEmpresa, $idGrupo > 0 ? $idGrupo : null);

        return [
            'id_usuario' => $usuario_entity->id,
            'usuario' => $usuario_entity->usuario,
            'nombres' => $perfil->nombres,
            'apellidos' => $perfil->apellidos,
            'nro_documento' => $perfil->nro_documento,
        ];
    }


    public function crearPerfilUsuario(array $datos, int $id_usuario)
    {
        $model_perfil = $this->getPerfilProvider();

        $perfil_entity = new UsuarioPerfilEntity();
        $perfil_entity->id_usuario = $id_usuario;

        $documento = $this->opcional($datos, 'nro_documento');
        $perfil_entity->nombres = trim((string) $datos['nombres']);
        $perfil_entity->apellidos = $this->opcional($datos, 'apellidos');
        $perfil_entity->id_tipo_documento = $documento === null ? null : $this->resolverIdTipoDocumento($datos);
        $perfil_entity->nro_documento = $documento;
        $perfil_entity->correo = $this->opcional($datos, 'correo');
        $perfil_entity->celular = $this->opcional($datos, 'celular');
        $perfil_entity->celular_secundario = $this->opcional($datos, 'celular_secundario');

        if ($model_perfil->save($perfil_entity)) {
            $perfil_entity->id = $model_perfil->insertID;
            return $perfil_entity;
        } else {
            throw new \Exception(json_encode($model_perfil->errors()));
        }
    }



    public static function encriptarContrasenia(array $datos)
    {
        if (isset($datos['password']) && !empty($datos['password'])) {
            return password_hash($datos['password'], PASSWORD_BCRYPT);
        } else {
            throw new \Exception(lang('UsuariosL.noPassword'));
        }
    }


    public function obtenerUsuarioPorApp($id_app)
    {
        $usuario_model = $this->getUsuarioProvider();
        return $usuario_model
            ->join('usuario_app', 'usuario_app.id_usuario = usuario.id')
            ->where('usuario_app.id_app', $id_app)
            ->where('usuario_app.deleted_at', null)
            ->where('usuario.activo', 1)
            ->where('usuario.deleted_at', null)
            ->first();
    }


    public function listar_usuarios($page = 1, $perPage = 10, $search = null)
    {
        $usuario_model = $this->getUsuarioProvider();
        $usuario_model->select([
            'usuario.id as id_usuario',
            'usuario.created_at',
            'usuario.usuario',
            'usuario.activo',
            'usuario.es_bot',
            'up.nombres',
            'up.apellidos',
            'up.id_tipo_documento',
            'up.nro_documento',
            'up.celular',
            'up.celular_secundario',
            'doc.tipo as nombre_documento',
            'up.correo',
            'usuario.cuenta_bloqueada',
            'usuario.sudo',
            'gur.rol',
            'gur.es_admin',
            'guu.id_grupo_usuario_rol as id_rol',
            'guu.id_grupo_usuario',
            'gu.grupo',
        ]);
        $usuario_model->join('usuario_perfil as up', 'up.id_usuario = usuario.id');
        $usuario_model->join('tipo_documento as doc', 'doc.id = up.id_tipo_documento', 'left');
        $usuario_model->join('grupo_usuario_usuario as guu', 'guu.id_usuario = usuario.id AND guu.deleted_at IS NULL', 'left');
        $usuario_model->join('grupo_usuario_rol as gur', 'gur.id = guu.id_grupo_usuario_rol AND gur.deleted_at IS NULL', 'left');
        $usuario_model->join('grupo_usuario as gu', 'gu.id = guu.id_grupo_usuario AND gu.deleted_at IS NULL', 'left');
        $usuario_model->where('usuario.es_bot', 0);
        
        // Filtro de búsqueda
        if (!empty($search)) {
            $usuario_model->groupStart();
            $usuario_model->like('usuario.usuario', $search);
            $usuario_model->orLike('up.nombres', $search);
            $usuario_model->orLike('up.apellidos', $search);
            $usuario_model->orLike('up.correo', $search);
            $usuario_model->orLike('up.nro_documento', $search);
            $usuario_model->orLike('up.celular', $search);
            $usuario_model->orLike('up.celular_secundario', $search);
            $usuario_model->groupEnd();
        }
        
        $usuario_model->orderBy('up.nombres', 'ASC');
        $usuario_model->orderBy('up.apellidos', 'ASC');

        // Sin paginación si perPage es 'all'
        if ($perPage === 'all') {
            $usuarios = $usuario_model->findAll();
            $total = count($usuarios);
            return [
                'data' => $usuarios,
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

        // Con paginación
        $result = $usuario_model->paginate($perPage, 'default', $page);
        $pager = $usuario_model->pager;
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

    public function editarUsuario(array $datos, int $id)
    {
        $this->exigirAdministradorEmpresa();
        $usuario_model = $this->getUsuarioProvider();
        $existe_usuario = $usuario_model->find($id);

        if (!empty($existe_usuario)) {
            $this->validarDatosPerfil($datos, false);

            $usuario_entity = new UsuarioEntity();
            $usuario_entity->id = $id;
            $usuario_entity->usuario = isset($datos['usuario']) && trim((string) $datos['usuario']) !== ''
                ? strtolower(trim((string) $datos['usuario']))
                : $existe_usuario->usuario;
            if ($usuario_entity->usuario !== $existe_usuario->usuario) {
                $ocupado = db_connect()->table('usuario')
                    ->where('id_empresa', $existe_usuario->id_empresa)
                    ->where('usuario', $usuario_entity->usuario)
                    ->where('id !=', $id)
                    ->where('deleted_at', null)
                    ->countAllResults();
                if ($ocupado > 0) {
                    throw new \Exception('Ese usuario ya existe en la empresa.');
                }
            }
            $cambiaClave = isset($datos['password']) && $datos['password'] !== '';
            if ($cambiaClave && strlen((string) $datos['password']) < 8) {
                throw new \Exception('La contraseña debe tener al menos 8 caracteres.');
            }

            if ($cambiaClave) {
                $usuario_entity->password = $this->encriptarContrasenia($datos);
            }
            
            $usuario_entity->cuenta_bloqueada = !isset($datos['cuenta_bloqueada']) ? $existe_usuario->cuenta_bloqueada : $datos['cuenta_bloqueada'];
            $usuario_entity->activo = !isset($datos['activo']) ? $existe_usuario->activo : $datos['activo'];
            $usuario_entity->sudo = !isset($datos['sudo']) ? $existe_usuario->sudo : $datos['sudo'];
            $usuario_entity->id_estado_usuario = !isset($datos['id_estado_usuario']) ? $existe_usuario->id_estado_usuario : $datos['id_estado_usuario'];


            if ($usuario_model->save($usuario_entity)) {
                if ($cambiaClave) {
                    auth()->cerrarSesionesUsuario($id);
                }
                $perfil = $this->editarPerfil($datos, $id);
                $idRol = (int) ($datos['id_rol'] ?? 0);
                if ($idRol > 0) {
                    $idEmpresa = (int) ($existe_usuario->id_empresa ?? $this->resolverIdEmpresaUsuario($datos));
                    $rol = acceso()->rolDeEmpresa($idRol, $idEmpresa);
                    $idGrupo = (int) ($datos['id_grupo_usuario'] ?? 0);
                    acceso()->asignarUsuarioARol($id, $idRol, $idEmpresa, $idGrupo > 0 ? $idGrupo : null);
                    $usuario_entity->sudo = (int) ($rol['es_admin'] ?? 0) === 1 ? 1 : 0;
                    $usuario_model->save($usuario_entity);
                }
                return [
                    'id_usuario' => $usuario_entity->id,
                    'usuario' => $usuario_entity->usuario,
                    'nombres' => $perfil->nombres,
                    'nro_documento' => $perfil->nro_documento,
                    'sudo' => $usuario_entity->sudo,
                    'activo' => $usuario_entity->activo
                ];
            } else {
                throw new \Exception(json_encode($usuario_model->errors()));
            }
        } else {
            throw new \Exception(lang('UsuariosL.noIdUser'));
        }
    }

    public function editarPerfil(array $datos, int $id_usuario_perfil)
    {
        $perfil_model = $this->getPerfilProvider();
        $existe_perfil_model = $perfil_model->where('id_usuario', $id_usuario_perfil)->first();

        if (isset($existe_perfil_model->id_usuario)) {
            $valor = fn (string $campo) => array_key_exists($campo, $datos)
                ? $this->opcional($datos, $campo)
                : $existe_perfil_model->{$campo};

            $perfil_entity = new UsuarioPerfilEntity();
            $perfil_entity->id_usuario = $id_usuario_perfil;
            $perfil_entity->nombres = $valor('nombres') ?? $existe_perfil_model->nombres;
            $perfil_entity->apellidos = $valor('apellidos');
            $perfil_entity->nro_documento = $valor('nro_documento');
            $perfil_entity->id_tipo_documento = $perfil_entity->nro_documento === null
                ? null
                : $this->resolverIdTipoDocumento($datos, (int) ($existe_perfil_model->id_tipo_documento ?? 0));
            $perfil_entity->correo = $valor('correo');
            $perfil_entity->celular = $valor('celular');
            $perfil_entity->celular_secundario = $valor('celular_secundario');

            if ($perfil_model->save($perfil_entity)) {
                return $perfil_entity;
            } else {
                throw new \Exception(json_encode($perfil_model->errors()));
            }
        } else {
            throw new \Exception(lang('UsuariosL.noIdUserPerfil'));
        }
    }

    private function resolverIdTipoDocumento(array $datos, ?int $idActual = null): int
    {
        $sinCambio = !array_key_exists('id_tipo_documento', $datos)
            || $datos['id_tipo_documento'] === null
            || trim((string) $datos['id_tipo_documento']) === '';

        if ($sinCambio) {
            return $idActual !== null && $idActual > 0 ? $idActual : self::TIPO_DOCUMENTO_POR_DEFECTO;
        }

        $idTipoDocumento = (int) $datos['id_tipo_documento'];
        if ($idTipoDocumento <= 0 || !$this->existeTipoDocumento($idTipoDocumento)) {
            throw new \Exception('El tipo de documento seleccionado no existe');
        }

        return $idTipoDocumento;
    }

    private function existeTipoDocumento(int $idTipoDocumento): bool
    {
        $tipoDocumento = (new \Belly\Configuracion\Models\TipoDocumentoModel())->find($idTipoDocumento);
        return !empty($tipoDocumento);
    }

    public function cambiarEstadoUsuario(array $datos, int $id)
    {
        $this->exigirAdministradorEmpresa();
        if (! (bool) ($datos['estado'] ?? true)) {
            $this->impedirSobreSiMismo($id, 'No puedes desactivar tu propio usuario.');
        }
        $usuario_model = $this->getUsuarioProvider();
        $existe_usuario = $usuario_model->find($id);

        if (!empty($existe_usuario)) {
            $existe_usuario->activo = $datos['estado'];
            if ($usuario_model->save($existe_usuario)) {
                return [
                    'id_usuario' => $existe_usuario->id,
                    'usuario' => $existe_usuario->usuario,
                    'activo' => $existe_usuario->activo
                ];
            } else {
                throw new \Exception(json_encode($usuario_model->errors()));
            }
        } else {
            throw new \Exception(lang('UsuariosL.noIdUser'));
        }
    }

    /**
     * Cambia el campo `id_estado_usuario` de un usuario.
     * Espera en el array $datos la clave 'id_estado_usuario'.
     */
    public function cambiarIdEstadoUsuario(array $datos, int $id)
    {
        if (! Autorizacion::esUsuarioActual($id) && ! Autorizacion::esAdministradorEmpresa()) {
            throw new \Exception('Solo puedes cambiar tu propio estado.');
        }
        $usuario_model = $this->getUsuarioProvider();
        $existe_usuario = $usuario_model->find($id);

        if (!empty($existe_usuario)) {
            $existe_usuario->id_estado_usuario = $datos['id_estado_usuario'];
            if ($usuario_model->save($existe_usuario)) {
                return [
                    'id_usuario' => $existe_usuario->id,
                    'usuario' => $existe_usuario->usuario,
                    'id_estado_usuario' => $existe_usuario->id_estado_usuario
                ];
            } else {
                throw new \Exception(json_encode($usuario_model->errors()));
            }
        } else {
            throw new \Exception(lang('UsuariosL.noIdUser'));
        }
    }

    public function eliminar_usuario(?int $id)
    {
        $this->exigirAdministradorEmpresa();
        $this->impedirSobreSiMismo((int) $id, 'No puedes eliminar tu propio usuario.');
        $perfil_model = $this->getPerfilProvider();
        $usuario_model = $this->getUsuarioProvider();
        if (isset($id) && $id !== null) {
            $existe_perfil = $perfil_model->find($id);
            $existe_usuario = $usuario_model->find($id);

            if (!empty($existe_usuario) && !empty($existe_perfil)) {
                $this->desactivarUsuario($existe_usuario);
                if (!$perfil_model->delete($id)) {
                    throw new \Exception(lang('UsuariosL.noDeletePerfil'));
                } else {
                    if (!$usuario_model->delete($id)) {
                        throw new \Exception(lang('UsuariosL.noDeleteUsuario'));
                    } else {
                        // return $id;
                        return 'se eliminó correctamente';
                    }
                }
            } else {
                throw new \Exception(lang('UsuariosL.noIdUser'));
            }
        } else {
            throw new \Exception(lang('UsuariosL.noIdUser'));
        }
    }

    public function desactivarUsuario(object $existe_usuario)
    {
        $usuario_model = $this->getUsuarioProvider();
        $existe_usuario->activo = false;
        if (!$usuario_model->save($existe_usuario)) {
            throw new \Exception(json_encode($usuario_model->errors()));
        }
    }

 
    public function listarUsuariosPorRol(int $id_grupo_usuario_rol, $page = 1, $perPage = 10, $search = null): array
    {
        $usuario_model = $this->getUsuarioProvider();
        
        $usuario_model
            ->select([
                'usuario.id as id_usuario',
                'usuario.usuario',
                'usuario.activo',
                'usuario.id_estado_usuario',
                'usuario.es_bot',
                'usuario.created_at',
                'usuario_perfil.nombres',
                'usuario_perfil.apellidos',
                'usuario_perfil.correo',
                'usuario_perfil.celular',
                'usuario_perfil.celular_secundario',
                'tipo_documento.tipo as nombre_documento',
                'usuario_perfil.nro_documento',
                'grupo_usuario_rol.rol',
                'grupo_usuario_rol.id as id_grupo_usuario_rol',
                'estado_usuario.nombre as estado_nombre',
                'estado_usuario.color as estado_color'
            ])
            ->join('usuario_perfil', 'usuario_perfil.id_usuario = usuario.id', 'inner')
            ->join('tipo_documento', 'tipo_documento.id = usuario_perfil.id_tipo_documento', 'left')
            ->join('estado_usuario', 'estado_usuario.id = usuario.id_estado_usuario', 'left')
            ->join('grupo_usuario_usuario', 'grupo_usuario_usuario.id_usuario = usuario.id', 'inner')
            ->join('grupo_usuario_rol', 'grupo_usuario_rol.id = grupo_usuario_usuario.id_grupo_usuario_rol', 'inner')
            ->where('grupo_usuario_usuario.id_grupo_usuario_rol', $id_grupo_usuario_rol)
            ->where('grupo_usuario_usuario.deleted_at', null)
            ->where('usuario.deleted_at', null)
            ->where('usuario.activo', 1)
            ->groupBy(['usuario.id', 'estado_usuario.nombre', 'estado_usuario.color', 'usuario_perfil.nombres', 'usuario_perfil.apellidos'])
            ->orderBy('estado_usuario.nombre', 'ASC')
            ->orderBy('usuario_perfil.nombres', 'ASC')
            ->orderBy('usuario_perfil.apellidos', 'ASC');

        // Agregar filtro de búsqueda
        if (!empty($search)) {
            $usuario_model->groupStart();
            $usuario_model->like('usuario_perfil.nombres', $search);
            $usuario_model->orLike('usuario_perfil.apellidos', $search);
            $usuario_model->orLike('usuario.usuario', $search);
            $usuario_model->orLike('usuario_perfil.correo', $search);
            $usuario_model->orLike('usuario_perfil.nro_documento', $search);
            $usuario_model->orLike('usuario_perfil.celular', $search);
            $usuario_model->orLike('usuario_perfil.celular_secundario', $search);
            $usuario_model->groupEnd();
        }

        if ($perPage === 'all') {
            $usuarios = $usuario_model->findAll();
            $total = count($usuarios);
            return [
                'data' => $usuarios,
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

        $result = $usuario_model->paginate($perPage, 'default', $page);
        $pager = $usuario_model->pager;
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

    public function obtenerHistorialEstadosAsesores($fecha)
    {
        if (empty($fecha)) {
            $fecha = date('Y-m-d');
        }
        
        $db = \Config\Database::connect();
        
        $estados = $db->table('estado_usuario')
            ->where('deleted_at', null)
            ->orderBy('nombre', 'ASC')
            ->get()
            ->getResult();
        
        $header = array_map(function($estado) {
            return $estado->nombre;
        }, $estados);
        
        // Obtener usuarios activos con sus perfiles
        $usuarios = $db->table('usuario u')
            ->select('u.id, u.usuario, up.nombres, up.apellidos')
            ->join('usuario_perfil up', 'up.id_usuario = u.id', 'inner')
            ->where('u.activo', 1)
            ->where('u.deleted_at', null)
            ->where('up.deleted_at', null)
            ->get()
            ->getResult();
        
        $data = [];
        foreach ($usuarios as $usuario) {
            $nombreCompleto = trim(($usuario->nombres ?? '') . ' ' . ($usuario->apellidos ?? ''));
            $nombreUsuario = !empty($nombreCompleto) ? $nombreCompleto : $usuario->usuario;
            
            $estadosData = [];
            foreach ($estados as $estado) {
                $estadosData[$estado->nombre] = [
                    'minutos' => 0
                ];
            }
            
            $data[$usuario->id] = [
                'usuario' => $nombreUsuario,
                'estados' => $estadosData
            ];
        }
        
        $db->close();
        
        return [
            'header' => $header,
            'data' => $data
        ];
    }

    public function crearAdministradorEmpresa(int $idEmpresa, array $datos): array
    {
        if ($idEmpresa <= 0) {
            throw new \Exception('id_empresa es obligatorio para el administrador.');
        }

        $this->validarNuevoUsuario($datos);

        $usuario = strtolower(trim((string) $datos['usuario']));
        $ocupado = db_connect()->table('usuario')
            ->where('id_empresa', $idEmpresa)
            ->where('usuario', $usuario)
            ->where('deleted_at', null)
            ->countAllResults();
        if ($ocupado > 0) {
            throw new \Exception('Ese usuario ya existe en la empresa.');
        }

        $datos['usuario'] = $usuario;
        $datos['id_empresa'] = $idEmpresa;

        $usuario_entity = new UsuarioEntity();
        $usuario_entity->id_empresa = $idEmpresa;
        $usuario_entity->usuario = $usuario;
        $usuario_entity->password = $this->encriptarContrasenia($datos);
        $usuario_entity->login_fallidos = 0;
        $usuario_entity->cuenta_bloqueada = 0;
        $usuario_entity->id_estado_usuario = 6;
        $usuario_entity->activo = 1;
        $usuario_entity->sudo = 1;

        $usuario_model = new UsuarioModel();
        if (! $usuario_model->save($usuario_entity)) {
            throw new \Exception(json_encode($usuario_model->errors()));
        }

        $usuario_entity->id = (int) $usuario_model->insertID;
        $perfil = $this->crearPerfilUsuario($datos, $usuario_entity->id);

        return [
            'id_usuario' => $usuario_entity->id,
            'usuario' => $usuario_entity->usuario,
            'nombres' => $perfil->nombres,
            'apellidos' => $perfil->apellidos,
        ];
    }

    private function resolverIdEmpresaUsuario(array $datos): int
    {
        helper('vip2system');
        $id = (int) (id_empresa_sesion() ?? 0);
        if ($id <= 0) {
            throw new \Exception('id_empresa es obligatorio para el usuario.');
        }

        return $id;
    }

    private function exigirAdministradorEmpresa(): void
    {
        Autorizacion::exigirAdministradorEmpresa('Solo el administrador de la empresa puede gestionar usuarios.');
    }

    /** @param array<string, mixed> $datos */
    private function validarNuevoUsuario(array $datos): void
    {
        foreach (['nombres' => 'el nombre', 'usuario' => 'el usuario', 'password' => 'la contraseña'] as $campo => $nombre) {
            if ($this->opcional($datos, $campo) === null) {
                throw new \Exception('Falta ' . $nombre . '.');
            }
        }
        if (strlen((string) $datos['password']) < 8) {
            throw new \Exception('La contraseña debe tener al menos 8 caracteres.');
        }
        if (! preg_match('/^[a-z0-9._-]{3,45}$/i', trim((string) $datos['usuario']))) {
            throw new \Exception('El usuario debe tener de 3 a 45 caracteres: letras, números, punto, guion o guion bajo.');
        }
        $this->validarDatosPerfil($datos, true);
    }

    /** @param array<string, mixed> $datos */
    private function validarDatosPerfil(array $datos, bool $nuevo): void
    {
        if (! $nuevo && array_key_exists('nombres', $datos) && $this->opcional($datos, 'nombres') === null) {
            throw new \Exception('Falta el nombre.');
        }
        $correo = $this->opcional($datos, 'correo');
        if ($correo !== null && filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            throw new \Exception('El correo no es válido.');
        }
        $documento = $this->opcional($datos, 'nro_documento');
        if ($documento !== null && strlen($documento) > 15) {
            throw new \Exception('El documento admite hasta 15 caracteres.');
        }
    }

    /** @param array<string, mixed> $datos */
    private function opcional(array $datos, string $campo): ?string
    {
        $valor = trim((string) ($datos[$campo] ?? ''));

        return $valor === '' ? null : $valor;
    }

    private function impedirSobreSiMismo(int $id, string $mensaje): void
    {
        if (Autorizacion::esUsuarioActual($id)) {
            throw new \Exception($mensaje);
        }
    }

}
