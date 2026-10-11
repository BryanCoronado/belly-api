<?php

namespace Belly\Media\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGaleriaCampaniaTable extends Migration
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
            'id_galeria' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'campaign_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
            ],
            'nombre_campania' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('id_galeria', 'galeria', 'id', 'CASCADE', 'CASCADE', 'fk_galeria_campania_galeria');
        $this->forge->addUniqueKey(['id_galeria', 'campaign_id'], 'uk_galeria_campania');
        $this->forge->addKey('campaign_id');
        $this->forge->createTable('galeria_campania');

        $this->db->query(
            'INSERT INTO galeria_campania (id_galeria, campaign_id, created_at, updated_at)
            SELECT g.id, TRIM(g.campaign_id), NOW(), NOW()
            FROM galeria g
            WHERE g.campaign_id IS NOT NULL
              AND TRIM(g.campaign_id) <> \'\''
        );
    }

    public function down()
    {
        $this->forge->dropTable('galeria_campania', true);
    }
}
