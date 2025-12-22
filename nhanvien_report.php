<?php
session_start();
require_once 'controllers/NhanVienReportController.php';
require_once 'views/components/navbar.php';

$controller = new NhanVienReportController();
$controller->showReport();
?>