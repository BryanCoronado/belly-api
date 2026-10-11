<?php

namespace Belly;

use CodeIgniter\Autoloader\Autoloader as BaseAutoloader;
use Config\Autoload;
use Belly\Config\Autoload as BellyAutoload;
use Config\Modules;

class Bootstrap extends BaseAutoloader {

    public function initialize(Autoload $config, Modules $modules) {
        parent::initialize($config, $modules);

        $belly_config = new BellyAutoload();

        // We have to have one or the other, though we don't enforce the need
        // to have both present in order to work.
        if ($belly_config->psr4 === [] && $belly_config->classmap === []) {
            throw new InvalidArgumentException('Config array must contain either the \'psr4\' key or the \'classmap\' key.');
        }

        if ($belly_config->psr4 !== []) {
            unset($belly_config->psr4['CodeIgniter'], $belly_config->psr4['Config']);

            $this->addNamespace($belly_config->psr4);
        }

        if ($belly_config->classmap !== []) {
            $this->classmap = array_merge($this->classmap, $belly_config->classmap);
        }

        if (isset($belly_config->helpers)) {
            $this->helpers = [...$this->helpers, ...$belly_config->helpers];
        }

        return $this;
    }
}
