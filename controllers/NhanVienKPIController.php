<?php
/**
 * ✅ CONTROLLER: KPI PHÁT HIỆN BẤT THƯỜNG NHÂN VIÊN
 * Tích hợp từ KPIReportController.php
 * Điều chỉnh cho database GKHL
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
        
        try {
            // ✅ Lấy danh sách tháng
            $available_months = $this->model->getAvailableMonths();
            
            if (empty($available_months)) {
                $message = "⚠️ Chưa có dữ liệu. Vui lòng import OrderDetail trước.";
                $type = 'warning';
                require_once 'views/nhanvien_kpi/report.php';
                return;
            }
            
            $available_products = $this->model->getAvailableProducts();
            
            // ✅ Lấy filters
            $thang = !empty($_GET['thang']) ? $_GET['thang'] : $available_months[0];
            if (!in_array($thang, $available_months)) $thang = $available_months[0];
            
            $tu_ngay = !empty($_GET['tu_ngay']) ? $_GET['tu_ngay'] : $thang . '-01';
            $den_ngay = !empty($_GET['den_ngay']) ? $_GET['den_ngay'] : date('Y-m-t', strtotime($thang . '-01'));
            
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
            
            // ✅ Lấy danh sách nhân viên
            $employees = $this->model->getAllEmployees();
            
            if (empty($employees)) {
                $message = "⚠️ Không có dữ liệu nhân viên.";
                $type = 'warning';
                require_once 'views/nhanvien_kpi/report.php';
                return;
            }
            
            // ✅ Tính toán KPI
            $employee_kpi_list = [];
            $all_risk_scores = [];
            $system_metrics = [
                'total_orders' => 0,
                'total_amount' => 0,
                'all_daily_orders' => [],
                'all_daily_amounts' => [],
                'employee_count' => 0,
                'max_daily_orders' => 0,
                'max_daily_amount' => 0,
                'total_working_days' => 0
            ];
            
            foreach ($employees as $emp) {
                $kpi = $this->calculateEmployeeKPI($emp, $tu_ngay, $den_ngay, $product_filter);
                
                if ($kpi['total_orders'] > 0) {
                    $employee_kpi_list[] = $kpi;
                    $system_metrics['total_orders'] += $kpi['total_orders'];
                    $system_metrics['total_amount'] += $kpi['total_amount'];
                    $system_metrics['total_working_days'] += $kpi['working_days'];
                    $system_metrics['employee_count']++;
                    $system_metrics['max_daily_orders'] = max($system_metrics['max_daily_orders'], $kpi['max_day_orders']);
                    $system_metrics['max_daily_amount'] = max($system_metrics['max_daily_amount'], $kpi['max_day_amount']);
                    
                    $system_metrics['all_daily_orders'] = array_merge($system_metrics['all_daily_orders'], $kpi['daily_orders'] ?? []);
                    $system_metrics['all_daily_amounts'] = array_merge($system_metrics['all_daily_amounts'], $kpi['daily_amounts'] ?? []);
                }
            }
            
            // ✅ Tính benchmark
            if ($system_metrics['employee_count'] > 0) {
                $system_metrics['avg_orders_per_emp'] = $system_metrics['total_orders'] / $system_metrics['employee_count'];
                $system_metrics['avg_daily_orders'] = $system_metrics['total_orders'] / max(1, $system_metrics['total_working_days']);
                $system_metrics['avg_daily_amount'] = $system_metrics['total_amount'] / max(1, $system_metrics['total_working_days']);
                $system_metrics['std_dev_orders'] = $this->calculateStdDev($system_metrics['all_daily_orders']);
                $system_metrics['std_dev_amount'] = $this->calculateStdDev($system_metrics['all_daily_amounts']);
            }
            
            // ✅ Phân loại
            $suspicious_employees = [];
            $warning_employees = [];
            $normal_employees = [];
            
            foreach ($employee_kpi_list as &$emp_kpi) {
                $emp_kpi = $this->calculateRiskScore($emp_kpi, $system_metrics);
                $all_risk_scores[] = $emp_kpi['risk_score'];
                
                if ($emp_kpi['risk_score'] >= 75) $suspicious_employees[] = $emp_kpi;
                elseif ($emp_kpi['risk_score'] >= 50) $warning_employees[] = $emp_kpi;
                else $normal_employees[] = $emp_kpi;
            }
            unset($emp_kpi);
            
            // Sắp xếp
            usort($suspicious_employees, fn($a, $b) => $b['risk_score'] <=> $a['risk_score']);
            usort($warning_employees, fn($a, $b) => $b['risk_score'] <=> $a['risk_score']);
            usort($normal_employees, fn($a, $b) => $b['risk_score'] <=> $a['risk_score']);
            
            $statistics = [
                'total_employees' => count($employees),
                'employees_with_orders' => $system_metrics['employee_count'],
                'total_orders' => $system_metrics['total_orders'],
                'total_amount' => $system_metrics['total_amount'],
                'avg_orders_per_emp' => round($system_metrics['avg_orders_per_emp'] ?? 0, 2),
                'avg_daily_orders' => round($system_metrics['avg_daily_orders'] ?? 0, 2),
                'avg_daily_amount' => round($system_metrics['avg_daily_amount'] ?? 0, 0),
                'max_daily_orders' => $system_metrics['max_daily_orders'],
                'max_daily_amount' => $system_metrics['max_daily_amount'],
                'std_dev_orders' => round($system_metrics['std_dev_orders'] ?? 0, 2),
                'warning_count' => count($warning_employees),
                'danger_count' => count($suspicious_employees),
                'normal_count' => count($normal_employees)
            ];
            
            $kpi_data = array_merge($suspicious_employees, $warning_employees, $normal_employees);
            
            if (empty($kpi_data)) {
                $message = "⚠️ Không có dữ liệu cho khoảng thời gian này.";
                $type = 'warning';
            }
        } catch (Exception $e) {
            $message = "❌ Lỗi: " . $e->getMessage();
            $type = 'danger';
        }
        
        require_once 'views/nhanvien_kpi/report.php';
    }
    
    /**
     * ✅ Tính KPI cho nhân viên
     */
    private function calculateEmployeeKPI($emp, $tu_ngay, $den_ngay, $product_filter = '') {
        $daily_data = $this->model->getEmployeeDailyKPI($emp['DSRCode'], $tu_ngay, $den_ngay, $product_filter);
        
        $total_orders = 0;
        $total_amount = 0;
        $max_day_orders = 0;
        $max_day_amount = 0;
        $min_day_orders = PHP_INT_MAX;
        $min_day_amount = PHP_INT_MAX;
        $working_days = 0;
        $daily_orders = [];
        $daily_amounts = [];
        
        if (!empty($daily_data)) {
            $order_counts = array_column($daily_data, 'order_count');
            $amounts = array_column($daily_data, 'total_amount');
            
            $total_orders = array_sum($order_counts);
            $total_amount = array_sum($amounts);
            $max_day_orders = max($order_counts);
            $max_day_amount = max($amounts);
            $min_day_orders = min($order_counts);
            $min_day_amount = min($amounts);
            $working_days = count($daily_data);
            
            $daily_orders = $order_counts;
            $daily_amounts = $amounts;
        }
        
        if ($min_day_orders === PHP_INT_MAX) $min_day_orders = 0;
        if ($min_day_amount === PHP_INT_MAX) $min_day_amount = 0;
        
        $avg_daily_orders = $working_days > 0 ? $total_orders / $working_days : 0;
        
        return [
            'ma_nv' => $emp['DSRCode'],
            'ten_nv' => $emp['ten_nv'] ?? '',
            'tinh' => $emp['DSRTypeProvince'] ?? '',
            'total_orders' => $total_orders,
            'total_amount' => $total_amount,
            'avg_daily_orders' => round($avg_daily_orders, 2),
            'avg_daily_amount' => round($total_amount / max(1, $working_days), 0),
            'max_day_orders' => $max_day_orders,
            'max_day_amount' => $max_day_amount,
            'min_day_orders' => $min_day_orders,
            'min_day_amount' => $min_day_amount,
            'working_days' => $working_days,
            'consistency_score' => $this->calculateConsistency($daily_orders),
            'daily_orders' => $daily_orders,
            'daily_amounts' => $daily_amounts
        ];
    }
    
    /**
     * ✅ Tính Risk Score đơn giản
     */
    private function calculateRiskScore($emp_kpi, $system_metrics) {
        $score = 0;
        $reasons = [];
        
        // Performance
        if (isset($system_metrics['avg_daily_orders']) && $system_metrics['avg_daily_orders'] > 0) {
            $perf_ratio = $emp_kpi['avg_daily_orders'] / $system_metrics['avg_daily_orders'];
            
            if ($perf_ratio >= 1.5) {
                $score += 30;
                $reasons[] = "Hiệu suất " . number_format($perf_ratio * 100, 0) . "% so với chung";
            }
        }
        
        // Consistency
        if ($emp_kpi['consistency_score'] < 50) {
            $score += 25;
            $reasons[] = "Không nhất quán: " . round($emp_kpi['consistency_score'], 1) . "%";
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
        
        return $emp_kpi;
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