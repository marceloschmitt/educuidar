<?php
require_once 'config/init.php';
require_once 'controllers/AlunosController.php';

$controller = new AlunosController();
$controller->index();
?>


