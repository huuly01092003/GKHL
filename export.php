<?php
session_start();
require_once 'controllers/ExportController.php';
require_once 'views/components/navbar.php';

$controller = new ExportController();
$action = $_GET['action'] ?? 'form';

if ($action === 'download') {
    // Export CSV với multi-select support
    $controller->exportCSV();
} else {
    // Hiển thị form export (nếu cần)
    require_once 'views/export_form.php';
}
?>