<?php
session_start();
require_once 'controllers/NhanVienKPIController.php';

$controller = new NhanVienKPIController();
$controller->showKPIReport();
?>