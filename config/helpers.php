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

if (!function_exists('resolveIncluirSabadosSession')) {
    /**
     * Preferência de exibir eventos em sábado (persistida na sessão).
     * Padrão: true (mostrar). Baseado apenas em data_evento.
     */
    function resolveIncluirSabadosSession() {
        if (!isset($_SESSION['incluir_sabados'])) {
            $_SESSION['incluir_sabados'] = true;
        }

        if (isset($_GET['limpar_filtros'])) {
            $_SESSION['incluir_sabados'] = true;
            return true;
        }

        if (isset($_GET['incluir_sabados'])) {
            $_SESSION['incluir_sabados'] = $_GET['incluir_sabados'] === '1';
        }

        return (bool) $_SESSION['incluir_sabados'];
    }
}

if (!function_exists('isEventoEmSabado')) {
    function isEventoEmSabado($data_evento) {
        if (empty($data_evento)) {
            return false;
        }
        return (int) date('N', strtotime($data_evento)) === 6;
    }
}

if (!function_exists('filterEventosPorSabado')) {
    function filterEventosPorSabado(array $eventos, $incluir_sabados) {
        if ($incluir_sabados) {
            return $eventos;
        }
        return array_values(array_filter($eventos, function ($evt) {
            return !isEventoEmSabado($evt['data_evento'] ?? '');
        }));
    }
}

if (!function_exists('sabadoFilterToggleHref')) {
    function sabadoFilterToggleHref($script, array $preserve_params = [], $incluir_sabados = null) {
        if ($incluir_sabados === null) {
            $incluir_sabados = resolveIncluirSabadosSession();
        }
        $params = array_filter($preserve_params, function ($value) {
            return $value !== '' && $value !== null;
        });
        $params['incluir_sabados'] = $incluir_sabados ? '0' : '1';
        return $script . '?' . http_build_query($params);
    }
}
