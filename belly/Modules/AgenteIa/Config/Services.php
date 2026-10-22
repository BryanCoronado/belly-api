<?php

namespace Belly\AgenteIa\Config;

use Belly\AgenteIa\UseCases\AgenteIaUseCase;
use Belly\AgenteIa\UseCases\AtencionIaUseCase;
use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function AgenteIaUseCase($config = null, bool $getShared = true): AgenteIaUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('AgenteIaUseCase', $config);
        }

        return new AgenteIaUseCase();
    }

    public static function AtencionIaUseCase($config = null, bool $getShared = true): AtencionIaUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('AtencionIaUseCase', $config);
        }

        return new AtencionIaUseCase();
    }
}
