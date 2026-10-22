<?php

namespace Belly\Media\Config;

use Belly\Media\UseCases\MediaUseCase;
use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function MediaUseCase($config = null, bool $getShared = true): MediaUseCase
    {
        if ($getShared) {
            return self::getSharedInstance('MediaUseCase', $config);
        }

        $config = $config ?? config('MediaConfig');
        return new MediaUseCase($config);
    }
}
