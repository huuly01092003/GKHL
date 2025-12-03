<?php
// export.php
session_start();
require_once 'controllers/ExportController.php';

$controller = new ExportController();
$action = $_GET['action'] ?? 'form';

if ($action === 'download') {
    // Export CSV
    $controller->exportCSV();
} else {
    // Hiển thị form export
    require_once 'views/export_form.php';
}
?>