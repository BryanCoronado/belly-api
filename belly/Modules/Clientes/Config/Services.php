<?php

namespace Belly\Clientes\Config;
use Belly\Clientes\UseCases\ClienteUseCase;
use Belly\Clientes\UseCases\ListaNegraUseCase;

use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function ClienteUseCase($config = null, bool $getShared = true): ClienteUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('ClienteUseCase', $config);
        }

        $config = $config ?? config('ClientesConfig');
        return new ClienteUseCase($config);
    }

    public static function ListaNegraUseCase($config = null, bool $getShared = true): ListaNegraUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('ListaNegraUseCase', $config);
        }

        $config = $config ?? config('ClientesConfig');
        return new ListaNegraUseCase($config);
    }
}
