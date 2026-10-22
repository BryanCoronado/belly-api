<?php

namespace Belly\Crm\Models;

use Belly\Configuracion\Models\FiltraPorEmpresa;
use CodeIgniter\Model;

class EmbudoEtapaModel extends Model
{
    use FiltraPorEmpresa;

    protected $table = 'embudo_etapa';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = ['id_empresa', 'nombre', 'color', 'orden', 'resultado'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
}
