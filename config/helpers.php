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

if (!function_exists('normalizeCpf')) {
    /** Mantém apenas os dígitos do CPF (até 11). */
    function normalizeCpf($value) {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if ($digits === null || $digits === '') {
            return '';
        }
        return substr($digits, 0, 11);
    }
}

if (!function_exists('formatCpf')) {
    /** Formata CPF como 000.000.000-00 quando houver 11 dígitos. */
    function formatCpf($value) {
        $digits = normalizeCpf($value);
        if (strlen($digits) !== 11) {
            return $digits;
        }
        return substr($digits, 0, 3) . '.' .
               substr($digits, 3, 3) . '.' .
               substr($digits, 6, 3) . '-' .
               substr($digits, 9, 2);
    }
}

if (!function_exists('calcularIdadeAnos')) {
    /**
     * Idade em anos completos na data de referência (padrão: hoje).
     * @return int|null null se data de nascimento inválida/ausente
     */
    function calcularIdadeAnos($data_nascimento, $referencia = null) {
        $nasc_raw = trim((string) $data_nascimento);
        if ($nasc_raw === '' || $nasc_raw === '0000-00-00') {
            return null;
        }
        try {
            $nasc = new DateTime(substr($nasc_raw, 0, 10));
            if ($referencia === null || trim((string) $referencia) === '') {
                $ref = new DateTime('today');
            } else {
                $ref = new DateTime(substr((string) $referencia, 0, 10));
            }
        } catch (Exception $e) {
            return null;
        }
        if ($nasc > $ref) {
            return null;
        }
        return (int) $nasc->diff($ref)->y;
    }
}

if (!function_exists('ehMaiorIdade')) {
    /** True se tiver 18 anos ou mais na data de referência. Sem nascimento = menor. */
    function ehMaiorIdade($data_nascimento, $referencia = null, $anos = 18) {
        $idade = calcularIdadeAnos($data_nascimento, $referencia);
        return $idade !== null && $idade >= (int) $anos;
    }
}

if (!function_exists('ehMaiorIdadeAutonomo')) {
    /**
     * Maior de idade que NÃO depende dos responsáveis (autoriza sozinho).
     * Se marcado como dependente/incapaz, trata como menor para autorizações.
     */
    function ehMaiorIdadeAutonomo($data_nascimento, $referencia = null, $dependente_responsaveis = false, $anos = 18) {
        if (!empty($dependente_responsaveis)) {
            return false;
        }
        return ehMaiorIdade($data_nascimento, $referencia, $anos);
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

if (!function_exists('getCursosCoordenadosPermitidos')) {
    function getCursosCoordenadosPermitidos($user, $user_id = null) {
        $user_id = $user_id ?? ($_SESSION['user_id'] ?? null);
        if (!$user || !$user_id || !$user->isCoordenador($user_id) || $user->isAdmin()) {
            return null;
        }
        return array_column($user->getCursosCoordenados($user_id), 'id');
    }
}

if (!function_exists('usuarioPodeVerPopupAlertas')) {
    function usuarioPodeVerPopupAlertas($user, $user_id = null) {
        $user_id = $user_id ?? ($_SESSION['user_id'] ?? null);
        if (!$user || !$user_id || $user->isNivel2()) {
            return false;
        }
        return $user->isAdmin() || $user->isNivel0() || $user->isCoordenador($user_id);
    }
}

if (!function_exists('resolverFiltroAutorEventos')) {
    /**
     * Resolve o filtro de autor para listagens de eventos.
     * Nível 2: vê tudo de outros nível 2 + marcações sem observações dos demais,
     * a menos que "apenas meus" esteja ativo.
     *
     * @return array{registrado_por: int|null, visibilidade_nivel2: bool}
     */
    function resolverFiltroAutorEventos($user, $user_id = null, $apenas_meus_eventos = false) {
        $user_id = $user_id ?? ($_SESSION['user_id'] ?? null);
        if ($apenas_meus_eventos && $user_id) {
            return [
                'registrado_por' => (int) $user_id,
                'visibilidade_nivel2' => false,
            ];
        }
        if ($user && $user->isNivel2()) {
            return [
                'registrado_por' => null,
                'visibilidade_nivel2' => true,
            ];
        }
        return [
            'registrado_por' => null,
            'visibilidade_nivel2' => false,
        ];
    }
}

if (!function_exists('sqlCondicaoVisibilidadeEventosNivel2')) {
    /**
     * SQL: eventos de autores nível 2 (com ou sem observação)
     * ou marcações sem observações de qualquer outro autor.
     */
    function sqlCondicaoVisibilidadeEventosNivel2($alias = 'e') {
        $alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $alias) ?: 'e';
        return "(
            EXISTS (
                SELECT 1
                FROM user_user_types uut
                INNER JOIN user_types ut ON ut.id = uut.user_type_id
                WHERE uut.user_id = {$alias}.registrado_por
                  AND ut.nivel = 'nivel2'
            )
            OR TRIM(COALESCE({$alias}.observacoes, '')) = ''
        )";
    }
}

if (!function_exists('usuarioPodeVerDetalhesEvento')) {
    /**
     * Nível 2 só vê observações/prontuário/anexos dos próprios eventos.
     * Demais níveis veem conforme as regras já existentes da tela.
     */
    function usuarioPodeVerDetalhesEvento($user, $registrado_por, $user_id = null) {
        $user_id = $user_id ?? ($_SESSION['user_id'] ?? null);
        if (!$user || !$user->isNivel2()) {
            return true;
        }
        return $user_id && (int) $registrado_por === (int) $user_id;
    }
}

if (!function_exists('ocultarDetalhesEventosAlheios')) {
    /**
     * Remove observações/prontuário de eventos que não são do visualizador.
     */
    function ocultarDetalhesEventosAlheios(array $eventos, $viewer_id) {
        $viewer_id = (int) $viewer_id;
        if ($viewer_id <= 0) {
            return $eventos;
        }
        foreach ($eventos as &$evento) {
            if ((int) ($evento['registrado_por'] ?? 0) !== $viewer_id) {
                $evento['observacoes'] = '';
                $evento['prontuario'] = '';
                $evento['_detalhes_ocultos'] = true;
            }
        }
        unset($evento);
        return $eventos;
    }
}

if (!function_exists('processarAlertasAluno')) {
    function processarAlertasAluno($db, $aluno_id) {
        $aluno_id = (int) $aluno_id;
        if ($aluno_id <= 0) {
            return;
        }

        $configuracao = new Configuracao($db);
        $detector = new AlertaDetector($db);
        $alerta_gerado = new AlertaGerado($db);

        $alertas = $detector->avaliarTodasRegrasAtivas([
            'ano_corrente' => $configuracao->getAnoCorrente(),
            'aluno_id' => $aluno_id,
        ]);

        $alerta_gerado->sincronizarAlertasAluno($aluno_id, $alertas);
    }
}

if (!function_exists('obterAlertasLoginPopup')) {
    function obterAlertasLoginPopup($db, $user, $user_id = null) {
        $user_id = $user_id ?? ($_SESSION['user_id'] ?? null);
        if (!usuarioPodeVerPopupAlertas($user, $user_id)) {
            return [];
        }

        $alerta_gerado = new AlertaGerado($db);
        return $alerta_gerado->getNaoNotificados(getCursosCoordenadosPermitidos($user, $user_id));
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
