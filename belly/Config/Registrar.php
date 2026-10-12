<?php

namespace Belly\Config;

use Belly\Filters\AuthFilter;
use Belly\Filters\PermisosFilter;
use Belly\Filters\RolFilter;
use Belly\Filters\WhatsappFilter;

class Registrar
{

    public static function Filters(): array
    {
        return [
            'aliases' => [
                'authFilter' => AuthFilter::class,
                'permisosFilter' => PermisosFilter::class,
                'rolFilter' => RolFilter::class,
                'whatsappFilter' => WhatsappFilter::class,
            ],
        ];
    }
}
