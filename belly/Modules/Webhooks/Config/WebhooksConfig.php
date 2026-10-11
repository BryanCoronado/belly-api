<?php

namespace Belly\Webhooks\Config;

use CodeIgniter\Config\BaseConfig;

class WebhooksConfig extends BaseConfig
{
    public int $page_limit = 10;

    /** Token de verificación del webhook de Meta. Se puede reemplazar con META_WEBHOOK_VERIFY_TOKEN. */
    public string $meta_verify_token = 'BRYANCITO';

    public function __construct()
    {
        parent::__construct();

        $this->meta_verify_token = (string) env('META_WEBHOOK_VERIFY_TOKEN', $this->meta_verify_token);
    }
}
