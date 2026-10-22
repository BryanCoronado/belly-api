<?php

namespace Belly\Catalogo\Config;

use Belly\Catalogo\UseCases\FamiliaProductoUseCase;
use Belly\Catalogo\UseCases\ImagenProductoUseCase;
use Belly\Catalogo\UseCases\ProductoUseCase;
use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function FamiliaProductoUseCase($config = null, bool $getShared = true): FamiliaProductoUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('FamiliaProductoUseCase', $config);
        }

        return new FamiliaProductoUseCase();
    }

    public static function ProductoUseCase($config = null, bool $getShared = true): ProductoUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('ProductoUseCase', $config);
        }

        return new ProductoUseCase();
    }

    public static function ImagenProductoUseCase($config = null, bool $getShared = true): ImagenProductoUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('ImagenProductoUseCase', $config);
        }

        return new ImagenProductoUseCase();
    }
}
