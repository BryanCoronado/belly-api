<?php

namespace Belly\Catalogo\Config;

use Belly\Catalogo\UseCases\FamiliaProductoUseCase;
use Belly\Catalogo\UseCases\ImagenProductoUseCase;
use Belly\Catalogo\UseCases\PedidoCatalogoUseCase;
use Belly\Catalogo\UseCases\ProductoUseCase;
use Belly\Catalogo\UseCases\VitrinaUseCase;
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

    public static function VitrinaUseCase($config = null, bool $getShared = true): VitrinaUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('VitrinaUseCase', $config);
        }

        return new VitrinaUseCase();
    }

    public static function PedidoCatalogoUseCase($config = null, bool $getShared = true): PedidoCatalogoUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('PedidoCatalogoUseCase', $config);
        }

        return new PedidoCatalogoUseCase();
    }
}
