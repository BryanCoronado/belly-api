<?php

namespace Belly\Configuracion\Config;
use Belly\Configuracion\Models\AppModel;
use Belly\Configuracion\Models\EmpresaModel;
use Belly\Configuracion\Models\PortafolioModel;
use Belly\Configuracion\Models\TipoDocumentoModel;

use CodeIgniter\Config\BaseConfig;
    

class ConfiguracionConfig extends BaseConfig
{
    public int $page_limit = 10;
    public string $app_provider = AppModel::class;
    public string $empresa_provider = EmpresaModel::class;
    public string $portafolio_provider = PortafolioModel::class;
    public string $tipo_documento_provider = TipoDocumentoModel::class;

}
