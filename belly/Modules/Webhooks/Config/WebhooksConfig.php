<?php

namespace Belly\Webhooks\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * El token de verificación y el App Secret de Meta son por empresa (tabla conexion_meta).
 */
class WebhooksConfig extends BaseConfig
{
    public int $page_limit = 10;
}
