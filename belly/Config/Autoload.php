<?php

namespace Belly\Config;

use CodeIgniter\Config\AutoloadConfig;

class Autoload extends AutoloadConfig {

    public $psr4 = [
        'Belly' => ROOTPATH . 'belly',
        'Belly\ModelPivot' => ROOTPATH . 'belly/Libraries/modelPivot',
        'Belly\Relations' => ROOTPATH . 'belly/Libraries/Relations',
        'Firebase\JWT' => ROOTPATH . 'belly/Modules/Auth/Libraries/php-jwt-6.11.1/src',

        'Belly\Usuarios' => ROOTPATH . 'belly/Modules/Usuarios',
        'Belly\Auth' => ROOTPATH . 'belly/Modules/Auth',
        'Belly\Permisos' => ROOTPATH . 'belly/Modules/Permisos',
        'Belly\Webhooks' => ROOTPATH . 'belly/Modules/Webhooks',
        'Belly\Mensajes' => ROOTPATH . 'belly/Modules/Mensajes',
        'Belly\Clientes' => ROOTPATH . 'belly/Modules/Clientes',
        'Belly\Conversacion' => ROOTPATH . 'belly/Modules/Conversacion',
        'Belly\Configuracion' => ROOTPATH . 'belly/Modules/Configuracion',
        'Belly\Media' => ROOTPATH . 'belly/Modules/Media',
        'Belly\Catalogo' => ROOTPATH . 'belly/Modules/Catalogo',
        'Belly\Modulos' => ROOTPATH . 'belly/Modules/Modulos',
        'Belly\Flujos' => ROOTPATH . 'belly/Modules/Flujos',
        'Belly\Reparto' => ROOTPATH . 'belly/Modules/Reparto',
        'Belly\AgenteIa' => ROOTPATH . 'belly/Modules/AgenteIa',
        'Belly\Crm' => ROOTPATH . 'belly/Modules/Crm',
    ];

    public $classmap = [];

    public $helpers = [
        'vip2system_helper',
        'whatsapp_helper',
        'canal_social_helper',
        'permisos_helper',
        'socket_helper',
        'file_helper'
    ];
}
