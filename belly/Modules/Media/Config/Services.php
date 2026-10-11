<?php

namespace Belly\Media\Config;
use Belly\Media\UseCases\MediaUseCase;
use Belly\Media\UseCases\MediaGaleriaUseCase;
use Belly\Media\UseCases\MediaDocumentoUseCase;
use Belly\Media\UseCases\GrupoProductoUseCase;
use Belly\Media\UseCases\ProductoUseCase;
use Belly\Media\UseCases\ProductoReferenciaUseCase;
use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
     public static function MediaUseCase($config = null, bool $getShared = true): MediaUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('MediaUseCase', $config);
        }

        // Asegúrate de pasar el argumento necesario al constructor
        $config = $config ?? config('MediaConfig');
        return new MediaUseCase($config);
    }

    public static function MediaGaleriaUseCase($config = null, bool $getShared = true): MediaGaleriaUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('MediaGaleriaUseCase', $config);
        }

        // Asegúrate de pasar el argumento necesario al constructor
        $config = $config ?? config('MediaConfig');
        return new MediaGaleriaUseCase($config);
    }

    public static function MediaDocumentoUseCase($config = null, bool $getShared = true): MediaDocumentoUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('MediaDocumentoUseCase', $config);
        }

        // Asegúrate de pasar el argumento necesario al constructor
        $config = $config ?? config('MediaConfig');
        return new MediaDocumentoUseCase($config);
    }

    public static function GrupoProductoUseCase($config = null, bool $getShared = true): GrupoProductoUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('GrupoProductoUseCase', $config);
        }

        // Asegúrate de pasar el argumento necesario al constructor
        $config = $config ?? config('MediaConfig');
        return new GrupoProductoUseCase($config);
    }


    public static function ProductoUseCase($config = null, bool $getShared = true): ProductoUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('ProductoUseCase', $config);
        }

        // Asegúrate de pasar el argumento necesario al constructor
        $config = $config ?? config('MediaConfig');
        return new ProductoUseCase($config);
    }

    public static function ProductoReferenciaUseCase($config = null, bool $getShared = true): ProductoReferenciaUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('ProductoReferenciaUseCase', $config);
        }

        $config = $config ?? config('MediaConfig');
        return new ProductoReferenciaUseCase($config);
    }
    
}
