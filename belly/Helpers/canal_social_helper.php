<?php

if (!function_exists('canales_sociales_puente_whatsapp_default')) {
    /**
     * Canales sociales que califican en la red y pasan a WhatsApp al obtener teléfono.
     *
     * @return list<string>
     */
    function canales_sociales_puente_whatsapp_default(): array
    {
        return ['messenger', 'instagram', 'tiktok', 'facebook', 'meta'];
    }
}

if (!function_exists('normalizar_canal_conversacion')) {
    function normalizar_canal_conversacion(?string $origen): string
    {
        $origen = strtolower(trim((string) $origen));
        if ($origen === '') {
            return '';
        }

        if (str_contains($origen, 'messenger')) {
            return 'messenger';
        }
        if (str_contains($origen, 'instagram')) {
            return 'instagram';
        }
        if (str_contains($origen, 'tiktok')) {
            return 'tiktok';
        }
        if ($origen === 'facebook' || str_contains($origen, 'facebook')) {
            return 'facebook';
        }

        return $origen;
    }
}

if (!function_exists('obtener_canales_puente_whatsapp')) {
    /**
     * @return list<string>
     */
    function obtener_canales_puente_whatsapp(?array $agentConfig = null): array
    {
        $cfg = is_array($agentConfig) ? $agentConfig : [];

        $lista = null;
        if (is_array($cfg['social_puente']['canales'] ?? null) && $cfg['social_puente']['canales'] !== []) {
            $lista = $cfg['social_puente']['canales'];
        } elseif (is_array($cfg['canales_puente_whatsapp'] ?? null) && $cfg['canales_puente_whatsapp'] !== []) {
            $lista = $cfg['canales_puente_whatsapp'];
        }

        if (! is_array($lista) || $lista === []) {
            return canales_sociales_puente_whatsapp_default();
        }

        $normalizados = [];
        foreach ($lista as $canal) {
            $canal = normalizar_canal_conversacion((string) $canal);
            if ($canal !== '') {
                $normalizados[] = $canal;
            }
        }

        return $normalizados !== [] ? array_values(array_unique($normalizados)) : canales_sociales_puente_whatsapp_default();
    }
}

if (!function_exists('es_conversacion_canal_social')) {
    function es_conversacion_canal_social(?string $origen, ?array $canalesPermitidos = null): bool
    {
        $canal = normalizar_canal_conversacion($origen);
        if ($canal === '') {
            return false;
        }

        $permitidos = $canalesPermitidos ?? canales_sociales_puente_whatsapp_default();

        return in_array($canal, $permitidos, true);
    }
}

if (!function_exists('origenes_conversacion_whatsapp')) {
    /**
     * @return list<string>
     */
    function origenes_conversacion_whatsapp(): array
    {
        return ['api-crm', 'whatsapp', 'wa', 'llamada'];
    }
}

if (!function_exists('sql_filtro_origen_whatsapp')) {
    /** Fragmento SQL: solo hilos WhatsApp (no Messenger/IG/…). */
    function sql_filtro_origen_whatsapp(string $columna = 'origen'): string
    {
        $columna = preg_replace('/[^a-zA-Z0-9_.]/', '', $columna) ?: 'origen';
        $inList = "'" . implode("','", array_map('addslashes', origenes_conversacion_whatsapp())) . "'";

        return "({$columna} IN ({$inList}) OR {$columna} LIKE '%whatsapp%')";
    }
}

if (!function_exists('etiqueta_canal_conversacion')) {
    function etiqueta_canal_conversacion(?string $canal): string
    {
        return match (normalizar_canal_conversacion($canal)) {
            'messenger' => 'Messenger (Facebook)',
            'instagram' => 'Instagram',
            'tiktok' => 'TikTok',
            'facebook' => 'Facebook',
            default => ucfirst(normalizar_canal_conversacion($canal)),
        };
    }
}
