<?php

namespace Belly\Media\Config;

use Belly\Media\Models\GaleriaModel;
use Belly\Media\Models\GrupoProductoModel;
use Belly\Media\Models\ProductoModel;
use Belly\Media\Models\ProductoReferenciaModel;
use CodeIgniter\Config\BaseConfig;

class MediaConfig extends BaseConfig
{
    public string $upload_path = '';
    public string $galeria_provider = GaleriaModel::class;
    public string $grupo_producto_provider = GrupoProductoModel::class;
    public string $producto_provider = ProductoModel::class;
    public string $producto_referencia_provider = ProductoReferenciaModel::class;

    public function __construct()
    {
        parent::__construct();
        $this->upload_path = WRITEPATH . 'uploads/whatsapp/';

        // Crear directorio si no existe
        if (!is_dir($this->upload_path)) {
            mkdir($this->upload_path, 0777, true);
        }
    }
}
