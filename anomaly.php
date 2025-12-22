<?php
session_start();
require_once 'controllers/AnomalyController.php';
require_once 'views/components/navbar.php';

$controller = new AnomalyController();
$action = $_GET['action'] ?? 'index';

if ($action === 'export') {
    $controller->exportCSV();
} else {
    $controller->index();
}
?>