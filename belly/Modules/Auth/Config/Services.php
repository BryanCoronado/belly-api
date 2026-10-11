<?php

namespace Belly\Auth\Config;
use Belly\Auth\Config\AuthConfig;
use Belly\Auth\UseCases\AuthUseCase;

use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function AuthUseCase(bool $getShared = true): AuthUseCase {
        if ($getShared) {
            return self::getSharedInstance('AuthUseCase');
        }

        /** @var AuthConfig $config */
        $config = config('AuthConfig');

        return new AuthUseCase($config);
    }
}
