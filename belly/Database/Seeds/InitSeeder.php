<?php

namespace Belly\Database\Seeds;

use Belly\Configuracion\Database\Seeds\CreatePortafolioSeeder;
use Belly\Conversacion\Database\Seeds\CreateEstadoConversacionSeeder;
use Belly\Conversacion\Database\Seeds\CreateMotivoConversacionSeeder;
use Belly\Mensajes\Database\Seeds\CreateEstadoMensajeSeeder;
use Belly\Mensajes\Database\Seeds\CreateEstadoPlantillaMetaSeeder;
use Belly\Usuarios\Database\Seeds\CreateEstadoUsuarioSeeder;
use Belly\Usuarios\Database\Seeds\CreateUsuariosSeeder;
use CodeIgniter\Database\Seeder;

/**
 * Instalación nueva: datos base y datos de prueba.
 *
 * php spark db:seed "Belly\Database\Seeds\InitSeeder"
 */
class InitSeeder extends Seeder
{
    public function run()
    {
        $this->call(CreateEstadoMensajeSeeder::class);
        $this->call(CreateEstadoPlantillaMetaSeeder::class);
        $this->call(CreateEstadoConversacionSeeder::class);
        $this->call(CreateMotivoConversacionSeeder::class);
        $this->call(CreateEstadoUsuarioSeeder::class);
        $this->call(CreateUsuariosSeeder::class);
        $this->call(CreatePortafolioSeeder::class);

        $this->call(CreateDemoSeeder::class);
    }
}
