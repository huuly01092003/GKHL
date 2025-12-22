<?php
session_start();
require_once 'controllers/NhanVienKPIController.php';
require_once 'views/components/navbar.php';

$controller = new NhanVienKPIController();

// ✅ HỖ TRỢ AJAX REQUEST
$action = $_GET['action'] ?? 'report';

if ($action === 'get_customers') {
    // AJAX call để lấy danh sách khách hàng
    $controller->getEmployeeCustomers();
} else {
    // Hiển thị trang báo cáo chính
    $controller->showKPIReport();
}