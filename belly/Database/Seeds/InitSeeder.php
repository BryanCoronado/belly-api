<?php

namespace Belly\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Belly\Usuarios\Database\Seeds\CreateUsuariosSeeder;
use Belly\Usuarios\Database\Seeds\CreateEstadoUsuarioSeeder;
use Belly\Configuracion\Database\Seeds\CreatePortafolioSeeder;
use Belly\Conversacion\Database\Seeds\CreateEstadoConversacionSeeder;
use Belly\Conversacion\Database\Seeds\CreateMotivoConversacionSeeder;
use Belly\Mensajes\Database\Seeds\CreateEstadoMensajeSeeder;
use Belly\Mensajes\Database\Seeds\CreateEstadoPlantillaMetaSeeder;
use Belly\Integraciones\Database\Seeds\CreateVehiculoMarcaSeeder;
use Belly\Integraciones\Database\Seeds\CreateVehiculoTipoSeeder;
use Belly\Integraciones\Database\Seeds\CreateVehiculoModeloSeeder;
use Belly\Media\Database\Seeds\CreateGrupoGaleriaSeeder;


class InitSeeder extends Seeder
{
    //php spark db:seed \Belly\Database\Seeds\InitSeeder
    //php spark db:seed \\Belly\\Database\\Seeds\\InitSeeder


    public function run()
    {
        $this->call(CreateEstadoMensajeSeeder::class);
        $this->call(CreateEstadoPlantillaMetaSeeder::class);
        $this->call(CreateEstadoConversacionSeeder::class);
        $this->call(CreateMotivoConversacionSeeder::class);
        $this->call(CreateEstadoUsuarioSeeder::class);
        $this->call(CreateUsuariosSeeder::class);
        $this->call(CreatePortafolioSeeder::class);

        // Integraciones (Vehicle seeders)
        $this->call(CreateVehiculoTipoSeeder::class);
        $this->call(CreateVehiculoMarcaSeeder::class);
        $this->call(CreateVehiculoModeloSeeder::class);

        // Media (Galeria seeders)
        $this->call(CreateGrupoGaleriaSeeder::class);
    

        // $this->call(CreateGruposModulosSeeder::class);
        // $this->call(CreatePermisoGruposSeeder::class);
    }
}
