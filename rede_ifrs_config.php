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

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ipv4 = trim((string) ($_POST['rede_ifrs_cidrs_ipv4'] ?? ''));
    $ipv6 = trim((string) ($_POST['rede_ifrs_cidrs_ipv6'] ?? ''));

    $invalidos = [];
    foreach (normalizarListaCidrs($ipv4) as $cidr) {
        $rede = $cidr;
        if (strpos($cidr, '/') !== false) {
            [$rede, $pref] = explode('/', $cidr, 2);
            if (!ctype_digit($pref) || (int) $pref < 0 || (int) $pref > 32) {
                $invalidos[] = $cidr;
                continue;
            }
        }
        if (!filter_var(trim($rede), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $invalidos[] = $cidr;
        }
    }
    foreach (normalizarListaCidrs($ipv6) as $cidr) {
        $rede = $cidr;
        if (strpos($cidr, '/') !== false) {
            [$rede, $pref] = explode('/', $cidr, 2);
            if (!ctype_digit($pref) || (int) $pref < 0 || (int) $pref > 128) {
                $invalidos[] = $cidr;
                continue;
            }
        }
        if (!filter_var(trim($rede), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $invalidos[] = $cidr;
        }
    }

    if ($invalidos) {
        $error = 'Intervalo(s) inválido(s): ' . implode(', ', $invalidos);
    } elseif (
        $configuracao->setRedeIfrsCidrsIpv4($ipv4)
        && $configuracao->setRedeIfrsCidrsIpv6($ipv6)
    ) {
        $success = 'Intervalos da rede IFRS salvos.';
    } else {
        $error = 'Não foi possível salvar a configuração.';
    }
}

$ipv4_cfg = $configuracao->getRedeIfrsCidrsIpv4();
$ipv6_cfg = $configuracao->getRedeIfrsCidrsIpv6();
ob_end_flush();

$page_title = 'Rede IFRS';
require_once 'includes/header.php';
?>

<div class="row">
    <div class="col-md-10 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-router"></i> Rede IFRS</h5>
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
                    Defina os intervalos (CIDR) da rede local do campus.
                    Na lista de autorizações, pedidos feitos pelo <strong>responsável</strong>
                    cujo IP estiver nesses intervalos aparecem com fundo vermelho
                    (registros da escola não são destacados).
                </div>

                <form method="POST" action="" autocomplete="off">
                    <div class="mb-3">
                        <label for="rede_ifrs_cidrs_ipv4" class="form-label">
                            <strong>Intervalos IPv4</strong>
                        </label>
                        <textarea class="form-control font-monospace" id="rede_ifrs_cidrs_ipv4"
                                  name="rede_ifrs_cidrs_ipv4" rows="6"
                                  placeholder="10.0.0.0/8&#10;192.168.0.0/16"><?php
                            echo htmlspecialchars($ipv4_cfg);
                        ?></textarea>
                        <div class="form-text">Um CIDR ou IP por linha (ex.: <code>10.20.0.0/16</code>).</div>
                    </div>

                    <div class="mb-3">
                        <label for="rede_ifrs_cidrs_ipv6" class="form-label">
                            <strong>Intervalos IPv6</strong>
                        </label>
                        <textarea class="form-control font-monospace" id="rede_ifrs_cidrs_ipv6"
                                  name="rede_ifrs_cidrs_ipv6" rows="6"
                                  placeholder="2801:82:80ff::/48"><?php
                            echo htmlspecialchars($ipv6_cfg);
                        ?></textarea>
                        <div class="form-text">Um CIDR ou IP por linha (ex.: <code>2801:82:80ff::/48</code>).</div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="autorizacoes.php" class="btn btn-secondary">Voltar às autorizações</a>
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
