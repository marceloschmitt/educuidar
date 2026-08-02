<?php
ob_start();

require_once __DIR__ . '/config/init.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$configuracao = new Configuracao($db);

if (!$user->isAdmin()) {
    header('Location: index.php');
    exit;
}

/**
 * Converte data HTML (YYYY-MM-DD) para formato da API (DD-MM-YYYY).
 */
function api_sigaa_data_para_api($iso) {
    $iso = trim((string) $iso);
    if ($iso === '') {
        return '';
    }
    $dt = DateTime::createFromFormat('Y-m-d', $iso);
    if (!$dt || $dt->format('Y-m-d') !== $iso) {
        return null;
    }
    return $dt->format('d-m-Y');
}

/**
 * Converte data da API (DD-MM-YYYY) para HTML date (YYYY-MM-DD).
 */
function api_sigaa_data_para_html($api) {
    $api = trim((string) $api);
    if ($api === '') {
        return '';
    }
    $dt = DateTime::createFromFormat('d-m-Y', $api);
    if (!$dt || $dt->format('d-m-Y') !== $api) {
        // Aceita também YYYY-MM-DD já salvo
        $dt = DateTime::createFromFormat('Y-m-d', $api);
        if (!$dt || $dt->format('Y-m-d') !== $api) {
            return '';
        }
    }
    return $dt->format('Y-m-d');
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $base_url = trim($_POST['api_sigaa_base_url'] ?? '');
    $oauth_url = trim($_POST['api_sigaa_oauth_url'] ?? '');
    $client_id = trim($_POST['api_sigaa_client_id'] ?? '');
    $client_secret = $_POST['api_sigaa_client_secret'] ?? '';
    $url_alunos = trim($_POST['api_sigaa_url_alunos'] ?? '');
    $verify_ssl = isset($_POST['api_sigaa_verify_ssl']) && $_POST['api_sigaa_verify_ssl'] === '1';
    $registro_user_id = trim($_POST['api_sigaa_registro_user_id'] ?? '');
    $periodo_letivo = trim($_POST['api_sigaa_periodo_letivo'] ?? '');
    $data_inicial_iso = trim($_POST['api_sigaa_frequencia_data_inicial'] ?? '');
    $data_final_iso = trim($_POST['api_sigaa_frequencia_data_final'] ?? '');

    $data_inicial = api_sigaa_data_para_api($data_inicial_iso);
    $data_final = api_sigaa_data_para_api($data_final_iso);

    if ($base_url === '') {
        $error = 'Informe a URL base da API.';
    } elseif ($client_id === '') {
        $error = 'Informe o Client ID.';
    } elseif ($url_alunos === '' || strpos($url_alunos, '{login}') === false) {
        $error = 'Informe a URL de alunos contendo o placeholder {login}.';
    } elseif ($periodo_letivo === '') {
        $error = 'Informe o período letivo (ex: 2026/1).';
    } elseif ($data_inicial === null || $data_inicial === '') {
        $error = 'Informe a data inicial da frequência.';
    } elseif ($data_final === null || $data_final === '') {
        $error = 'Informe a data final da frequência.';
    } elseif ($data_inicial_iso > $data_final_iso) {
        $error = 'A data inicial não pode ser posterior à data final.';
    } else {
        if ($oauth_url === '') {
            $oauth_url = rtrim($base_url, '/') . '/oauth/token';
        }

        $ok = 0;
        if ($configuracao->setApiSigaaBaseUrl($base_url)) $ok++;
        if ($configuracao->setApiSigaaOauthUrl($oauth_url)) $ok++;
        if ($configuracao->setApiSigaaClientId($client_id)) $ok++;
        if ($configuracao->setApiSigaaUrlAlunos($url_alunos)) $ok++;
        if ($configuracao->setApiSigaaVerifySsl($verify_ssl)) $ok++;
        if ($configuracao->setApiSigaaRegistroUserId($registro_user_id === '' ? null : $registro_user_id)) $ok++;
        if ($configuracao->setApiSigaaPeriodoLetivo($periodo_letivo)) $ok++;
        if ($configuracao->setApiSigaaFrequenciaDataInicial($data_inicial)) $ok++;
        if ($configuracao->setApiSigaaFrequenciaDataFinal($data_final)) $ok++;

        if ($client_secret !== '') {
            if ($configuracao->setApiSigaaClientSecret($client_secret)) $ok++;
        } elseif ($configuracao->getApiSigaaClientSecret() === '') {
            $error = 'Informe o Client Secret na primeira configuração.';
            $ok = 0;
        }

        if ($error === '' && $ok > 0) {
            $success = 'Configurações da API SIGAA salvas com sucesso!';
        } elseif ($error === '') {
            $error = 'Erro ao salvar as configurações. Tente novamente.';
        }
    }
}

$api_base_url = $configuracao->getApiSigaaBaseUrl();
$api_oauth_url = $configuracao->get('api_sigaa_oauth_url') ?: '';
$api_client_id = $configuracao->getApiSigaaClientId();
$api_url_alunos = $configuracao->getApiSigaaUrlAlunos();
$api_verify_ssl = $configuracao->getApiSigaaVerifySsl();
$api_registro_user_id = $configuracao->getApiSigaaRegistroUserId();
$tem_secret = $configuracao->getApiSigaaClientSecret() !== '';
$api_periodo_letivo = $configuracao->getApiSigaaPeriodoLetivo();
$api_data_inicial = api_sigaa_data_para_html($configuracao->getApiSigaaFrequenciaDataInicial());
$api_data_final = api_sigaa_data_para_html($configuracao->getApiSigaaFrequenciaDataFinal());

