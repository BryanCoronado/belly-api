<?php

namespace Belly\Permisos\Config;

use CodeIgniter\Config\BaseConfig;
use Belly\Permisos\Models\PermisoModel;
use Belly\Permisos\Models\GrupoPermisoModel;
use Belly\Permisos\Models\GrupoUsuarioPermisoModel;
    

class PermisosConfig extends BaseConfig
{

    public string $grupo_permiso_provider = GrupoPermisoModel::class;
    public string $permiso_provider = PermisoModel::class;
    public string $grupo_usuario_permiso_provider = GrupoUsuarioPermisoModel::class;

    public int $page_limit = 5;
}
