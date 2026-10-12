<?php

namespace Belly\Usuarios\Config;

use Belly\Usuarios\Config\UsuarioConfig;
use Belly\Usuarios\UseCases\AccesoUseCase;
use Belly\Usuarios\UseCases\UsuarioUseCase;
use Belly\Usuarios\UseCases\GrupoUsuarioPermisoGrupoUsuarioRolUseCase;
use Belly\Usuarios\UseCases\GrupoUsuarioPermisoRolUseCase;
use Belly\Usuarios\UseCases\GrupoUsuarioPermisoUsuarioUseCase;
use Belly\Usuarios\UseCases\GrupoUsuarioRolUseCase;
use Belly\Usuarios\UseCases\GrupoUsuarioUseCase;
use Belly\Usuarios\UseCases\GrupoUsuarioUsuarioUseCase;
use Belly\Usuarios\UseCases\HorarioGrupoUsuarioRolUseCase;

use Belly\Usuarios\UseCases\UsuarioPermisoEspecialUseCase;
use Belly\Usuarios\UseCases\EstadoUsuarioUseCase;
use Belly\Usuarios\UseCases\UsuarioMetricaUseCase;



use CodeIgniter\Config\BaseService;


class Services extends BaseService
{
    public static function AccesoUseCase(bool $getShared = true): AccesoUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('AccesoUseCase');
        }

        return new AccesoUseCase();
    }

    public static function UsuarioUseCase($config = null, bool $getShared = true): UsuarioUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('UsuarioUseCase', $config);
        }

        $config = $config ?? config('UsuarioConfig');
        return new UsuarioUseCase($config);
    }
     public static function GrupoUsuarioRolUseCase(bool $getShared = true): GrupoUsuarioRolUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('GrupoUsuarioRolUseCase');
        }

        /** @var UsuarioConfig $config */
        $config = config('UsuarioConfig');

        return new GrupoUsuarioRolUseCase($config);
    }

    public static function GrupoUsuarioUseCase(bool $getShared = true): GrupoUsuarioUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('GrupoUsuarioUseCase');
        }

        /** @var UsuarioConfig $config */
        $config = config('UsuarioConfig');

        return new GrupoUsuarioUseCase($config);
    }

    public static function GrupoUsuarioUsuarioUseCase(bool $getShared = true): GrupoUsuarioUsuarioUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('GrupoUsuarioUsuarioUseCase');
        }

        /** @var UsuarioConfig $config */
        $config = config('UsuarioConfig');

        return new GrupoUsuarioUsuarioUseCase($config);
    }

    public static function UsuarioPermisoEspecialUseCase(bool $getShared = true): UsuarioPermisoEspecialUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('UsuarioPermisoEspecialUseCase');
        }

        /** @var UsuarioConfig $config */
        $config = config('UsuarioConfig');

        return new UsuarioPermisoEspecialUseCase($config);
    }

    public static function GrupoUsuarioPermisoUsuarioUseCase(bool $getShared = true): GrupoUsuarioPermisoUsuarioUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('GrupoUsuarioPermisoUsuarioUseCase');
        }

        /** @var UsuarioConfig $config */
        $config = config('UsuarioConfig');

        return new GrupoUsuarioPermisoUsuarioUseCase($config);
    }

    public static function GrupoUsuarioPermisoRolUseCase(bool $getShared = true): GrupoUsuarioPermisoRolUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('GrupoUsuarioPermisoRolUseCase');
        }

        /** @var UsuarioConfig $config */
        $config = config('UsuarioConfig');

        return new GrupoUsuarioPermisoRolUseCase($config);
    }
    
    public static function EstadoUsuarioUseCase(bool $getShared = true): EstadoUsuarioUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('EstadoUsuarioUseCase');
        }

        $config = config('UsuarioConfig');

        return new EstadoUsuarioUseCase($config);
    }

    public static function UsuarioMetricaUseCase(bool $getShared = true): UsuarioMetricaUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('UsuarioMetricaUseCase');
        }
        $config = config('UsuarioConfig');

        return new UsuarioMetricaUseCase($config);
    }

    public static function HorarioGrupoUsuarioRolUseCase(bool $getShared = true): HorarioGrupoUsuarioRolUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('HorarioGrupoUsuarioRolUseCase');
        }

        /** @var UsuarioConfig $config */
        $config = config('UsuarioConfig');

        return new HorarioGrupoUsuarioRolUseCase($config);
    }
}
