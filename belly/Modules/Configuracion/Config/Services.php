<?php

namespace Belly\Configuracion\Config;
use Belly\Configuracion\UseCases\AppUseCase;
use Belly\Configuracion\UseCases\EmpresaUseCase;
use Belly\Configuracion\UseCases\PortafolioUseCase ;


use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function AppUseCase($config = null, bool $getShared = true): AppUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('AppUseCase', $config);
        }

        // Asegúrate de pasar el argumento necesario al constructor
        $config = $config ?? config('ConfiguracionConfig');
        return new AppUseCase($config);
    }

    public static function PortafolioUseCase($config = null, bool $getShared = true): PortafolioUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('PortafolioUseCase', $config);
        }

        // Asegúrate de pasar el argumento necesario al constructor
        $config = $config ?? config('ConfiguracionConfig');
        return new PortafolioUseCase($config);
    }

    public static function EmpresaUseCase($config = null, bool $getShared = true): EmpresaUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('EmpresaUseCase', $config);
        }

        $config = $config ?? config('ConfiguracionConfig');
        return new EmpresaUseCase($config);
    }
}
