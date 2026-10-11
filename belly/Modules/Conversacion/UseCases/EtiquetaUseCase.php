<?php

namespace Belly\Conversacion\UseCases;

use Belly\Conversacion\Config\ConversacionConfig;
use Belly\Conversacion\Models\EtiquetaModel;

class EtiquetaUseCase
{
    protected ConversacionConfig $config;
    protected ?EtiquetaModel $etiqueta_provider = null;

    public function __construct(ConversacionConfig $config)
    {
        $this->config = $config;
    }

    public function getEtiquetaProvider(): EtiquetaModel
    {
        if ($this->etiqueta_provider !== null) {
            return $this->etiqueta_provider;
        }

        $class_name = $this->config->etiqueta_provider;
        $this->etiqueta_provider = new $class_name();

        return $this->etiqueta_provider;
    }

    /** @return object[] */
    public function listarEtiquetas(): array
    {
        return $this->getEtiquetaProvider()
            ->where('deleted_at', null)
            ->orderBy('nombre', 'ASC')
            ->findAll();
    }
}
