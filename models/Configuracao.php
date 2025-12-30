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
        return $this->get('ldap_host') ?: 'ldap://ldap.ifrs.edu.br';
    }

    public function setLdapHost($host) {
        return $this->set('ldap_host', $host, 'Endereço do servidor LDAP');
    }

    public function getLdapPort() {
        return $this->get('ldap_port') ?: '389';
    }

    public function setLdapPort($port) {
        return $this->set('ldap_port', (string)$port, 'Porta do servidor LDAP');
    }

    public function getLdapBaseDn() {
        return $this->get('ldap_base_dn') ?: 'ou=users,dc=ifrs,dc=edu,dc=br';
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
        return $this->get('ldap_user_attribute') ?: 'uid';
    }

    public function setLdapUserAttribute($attribute) {
        return $this->set('ldap_user_attribute', $attribute, 'Atributo LDAP usado para buscar usuários (ex: uid, sAMAccountName, userPrincipalName)');
    }
}
?>

