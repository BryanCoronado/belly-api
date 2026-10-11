<?php

namespace Belly\Usuarios\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CreateUsuariosPerfilSeeder extends Seeder
{
    /*
     * php spark db:seed \\Belly\\Usuarios\\Database\\Seeds\\CreateUsuariosPerfilSeeder
     */

    public function run()

    {
        $data = [
            ['id_usuario' => 1, 'nombres' => 'Developer', 'apellidos' => 'Belly', 'id_tipo_documento' => 1, 'nro_documento' => '00000000', 'correo' => NULL, 'celular' => NULL, 'celular_secundario' => NULL, 'created_at' => date('Y-m-d H:i:s')],
            ['id_usuario' => 2, 'nombres' => 'Administrador', 'apellidos' => 'Belly', 'id_tipo_documento' => 1, 'nro_documento' => '00000001', 'correo' => NULL, 'celular' => NULL, 'celular_secundario' => NULL, 'created_at' => date('Y-m-d H:i:s')],




            //jefe asesores
            ['id_usuario' => 3, 'nombres' => 'jefatura', 'apellidos' => 'asesores', 'id_tipo_documento' => 1, 'nro_documento' => '74239464', 'correo' => NULL, 'celular' => NULL,'celular_secundario' => NULL, 'created_at' => date('Y-m-d H:i:s')],
            //asesores
            ['id_usuario' => 4, 'nombres' => 'Gabriela', 'apellidos' => 'Castañeda', 'id_tipo_documento' => 1, 'nro_documento' => '74239464', 'correo' => NULL, 'celular' => '922422560','celular_secundario' => NULL, 'created_at' => date('Y-m-d H:i:s')],
            ['id_usuario' => 5, 'nombres' => 'Mellanie', 'apellidos' => 'Curay', 'id_tipo_documento' => 1, 'nro_documento' => '74839143', 'correo' => NULL, 'celular' => '908801093', 'celular_secundario' => NULL, 'created_at' => date('Y-m-d H:i:s')],
            ['id_usuario' => 6, 'nombres' => 'Blanka', 'apellidos' => 'Lainez', 'id_tipo_documento' => 1, 'nro_documento' => '70644439', 'correo' => NULL, 'celular' => '908543149', 'celular_secundario' => NULL, 'created_at' => date('Y-m-d H:i:s')],
            ['id_usuario' => 7, 'nombres' => 'Elizabeth', 'apellidos' => 'Diaz', 'id_tipo_documento' => 1, 'nro_documento' => '71929889', 'correo' => NULL, 'celular' => '943874379', 'celular_secundario' => NULL, 'created_at' => date('Y-m-d H:i:s')],
            ['id_usuario' => 8, 'nombres' => 'Celeste', 'apellidos' => 'Cahuachi', 'id_tipo_documento' => 1, 'nro_documento' => '73955766', 'correo' => NULL, 'celular' => '907834453', 'celular_secundario' => NULL, 'created_at' => date('Y-m-d H:i:s')],
            ['id_usuario' => 9, 'nombres' => 'Miriam', 'apellidos' => 'Ochoa', 'id_tipo_documento' => 1, 'nro_documento' => '43780077', 'correo' => NULL, 'celular' => '906659115', 'celular_secundario' => NULL, 'created_at' => date('Y-m-d H:i:s')],
            ['id_usuario' => 10, 'nombres' => 'Erika', 'apellidos' => 'Caldas', 'id_tipo_documento' => 1, 'nro_documento' => '72439364', 'correo' => NULL, 'celular' => '933252458', 'celular_secundario' => '908891426', 'created_at' => date('Y-m-d H:i:s')],


            //jefe marketing
            ['id_usuario' => 11, 'nombres' => 'jefatura', 'apellidos' => 'marketing', 'id_tipo_documento' => 1, 'nro_documento' => '74044468', 'correo' => NULL, 'celular' => NULL, 'celular_secundario' => NULL, 'created_at' => date('Y-m-d H:i:s')],
            //marketing
            ['id_usuario' => 12, 'nombres' => 'Jean Pierre', 'apellidos' => 'Castillo', 'id_tipo_documento' => 1, 'nro_documento' => '74044468', 'correo' => NULL, 'celular' => '912397609', 'celular_secundario' => NULL, 'created_at' => date('Y-m-d H:i:s')],
            ['id_usuario' => 13, 'nombres' => 'Andy', 'apellidos' => 'Lino', 'id_tipo_documento' => 1, 'nro_documento' => '7528415', 'correo' => NULL, 'celular' => NULL, 'celular_secundario' => NULL, 'created_at' => date('Y-m-d H:i:s')],
            ['id_usuario' => 14, 'nombres' => 'Ronald', 'apellidos' => 'Ochoa', 'id_tipo_documento' => 1, 'nro_documento' => '123456', 'correo' => NULL, 'celular' => NULL, 'celular_secundario' => NULL, 'created_at' => date('Y-m-d H:i:s')],
            ['id_usuario' => 15, 'nombres' => 'Sharon', 'apellidos' => 'Sanchez', 'id_tipo_documento' => 1, 'nro_documento' => '72528633', 'correo' => NULL, 'celular' => '933252458', 'celular_secundario' => '908891426', 'created_at' => date('Y-m-d H:i:s')],
            ['id_usuario' => 16, 'nombres' => 'Mileni', 'apellidos' => 'Arimuya', 'id_tipo_documento' => 1, 'nro_documento' => '61266725', 'correo' => NULL, 'celular' => '933252458', 'celular_secundario' => '908891426', 'created_at' => date('Y-m-d H:i:s')],
            ['id_usuario' => 17, 'nombres' => 'Pamela', 'apellidos' => 'Vasquez', 'id_tipo_documento' => 1, 'nro_documento' => '74826045', 'correo' => NULL, 'celular' => '933252458', 'celular_secundario' => '908891426', 'created_at' => date('Y-m-d H:i:s')],
        ];

        $this->db->table('usuario_perfil')->insertBatch($data);
    }
}