ob_end_flush();

$page_title = 'Configuração API SIGAA';
require_once 'includes/header.php';
?>

<div class="row">
    <div class="col-md-10 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-cloud"></i> Configuração API SIGAA</h5>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    Credenciais e intervalo usados pelos scripts Python (<code>consulta_alunos.py</code>)
                    para consultar frequências e gerar faltas automáticas.
                    Os valores ficam na tabela <code>configuracoes</code>.
                </div>

                <form method="POST" action="" autocomplete="off">
                    <div class="mb-3">
                        <label for="api_sigaa_base_url" class="form-label">
                            <strong>URL base</strong> <span class="text-danger">*</span>
                        </label>
                        <input type="url" class="form-control" id="api_sigaa_base_url" name="api_sigaa_base_url"
                               value="<?php echo htmlspecialchars($api_base_url); ?>"
                               placeholder="https://app.ifrs.edu.br" required>
                    </div>

                    <div class="mb-3">
                        <label for="api_sigaa_oauth_url" class="form-label">
                            <strong>URL OAuth (token)</strong>
                        </label>
                        <input type="url" class="form-control" id="api_sigaa_oauth_url" name="api_sigaa_oauth_url"
                               value="<?php echo htmlspecialchars($api_oauth_url); ?>"
                               placeholder="https://app.ifrs.edu.br/oauth/token">
                        <div class="form-text">Se vazio, usa <code>{URL base}/oauth/token</code>.</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="api_sigaa_client_id" class="form-label">
                                <strong>Client ID</strong> <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="api_sigaa_client_id" name="api_sigaa_client_id"
                                   value="<?php echo htmlspecialchars($api_client_id); ?>" required autocomplete="off">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="api_sigaa_client_secret" class="form-label">
                                <strong>Client Secret</strong>
                                <?php if (!$tem_secret): ?><span class="text-danger">*</span><?php endif; ?>
                            </label>
                            <input type="password" class="form-control" id="api_sigaa_client_secret" name="api_sigaa_client_secret"
                                   value="" placeholder="<?php echo $tem_secret ? '•••••••• (deixe em branco para manter)' : ''; ?>"
                                   autocomplete="new-password" <?php echo $tem_secret ? '' : 'required'; ?>>
                            <?php if ($tem_secret): ?>
                            <div class="form-text">Secret já cadastrado. Preencha só para alterar.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="api_sigaa_url_alunos" class="form-label">
                            <strong>URL do endpoint de alunos</strong> <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="api_sigaa_url_alunos" name="api_sigaa_url_alunos"
                               value="<?php echo htmlspecialchars($api_url_alunos); ?>"
                               placeholder="https://app.ifrs.edu.br/api/v1/sig/sigaa/alunos?login={login}&tipo_frequencia=intervalo"
                               required>
                        <div class="form-text">Deve conter <code>{login}</code> (substituído pelo CPF do aluno).</div>
                    </div>

                    <hr class="my-4">
                    <h6 class="mb-3"><i class="bi bi-calendar-range"></i> Consulta de frequência</h6>

                    <div class="mb-3">
                        <label for="api_sigaa_periodo_letivo" class="form-label">
                            <strong>Período letivo</strong> <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="api_sigaa_periodo_letivo" name="api_sigaa_periodo_letivo"
                               value="<?php echo htmlspecialchars($api_periodo_letivo); ?>"
                               placeholder="2026/1" required pattern="\d{4}/\d" maxlength="6">
                        <div class="form-text">Formato <code>AAAA/S</code> (ex.: 2026/1 ou 2026/2).</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="api_sigaa_frequencia_data_inicial" class="form-label">
                                <strong>Data inicial</strong> <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control" id="api_sigaa_frequencia_data_inicial"
                                   name="api_sigaa_frequencia_data_inicial"
                                   value="<?php echo htmlspecialchars($api_data_inicial); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="api_sigaa_frequencia_data_final" class="form-label">
                                <strong>Data final</strong> <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control" id="api_sigaa_frequencia_data_final"
                                   name="api_sigaa_frequencia_data_final"
                                   value="<?php echo htmlspecialchars($api_data_final); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="api_sigaa_registro_user_id" class="form-label">
                            <strong>ID do usuário para registro automático</strong>
                        </label>
                        <input type="number" class="form-control" id="api_sigaa_registro_user_id" name="api_sigaa_registro_user_id"
                               value="<?php echo $api_registro_user_id !== null ? (int) $api_registro_user_id : ''; ?>"
                               min="1" placeholder="Opcional — usa o primeiro administrador se vazio">
                        <div class="form-text">Campo <code>registrado_por</code> nos eventos de falta automática.</div>
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" value="1" id="api_sigaa_verify_ssl"
                               name="api_sigaa_verify_ssl" <?php echo $api_verify_ssl ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="api_sigaa_verify_ssl">
                            Verificar certificado SSL
                        </label>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="configuracoes.php" class="btn btn-secondary">Voltar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
