<?php

namespace Belly\Conversacion\Config;
use Belly\Conversacion\UseCases\ConversacionUseCase;
use Belly\Conversacion\UseCases\ConversacionMotivoUseCase;
use Belly\Conversacion\UseCases\ConversacionVentaUseCase;
use Belly\Conversacion\UseCases\EtiquetaUseCase;

use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function ConversacionUseCase($config = null, bool $getShared = true): ConversacionUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('ConversacionUseCase', $config);
        }

        $config = $config ?? config('ConversacionConfig');
        return new ConversacionUseCase($config);
    }

    public static function MotivoConversacionUseCase($config = null, bool $getShared = true): ConversacionMotivoUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('MotivoConversacionUseCase', $config);
        }

        $config = $config ?? config('ConversacionConfig');
        return new ConversacionMotivoUseCase($config);
    }

    public static function EtiquetaUseCase($config = null, bool $getShared = true): EtiquetaUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('EtiquetaUseCase', $config);
        }

        $config = $config ?? config('ConversacionConfig');
        return new EtiquetaUseCase($config);
    }

    public static function ConversacionVentaUseCase($config = null, bool $getShared = true): ConversacionVentaUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('ConversacionVentaUseCase', $config);
        }

        $config = $config ?? config('ConversacionConfig');
        return new ConversacionVentaUseCase($config);
    }
}
