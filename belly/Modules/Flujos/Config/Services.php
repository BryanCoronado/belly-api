<?php

namespace Belly\Flujos\Config;

use Belly\Flujos\UseCases\FlujoBotUseCase;
use Belly\Flujos\UseCases\FlujoUseCase;
use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function FlujoUseCase($config = null, bool $getShared = true): FlujoUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('FlujoUseCase', $config);
        }

        return new FlujoUseCase();
    }

    public static function FlujoBotUseCase($config = null, bool $getShared = true): FlujoBotUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('FlujoBotUseCase', $config);
        }

        return new FlujoBotUseCase();
    }
}
