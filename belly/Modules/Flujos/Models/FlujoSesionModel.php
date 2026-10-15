<?php

namespace Belly\Flujos\Models;

use Belly\Configuracion\Models\FiltraPorEmpresa;
use Belly\Flujos\Entities\FlujoSesionEntity;
use CodeIgniter\Model;

class FlujoSesionModel extends Model
{
    use FiltraPorEmpresa;

    protected $table = 'flujo_sesion';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = FlujoSesionEntity::class;
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = ['id_empresa', 'id_flujo', 'id_conversacion', 'estado', 'nodo_actual', 'intentos', 'variables', 'reanudar_at'];
    protected bool $allowEmptyInserts = false;
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
