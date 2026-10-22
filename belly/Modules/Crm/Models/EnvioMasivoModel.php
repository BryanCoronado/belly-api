<?php

namespace Belly\Crm\Models;

use Belly\Configuracion\Models\FiltraPorEmpresa;
use CodeIgniter\Model;

class EnvioMasivoModel extends Model
{
    use FiltraPorEmpresa;

    protected $table = 'envio_masivo';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_empresa', 'id_app', 'id_usuario', 'nombre', 'modo', 'mensaje', 'plantilla', 'filtros', 'estado', 'total', 'terminado_at',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
}
