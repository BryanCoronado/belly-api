<?php

namespace Belly\Clientes\Config;

use CodeIgniter\Config\BaseConfig;
use Belly\Clientes\Models\ClienteModel;
use Belly\Clientes\Models\ClientePerfilModel;
use Belly\Conversacion\Models\ConversacionModel;
use Belly\Mensajes\Models\MensajeModel;
use Belly\Clientes\Models\ListaNegraModel;

class ClientesConfig extends BaseConfig
{
    public int $page_limit = 10;

    public string $cliente_provider = ClienteModel::class;
    public string $cliente_perfil_provider = ClientePerfilModel::class;
    public string $conversacion_provider = ConversacionModel::class;
    public string $mensaje_provider = MensajeModel::class;
    public string $lista_negra_provider = ListaNegraModel::class;


}
