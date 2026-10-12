<?php

namespace Belly\Services;

/**
 * Adaptador mínimo de la Graph API de Meta para conectar números de WhatsApp.
 * Nunca registra tokens en los logs.
 */
class MetaGraph
{
    private const BASE = 'https://graph.facebook.com/v21.0/';
    private const TIMEOUT = 15;

    /**
     * @return array{display_phone_number:string, verified_name:string, quality_rating:?string}
     */
    public function obtenerNumero(string $phoneNumberId, string $token): array
    {
        $datos = $this->solicitar('GET', rawurlencode($phoneNumberId) . '?fields=display_phone_number,verified_name,quality_rating', $token);

        return [
            'display_phone_number' => (string) ($datos['display_phone_number'] ?? ''),
            'verified_name' => (string) ($datos['verified_name'] ?? ''),
            'quality_rating' => isset($datos['quality_rating']) ? (string) $datos['quality_rating'] : null,
        ];
    }

    public function numeroPerteneceACuenta(string $wabaId, string $phoneNumberId, string $token): bool
    {
        $datos = $this->solicitar('GET', rawurlencode($wabaId) . '/phone_numbers?fields=id&limit=100', $token);
        foreach ($datos['data'] ?? [] as $numero) {
            if ((string) ($numero['id'] ?? '') === $phoneNumberId) {
                return true;
            }
        }

        return false;
    }

    /** Sin esta suscripción Meta no envía los mensajes de la cuenta al webhook. */
    public function suscribirApp(string $wabaId, string $token): void
    {
        $this->solicitar('POST', rawurlencode($wabaId) . '/subscribed_apps', $token);
    }

    /**
     * @return array<string, mixed>
     */
    private function solicitar(string $metodo, string $ruta, string $token): array
    {
        $ch = curl_init(self::BASE . $ruta);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        ]);

        $cuerpo = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errorRed = curl_error($ch);
        curl_close($ch);

        if ($cuerpo === false) {
            log_message('warning', '[META GRAPH] Sin conexión: ' . $errorRed);

            throw new \RuntimeException('No pudimos comunicarnos con Meta. Intenta de nuevo en unos minutos.');
        }

        $datos = json_decode((string) $cuerpo, true);
        if ($codigo < 200 || $codigo >= 300 || ! is_array($datos)) {
            $error = is_array($datos) ? ($datos['error'] ?? []) : [];
            log_message('warning', '[META GRAPH] ' . $metodo . ' falló con HTTP ' . $codigo . ': ' . (string) ($error['message'] ?? ''));

            throw new \RuntimeException($this->mensajeAmigable((int) ($error['code'] ?? 0)));
        }

        return $datos;
    }

    private function mensajeAmigable(int $codigo): string
    {
        return match ($codigo) {
            190 => 'El token no es válido o ya venció. Crea uno nuevo en Meta y pégalo otra vez.',
            10, 200, 294 => 'El token no tiene permiso para administrar WhatsApp. Revisa los permisos al crearlo en Meta.',
            100, 803 => 'Algún identificador no es correcto. Cópialo de nuevo desde Meta.',
            4, 17, 32, 613 => 'Meta está recibiendo demasiadas solicitudes. Espera un momento e inténtalo de nuevo.',
            default => 'Meta no aceptó los datos. Revisa que estén bien copiados.',
        };
    }
}
