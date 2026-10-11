<?php

namespace Belly\Web\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateWebLibroReclamacionTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'codigo' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'nombre_completo' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'tipo_documento' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
            ],
            'numero_documento' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'telefono' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 180,
            ],
            'direccion' => [
                'type'       => 'VARCHAR',
                'constraint' => 300,
            ],
            'tipo_bien' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'monto_reclamado' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'null'       => true,
            ],
            'numero_comprobante' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => true,
            ],
            'descripcion_bien' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'tipo_reclamo' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'detalle' => [
                'type' => 'TEXT',
            ],
            'pedido_consumidor' => [
                'type' => 'TEXT',
            ],
            'acepta_declaracion' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'acepta_privacidad' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'estado' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'pendiente',
            ],
            'ip_origen' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true,
            ],
            'user_agent' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('codigo');
        $this->forge->addKey('estado');
        $this->forge->addKey('tipo_reclamo');
        $this->forge->addKey('created_at');
        $this->forge->createTable('web_libro_reclamacion');
    }

    public function down()
    {
        $this->forge->dropTable('web_libro_reclamacion', true);
    }
}
