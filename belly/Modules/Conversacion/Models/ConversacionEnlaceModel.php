<?php

namespace Belly\Conversacion\Models;

use CodeIgniter\Model;
use Belly\Conversacion\Entities\ConversacionEnlaceEntity;

class ConversacionEnlaceModel extends Model
{
    protected $table = 'conversacion_enlace';
    protected $primaryKey = 'id';
    protected $returnType = ConversacionEnlaceEntity::class;
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'canal_origen',
        'canal_destino',
        'id_conversacion_origen',
        'id_conversacion_destino',
        'id_cliente_origen',
        'id_cliente_destino',
        'telefono',
        'contexto_json',
        'estado',
        'ultimo_aviso_origen_at',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
