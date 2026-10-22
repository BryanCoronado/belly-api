<?php

namespace Belly\Media\Config;

use CodeIgniter\Config\BaseConfig;

class MediaConfig extends BaseConfig
{
    public string $upload_path = '';

    public function __construct()
    {
        parent::__construct();
        $this->upload_path = WRITEPATH . 'uploads/whatsapp/';

        if (!is_dir($this->upload_path)) {
            mkdir($this->upload_path, 0777, true);
        }
    }
}
