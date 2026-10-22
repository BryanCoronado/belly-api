<?php

namespace Belly\Database\Seeds;

use Belly\AgenteIa\Database\Seeds\CreateAgenteIaDemoSeeder;
use Belly\Catalogo\Database\Seeds\CreateCatalogoDemoSeeder;
use Belly\Conversacion\Database\Seeds\CreateChatsDemoSeeder;
use Belly\Mensajes\Database\Seeds\CreatePlantillasRapidasDemoSeeder;
use Belly\Reparto\Database\Seeds\CreateRepartoDemoSeeder;
use Belly\Usuarios\Database\Seeds\CreateAsesoresDemoSeeder;
use CodeIgniter\Database\Seeder;

/**
 * Datos de prueba: asesores, asistente de IA, grupos y reparto de chats, productos, respuestas rápidas, clientes y conversaciones.
 * Se puede repetir sin duplicar. Va a la empresa 1; para otra, define SEED_EMPRESA_ID.
 *
 * php spark db:seed "Belly\Database\Seeds\CreateDemoSeeder"
 */
class CreateDemoSeeder extends Seeder
{
    public function run()
    {
        $this->call(CreateAsesoresDemoSeeder::class);
        $this->call(CreateAgenteIaDemoSeeder::class);
        $this->call(CreateRepartoDemoSeeder::class);
        $this->call(CreateCatalogoDemoSeeder::class);
        $this->call(CreatePlantillasRapidasDemoSeeder::class);
        $this->call(CreateChatsDemoSeeder::class);
    }
}
