<?php

namespace Belly\Database\Seeds;

use Belly\AgenteIa\Database\Seeds\CreateAgenteIaDemoSeeder;
use Belly\Catalogo\Database\Seeds\CreateCatalogoDemoSeeder;
use Belly\Configuracion\Database\Seeds\CreatePortafolioSeeder;
use Belly\Conversacion\Database\Seeds\CreateChatsDemoSeeder;
use Belly\Crm\Database\Seeds\CreateCrmDemoSeeder;
use Belly\Mensajes\Database\Seeds\CreatePlantillasRapidasDemoSeeder;
use Belly\Reparto\Database\Seeds\CreateRepartoDemoSeeder;
use Belly\Usuarios\Database\Seeds\CreateAsesoresDemoSeeder;
use Belly\Usuarios\Database\Seeds\CreateUsuariosSeeder;
use CodeIgniter\Database\Seeder;

/**
 * Solo desarrollo: empresa demo, usuarios de prueba, WhatsApp de prueba y chats de ejemplo.
 * No se corre en producción.
 *
 * php spark db:seed "Belly\Database\Seeds\CreateDemoSeeder"
 */
class CreateDemoSeeder extends Seeder
{
    use SoloDesarrollo;

    public function run()
    {
        $this->exigirDesarrollo();
        $this->call(CreateCatalogoSeeder::class);
        $this->call(CreateUsuariosSeeder::class);
        $this->call(CreatePortafolioSeeder::class);
        $this->call(CreateAsesoresDemoSeeder::class);
        $this->call(CreateAgenteIaDemoSeeder::class);
        $this->call(CreateRepartoDemoSeeder::class);
        $this->call(CreateCatalogoDemoSeeder::class);
        $this->call(CreatePlantillasRapidasDemoSeeder::class);
        $this->call(CreateCrmDemoSeeder::class);
        $this->call(CreateChatsDemoSeeder::class);
    }
}
