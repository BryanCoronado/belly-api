<?php

namespace Belly\Database\Seeds;

use Belly\Conversacion\Database\Seeds\CreateEstadoConversacionSeeder;
use Belly\Conversacion\Database\Seeds\CreateMotivoConversacionSeeder;
use Belly\Mensajes\Database\Seeds\CreateEstadoMensajeSeeder;
use Belly\Mensajes\Database\Seeds\CreateEstadoPlantillaMetaSeeder;
use Belly\Modulos\Database\Seeds\CreateGruposModulosSeeder;
use Belly\Modulos\Database\Seeds\CreateModulosSeeder;
use Belly\Permisos\Database\Seeds\CreatePermisosGruposSeeder;
use Belly\Permisos\Database\Seeds\CreatePermisosSeeder;
use Belly\Usuarios\Database\Seeds\CreateEstadoUsuarioSeeder;
use Belly\Usuarios\Database\Seeds\TiposDocumentosSeeder;
use CodeIgniter\Database\Seeder;

/**
 * Catálogos que el sistema necesita vacío: estados, documentos, menú y permisos.
 * No crea empresas, usuarios ni WhatsApp. Se puede repetir.
 *
 * php spark db:seed "Belly\Database\Seeds\CreateCatalogoSeeder"
 */
class CreateCatalogoSeeder extends Seeder
{
    public function run()
    {
        $this->call(CreateEstadoMensajeSeeder::class);
        $this->call(CreateEstadoPlantillaMetaSeeder::class);
        $this->call(CreateEstadoConversacionSeeder::class);
        $this->call(CreateMotivoConversacionSeeder::class);
        $this->call(CreateEstadoUsuarioSeeder::class);
        $this->call(TiposDocumentosSeeder::class);
        $this->call(CreateGruposModulosSeeder::class);
        $this->call(CreatePermisosGruposSeeder::class);
        $this->call(CreatePermisosSeeder::class);
        $this->call(CreateModulosSeeder::class);
    }
}
