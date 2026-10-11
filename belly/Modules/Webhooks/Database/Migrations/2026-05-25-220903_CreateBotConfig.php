<?php

namespace Belly\Webhooks\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateBotConfig extends Migration
{
    public function up()
    {
        $this->createTableBotConfig();
        $this->createTableAiConversationState();
        $this->createTableAiTestSession();
        $this->createTableAiTestMessage();
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');

        $this->forge->dropTable('ai_agent_test_message', true);
        $this->forge->dropTable('ai_agent_test_session', true);
        $this->forge->dropTable('ai_conversation_state', true);
        $this->forge->dropTable('bot_config', true);

        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createTableBotConfig(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'id_app' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'activo' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'nombre_bot' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'default'    => 'Equipo CIMAK',
            ],
            'tono' => [
                'type'       => 'ENUM',
                'constraint' => ['cercano', 'formal'],
                'default'    => 'cercano',
            ],
            'rol_descripcion' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'contexto_empresa' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'instrucciones_respuesta' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'instrucciones_cotizacion' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'restricciones' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'instrucciones_derivacion' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'mensaje_derivacion_humano' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'derivar_automatico' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'id_usuario_bot' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'Usuario dueño temporal de conversaciones atendidas por IA.',
            ],
            'id_usuario_derivacion_default' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'Usuario destino cuando la IA deriva a humano sin regla específica.',
            ],
            'modo_ia' => [
                'type'       => 'ENUM',
                'constraint' => ['filtro', 'asistente', 'prueba'],
                'default'    => 'filtro',
            ],
            'modelo' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'gpt-4o',
            ],
            'temperature' => [
                'type'       => 'DECIMAL',
                'constraint' => '3,2',
                'default'    => 0.35,
            ],
            'max_tokens' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 500,
            ],
            'history_limit' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 20,
            ],
            'auto_cotizar' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'enviar_pdf_auto' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'campos_cotizacion_json' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'leer_texto_imagen' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'leer_audio' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'leer_documento' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'derivar_palabras_json' => [
                'type' => 'JSON',
                'null' => true,
                'comment'    => 'Respaldo si la IA no detecta: asesor, humano, persona, reclamo, etc.',
            ],
            'instrucciones_json' => [
                'type'    => 'JSON',
                'null'    => true,
                'comment' => 'Instrucciones ilimitadas configurables desde el frontend tipo Meta.',
            ],
            'config_json' => [
                'type'    => 'JSON',
                'null'    => true,
                'comment' => 'Flags del agente: pausa por humano, derivación, multimedia, pruebas, etc.',
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
        $this->forge->addUniqueKey('id_app', 'uq_bot_config_id_app');
        $this->forge->addForeignKey('id_app', 'app', 'id', 'CASCADE', 'CASCADE', 'fk_bot_config_app');
        $this->forge->addKey('id_usuario_bot');
        $this->forge->addKey('id_usuario_derivacion_default');
        $this->forge->addKey('deleted_at');
        $this->forge->createTable('bot_config');
    }

    private function createTableAiConversationState(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'id_conversacion' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'id_bot_config' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'ia_activa' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'estado' => [
                'type'       => 'ENUM',
                'constraint' => ['ia_atendiendo', 'ia_pausada', 'ia_desactivada', 'derivado_humano', 'humano_atendiendo', 'finalizada', 'error'],
                'default'    => 'ia_atendiendo',
            ],
            'temperatura' => [
                'type'       => 'ENUM',
                'constraint' => ['frio', 'tibio', 'caliente'],
                'default'    => 'frio',
            ],
            'motivo_derivacion' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => true,
            ],
            'datos_json' => [
                'type' => 'JSON',
                'null' => true,
                'comment' => 'Estado flexible: faltantes, pedido, motivo pausa, ultimo mensaje IA/cliente, trazas.',
            ],
            'pausado_hasta' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'desactivado_por' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'desactivado_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'reactivado_por' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'reactivado_at' => [
                'type' => 'DATETIME',
                'null' => true,
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
        $this->forge->addUniqueKey('id_conversacion', 'uq_ai_state_conversacion');
        $this->forge->addForeignKey('id_conversacion', 'conversacion', 'id', 'CASCADE', 'CASCADE', 'fk_ai_state_conversacion');
        $this->forge->addForeignKey('id_bot_config', 'bot_config', 'id', 'SET NULL', 'CASCADE', 'fk_ai_state_bot_config');
        $this->forge->addKey(['ia_activa', 'estado'], false, false, 'idx_ai_state_active_estado');
        $this->forge->addKey('pausado_hasta');
        $this->forge->addKey('deleted_at');
        $this->forge->createTable('ai_conversation_state');
    }

    private function createTableAiTestSession(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'id_bot_config' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'id_app' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'id_usuario' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'titulo' => [
                'type'       => 'VARCHAR',
                'constraint' => 160,
                'null'       => true,
            ],
            'metadata_json' => [
                'type' => 'JSON',
                'null' => true,
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
        $this->forge->addForeignKey('id_bot_config', 'bot_config', 'id', 'CASCADE', 'CASCADE', 'fk_ai_test_session_bot_config');
        $this->forge->addForeignKey('id_app', 'app', 'id', 'CASCADE', 'CASCADE', 'fk_ai_test_session_app');
        $this->forge->addKey(['id_bot_config', 'created_at'], false, false, 'idx_ai_test_session_agent');
        $this->forge->addKey('deleted_at');
        $this->forge->createTable('ai_agent_test_session');
    }

    private function createTableAiTestMessage(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'id_test_session' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'role' => [
                'type'       => 'ENUM',
                'constraint' => ['user', 'assistant', 'system'],
                'default'    => 'user',
            ],
            'mensaje' => [
                'type' => 'TEXT',
            ],
            'metadata_json' => [
                'type' => 'JSON',
                'null' => true,
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
        $this->forge->addForeignKey('id_test_session', 'ai_agent_test_session', 'id', 'CASCADE', 'CASCADE', 'fk_ai_test_message_session');
        $this->forge->addKey(['id_test_session', 'created_at'], false, false, 'idx_ai_test_message_session');
        $this->forge->addKey('deleted_at');
        $this->forge->createTable('ai_agent_test_message');
    }
}
