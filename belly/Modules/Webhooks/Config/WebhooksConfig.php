<?php

namespace Belly\Webhooks\Config;

use CodeIgniter\Config\BaseConfig;

class WebhooksConfig extends BaseConfig
{
    public int $page_limit = 10;

    /** Token de verificación del webhook de Meta. Se puede reemplazar con META_WEBHOOK_VERIFY_TOKEN. */
    public string $meta_verify_token = 'belly-webhook';

    /** App Secret de Meta. Firma los POST del webhook (X-Hub-Signature-256). */
    public string $meta_app_secret = '';

    public function __construct()
    {
        parent::__construct();

        $this->meta_verify_token = (string) env('META_WEBHOOK_VERIFY_TOKEN', $this->meta_verify_token);
        $this->meta_app_secret = (string) env('META_APP_SECRET', $this->meta_app_secret);
    }
}
