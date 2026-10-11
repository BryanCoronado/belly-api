<?php
namespace Belly\Configuracion\UseCases;

use Belly\Configuracion\Config\ConfiguracionConfig;
use Belly\Configuracion\Models\PortafolioModel;
use Belly\Configuracion\Entities\PortafolioEntity;

class PortafolioUseCase {
    protected ConfiguracionConfig $config;
    protected ?PortafolioModel $portafolio_provider = NULL;

    public function __construct(ConfiguracionConfig $config)
    {
        $this->config = $config;
    }

    public function getPortafolioProvider(): PortafolioModel
    {
        if ($this->portafolio_provider !== null) {
            return $this->portafolio_provider;
        }

        $class_name = $this->config->portafolio_provider;
        $this->portafolio_provider = new $class_name();

        return $this->portafolio_provider;
    }

    


}
