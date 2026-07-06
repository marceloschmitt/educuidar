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

if (!function_exists('formatAlertaCriterioResumo')) {
    function formatAlertaCriterioResumo(array $regra) {
        $qtd = (int) ($regra['quantidade'] ?? 0);
        switch ($regra['tipo_criterio'] ?? '') {
            case 'dias_consecutivos':
                return $qtd . ' dia' . ($qtd === 1 ? '' : 's') . ' consecutivo' . ($qtd === 1 ? '' : 's');
            case 'intervalo_dias':
                $dias = (int) ($regra['intervalo_dias'] ?? 0);
                return $qtd . ' ocorrência' . ($qtd === 1 ? '' : 's') . ' em ' . $dias . ' dia' . ($dias === 1 ? '' : 's');
            case 'mesmo_dia':
                return $qtd . ' ocorrência' . ($qtd === 1 ? '' : 's') . ' no mesmo dia';
            default:
                return '';
        }
    }
}

if (!function_exists('formatAlertaPeriodoLabel')) {
    function formatAlertaPeriodoLabel($data_inicio, $data_fim, array $datas = []) {
        if ($data_inicio === $data_fim) {
            return date('d/m/Y', strtotime($data_inicio));
        }
        return date('d/m/Y', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim));
    }
}

if (!function_exists('parseAlertaRegraFromPost')) {
    function parseAlertaRegraFromPost() {
        $tipo = $_POST['tipo_criterio'] ?? '';
        $quantidade = (int) ($_POST['quantidade'] ?? 0);
        $intervalo_dias = ($tipo === 'intervalo_dias') ? (int) ($_POST['intervalo_dias'] ?? 0) : null;
        $tipo_ids = $_POST['tipos_evento'] ?? [];
        if (!is_array($tipo_ids)) {
            $tipo_ids = [];
        }
        $tipo_ids = array_values(array_unique(array_filter(array_map('intval', $tipo_ids), function ($id) {
            return $id > 0;
        })));

        $errors = [];
        if (empty(trim($_POST['nome'] ?? ''))) {
            $errors[] = 'Informe o nome da regra.';
        }
        if (!in_array($tipo, ['dias_consecutivos', 'intervalo_dias', 'mesmo_dia'], true)) {
            $errors[] = 'Selecione um tipo de critério válido.';
        }
        if ($quantidade < 1) {
            $errors[] = 'A quantidade deve ser pelo menos 1.';
        }
        if ($tipo === 'intervalo_dias' && ($intervalo_dias === null || $intervalo_dias < 1)) {
            $errors[] = 'Informe o intervalo de dias (mínimo 1).';
        }
        if (empty($tipo_ids)) {
            $errors[] = 'Selecione pelo menos um tipo de evento.';
        }

        return [
            'errors' => $errors,
            'nome' => trim($_POST['nome'] ?? ''),
            'descricao' => trim($_POST['descricao'] ?? ''),
            'tipo_criterio' => $tipo,
            'quantidade' => $quantidade,
            'intervalo_dias' => $intervalo_dias,
            'ignorar_domingos' => isset($_POST['ignorar_domingos']),
            'ignorar_sabados' => isset($_POST['ignorar_sabados']),
            'ativo' => isset($_POST['ativo']),
            'tipos_evento' => $tipo_ids,
        ];
    }
}
