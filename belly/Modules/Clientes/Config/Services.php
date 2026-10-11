<?php

namespace Belly\Clientes\Config;
use Belly\Clientes\UseCases\ClienteUseCase;
use Belly\Clientes\UseCases\ClienteMetricaUseCase;
use Belly\Clientes\UseCases\ListaNegraUseCase;

use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function ClienteUseCase($config = null, bool $getShared = true): ClienteUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('ClienteUseCase', $config);
        }

        // Asegúrate de pasar el argumento necesario al constructor
        $config = $config ?? config('ClientesConfig');
        return new ClienteUseCase($config);
    }

    public static function ClienteMetricaUseCase($config = null, bool $getShared = true): ClienteMetricaUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('ClienteMetricaUseCase', $config);
        }

        // Asegúrate de pasar el argumento necesario al constructor
        $config = $config ?? config('ClientesConfig');
        return new ClienteMetricaUseCase($config);
    }
    public static function ListaNegraUseCase($config = null, bool $getShared = true): ListaNegraUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('ListaNegraUseCase', $config);
        }

        // Asegúrate de pasar el argumento necesario al constructor
        $config = $config ?? config('ClientesConfig');
        return new ListaNegraUseCase($config);
    }

}
