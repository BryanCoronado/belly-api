<?php

namespace Belly\Configuracion\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCorreoToEmpresa extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('correo', 'empresa')) {
            return;
        }

        $this->forge->addColumn('empresa', [
            'correo' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
                'after'      => 'nombre',
            ],
        ]);
    }

    public function down()
    {
        if (! $this->db->fieldExists('correo', 'empresa')) {
            return;
        }
        $this->forge->dropColumn('empresa', 'correo');
    }
}
