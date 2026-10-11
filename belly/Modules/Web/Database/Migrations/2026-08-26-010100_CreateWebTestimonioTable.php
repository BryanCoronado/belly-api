<?php

namespace Belly\Web\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateWebTestimonioTable extends Migration
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
            'nombre' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
            ],
            'comentario' => [
                'type' => 'TEXT',
            ],
            'rating' => [
                'type'       => 'DECIMAL',
                'constraint' => '2,1',
                'default'    => 5.0,
            ],
            'fuente' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'google',
            ],
            'avatar_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 1000,
                'null'       => true,
            ],
            'orden' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'activo' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('activo');
        $this->forge->addKey('orden');
        $this->forge->addKey('deleted_at');
        $this->forge->createTable('web_testimonio');
    }

    public function down()
    {
        $this->forge->dropTable('web_testimonio', true);
    }
}
