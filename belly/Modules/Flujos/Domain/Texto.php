<?php

namespace Belly\Flujos\Domain;

/** Utilidades de texto del motor de flujos. Sin dependencias del framework. */
final class Texto
{
    private const ACENTOS = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ñ' => 'n',
    ];

    /** Minúsculas, sin tildes ni signos: "¡Hola, Ñandú!" → "hola nandu". */
    public static function normalizar(string $texto): string
    {
        $texto = strtr(mb_strtolower($texto), self::ACENTOS);
        $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto) ?? '';

        return trim($texto);
    }

    /** ¿El texto contiene la palabra o frase completa? (no coincide dentro de otra palabra) */
    public static function contienePalabra(string $texto, string $palabra): bool
    {
        $palabra = self::normalizar($palabra);
        if ($palabra === '') {
            return false;
        }

        return str_contains(' ' . self::normalizar($texto) . ' ', ' ' . $palabra . ' ');
    }

    /** Reemplaza {{variable}} por su valor (vacío si no existe). */
    public static function plantilla(string $texto, array $variables): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            static fn (array $m): string => (string) ($variables[strtolower($m[1])] ?? ''),
            $texto
        ) ?? $texto;
    }

    /** Nombre de variable válido: minúsculas, números y guion bajo. */
    public static function nombreVariable(string $nombre): string
    {
        $nombre = self::normalizar($nombre);

        return substr(str_replace(' ', '_', $nombre), 0, 40);
    }
}
