<?php

if (! function_exists('origenes_conversacion_whatsapp')) {
    /**
     * @return list<string>
     */
    function origenes_conversacion_whatsapp(): array
    {
        return ['api-crm', 'whatsapp', 'wa', 'manual', 'llamada'];
    }
}

if (! function_exists('sql_filtro_origen_whatsapp')) {
    function sql_filtro_origen_whatsapp(string $columna = 'origen'): string
    {
        $columna = preg_replace('/[^a-zA-Z0-9_.]/', '', $columna) ?: 'origen';
        $inList = "'" . implode("','", array_map('addslashes', origenes_conversacion_whatsapp())) . "'";

        return "({$columna} IN ({$inList}) OR {$columna} LIKE '%whatsapp%')";
    }
}
