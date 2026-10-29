<?php

namespace Belly\Usuarios\Database\Seeds;

use Belly\Database\Seeds\AseguraCatalogo;
use CodeIgniter\Database\Seeder;

class CreateEstadoUsuarioSeeder extends Seeder
{
    use AseguraCatalogo;

    /*
     * php spark db:seed \\Belly\\Usuarios\\Database\\Seeds\\CreateEstadoUsuarioSeeder
     */

    public function run()
    {
        $ahora = date('Y-m-d H:i:s');
        $this->asegurarPorId('estado_usuario', [
            ['id' => 1, 'nombre' => 'Disponible', 'descripcion' => 'Usuario activo', 'color' => '#10b981', 'created_at' => $ahora],
            ['id' => 2, 'nombre' => 'Refrigerio', 'descripcion' => 'Usuario en refrigerio', 'color' => '#f59e0b', 'created_at' => $ahora],
            ['id' => 3, 'nombre' => 'Reunion', 'descripcion' => 'Usuario en reunion', 'color' => '#3b82f6', 'created_at' => $ahora],
            ['id' => 4, 'nombre' => 'Formación', 'descripcion' => 'Usuario en formación', 'color' => '#8b5cf6', 'created_at' => $ahora],
            ['id' => 5, 'nombre' => 'Coaching', 'descripcion' => 'Usuario en coaching', 'color' => '#ec4899', 'created_at' => $ahora],
            ['id' => 6, 'nombre' => 'Offline', 'descripcion' => 'Usuario offline', 'color' => '#6b7280', 'created_at' => $ahora],
            ['id' => 7, 'nombre' => 'Servicios Higiénicos', 'descripcion' => 'Usuario en servicios higiénicos', 'color' => '#ef4444', 'created_at' => $ahora],
        ]);
    }
}
