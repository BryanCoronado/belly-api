<?php

namespace Belly\Usuarios\Config;

use Belly\Usuarios\Models\UsuarioModel;
use Belly\Usuarios\Models\GrupoUsuarioModel;
use Belly\Usuarios\Models\UsuarioPerfilModel;
use Belly\Usuarios\Models\GrupoUsuarioPermisoGrupoUsuarioRolModel;
use Belly\Usuarios\Models\GrupoUsuarioPermisoRolModel;
use Belly\Usuarios\Models\GrupoUsuarioPermisoUsuarioModel;
use Belly\Usuarios\Models\GrupoUsuarioRolModel;
use Belly\Usuarios\Models\GrupoUsuarioUsuarioModel;
use Belly\Usuarios\Models\UsuarioPermisoEspecialModel;
use Belly\Usuarios\Models\EstadoUsuarioModel;

use CodeIgniter\Config\BaseConfig;
    

class UsuarioConfig extends BaseConfig
{
    public int $page_limit = 10;
    public string $usuario_provider = UsuarioModel::class;
    public string $perfil_provider = UsuarioPerfilModel::class;
    public string $grupo_usuario_provider = GrupoUsuarioModel::class;
    public string $grupo_provider = GrupoUsuarioRolModel::class;
    public string $grupo_usuario_usuario_provider = GrupoUsuarioUsuarioModel::class;
    public string $usuario_permiso_especial_provider = UsuarioPermisoEspecialModel::class;
    public string $grupo_usuario_permiso_user_provider = GrupoUsuarioPermisoUsuarioModel::class;
    public string $grupo_usuario_permiso_rol_provider = GrupoUsuarioPermisoRolModel::class;
    public string $estado_usuario_provider = EstadoUsuarioModel::class;
}
