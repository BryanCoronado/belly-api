<?php

namespace Belly\Modulos\Config;

use Belly\Modulos\Models\ModuloGrupoModel;
use Belly\Modulos\Models\ModuloModel;

use CodeIgniter\Config\BaseConfig;

class ModulosConfig extends BaseConfig
{
    public string $modulo_provider = ModuloModel::class;
    public string $modulo_grupo_provider = ModuloGrupoModel::class;
    public int $page_limit = 5;
    
}
