<?php

namespace Belly\Crm\Config;

use Belly\Crm\UseCases\AjustesCrmUseCase;
use Belly\Crm\UseCases\AltaClientesUseCase;
use Belly\Crm\UseCases\CarteraClientesUseCase;
use Belly\Crm\UseCases\DashboardCrmUseCase;
use Belly\Crm\UseCases\EnvioMasivoUseCase;
use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function AjustesCrmUseCase($config = null, bool $getShared = true): AjustesCrmUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('AjustesCrmUseCase', $config);
        }

        return new AjustesCrmUseCase();
    }

    public static function CarteraClientesUseCase($config = null, bool $getShared = true): CarteraClientesUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('CarteraClientesUseCase', $config);
        }

        return new CarteraClientesUseCase();
    }

    public static function AltaClientesUseCase($config = null, bool $getShared = true): AltaClientesUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('AltaClientesUseCase', $config);
        }

        return new AltaClientesUseCase();
    }

    public static function DashboardCrmUseCase($config = null, bool $getShared = true): DashboardCrmUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('DashboardCrmUseCase', $config);
        }

        return new DashboardCrmUseCase();
    }

    public static function EnvioMasivoUseCase($config = null, bool $getShared = true): EnvioMasivoUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('EnvioMasivoUseCase', $config);
        }

        return new EnvioMasivoUseCase();
    }
}
