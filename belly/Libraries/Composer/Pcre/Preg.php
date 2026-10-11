<?php

namespace Composer\Pcre;

/**
 * Implementación simple de Preg para PhpSpreadsheet
 * Usa las funciones nativas de PHP para expresiones regulares
 */
class Preg
{
    /**
     * Perform a regular expression search and replace
     *
     * @param string|string[] $pattern
     * @param string|string[] $replacement
     * @param string|string[] $subject
     * @param int $limit
     * @param int|null $count
     * @return string|string[]
     */
    public static function replace($pattern, $replacement, $subject, $limit = -1, &$count = null)
    {
        return preg_replace($pattern, $replacement, $subject, $limit, $count);
    }

    /**
     * Perform a regular expression match
     *
     * @param string $pattern
     * @param string $subject
     * @param array|null $matches
     * @param int $flags
     * @param int $offset
     * @return bool|int
     */
    public static function match($pattern, $subject, &$matches = null, $flags = 0, $offset = 0)
    {
        return preg_match($pattern, $subject, $matches, $flags, $offset);
    }

    /**
     * Check if a pattern matches a subject
     *
     * @param string $pattern
     * @param string $subject
     * @param int $flags
     * @param int $offset
     * @return bool
     */
    public static function isMatch($pattern, $subject, $flags = 0, $offset = 0)
    {
        return (bool) preg_match($pattern, $subject, $matches, $flags, $offset);
    }

    /**
     * Perform a global regular expression match
     *
     * @param string $pattern
     * @param string $subject
     * @param array|null $matches
     * @param int $flags
     * @param int $offset
     * @return bool|int
     */
    public static function matchAll($pattern, $subject, &$matches = null, $flags = PREG_PATTERN_ORDER, $offset = 0)
    {
        return preg_match_all($pattern, $subject, $matches, $flags, $offset);
    }

    /**
     * Perform a regular expression match with offsets
     *
     * @param string $pattern
     * @param string $subject
     * @param array|null $matches
     * @param int $flags
     * @param int $offset
     * @return bool|int
     */
    public static function matchAllWithOffsets($pattern, $subject, &$matches = null, $flags = PREG_PATTERN_ORDER, $offset = 0)
    {
        return preg_match_all($pattern, $subject, $matches, $flags | PREG_OFFSET_CAPTURE, $offset);
    }

    /**
     * Perform a regular expression search and replace using a callback
     *
     * @param string|array $pattern
     * @param callable $callback
     * @param string|array $subject
     * @param int $limit
     * @param int|null $count
     * @return string|array
     */
    public static function replaceCallback($pattern, $callback, $subject, $limit = -1, &$count = null)
    {
        return preg_replace_callback($pattern, $callback, $subject, $limit, $count);
    }

    /**
     * Split string by a regular expression
     *
     * @param string $pattern
     * @param string $subject
     * @param int $limit
     * @param int $flags
     * @return array|false
     */
    public static function split($pattern, $subject, $limit = -1, $flags = 0)
    {
        return preg_split($pattern, $subject, $limit, $flags);
    }
}

