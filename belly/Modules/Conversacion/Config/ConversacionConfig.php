<?php

namespace Belly\Conversacion\Config;
use Belly\Conversacion\Models\ConversacionModel;
use Belly\Conversacion\Models\MotivoConversacionModel;
use Belly\Conversacion\Models\ConversacionVentaModel;
use Belly\Conversacion\Models\EtiquetaModel;

use CodeIgniter\Config\BaseConfig;

class ConversacionConfig extends BaseConfig
{
    public int $page_limit = 10;
    public string $conversacion_provider = ConversacionModel::class;
    public string $motivo_conversacion_provider = MotivoConversacionModel::class;
    public string $etiqueta_provider = EtiquetaModel::class;
    public string $conversacion_venta_provider = ConversacionVentaModel::class;
}
