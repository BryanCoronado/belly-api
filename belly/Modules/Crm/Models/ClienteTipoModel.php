<?php

namespace Belly\Crm\Models;

use Belly\Configuracion\Models\FiltraPorEmpresa;
use CodeIgniter\Model;

class ClienteTipoModel extends Model
{
    use FiltraPorEmpresa;

    protected $table = 'cliente_tipo';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = ['id_empresa', 'nombre', 'color', 'orden'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
}
