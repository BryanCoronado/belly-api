<?php

namespace Belly\Mensajes\Config;
use Belly\Mensajes\UseCases\MensajeUseCase;
use Belly\Mensajes\UseCases\PlantillaMensajeUseCase;
use Belly\Mensajes\UseCases\PlantillaRapidaUseCase;

use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
     public static function MensajeUseCase($config = null, bool $getShared = true): MensajeUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('MensajeUseCase', $config);
        }

        // Asegúrate de pasar el argumento necesario al constructor
        $config = $config ?? config('MensajesConfig');
        return new MensajeUseCase($config);
    }

    public static function PlantillaMensajeUseCase($config = null, bool $getShared = true): PlantillaMensajeUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('PlantillaMensajeUseCase', $config);
        }

        // Asegúrate de pasar el argumento necesario al constructor
        $config = $config ?? config('MensajesConfig');
        return new PlantillaMensajeUseCase($config);
    }

    public static function PlantillaRapidaUseCase($config = null, bool $getShared = true): PlantillaRapidaUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('PlantillaRapidaUseCase', $config);
        }

        // Asegúrate de pasar el argumento necesario al constructor
        $config = $config ?? config('MensajesConfig');
        return new PlantillaRapidaUseCase($config);
    }

}
