<?php

namespace Belly\Conversacion\Models;

use Belly\Conversacion\Entities\ConversacionVentaEntity;
use CodeIgniter\Model;

class ConversacionVentaModel extends Model
{
    protected $table = 'conversacion_venta';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = ConversacionVentaEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_conversacion',
        'id_cliente',
        'id_usuario',
        'id_usuario_registro',
        'temperatura_anterior',
        'registrada_at',
    ];
    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
}
