<?php

namespace Belly\Webhooks\Config;

use Belly\Webhooks\UseCases\WebhookUseCase;
use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function WebhookUseCase($config = null, bool $getShared = true): WebhookUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('WebhookUseCase', $config);
        }

        return new WebhookUseCase();
    }
}
