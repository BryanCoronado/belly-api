<?php

namespace Belly\Mensajes\Config;
use Belly\Mensajes\Models\MensajeModel;
use Belly\Mensajes\Models\PlantillaMetaModel;
use Belly\Mensajes\Models\PlantillaRapidaModel;

use CodeIgniter\Config\BaseConfig;
    

class MensajesConfig extends BaseConfig
{
    public int $page_limit = 10;
    public string $mensaje_provider = MensajeModel::class;
    public string $plantilla_meta_provider = PlantillaMetaModel::class;
    public string $plantilla_rapida_provider = PlantillaRapidaModel::class;
}
