<?php

namespace Belly\Config;

use CodeIgniter\Config\AutoloadConfig;

class Autoload extends AutoloadConfig {

    public $psr4 = [
        'Belly' => ROOTPATH . 'belly',
         // Librerias
        'Belly\ModelPivot' => ROOTPATH . 'belly/Libraries/modelPivot',
        'Belly\Relations' => ROOTPATH . 'belly/Libraries/Relations',
        'Belly\Entities' => ROOTPATH . 'belly/Libraries/Entities',
        'Firebase\JWT' => ROOTPATH . 'belly/Modules/Auth/Libraries/php-jwt-6.11.1/src',
        // Ajuste: Apuntar a la carpeta real de PhpSpreadsheet
        'PhpOffice\\PhpSpreadsheet\\' => ROOTPATH . 'belly/Libraries/PhpOffice/PhpSpreadsheet-4.4.0/',
        'ZipStream\\' => ROOTPATH . 'belly/Libraries/ZipStream-php-3.1.2/src/',
        'Psr\SimpleCache' => ROOTPATH . 'belly/Libraries/SimpleCache',
        'Composer\Pcre' => ROOTPATH . 'belly/Libraries/Composer/Pcre',

        // Dompdf 3 + dependencias (sin Composer: carpeta belly/Libraries/DompdfManual; reinstalar con belly/Libraries/install_dompdf_manual.ps1)
        'Dompdf\\' => ROOTPATH . 'belly/Libraries/DompdfManual/dompdf/src/',
        'FontLib\\' => ROOTPATH . 'belly/Libraries/DompdfManual/php-font-lib/src/FontLib/',
        'Svg\\' => ROOTPATH . 'belly/Libraries/DompdfManual/php-svg-lib/src/Svg/',
        'Sabberworm\\CSS\\' => ROOTPATH . 'belly/Libraries/DompdfManual/php-css-parser/src/',
        'Masterminds\\' => ROOTPATH . 'belly/Libraries/DompdfManual/html5/src/',
        
        // Librerías externas para SFTP
        'phpseclib4\\' => ROOTPATH . 'belly/Libraries/phpseclib',
        'ParagonIE\\ConstantTime\\' => ROOTPATH . 'belly/Libraries/ParagonIE/ConstantTime',


        'Belly\Usuarios' => ROOTPATH . 'belly/Modules/Usuarios',
        'Belly\Auth' => ROOTPATH . 'belly/Modules/Auth',
        'Belly\Permisos' => ROOTPATH . 'belly/Modules/Permisos',
        'Belly\Webhooks' => ROOTPATH . 'belly/Modules/Webhooks',
        'Belly\Mensajes' => ROOTPATH . 'belly/Modules/Mensajes', 
        'Belly\Clientes' => ROOTPATH . 'belly/Modules/Clientes',
        'Belly\Conversacion' => ROOTPATH . 'belly/Modules/Conversacion',
        'Belly\Configuracion' => ROOTPATH . 'belly/Modules/Configuracion',
        'Belly\Media' => ROOTPATH . 'belly/Modules/Media',
        'Belly\Modulos' => ROOTPATH . 'belly/Modules/Modulos',
        'Belly\Metricas' => ROOTPATH . 'belly/Modules/Metricas',
        'Belly\Flujos' => ROOTPATH . 'belly/Modules/Flujos',


        

    ];


    public $classmap = [
        'Dompdf\\Cpdf' => ROOTPATH . 'belly/Libraries/DompdfManual/dompdf/lib/Cpdf.php',
    ];

    public $helpers = [
        'vip2system_helper',
        'whatsapp_helper',
        'canal_social_helper',
        'permisos_helper',
        'socket_helper',
        'file_helper'
    ];
}
