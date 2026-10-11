<?php
namespace Belly\Usuarios\UseCases;

use Belly\Usuarios\Config\UsuarioConfig;
use Belly\Usuarios\Models\UsuarioModel;
use Belly\Usuarios\Entities\UsuarioEntity;
use Belly\Usuarios\Entities\UsuarioPerfilEntity;
use Belly\Usuarios\Models\UsuarioPerfilModel;

class UsuarioUseCase {
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
        $usuario_model->select(['usuario.id as id_usuario','usuario.id_estado_usuario', 'es.nombre as nombre_estado_usuario', 'usuario.created_at', 'usuario.usuario', 'usuario.activo', 'up.nombres', 'up.apellidos', 'up.id_tipo_documento', 'up.nro_documento','doc.tipo as nombre_documento', 'up.correo', 'up.celular', 'up.celular_secundario', 'up.extension', 'up.sip_password', 'usuario.cuenta_bloqueada']);
        $usuario_model->join('usuario_perfil as up', 'up.id_usuario = usuario.id');
        $usuario_model->join('estado_usuario as es','es.id = usuario.id_estado_usuario');
        $usuario_model->join('tipo_documento as doc', 'doc.id = up.id_tipo_documento');
        $usuario = $usuario_model->find($id);

        if (isset($usuario) && !empty($usuario)) {
            return $usuario;
        }

