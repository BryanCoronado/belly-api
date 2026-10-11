<?php

namespace Belly\Config;

use Belly\Filters\AuthFilter;
use Belly\Filters\PermisosFilter;

class Registrar
{

    public static function Filters(): array
    {
        return [
            'aliases' => [
                'authFilter' => AuthFilter::class,
                'permisosFilter' => PermisosFilter::class,
            ],
        ];
    }
}
