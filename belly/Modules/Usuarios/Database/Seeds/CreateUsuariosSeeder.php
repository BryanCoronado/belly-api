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
class CreateUsuariosSeeder extends Seeder
{
    /*
     * php spark db:seed \\Belly\\Usuarios\\Database\\Seeds\\CreateUsuariosSeeder
     */




         //['id' => 3,'usuario' => 'lzagaceta','password' => '$2y$12$ZLH56Glc.QkKtqXMGNVOb.z/uDaEplnpHW995KpOeZiJzU6vjjGgG','activo' => 1,'id_estado_usuario' => 1,'sudo' => 1,'es_bot' => 0,'created_at' => date('Y-m-d H:i:s'),],

    public function run()
    {
        $this->asegurarEmpresaPrincipal();

        $data = [
            ['usuario' => 'developer', 'password' => password_hash('Dev.123', PASSWORD_DEFAULT), 'activo' => 1, 'id_estado_usuario' => 1, 'sudo' => 1, 'es_bot' => 0, 'created_at' => date('Y-m-d H:i:s')],
            ['usuario' => 'administrador', 'password' => password_hash('Admin.123', PASSWORD_DEFAULT), 'activo' => 1, 'id_estado_usuario' => 1, 'sudo' => 1, 'es_bot' => 0, 'created_at' => date('Y-m-d H:i:s')],



            
            //jefe asesores
            ['usuario' => 'jasesores', 'password' => password_hash('jasesores', PASSWORD_DEFAULT), 'activo' => 1, 'id_estado_usuario' => 1, 'sudo' => 0, 'es_bot' => 0, 'created_at' => date('Y-m-d H:i:s')],
            //asesores
            ['usuario' => 'gcastaneda', 'password' => password_hash('74239464', PASSWORD_DEFAULT), 'activo' => 1, 'id_estado_usuario' => 1, 'sudo' => 0, 'es_bot' => 0, 'created_at' => date('Y-m-d H:i:s')],
            ['usuario' => 'mcuray', 'password' => password_hash('74839143', PASSWORD_DEFAULT), 'activo' => 1, 'id_estado_usuario' => 1, 'sudo' => 0, 'es_bot' => 0, 'created_at' => date('Y-m-d H:i:s')],
            ['usuario' => 'blainez', 'password' => password_hash('70644439', PASSWORD_DEFAULT), 'activo' => 1, 'id_estado_usuario' => 1, 'sudo' => 0, 'es_bot' => 0, 'created_at' => date('Y-m-d H:i:s')],
            ['usuario' => 'ediaz', 'password' => password_hash('71929889', PASSWORD_DEFAULT), 'activo' => 1, 'id_estado_usuario' => 1, 'sudo' => 0, 'es_bot' => 0, 'created_at' => date('Y-m-d H:i:s')],
            ['usuario' => 'ccahuachi', 'password' => password_hash('73955766', PASSWORD_DEFAULT), 'activo' => 1, 'id_estado_usuario' => 1, 'sudo' => 0, 'es_bot' => 0, 'created_at' => date('Y-m-d H:i:s')],
            ['usuario' => 'mochoa', 'password' => password_hash('43780077', PASSWORD_DEFAULT), 'activo' => 1, 'id_estado_usuario' => 1, 'sudo' => 0, 'es_bot' => 0, 'created_at' => date('Y-m-d H:i:s')],
            ['usuario' => 'ecaldas', 'password' => password_hash('72439364', PASSWORD_DEFAULT), 'activo' => 1, 'id_estado_usuario' => 1, 'sudo' => 0, 'es_bot' => 0, 'created_at' => date('Y-m-d H:i:s')],




            //jefe marketing
            ['usuario' => 'jmarketing', 'password' => password_hash('jmarketing', PASSWORD_DEFAULT), 'activo' => 1, 'id_estado_usuario' => 1, 'sudo' => 0, 'es_bot' => 0, 'created_at' => date('Y-m-d H:i:s')],
            //marketing
            ['usuario' => 'jcastillo', 'password' => password_hash('74044468', PASSWORD_DEFAULT), 'activo' => 1, 'id_estado_usuario' => 1, 'sudo' => 0, 'es_bot' => 0, 'created_at' => date('Y-m-d H:i:s')],
            ['usuario' => 'alino', 'password' => password_hash('7528415', PASSWORD_DEFAULT), 'activo' => 1, 'id_estado_usuario' => 1, 'sudo' => 0, 'es_bot' => 0, 'created_at' => date('Y-m-d H:i:s')],
            ['usuario' => 'rochoa', 'password' => password_hash('123456', PASSWORD_DEFAULT), 'activo' => 1, 'id_estado_usuario' => 1, 'sudo' => 0, 'es_bot' => 0, 'created_at' => date('Y-m-d H:i:s')],
            ['usuario' => 'ssanchez', 'password' => password_hash('72528633', PASSWORD_DEFAULT), 'activo' => 1, 'id_estado_usuario' => 1, 'sudo' => 0, 'es_bot' => 0, 'created_at' => date('Y-m-d H:i:s')],
            ['usuario' => 'marimuya', 'password' => password_hash('61266725', PASSWORD_DEFAULT), 'activo' => 1, 'id_estado_usuario' => 1, 'sudo' => 0, 'es_bot' => 0, 'created_at' => date('Y-m-d H:i:s')],
            ['usuario' => 'pvazquez', 'password' => password_hash('74826045', PASSWORD_DEFAULT), 'activo' => 1, 'id_estado_usuario' => 1, 'sudo' => 0, 'es_bot' => 0, 'created_at' => date('Y-m-d H:i:s')],
        ];

        foreach ($data as &$fila) {
            $fila['id_empresa'] = 1;
        }
        unset($fila);

        $this->db->table('usuario')->insertBatch($data);

        $this->call(TiposDocumentosSeeder::class);
        $this->call(CreateUsuariosPerfilSeeder::class);
        $this->call(CreateGruposUsuariosSeeder::class);
        $this->call(CreateRolesSeeder::class);
        $this->call(AsignarUsuarioGrupoSeeder::class);
        $this->call(CreateGruposModulosSeeder::class);
        $this->call(CreatePermisosGruposSeeder::class);
        $this->call(CreatePermisosSeeder::class);
        $this->call(CreateModulosSeeder::class);
        $this->call(ConcederAccesoSeeder::class);
    }

    private function asegurarEmpresaPrincipal(): void
    {
        $existe = $this->db->table('empresa')->where('id', 1)->countAllResults();
        if ($existe > 0) {
            return;
        }

        $this->db->table('empresa')->insert([
            'id' => 1,
            'nombre' => 'Empresa principal',
            'slug' => 'principal',
            'plan' => 'base',
            'estado' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
