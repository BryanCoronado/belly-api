<?php

namespace Belly\Catalogo\Models;

use Belly\Catalogo\Entities\VitrinaEntity;
use Belly\Configuracion\Models\FiltraPorEmpresa;
use CodeIgniter\Model;

class VitrinaModel extends Model
{
    use FiltraPorEmpresa;

    protected $table = 'vitrina';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = VitrinaEntity::class;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'id_empresa', 'slug', 'titulo', 'eslogan', 'color', 'logo_url',
        'whatsapp', 'mostrar_precios', 'publicado', 'pagina',
    ];
    protected bool $allowEmptyInserts = false;
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    protected $validationRules = [
        'id_empresa' => 'required|is_natural_no_zero',
        'slug' => 'required|min_length[3]|max_length[60]',
        'titulo' => 'required|min_length[2]|max_length[80]',
        'eslogan' => 'permit_empty|max_length[160]',
        'color' => 'permit_empty|max_length[7]',
        'logo_url' => 'permit_empty|max_length[500]',
        'whatsapp' => 'permit_empty|max_length[20]',
    ];
    protected $validationMessages = [
        'slug' => [
            'required' => 'Elige el nombre del enlace.',
            'min_length' => 'El nombre del enlace es muy corto.',
            'max_length' => 'El nombre del enlace no puede pasar de 60 letras.',
        ],
        'titulo' => [
            'required' => 'Ponle un título al catálogo.',
            'min_length' => 'El título es muy corto.',
            'max_length' => 'El título no puede pasar de 80 letras.',
        ],
    ];
}
