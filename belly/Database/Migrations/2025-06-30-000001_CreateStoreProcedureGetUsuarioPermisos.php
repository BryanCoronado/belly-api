<?php

use CodeIgniter\Database\Migration;

class CreateStoreProcedureGetUsuarioPermisos extends Migration
{
    public function up()
    {
        // Primero elimina el procedimiento si existe
        $this->db->query("DROP PROCEDURE IF EXISTS get_usuario_permisos");

        // LÓGICA CORRECTA Y FLEXIBLE DE PERMISOS (Basada en RBAC estándar de grandes empresas):
        // 
        // JERARQUÍA DE PRIORIDAD (de mayor a menor):
        // 1. Permisos especiales del usuario (máxima prioridad) - independientes del grupo
        // 2. Permisos específicos del usuario en el grupo - sobrescribe grupo y rol
        // 3. Permisos del rol en el grupo - sobrescribe permisos del grupo
        // 4. Permisos del grupo (menor prioridad) - permisos base
        // 
        // REGLAS FLEXIBLES (como en sistemas enterprise):
        // - Si un permiso está SOLO en el grupo → el usuario lo hereda
        // - Si un permiso está SOLO en el rol → el usuario lo hereda (aunque no esté en el grupo)
        // - Si un permiso está en grupo Y rol → el rol SOBRESCRIBE el grupo (incluso si el rol lo desactiva con 0)
        // - Los permisos especiales siempre tienen prioridad máxima
        $this->db->query("
            CREATE PROCEDURE get_usuario_permisos(IN param_id_usuario INT)
            BEGIN
                DROP TEMPORARY TABLE IF EXISTS tpm_usuario_permiso;
                
                -- Crear tabla temporal con todos los permisos del usuario y sus valores por nivel
                CREATE TEMPORARY TABLE tpm_usuario_permiso AS
                SELECT 
                    p.id_permiso,
                    param_id_usuario AS id_usuario,
                    -- Aplicar prioridad flexible usando COALESCE (estándar enterprise):
                    -- Prioridad: especial > usuario > rol > grupo
                    -- COALESCE toma el primer valor NO-NULL, permitiendo desactivar permisos en niveles superiores
                    COALESCE(esp.acc_crear, usu.acc_crear, rol.acc_crear, gru.acc_crear) AS acc_crear,
                    COALESCE(esp.acc_lectura, usu.acc_lectura, rol.acc_lectura, gru.acc_lectura) AS acc_lectura,
                    COALESCE(esp.acc_actualizar, usu.acc_actualizar, rol.acc_actualizar, gru.acc_actualizar) AS acc_actualizar,
                    COALESCE(esp.acc_eliminar, usu.acc_eliminar, rol.acc_eliminar, gru.acc_eliminar) AS acc_eliminar
                FROM (
                    -- Obtener TODOS los permisos únicos del usuario (de cualquier nivel)
                    SELECT DISTINCT id_permiso
                    FROM (
                        -- Permisos del grupo
                        SELECT gup.id_permiso
                        FROM grupo_usuario_usuario AS guu
                        INNER JOIN grupo_usuario_permiso AS gup ON (
                            gup.id_grupo_usuario = guu.id_grupo_usuario 
                            AND gup.deleted_at IS NULL
                        )
                        WHERE guu.id_usuario = param_id_usuario AND guu.deleted_at IS NULL
                        
                        UNION
                        
                        -- Permisos del rol (incluso si no están en el grupo)
                        SELECT gupr.id_permiso
                        FROM grupo_usuario_usuario AS guu
                        INNER JOIN grupo_usuario_permiso_rol AS gupr ON (
                            gupr.id_grupo_usuario = guu.id_grupo_usuario 
                            AND gupr.id_grupo_usuario_rol = guu.id_grupo_usuario_rol 
                            AND gupr.deleted_at IS NULL
                        )
                        WHERE guu.id_usuario = param_id_usuario AND guu.deleted_at IS NULL
                        
                        UNION
                        
                        -- Permisos específicos del usuario (incluso si no están en el grupo/rol)
                        SELECT gupu.id_permiso
                        FROM grupo_usuario_usuario AS guu
                        INNER JOIN grupo_usuario_permiso_usuario AS gupu ON (
                            gupu.id_grupo_usuario = guu.id_grupo_usuario 
                            AND gupu.id_usuario = guu.id_usuario 
                            AND gupu.deleted_at IS NULL
                        )
                        WHERE guu.id_usuario = param_id_usuario AND guu.deleted_at IS NULL
                        
                        UNION
                        
                        -- Permisos especiales (independientes del grupo)
                        SELECT upe.id_permiso
                        FROM usuario_permiso_especial AS upe
                        WHERE upe.id_usuario = param_id_usuario AND upe.deleted_at IS NULL
                    ) AS todos_permisos
                ) AS p
                -- LEFT JOIN para obtener valores de cada nivel (en orden de prioridad inversa para COALESCE)
                LEFT JOIN (
                    -- 4. Permisos del grupo (menor prioridad)
                    SELECT guu.id_usuario, gup.id_permiso, 
                           gup.acc_crear, gup.acc_lectura, gup.acc_actualizar, gup.acc_eliminar
                    FROM grupo_usuario_usuario AS guu
                    INNER JOIN grupo_usuario_permiso AS gup ON (
                        gup.id_grupo_usuario = guu.id_grupo_usuario 
                        AND gup.deleted_at IS NULL
                    )
                    WHERE guu.id_usuario = param_id_usuario AND guu.deleted_at IS NULL
                ) AS gru ON (p.id_permiso = gru.id_permiso)
                LEFT JOIN (
                    -- 3. Permisos del rol (sobrescribe grupo)
                    SELECT guu.id_usuario, gupr.id_permiso,
                           gupr.acc_crear, gupr.acc_lectura, gupr.acc_actualizar, gupr.acc_eliminar
                    FROM grupo_usuario_usuario AS guu
                    INNER JOIN grupo_usuario_permiso_rol AS gupr ON (
                        gupr.id_grupo_usuario = guu.id_grupo_usuario 
                        AND gupr.id_grupo_usuario_rol = guu.id_grupo_usuario_rol 
                        AND gupr.deleted_at IS NULL
                    )
                    WHERE guu.id_usuario = param_id_usuario AND guu.deleted_at IS NULL
                ) AS rol ON (p.id_permiso = rol.id_permiso)
                LEFT JOIN (
                    -- 2. Permisos específicos del usuario (sobrescribe grupo y rol)
                    SELECT id_usuario, id_permiso,
                           acc_crear, acc_lectura, acc_actualizar, acc_eliminar
                    FROM grupo_usuario_permiso_usuario
                    WHERE id_usuario = param_id_usuario AND deleted_at IS NULL
                ) AS usu ON (p.id_permiso = usu.id_permiso)
                LEFT JOIN (
                    -- 1. Permisos especiales (máxima prioridad, sobrescribe todo)
                    SELECT id_usuario, id_permiso,
                           acc_crear, acc_lectura, acc_actualizar, acc_eliminar
                    FROM usuario_permiso_especial
                    WHERE id_usuario = param_id_usuario AND deleted_at IS NULL
                ) AS esp ON (p.id_permiso = esp.id_permiso)
                -- Solo incluir permisos que existen en al menos un nivel
                WHERE gru.id_permiso IS NOT NULL 
                   OR rol.id_permiso IS NOT NULL 
                   OR usu.id_permiso IS NOT NULL 
                   OR esp.id_permiso IS NOT NULL;

                SELECT 
                    t1.id_usuario,
                    t1.id_permiso,
                    t2.permiso,
                    t2.alias,
                    t1.acc_crear,
                    t1.acc_lectura,
                    t1.acc_actualizar,
                    t1.acc_eliminar
                FROM tpm_usuario_permiso AS t1
                INNER JOIN permiso AS t2 ON (t2.id = t1.id_permiso AND t2.activo = 1 AND t2.deleted_at IS NULL);
            END
        ");
    }

    public function down()
    {
        $this->db->query("DROP PROCEDURE IF EXISTS get_usuario_permisos");
    }
}