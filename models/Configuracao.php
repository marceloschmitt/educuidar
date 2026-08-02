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

    // System installation status
    public function isSistemaInstalado() {
        $valor = $this->get('sistema_instalado');
        return $valor === '1' || $valor === 1;
    }

    public function setSistemaInstalado($instalado = true) {
        return $this->set('sistema_instalado', $instalado ? '1' : '0', 'Indica se o sistema foi instalado e configurado');
    }
}
?>

