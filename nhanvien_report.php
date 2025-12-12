<?php
session_start();
require_once 'controllers/NhanVienReportController.php';

$controller = new NhanVienReportController();
$controller->showReport();
?>