<?php
/**
 * Shared helper functions.
 */

if (!function_exists('normalizeSearchText')) {
    function normalizeSearchText($value) {
        $value = mb_strtolower((string)$value, 'UTF-8');
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }
        return preg_replace('/\s+/', ' ', trim($value));
    }
}
