<?php
require_once __DIR__ . '/../config/init.php';

$resp = new Responsavel((new Database())->getConnection());
$resp->logout();
header('Location: login.php');
exit;
