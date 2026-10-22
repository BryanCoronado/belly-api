<?php

namespace Belly\Reparto\Config;

use Belly\Reparto\UseCases\AsignadorChatUseCase;
use Belly\Reparto\UseCases\GrupoAtencionUseCase;
use Belly\Reparto\UseCases\RepartoUseCase;
use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function RepartoUseCase($config = null, bool $getShared = true): RepartoUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('RepartoUseCase', $config);
        }

        return new RepartoUseCase();
    }

    public static function AsignadorChatUseCase($config = null, bool $getShared = true): AsignadorChatUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('AsignadorChatUseCase', $config);
        }

        return new AsignadorChatUseCase();
    }

    public static function GrupoAtencionUseCase($config = null, bool $getShared = true): GrupoAtencionUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('GrupoAtencionUseCase', $config);
        }

        return new GrupoAtencionUseCase();
    }
}
