<?php
/**
 * ✅ FILE CHÍNH - nhanvien_report.php
 */

require_once 'controllers/NhanVienReportController.php';

$controller = new NhanVienReportController();
$action = $_GET['action'] ?? 'nhanvien_report';

// ✅ Xử lý API request cho đơn hàng
if ($action === 'get_employee_orders') {
    $controller->getEmployeeOrders();
    exit;
}

// ✅ Hiển thị báo cáo
$controller->showReport();
?>