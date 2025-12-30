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

