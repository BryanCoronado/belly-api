<?php

namespace Belly\Permisos\Config;

use Belly\Permisos\Config\PermisosConfig;
use Belly\Permisos\UseCases\GrupoPermisoUseCase;
use Belly\Permisos\UseCases\GrupoUsuarioPermisoUseCase;
use Belly\Permisos\UseCases\PermisoUseCase;

use CodeIgniter\Config\BaseService;


class Services extends BaseService
{
     public static function GrupoPermisoUseCase(bool $getShared = true): GrupoPermisoUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('GrupoPermisoUseCase');
        }

        /** @var PermisosConfig $config */
        $config = config('PermisosConfig'); 
        return new GrupoPermisoUseCase($config);
    }
    public static function PermisoUseCase(bool $getShared = true): PermisoUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('PermisoUseCase');
        }

        /** @var PermisosConfig $config */
        $config = config('PermisosConfig');

        return new PermisoUseCase($config);
    }
    public static function GrupoUsuarioPermisoUseCase(bool $getShared = true): GrupoUsuarioPermisoUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('GrupoUsuarioPermisoUseCase');
        }

        /** @var PermisosConfig $config */
        $config = config('PermisosConfig');

        return new GrupoUsuarioPermisoUseCase($config);
    }
}
