<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AmpliarCamposMensajeMediaPayload extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('mensaje', [
            'media_url' => [
                'name' => 'media_url',
                'type' => 'TEXT',
                'null' => true,
            ],
            'media_path' => [
                'name' => 'media_path',
                'type' => 'TEXT',
                'null' => true,
            ],
            'payload' => [
                'name' => 'payload',
                'type' => 'LONGTEXT',
                'null' => true,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->modifyColumn('mensaje', [
            'media_url' => [
                'name' => 'media_url',
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'media_path' => [
                'name' => 'media_path',
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'payload' => [
                'name' => 'payload',
                'type' => 'TEXT',
                'null' => true,
            ],
        ]);
    }
}
