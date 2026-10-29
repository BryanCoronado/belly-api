<?php

namespace Belly\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Instalación de producción: solo catálogos.
 * La primera empresa y su administrador se crean en /registro.
 *
 * php spark migrate --all
 * php spark db:seed "Belly\Database\Seeds\InitSeeder"
 */
class InitSeeder extends Seeder
{
    public function run()
    {
        $this->call(CreateCatalogoSeeder::class);
    }
}
