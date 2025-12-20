<?php
/**
 * ✅ CONTROLLER TỐI ƯU V2 - KPI Nhân Viên với Ngưỡng N
 */

require_once 'models/NhanVienKPIModel.php';

class NhanVienKPIController {
    private $model;

    public function __construct() {
        $this->model = new NhanVienKPIModel();
    }

    public function showKPIReport() {
        $message = '';
        $type = '';
        $kpi_data = [];
        $statistics = [];
        $filters = [];
        $available_months = [];
        $available_products = [];
        $has_filtered = false;
        
        // ✅ NGƯỠNG N MẶC ĐỊNH
        $threshold_n = isset($_GET['threshold_n']) ? intval($_GET['threshold_n']) : 5;
        
        try {
            $available_months = $this->model->getAvailableMonths();
            
            if (empty($available_months)) {
                $message = "⚠️ Chưa có dữ liệu. Vui lòng import OrderDetail trước.";
                $type = 'warning';
                require_once 'views/nhanvien_kpi/report.php';
                return;
            }
            
            $available_products = $this->model->getAvailableProducts();
            
            $has_filtered = !empty($_GET['tu_ngay']) && !empty($_GET['den_ngay']);
            
            if (!$has_filtered) {
                $filters = [
                    'thang' => $available_months[0] ?? '',
                    'tu_ngay' => !empty($available_months[0]) ? $available_months[0] . '-01' : '',
                    'den_ngay' => !empty($available_months[0]) ? date('Y-m-t', strtotime($available_months[0] . '-01')) : '',
                    'product_filter' => '',
                    'threshold_n' => $threshold_n
                ];
                
                $statistics = [
                    'total_employees' => 0,
                    'employees_with_orders' => 0,
                    'total_orders' => 0,
                    'total_customers' => 0,
                    'total_amount' => 0,
                    'avg_orders_per_emp' => 0,
                    'avg_customers_per_emp' => 0,
                    'warning_count' => 0,
                    'danger_count' => 0,
                    'normal_count' => 0
                ];
                
                require_once 'views/nhanvien_kpi/report.php';
                return;
            }
            
            // User đã submit
            $thang = !empty($_GET['thang']) ? $_GET['thang'] : $available_months[0];
            if (!in_array($thang, $available_months)) $thang = $available_months[0];
            
            $tu_ngay = trim($_GET['tu_ngay']);
            $den_ngay = trim($_GET['den_ngay']);
            
            if (strtotime($tu_ngay) > strtotime($den_ngay)) {
                list($tu_ngay, $den_ngay) = [$den_ngay, $tu_ngay];
            }
            
            $product_filter = !empty($_GET['product_filter']) ? trim($_GET['product_filter']) : '';
            if ($product_filter === '--all--') $product_filter = '';
            if (!empty($product_filter)) $product_filter = substr($product_filter, 0, 2);
            
            $filters = [
                'thang' => $thang,
                'tu_ngay' => $tu_ngay,
                'den_ngay' => $den_ngay,
                'product_filter' => $product_filter,
                'threshold_n' => $threshold_n
            ];
            
            // ✅ LẤY DỮ LIỆU VỚI NGƯỠNG N
            $employees = $this->model->getAllEmployeesWithKPI($tu_ngay, $den_ngay, $product_filter, $threshold_n);
            
            if (empty($employees)) {
                $message = "⚠️ Không có dữ liệu nhân viên.";
                $type = 'warning';
                require_once 'views/nhanvien_kpi/report.php';
                return;
            }
            
            $system_metrics = $this->model->getSystemMetrics($tu_ngay, $den_ngay, $product_filter);
            
            // Tính toán benchmark
            $emp_count = $system_metrics['emp_count'];
            $total_orders = $system_metrics['total_orders'];
            $total_customers = $system_metrics['total_customers'];
            $total_amount = $system_metrics['total_amount'];
            
            $avg_orders_per_emp = $emp_count > 0 ? $total_orders / $emp_count : 0;
            $avg_customers_per_emp = $emp_count > 0 ? $total_customers / $emp_count : 0;
            
            // Phân loại
            $suspicious_employees = [];
            $warning_employees = [];
            $normal_employees = [];
            
            foreach ($employees as &$emp_kpi) {
                // Risk reasons
                $reasons = [];
                
                if ($emp_kpi['violation_count'] > 0) {
                    $reasons[] = "Vi phạm ngưỡng {$emp_kpi['violation_count']} ngày ({$emp_kpi['risk_analysis']['violation_rate']}% thời gian)";
                }
                
                if ($emp_kpi['risk_analysis']['max_violation'] > 0) {
                    $reasons[] = "Vượt tối đa {$emp_kpi['risk_analysis']['max_violation']} khách so với ngưỡng";
                }
                
                if ($emp_kpi['risk_analysis']['consecutive_violations'] >= 3) {
                    $reasons[] = "Vi phạm liên tục {$emp_kpi['risk_analysis']['consecutive_violations']} ngày";
                }
                
                if (empty($reasons)) {
                    $reasons[] = "Hoạt động trong ngưỡng cho phép";
                }
                
                $emp_kpi['risk_reasons'] = $reasons;
                
                // Phân loại
                if ($emp_kpi['risk_level'] === 'critical') {
                    $suspicious_employees[] = $emp_kpi;
                } elseif ($emp_kpi['risk_level'] === 'warning') {
                    $warning_employees[] = $emp_kpi;
                } else {
                    $normal_employees[] = $emp_kpi;
                }
            }
            unset($emp_kpi);
            
            // Sắp xếp
            usort($suspicious_employees, fn($a, $b) => $b['risk_score'] <=> $a['risk_score']);
            usort($warning_employees, fn($a, $b) => $b['risk_score'] <=> $a['risk_score']);
            usort($normal_employees, fn($a, $b) => $b['risk_score'] <=> $a['risk_score']);
            
            $statistics = [
                'total_employees' => count($employees),
                'employees_with_orders' => $emp_count,
                'total_orders' => $total_orders,
                'total_customers' => $total_customers,
                'total_amount' => $total_amount,
                'avg_orders_per_emp' => round($avg_orders_per_emp, 2),
                'avg_customers_per_emp' => round($avg_customers_per_emp, 2),
                'warning_count' => count($warning_employees),
                'danger_count' => count($suspicious_employees),
                'normal_count' => count($normal_employees),
                'threshold_n' => $threshold_n
            ];
            
            $kpi_data = array_merge($suspicious_employees, $warning_employees, $normal_employees);
            
            if (empty($kpi_data)) {
                $message = "⚠️ Không có dữ liệu cho khoảng thời gian này.";
                $type = 'warning';
            } else {
                $message = "✅ Đã phân tích " . count($kpi_data) . " nhân viên với ngưỡng N={$threshold_n} khách/ngày!";
                $type = 'success';
            }
            
        } catch (Exception $e) {
            $message = "❌ Lỗi: " . $e->getMessage();
            $type = 'danger';
            error_log("NhanVienKPIController Error: " . $e->getMessage());
        }
        
        require_once 'views/nhanvien_kpi/report.php';
    }
    
    /**
     * ✅ AJAX: Lấy chi tiết khách hàng của nhân viên
     */
    public function getEmployeeCustomers() {
        header('Content-Type: application/json');
        
        $dsr_code = $_GET['dsr_code'] ?? '';
        $tu_ngay = $_GET['tu_ngay'] ?? '';
        $den_ngay = $_GET['den_ngay'] ?? '';
        $product_filter = $_GET['product_filter'] ?? '';
        
        if (empty($dsr_code) || empty($tu_ngay) || empty($den_ngay)) {
            echo json_encode(['success' => false, 'error' => 'Missing parameters']);
            return;
        }
        
        try {
            $customers = $this->model->getEmployeeCustomerDetails($dsr_code, $tu_ngay, $den_ngay, $product_filter);
            echo json_encode(['success' => true, 'data' => $customers]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}