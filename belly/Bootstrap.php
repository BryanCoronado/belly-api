<?php

namespace Belly;

use CodeIgniter\Autoloader\Autoloader as BaseAutoloader;
use Config\Autoload;
use Belly\Config\Autoload as CimakAutoload;
use Config\Modules;

class Bootstrap extends BaseAutoloader {

    public function initialize(Autoload $config, Modules $modules) {
        parent::initialize($config, $modules);

        $cimak_config = new CimakAutoload();

        // We have to have one or the other, though we don't enforce the need
        // to have both present in order to work.
        if ($cimak_config->psr4 === [] && $cimak_config->classmap === []) {
            throw new InvalidArgumentException('Config array must contain either the \'psr4\' key or the \'classmap\' key.');
        }

        if ($cimak_config->psr4 !== []) {
            unset($cimak_config->psr4['CodeIgniter'], $cimak_config->psr4['Config']);

            $this->addNamespace($cimak_config->psr4);
        }

        if ($cimak_config->classmap !== []) {
            $this->classmap = array_merge($this->classmap, $cimak_config->classmap);
        }

        if (isset($cimak_config->helpers)) {
            $this->helpers = [...$this->helpers, ...$cimak_config->helpers];
        }

        return $this;
    }
}
