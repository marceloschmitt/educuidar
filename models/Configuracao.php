<?php
/**
 * Configuracao model - handles system configuration
 */

class Configuracao {
    private $conn;
    private $table = 'configuracoes';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function get($chave) {
        $query = "SELECT valor FROM " . $this->table . " WHERE chave = :chave LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':chave', $chave);
        $stmt->execute();

        $result = $stmt->fetch();
        return $result ? $result['valor'] : null;
    }

    public function set($chave, $valor, $descricao = null) {
        $query = "INSERT INTO " . $this->table . " (chave, valor, descricao) 
                  VALUES (:chave, :valor, :descricao)
                  ON DUPLICATE KEY UPDATE valor = :valor_update, descricao = COALESCE(:descricao_update, descricao)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':chave', $chave);
        $stmt->bindParam(':valor', $valor);
        $desc = $descricao;
        $stmt->bindParam(':descricao', $desc);
        $stmt->bindParam(':valor_update', $valor);
        $stmt->bindParam(':descricao_update', $desc);

        return $stmt->execute();
    }

    public function getAnoCorrente() {
        $ano = $this->get('ano_corrente');
        return $ano ? (int)$ano : (int)date('Y');
    }

    public function setAnoCorrente($ano) {
        return $this->set('ano_corrente', (string)$ano, 'Ano civil corrente para controle de eventos');
    }

    // LDAP Configuration methods
    public function getLdapHost() {
        return $this->get('ldap_host') ?: '';
    }

    public function setLdapHost($host) {
        return $this->set('ldap_host', $host, 'Endereço do servidor LDAP');
    }

    public function getLdapBaseDn() {
        return $this->get('ldap_base_dn') ?: '';
    }

    public function setLdapBaseDn($base_dn) {
        return $this->set('ldap_base_dn', $base_dn, 'Base DN para busca de usuários no LDAP');
    }

    public function getLdapBindDn() {
        return $this->get('ldap_bind_dn') ?: '';
    }

    public function setLdapBindDn($bind_dn) {
        return $this->set('ldap_bind_dn', $bind_dn, 'DN para bind administrativo no LDAP (opcional)');
    }

    public function getLdapBindPassword() {
        return $this->get('ldap_bind_password') ?: '';
    }

    public function setLdapBindPassword($password) {
        return $this->set('ldap_bind_password', $password, 'Senha para bind administrativo no LDAP (opcional)');
    }

    public function getLdapUserAttribute() {
        return $this->get('ldap_user_attribute') ?: '';
    }

    public function setLdapUserAttribute($attribute) {
        return $this->set('ldap_user_attribute', $attribute, 'Atributo LDAP usado para buscar usuários (ex: uid, sAMAccountName, userPrincipalName)');
    }

    // API SIGAA (frequência / faltas automáticas)
    public function getApiSigaaBaseUrl() {
        return $this->get('api_sigaa_base_url') ?: 'https://app.ifrs.edu.br';
    }

    public function setApiSigaaBaseUrl($url) {
        return $this->set('api_sigaa_base_url', $url, 'URL base da API IFRS/SIGAA');
    }

    public function getApiSigaaOauthUrl() {
        $url = $this->get('api_sigaa_oauth_url');
        if ($url) {
            return $url;
        }
        return rtrim($this->getApiSigaaBaseUrl(), '/') . '/oauth/token';
    }

    public function setApiSigaaOauthUrl($url) {
        return $this->set('api_sigaa_oauth_url', $url, 'URL OAuth token da API IFRS/SIGAA');
    }

    public function getApiSigaaClientId() {
        return $this->get('api_sigaa_client_id') ?: '';
    }

    public function setApiSigaaClientId($client_id) {
        return $this->set('api_sigaa_client_id', $client_id, 'Client ID OAuth da API SIGAA');
    }

    public function getApiSigaaClientSecret() {
        return $this->get('api_sigaa_client_secret') ?: '';
    }

    public function setApiSigaaClientSecret($client_secret) {
        return $this->set('api_sigaa_client_secret', $client_secret, 'Client Secret OAuth da API SIGAA');
    }

    public function getApiSigaaUrlAlunos() {
        return $this->get('api_sigaa_url_alunos')
            ?: 'https://app.ifrs.edu.br/api/v1/sig/sigaa/alunos?login={login}&tipo_frequencia=intervalo';
    }

    public function setApiSigaaUrlAlunos($url) {
        return $this->set('api_sigaa_url_alunos', $url, 'URL do endpoint de alunos/frequência SIGAA');
    }

    public function getApiSigaaVerifySsl() {
        $valor = $this->get('api_sigaa_verify_ssl');
        if ($valor === null || $valor === '') {
            return false;
        }
        return $valor === '1' || $valor === 1 || $valor === 'true';
    }

    public function setApiSigaaVerifySsl($verify) {
        return $this->set('api_sigaa_verify_ssl', $verify ? '1' : '0', 'Verificar certificado SSL na API SIGAA');
    }

    public function getApiSigaaRegistroUserId() {
        $valor = $this->get('api_sigaa_registro_user_id');
        return ($valor !== null && $valor !== '') ? (int) $valor : null;
    }

    public function setApiSigaaRegistroUserId($user_id) {
        $valor = ($user_id === null || $user_id === '') ? '' : (string) (int) $user_id;
        return $this->set('api_sigaa_registro_user_id', $valor, 'users.id usado em registrado_por dos eventos automáticos');
    }

    public function getApiSigaaFrequenciaDataInicial() {
        return $this->get('api_sigaa_frequencia_data_inicial') ?: '';
    }

    public function setApiSigaaFrequenciaDataInicial($data) {
        return $this->set('api_sigaa_frequencia_data_inicial', $data, 'Data inicial da consulta de frequência SIGAA (DD-MM-AAAA)');
    }

    public function getApiSigaaFrequenciaDataFinal() {
        return $this->get('api_sigaa_frequencia_data_final') ?: '';
    }

    public function setApiSigaaFrequenciaDataFinal($data) {
        return $this->set('api_sigaa_frequencia_data_final', $data, 'Data final da consulta de frequência SIGAA (DD-MM-AAAA)');
    }

    public function getApiSigaaPeriodoLetivo() {
        return $this->get('api_sigaa_periodo_letivo') ?: '';
    }

    public function setApiSigaaPeriodoLetivo($periodo) {
        return $this->set('api_sigaa_periodo_letivo', $periodo, 'Período letivo da consulta SIGAA (ex: 2026/1)');
    }

    /** @return int|null tipos_eventos.id das faltas lidas do SIGAA */
    public function getApiSigaaTipoEventoFaltaId() {
        $valor = $this->get('api_sigaa_tipo_evento_falta_id');
        return ($valor !== null && ctype_digit((string) $valor) && (int) $valor > 0) ? (int) $valor : null;
    }

    /**
     * Ao trocar o tipo, a próxima coleta faz carga inicial silenciosa
     * (sem e-mails nem pop-up de alertas).
     */
    public function setApiSigaaTipoEventoFaltaId($tipo_id) {
        $valor = $tipo_id ? (string) (int) $tipo_id : '';
        return $this->set('api_sigaa_tipo_evento_falta_id', $valor, 'tipos_eventos.id usado nas faltas lidas do SIGAA');
    }

    /** @return int|null tipos_eventos.id com que o dashboard abre; null = todos os tipos */
    public function getDashboardTipoEventoPadraoId() {
        $valor = (string) $this->get('dashboard_tipo_evento_padrao');
        return (ctype_digit($valor) && (int) $valor > 0) ? (int) $valor : null;
    }

    public function setDashboardTipoEventoPadraoId($tipo_id) {
        $valor = (ctype_digit((string) $tipo_id) && (int) $tipo_id > 0) ? (string) (int) $tipo_id : '';
        return $this->set('dashboard_tipo_evento_padrao', $valor, 'tipos_eventos.id com que o dashboard abre (vazio = todos os tipos)');
    }

    // System installation status
    public function isSistemaInstalado() {
        $valor = $this->get('sistema_instalado');
        return $valor === '1' || $valor === 1;
    }

    public function setSistemaInstalado($instalado = true) {
        return $this->set('sistema_instalado', $instalado ? '1' : '0', 'Indica se o sistema foi instalado e configurado');
    }

    public function isCadastroResponsaveisHabilitado() {
        $valor = $this->get('cadastro_responsaveis_habilitado');
        return $valor === '1' || $valor === 1 || $valor === 'true';
    }

    public function setCadastroResponsaveisHabilitado($habilitado) {
        return $this->set(
            'cadastro_responsaveis_habilitado',
            $habilitado ? '1' : '0',
            'Permite cadastro público de responsáveis (1=aberto, 0=fechado)'
        );
    }

    public function getAutorizacaoTipoEventoId($tipo_autorizacao) {
        $chave = $tipo_autorizacao === 'saida_fora_horario'
            ? 'autorizacao_saida_tipo_evento_id'
            : 'autorizacao_entrada_tipo_evento_id';
        $valor = $this->get($chave);
        return ($valor !== null && $valor !== '') ? (int) $valor : null;
    }

    public function setAutorizacaoTipoEventoId($tipo_autorizacao, $tipo_evento_id) {
        $chave = $tipo_autorizacao === 'saida_fora_horario'
            ? 'autorizacao_saida_tipo_evento_id'
            : 'autorizacao_entrada_tipo_evento_id';
        $desc = $tipo_autorizacao === 'saida_fora_horario'
            ? 'Tipo de evento criado ao marcar saída fora do horário como ocorrida'
            : 'Tipo de evento criado ao marcar entrada fora do horário como ocorrida';
        $valor = ($tipo_evento_id === null || $tipo_evento_id === '') ? '' : (string) (int) $tipo_evento_id;
        return $this->set($chave, $valor, $desc);
    }

    // E-mail SMTP (mesmos parâmetros do projeto MAPA)
    public const HORA_RESUMO_PADRAO = '19:30';

    private static function valorVerdadeiro($valor) {
        return in_array(strtolower((string) $valor), ['1', 'true', 'yes', 'on'], true);
    }

    private static function normalizarHora($valor) {
        $valor = trim((string) $valor);
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $valor) ? $valor : null;
    }

    /** Resumo diário aos responsáveis habilitado (chave histórica email_enabled). */
    public function isEmailEnabled() {
        return self::valorVerdadeiro($this->get('email_enabled') ?? '0');
    }

    public function setEmailEnabled($enabled) {
        return $this->set(
            'email_enabled',
            $enabled ? '1' : '0',
            'Enviar resumo diário de eventos aos responsáveis'
        );
    }

    /** Sem valor salvo, segue email_enabled (quando havia um único interruptor para os dois). */
    public function isEmailCoordenadoresEnabled() {
        $valor = $this->get('email_coordenadores_enabled');
        if ($valor === null || $valor === '') {
            return $this->isEmailEnabled();
        }
        return self::valorVerdadeiro($valor);
    }

    public function setEmailCoordenadoresEnabled($enabled) {
        return $this->set(
            'email_coordenadores_enabled',
            $enabled ? '1' : '0',
            'Enviar resumo diário de eventos aos coordenadores'
        );
    }

    /** Horário (HH:MM) a partir do qual o resumo do dia aos responsáveis pode ser enviado. */
    public function getHoraResumoResponsaveis() {
        return self::normalizarHora($this->get('email_hora_resumo_responsaveis')) ?? self::HORA_RESUMO_PADRAO;
    }

    /** Horário (HH:MM) a partir do qual o resumo do dia aos coordenadores pode ser enviado. */
    public function getHoraResumoCoordenadores() {
        return self::normalizarHora($this->get('email_hora_resumo_coordenadores')) ?? self::HORA_RESUMO_PADRAO;
    }

    /**
     * Trava opcional de ambiente: se EMAIL_SEND estiver definido e não for true, bloqueia.
     * Se a variável não existir, usa apenas email_enabled do banco.
     */
    public function permiteEnvioEmail() {
        $env = getenv('EMAIL_SEND');
        if ($env === false || $env === '') {
            return true;
        }
        return in_array(strtolower(trim($env)), ['1', 'true', 'yes', 'on'], true);
    }

    public function getEmailConfig() {
        $port = (int) ($this->get('email_port') ?: 587);
        if ($port <= 0) {
            $port = 587;
        }
        $encryption = strtolower(trim((string) ($this->get('email_encryption') ?: 'tls')));
        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            $encryption = 'tls';
        }

        return [
            'enabled' => $this->isEmailEnabled(),
            'enabled_coordenadores' => $this->isEmailCoordenadoresEnabled(),
            'hora_responsaveis' => $this->getHoraResumoResponsaveis(),
            'hora_coordenadores' => $this->getHoraResumoCoordenadores(),
            'host' => (string) ($this->get('email_host') ?: ''),
            'port' => $port,
            'encryption' => $encryption,
            'username' => (string) ($this->get('email_username') ?: ''),
            'password' => (string) ($this->get('email_password') ?: ''),
            'from_address' => (string) ($this->get('email_from_address') ?: ''),
            'from_name' => (string) ($this->get('email_from_name') ?: 'EduCuidar'),
        ];
    }

    public function isEmailConfigured() {
        $cfg = $this->getEmailConfig();
        return $cfg['host'] !== '' && $cfg['from_address'] !== '' && $cfg['port'] > 0;
    }

    public function saveEmailConfig(array $dados) {
        $ok = true;
        $ok = $this->setEmailEnabled(!empty($dados['enabled'])) && $ok;
        if (array_key_exists('enabled_coordenadores', $dados)) {
            $ok = $this->setEmailCoordenadoresEnabled(!empty($dados['enabled_coordenadores'])) && $ok;
        }
        foreach ([
            'hora_responsaveis' => ['email_hora_resumo_responsaveis', 'Horário do resumo diário aos responsáveis (HH:MM)'],
            'hora_coordenadores' => ['email_hora_resumo_coordenadores', 'Horário do resumo diário aos coordenadores (HH:MM)'],
        ] as $campo => [$chave, $descricao]) {
            if (array_key_exists($campo, $dados)) {
                $hora = self::normalizarHora($dados[$campo]) ?? self::HORA_RESUMO_PADRAO;
                $ok = $this->set($chave, $hora, $descricao) && $ok;
            }
        }
        $ok = $this->set('email_host', trim((string) ($dados['host'] ?? '')), 'Host SMTP') && $ok;
        $ok = $this->set('email_port', (string) (int) ($dados['port'] ?? 587), 'Porta SMTP') && $ok;
        $enc = strtolower(trim((string) ($dados['encryption'] ?? 'tls')));
        if (!in_array($enc, ['tls', 'ssl', 'none'], true)) {
            $enc = 'tls';
        }
        $ok = $this->set('email_encryption', $enc, 'Criptografia SMTP') && $ok;
        $ok = $this->set('email_username', trim((string) ($dados['username'] ?? '')), 'Usuário SMTP') && $ok;
        if (array_key_exists('password', $dados) && $dados['password'] !== null && $dados['password'] !== '') {
            $ok = $this->set('email_password', (string) $dados['password'], 'Senha SMTP') && $ok;
        }
        $ok = $this->set(
            'email_from_address',
            trim((string) ($dados['from_address'] ?? '')),
            'Remetente (From)'
        ) && $ok;
        $fromName = trim((string) ($dados['from_name'] ?? ''));
        $ok = $this->set(
            'email_from_name',
            $fromName !== '' ? $fromName : 'EduCuidar',
            'Nome do remetente'
        ) && $ok;

        if (array_key_exists('eventos_desde', $dados)) {
            $desde = trim((string) $dados['eventos_desde']);
            if ($desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
                $ok = $this->setEmailEventosDesde($desde) && $ok;
            }
        } elseif ($this->get('email_eventos_desde') === null || $this->get('email_eventos_desde') === '') {
            $ok = $this->setEmailEventosDesde(date('Y-m-d')) && $ok;
        }

        return $ok;
    }

    /** Data inicial (Y-m-d): eventos anteriores não geram e-mail. */
    public function getEmailEventosDesde() {
        $valor = trim((string) ($this->get('email_eventos_desde') ?: ''));
        if ($valor !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return $valor . ' 00:00:00';
        }
        // Padrão: hoje (não envia histórico atrasado)
        return date('Y-m-d') . ' 00:00:00';
    }

    public function setEmailEventosDesde($data_ymd) {
        $data_ymd = trim((string) $data_ymd);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_ymd)) {
            $data_ymd = date('Y-m-d');
        }
        return $this->set(
            'email_eventos_desde',
            $data_ymd,
            'Data inicial para e-mails de eventos (não envia registros anteriores)'
        );
    }
}
?>

