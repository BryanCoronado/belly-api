<?php

namespace Belly\Conversacion\Config;
use Belly\Conversacion\UseCases\ConversacionUseCase;
use Belly\Conversacion\UseCases\ConversacionMotivoUseCase;
use Belly\Conversacion\UseCases\ComentarioConversacionUseCase;
use Belly\Conversacion\UseCases\ConversacionReporteUseCase;
use Belly\Conversacion\UseCases\CitaConversacionUseCase;
use Belly\Conversacion\UseCases\ConversacionVentaUseCase;
use Belly\Conversacion\UseCases\ConversacionMetricaUseCase;
use Belly\Conversacion\UseCases\EtiquetaUseCase;
use Belly\Conversacion\UseCases\ConversacionExportUseCase;

use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function ConversacionUseCase($config = null, bool $getShared = true): ConversacionUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('ConversacionUseCase', $config);
        }

        // Asegúrate de pasar el argumento necesario al constructor
        $config = $config ?? config('ConversacionConfig');
        return new ConversacionUseCase($config);
    }

    public static function MotivoConversacionUseCase($config = null, bool $getShared = true): ConversacionMotivoUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('MotivoConversacionUseCase', $config);
        }

        // Asegúrate de pasar el argumento necesario al constructor
        $config = $config ?? config('ConversacionConfig');
        return new ConversacionMotivoUseCase($config);
    }

    public static function ComentarioConversacionUseCase($config = null, bool $getShared = true): ComentarioConversacionUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('ComentarioConversacionUseCase', $config);
        }

        // Asegúrate de pasar el argumento necesario al constructor
        $config = $config ?? config('ConversacionConfig');
        return new ComentarioConversacionUseCase($config);
    }

    public static function ConversacionReporteUseCase($config = null, bool $getShared = true): ConversacionReporteUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('ConversacionReporteUseCase', $config);
        }

        $config = $config ?? config('ConversacionConfig');
        return new ConversacionReporteUseCase($config);
    }

    public static function CitaConversacionUseCase($config = null, bool $getShared = true): CitaConversacionUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('CitaConversacionUseCase', $config);
        }
        $config = $config ?? config('ConversacionConfig');
        return new CitaConversacionUseCase($config);
    }

    public static function ConversacionMetricaUseCase($config = null, bool $getShared = true): ConversacionMetricaUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('ConversacionMetricaUseCase', $config);
        }
        $config = $config ?? config('ConversacionConfig');
        return new ConversacionMetricaUseCase($config);
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

    public static function ConversacionExportUseCase(bool $getShared = true): ConversacionExportUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('ConversacionExportUseCase');
        }

        return new ConversacionExportUseCase();
    }
}
