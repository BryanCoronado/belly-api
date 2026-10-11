<?php

namespace Belly\Usuarios\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Belly\Usuarios\Database\Seeds\CreateUsuariosPerfilSeeder;
use Belly\Usuarios\Database\Seeds\TiposDocumentosSeeder;
use Belly\Usuarios\Database\Seeds\CreateGruposUsuariosSeeder;
use Belly\Usuarios\Database\Seeds\CreateRolesSeeder;
use Belly\Usuarios\Database\Seeds\AsignarUsuarioGrupoSeeder;
use Belly\Modulos\Database\Seeds\CreateModulosSeeder;
use Belly\Permisos\Database\Seeds\CreatePermisosGruposSeeder;
use Belly\Permisos\Database\Seeds\CreatePermisosSeeder;
use Belly\Usuarios\Database\Seeds\ConcederAccesoSeeder;
use Belly\Modulos\Database\Seeds\CreateGruposModulosSeeder;
use Belly\Database\Seeds\ModuloEmpresaSeeder;

class CreateEstadoUsuarioSeeder extends Seeder
{
    /*
     * php spark db:seed \\Belly\\Usuarios\\Database\\Seeds\\CreateEstadoUsuarioSeeder
     */

    public function run()
    {
        $data = [
            [
                'id' => 1,
                'nombre' => 'Disponible',
                'descripcion' => 'Usuario activo',
                'color' => '#10b981',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 2,
                'nombre' => 'Refrigerio',
                'descripcion' => 'Usuario en refrigerio',
                'color' => '#f59e0b',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 3,
                'nombre' => 'Reunion',
                'descripcion' => 'Usuario en reunion',
                'color' => '#3b82f6',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 4,
                'nombre' => 'Formación',
                'descripcion' => 'Usuario en formación',
                'color' => '#8b5cf6',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 5,
                'nombre' => 'Coaching',
                'descripcion' => 'Usuario en coaching',
                'color' => '#ec4899',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 6,
                'nombre' => 'Offline',
                'descripcion' => 'Usuario offline',
                'color' => '#6b7280',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 7,
                'nombre' => 'Servicios Higiénicos',
                'descripcion' => 'Usuario en servicios higiénicos',
                'color' => '#ef4444',
                'created_at' => date('Y-m-d H:i:s'),
            ],
        ];

        $this->db->table('estado_usuario')->insertBatch($data);
    }
}
