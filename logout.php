<?php
require_once 'config/init.php';

$user = new User((new Database())->getConnection());
$user->logout();

header('Location: login.php');
exit;
?>

