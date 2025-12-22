<?php
session_start();
require_once 'controllers/ReportController.php';
require_once 'views/components/navbar.php';

$controller = new ReportController();
$action = $_GET['action'] ?? 'index';

if ($action === 'detail') {
    $controller->detail();
} else {
    $controller->index();
}
?>