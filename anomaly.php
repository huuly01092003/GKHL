<?php
session_start();
require_once 'controllers/AnomalyController.php';

$controller = new AnomalyController();
$action = $_GET['action'] ?? 'index';

if ($action === 'export') {
    $controller->exportCSV();
} else {
    $controller->index();
}
?>