        return ['errors' => lang('Usuarios.userNoExist')];
    }



    public function crearUsuario(array $datos)
    {
        $password = $this->encriptarContrasenia($datos);

        $usuario_entity = new UsuarioEntity();
        $usuario_entity->usuario = $datos['usuario'];
        $usuario_entity->password = $password;
        $usuario_entity->login_fallidos = 0;
        $usuario_entity->cuenta_bloqueada = 0;
        $usuario_entity->id_estado_usuario = 6; //offline
        $usuario_entity->activo = 1;
        $usuario_entity->sudo = 0;

        if ($usuario_model = $this->getUsuarioProvider()) {
            if ($usuario_model->save($usuario_entity)) {
                $usuario_entity->id = $usuario_model->insertID;
                $perfil = $this->crearPerfilUsuario($datos, $usuario_entity->id);
                
                return [
                    'id_usuario' => $usuario_entity->id,
                    'usuario' => $usuario_entity->usuario,
                    'nombres' => $perfil->nombres,
                    'nro_documento' => $perfil->nro_documento,
                    'extension' => $perfil->extension,
                    'sip_password' => $perfil->sip_password,
                ];
            } else {
                throw new \Exception(json_encode($usuario_model->errors()));
            }
        } else {
            throw new \Exception('Error al obtener el modelo de usuario');
        }
    }


    public function crearPerfilUsuario(array $datos, int $id_usuario)
    {
        $model_perfil = $this->getPerfilProvider();

        $perfil_entity = new UsuarioPerfilEntity();
        $perfil_entity->id_usuario = $id_usuario;

        $perfil_entity->nombres = $datos['nombres'] ?? null;
        $perfil_entity->apellidos = $datos['apellidos'] ?? null;
        $perfil_entity->id_tipo_documento = $this->resolverIdTipoDocumento($datos);
        $perfil_entity->nro_documento = $datos['nro_documento'];
        $perfil_entity->correo = $datos['correo'];
        $perfil_entity->celular = $datos['celular'] ?? null;
        $perfil_entity->celular_secundario = $datos['celular_secundario'] ?? null;
        $perfil_entity->extension = isset($datos['extension']) && $datos['extension'] !== ''
            ? (int) $datos['extension']
            : null;
        $perfil_entity->sip_password = isset($datos['sip_password']) && $datos['sip_password'] !== ''
            ? (string) $datos['sip_password']
            : null;

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
            'up.extension',
            'up.sip_password',
            'doc.tipo as nombre_documento',
            'up.correo',
            'usuario.cuenta_bloqueada'
        ]);
        $usuario_model->join('usuario_perfil as up', 'up.id_usuario = usuario.id');
        $usuario_model->join('tipo_documento as doc', 'doc.id = up.id_tipo_documento');
        
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
        $usuario_model = $this->getUsuarioProvider();
        $existe_usuario = $usuario_model->find($id);

        if (!empty($usuario_model->find($id))) {
            $perfil_actual = $this->getPerfilProvider()->where('id_usuario', $id)->first();
            $this->resolverIdTipoDocumento($datos, $perfil_actual ? (int) $perfil_actual->id_tipo_documento : null);

            $usuario_entity = new UsuarioEntity();
            $usuario_entity->id = $id;
            $usuario_entity->usuario = $datos['usuario'];
            
            // Solo actualizar password si se proporciona una nueva
            if (isset($datos['password']) && !empty($datos['password'])) {
                $usuario_entity->password = $this->encriptarContrasenia($datos);
            }
            
            $usuario_entity->cuenta_bloqueada = !isset($datos['cuenta_bloqueada']) ? $existe_usuario->cuenta_bloqueada : $datos['cuenta_bloqueada'];
            $usuario_entity->activo = !isset($datos['activo']) ? $existe_usuario->activo : $datos['activo'];
            $usuario_entity->sudo = !isset($datos['sudo']) ? $existe_usuario->sudo : $datos['sudo'];
            $usuario_entity->id_estado_usuario = !isset($datos['id_estado_usuario']) ? $existe_usuario->id_estado_usuario : $datos['id_estado_usuario'];


            if ($usuario_model->save($usuario_entity)) {
                $perfil = $this->editarPerfil($datos, $id);
                return [
                    'id_usuario' => $usuario_entity->id,
                    'usuario' => $usuario_entity->usuario,
                    'nombres' => $perfil->nombres,
                    'nro_documento' => $perfil->nro_documento,
                    'extension' => $perfil->extension,
                    'sip_password' => $perfil->sip_password,
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
            $perfil_entity = new UsuarioPerfilEntity();
            $perfil_entity->id_usuario = $id_usuario_perfil;
            $perfil_entity->nombres = !isset($datos['nombres']) ? $existe_perfil_model->nombres : $datos['nombres'];
            $perfil_entity->apellidos = !isset($datos['apellidos']) ? $existe_perfil_model->apellidos : $datos['apellidos'];
            $perfil_entity->extension = ! array_key_exists('extension', $datos)
                ? $existe_perfil_model->extension
                : ($datos['extension'] === '' || $datos['extension'] === null ? null : (int) $datos['extension']);
            $perfil_entity->id_tipo_documento = $this->resolverIdTipoDocumento($datos, (int) $existe_perfil_model->id_tipo_documento);
            $perfil_entity->nro_documento = !isset($datos['nro_documento']) ? $existe_perfil_model->nro_documento : $datos['nro_documento'];
            $perfil_entity->correo = !isset($datos['correo']) ? $existe_perfil_model->correo : $datos['correo'];
            $perfil_entity->celular = !isset($datos['celular']) ? $existe_perfil_model->celular : $datos['celular'];
            $perfil_entity->celular_secundario = !isset($datos['celular_secundario']) ? $existe_perfil_model->celular_secundario : $datos['celular_secundario'];
            $perfil_entity->sip_password = ! array_key_exists('sip_password', $datos)
                ? $existe_perfil_model->sip_password
                : ($datos['sip_password'] === '' || $datos['sip_password'] === null ? null : (string) $datos['sip_password']);

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

        if ($sinCambio && $idActual !== null && $idActual > 0) {
            return $idActual;
        }

        if ($sinCambio) {
            throw new \Exception('El tipo de documento es obligatorio');
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
                'usuario_perfil.extension',
                'usuario_perfil.sip_password',
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
            $usuario_model->orLike('usuario_perfil.extension', $search);
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



    //Este método verifica si un usuario es un bot o IA y devuelve true o false

    public function esUsuarioIA(int $id_usuario): bool
    {
        $usuario = $this->getUsuarioProvider()->find($id_usuario);
        return $usuario && (int) ($usuario->es_bot ?? 0) === 1;
    }
}
