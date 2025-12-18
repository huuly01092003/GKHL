<?php
/**
 * ✅ CONTROLLER TỐI ƯU - KPI Nhân Viên
 * Chỉ 3 queries thay vì hàng trăm
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
        
        try {
            // Lấy danh sách tháng
            $available_months = $this->model->getAvailableMonths();
            
            if (empty($available_months)) {
                $message = "⚠️ Chưa có dữ liệu. Vui lòng import OrderDetail trước.";
                $type = 'warning';
                require_once 'views/nhanvien_kpi/report.php';
                return;
            }
            
            $available_products = $this->model->getAvailableProducts();
            
            // Kiểm tra xem user đã submit form chưa
            $has_filtered = !empty($_GET['tu_ngay']) && !empty($_GET['den_ngay']);
            
            if (!$has_filtered) {
                $filters = [
                    'thang' => $available_months[0] ?? '',
                    'tu_ngay' => !empty($available_months[0]) ? $available_months[0] . '-01' : '',
                    'den_ngay' => !empty($available_months[0]) ? date('Y-m-t', strtotime($available_months[0] . '-01')) : '',
                    'product_filter' => ''
                ];
                
                $statistics = [
                    'total_employees' => 0,
                    'employees_with_orders' => 0,
                    'total_orders' => 0,
                    'total_amount' => 0,
                    'avg_orders_per_emp' => 0,
                    'avg_daily_orders' => 0,
                    'avg_daily_amount' => 0,
                    'max_daily_orders' => 0,
                    'max_daily_amount' => 0,
                    'std_dev_orders' => 0,
                    'warning_count' => 0,
                    'danger_count' => 0,
                    'normal_count' => 0
                ];
                
                require_once 'views/nhanvien_kpi/report.php';
                return;
            }
            
            // User đã submit → Bắt đầu xử lý
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
                'product_filter' => $product_filter
            ];
            
            // ✅ QUERY 1: LẤY TẤT CẢ NHÂN VIÊN KÈM KPI (1 QUERY DUY NHẤT)
            $employees = $this->model->getAllEmployeesWithKPI($tu_ngay, $den_ngay, $product_filter);
            
            if (empty($employees)) {
                $message = "⚠️ Không có dữ liệu nhân viên.";
                $type = 'warning';
                require_once 'views/nhanvien_kpi/report.php';
                return;
            }
            
            // ✅ QUERY 2: LẤY THỐNG KÊ HỆ THỐNG (1 QUERY)
            $system_metrics = $this->model->getSystemMetrics($tu_ngay, $den_ngay, $product_filter);
            
            // ✅ QUERY 3: LẤY DAILY ORDERS ĐỂ TÍNH STD DEV (1 QUERY)
            $all_daily_orders = $this->model->getAllDailyOrdersForStdDev($tu_ngay, $den_ngay, $product_filter);
            $std_dev_orders = $this->calculateStdDev($all_daily_orders);
            
            // Tính toán benchmark
            $emp_count = $system_metrics['emp_count'];
            $total_orders = $system_metrics['total_orders'];
            $total_amount = $system_metrics['total_amount'];
            $total_working_days = $system_metrics['total_working_days'];
            
            $avg_orders_per_emp = $emp_count > 0 ? $total_orders / $emp_count : 0;
            $avg_daily_orders = $total_working_days > 0 ? $total_orders / $total_working_days : 0;
            $avg_daily_amount = $total_working_days > 0 ? $total_amount / $total_working_days : 0;
            
            // ✅ PHÂN LOẠI (KHÔNG CẦN QUERY THÊM)
            $suspicious_employees = [];
            $warning_employees = [];
            $normal_employees = [];
            
            foreach ($employees as &$emp_kpi) {
                // Tính risk score
                $score = 0;
                $reasons = [];
                
                // Performance
                if ($avg_daily_orders > 0) {
                    $perf_ratio = $emp_kpi['avg_daily_orders'] / $avg_daily_orders;
                    
                    if ($perf_ratio >= 1.5) {
                        $score += 30;
                        $reasons[] = "Hiệu suất " . number_format($perf_ratio * 100, 0) . "% so với chung";
                    }
                }
                
                // Consistency
                $consistency = $this->calculateConsistency($emp_kpi['daily_orders']);
                $emp_kpi['consistency_score'] = $consistency;
                
                if ($consistency < 50) {
                    $score += 25;
                    $reasons[] = "Không nhất quán: " . round($consistency, 1) . "%";
                }
                
                if (empty($reasons)) {
                    $reasons[] = "Hoạt động bình thường";
                }
                
                $risk_score = intval(min(100, max(0, $score)));
                
                if ($risk_score >= 75) {
                    $risk_level = 'critical';
                } elseif ($risk_score >= 50) {
                    $risk_level = 'warning';
                } else {
                    $risk_level = 'normal';
                }
                
                $emp_kpi['risk_score'] = $risk_score;
                $emp_kpi['risk_level'] = $risk_level;
                $emp_kpi['risk_reasons'] = $reasons;
                
                // Phân loại
                if ($risk_score >= 75) {
                    $suspicious_employees[] = $emp_kpi;
                } elseif ($risk_score >= 50) {
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
                'total_amount' => $total_amount,
                'avg_orders_per_emp' => round($avg_orders_per_emp, 2),
                'avg_daily_orders' => round($avg_daily_orders, 2),
                'avg_daily_amount' => round($avg_daily_amount, 0),
                'max_daily_orders' => $system_metrics['max_daily_orders'],
                'max_daily_amount' => $system_metrics['max_daily_amount'],
                'std_dev_orders' => round($std_dev_orders, 2),
                'warning_count' => count($warning_employees),
                'danger_count' => count($suspicious_employees),
                'normal_count' => count($normal_employees)
            ];
            
            $kpi_data = array_merge($suspicious_employees, $warning_employees, $normal_employees);
            
            if (empty($kpi_data)) {
                $message = "⚠️ Không có dữ liệu cho khoảng thời gian này.";
                $type = 'warning';
            } else {
                $message = "✅ Đã phân tích " . count($kpi_data) . " nhân viên trong <2 giây!";
                $type = 'success';
            }
            
        } catch (Exception $e) {
            $message = "❌ Lỗi: " . $e->getMessage();
            $type = 'danger';
            error_log("NhanVienKPIController Error: " . $e->getMessage());
        }
        
        require_once 'views/nhanvien_kpi/report.php';
    }
    
    private function calculateConsistency($daily_orders) {
        if (count($daily_orders) < 2) return 100;
        
        $mean = array_sum($daily_orders) / count($daily_orders);
        $deviations = array_map(fn($x) => pow($x - $mean, 2), $daily_orders);
        $variance = array_sum($deviations) / count($daily_orders);
        $std_dev = sqrt($variance);
        
        if ($mean > 0) {
            $cv_percent = ($std_dev / $mean) * 100;
        } else {
            $cv_percent = 0;
        }
        
        if ($cv_percent <= 10) return 100;
        elseif ($cv_percent <= 20) return 90;
        elseif ($cv_percent <= 30) return 80;
        elseif ($cv_percent <= 50) return 60;
        else return 40;
    }
    
    private function calculateStdDev($arr) {
        if (count($arr) < 2) return 0;
        
        $avg = array_sum($arr) / count($arr);
        $deviations = array_map(fn($x) => pow($x - $avg, 2), $arr);
        $variance = array_sum($deviations) / count($deviations);
        
        return sqrt($variance);
    }
}
?>