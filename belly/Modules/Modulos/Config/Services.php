<?php

namespace Belly\Modulos\Config;
use Belly\Modulos\UseCases\ModuloGrupoUseCase;
use Belly\Modulos\UseCases\ModulosUseCase;

use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
     public static function ModulosUseCase(bool $getShared = true): ModulosUseCase {
        if ($getShared) {
            return self::getSharedInstance('ModulosUseCase'); 
        }

        /** @var ModulosConfig $config */
        $config = config('ModulosConfig');

        return new ModulosUseCase($config);
    }
    
    public static function ModuloGrupoUseCase(bool $getShared = true): ModuloGrupoUseCase {
        if ($getShared) {
            return self::getSharedInstance('ModuloGrupoUseCase'); 
        }

        /** @var ModulosConfig $config */
        $config = config('ModulosConfig');

        return new ModuloGrupoUseCase($config);
    }

}
