<?php
/**
 * ✅ MODEL TỐI ƯU V2 - KPI Nhân Viên với Logic Ngưỡng N
 * Quét từng ngày để phát hiện vi phạm ngưỡng khách/ngày
 */

require_once 'config/database.php';

class NhanVienKPIModel {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * ✅ LẤY TẤT CẢ NHÂN VIÊN KÈM KPI + THÔNG TIN TỪ DSKH
     */
    public function getAllEmployeesWithKPI($tu_ngay, $den_ngay, $product_filter = '', $threshold_n = 5) {
        // Query lấy dữ liệu nhân viên + daily orders + thông tin từ DSKH
        $sql = "SELECT 
                    o.DSRCode,
                    o.DSRTypeProvince,
                    
                    -- ✅ THÔNG TIN TỪ DSKH (JOIN)
                    MAX(d.TenNVBH) as TenNVBH,
                    MAX(d.MaGSBH) as MaGSBH,
                    
                    -- Tổng đơn hàng
                    COUNT(DISTINCT o.OrderNumber) as total_orders,
                    
                    -- Tổng tiền
                    COALESCE(SUM(o.TotalNetAmount), 0) as total_amount,
                    
                    -- Số ngày hoạt động
                    COUNT(DISTINCT DATE(o.OrderDate)) as working_days,
                    
                    -- ✅ SỐ KHÁCH HÀNG DISTINCT
                    COUNT(DISTINCT o.CustCode) as total_customers,
                    
                    -- Lấy từ bảng tạm daily_stats
                    MAX(ds.max_day_orders) as max_day_orders,
                    MAX(ds.max_day_amount) as max_day_amount,
                    MIN(ds.min_day_orders) as min_day_orders,
                    MIN(ds.min_day_amount) as min_day_amount,
                    
                    -- ✅ MAX/MIN KHÁCH/NGÀY
                    MAX(ds.max_day_customers) as max_day_customers,
                    MIN(ds.min_day_customers) as min_day_customers,
                    
                    ds.daily_orders_str,
                    ds.daily_amounts_str,
                    ds.daily_customers_str,
                    ds.daily_dates_str
                    
                FROM orderdetail o
                
                -- ✅ JOIN VỚI DSKH ĐỂ LẤY TÊN NHÂN VIÊN & MÃ GSBH
                LEFT JOIN dskh d ON o.DSRCode = d.MaNVBH
                
                INNER JOIN (
                    -- Tính daily stats trong subquery riêng
                    SELECT 
                        DSRCode,
                        MAX(order_count_per_day) as max_day_orders,
                        MAX(amount_per_day) as max_day_amount,
                        MIN(order_count_per_day) as min_day_orders,
                        MIN(amount_per_day) as min_day_amount,
                        
                        -- ✅ THÊM MIN/MAX KHÁCH/NGÀY
                        MAX(customer_count_per_day) as max_day_customers,
                        MIN(customer_count_per_day) as min_day_customers,
                        
                        GROUP_CONCAT(order_count_per_day ORDER BY order_date) as daily_orders_str,
                        GROUP_CONCAT(amount_per_day ORDER BY order_date) as daily_amounts_str,
                        GROUP_CONCAT(customer_count_per_day ORDER BY order_date) as daily_customers_str,
                        GROUP_CONCAT(order_date ORDER BY order_date) as daily_dates_str
                    FROM (
                        SELECT 
                            DSRCode,
                            DATE(OrderDate) as order_date,
                            COUNT(DISTINCT OrderNumber) as order_count_per_day,
                            SUM(TotalNetAmount) as amount_per_day,
                            COUNT(DISTINCT CustCode) as customer_count_per_day
                        FROM orderdetail
                        WHERE DSRCode IS NOT NULL 
                        AND DSRCode != ''
                        AND DATE(OrderDate) >= ?
                        AND DATE(OrderDate) <= ?
                        " . (!empty($product_filter) ? "AND ProductCode LIKE ?" : "") . "
                        GROUP BY DSRCode, DATE(OrderDate)
                    ) daily_grouped
                    GROUP BY DSRCode
                ) ds ON o.DSRCode = ds.DSRCode
                
                WHERE o.DSRCode IS NOT NULL 
                AND o.DSRCode != ''
                AND DATE(o.OrderDate) >= ?
                AND DATE(o.OrderDate) <= ?
                " . (!empty($product_filter) ? "AND o.ProductCode LIKE ?" : "") . "
                GROUP BY o.DSRCode, o.DSRTypeProvince, ds.daily_orders_str, ds.daily_amounts_str, ds.daily_customers_str, ds.daily_dates_str
                HAVING total_orders > 0
                ORDER BY o.DSRCode";
        
        $params = [$tu_ngay, $den_ngay];
        if (!empty($product_filter)) {
            $params[] = $product_filter . '%';
        }
        $params[] = $tu_ngay;
        $params[] = $den_ngay;
        if (!empty($product_filter)) {
            $params[] = $product_filter . '%';
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Parse daily data và tính toán risk score theo ngưỡng N
        foreach ($results as &$row) {
            $row['daily_orders'] = !empty($row['daily_orders_str']) 
                ? array_map('intval', explode(',', $row['daily_orders_str'])) 
                : [];
            $row['daily_amounts'] = !empty($row['daily_amounts_str']) 
                ? array_map('floatval', explode(',', $row['daily_amounts_str'])) 
                : [];
            
            // ✅ PARSE SỐ KHÁCH/NGÀY
            $row['daily_customers'] = !empty($row['daily_customers_str']) 
                ? array_map('intval', explode(',', $row['daily_customers_str'])) 
                : [];
            
            $row['daily_dates'] = !empty($row['daily_dates_str']) 
                ? explode(',', $row['daily_dates_str']) 
                : [];
            
            // Tính avg
            $row['avg_daily_orders'] = $row['working_days'] > 0 
                ? round($row['total_orders'] / $row['working_days'], 2) 
                : 0;
            $row['avg_daily_amount'] = $row['working_days'] > 0 
                ? round($row['total_amount'] / $row['working_days'], 0) 
                : 0;
            
            // ✅ TB KHÁCH/NGÀY
            $row['avg_daily_customers'] = $row['working_days'] > 0 
                ? round($row['total_customers'] / $row['working_days'], 2) 
                : 0;
            
            // ✅ TÍNH RISK SCORE DỰA TRÊN NGƯỠNG N
            $row['risk_analysis'] = $this->analyzeRiskByThreshold($row['daily_customers'], $threshold_n, $row['daily_dates']);
            $row['risk_score'] = $row['risk_analysis']['risk_score'];
            $row['risk_level'] = $row['risk_analysis']['risk_level'];
            $row['violation_days'] = $row['risk_analysis']['violation_days'];
            $row['violation_count'] = $row['risk_analysis']['violation_count'];
            
            // Tên nhân viên
            $row['ten_nv'] = !empty($row['TenNVBH']) ? $row['TenNVBH'] : 'NV_' . $row['DSRCode'];
            
            unset($row['daily_orders_str']);
            unset($row['daily_amounts_str']);
            unset($row['daily_customers_str']);
            unset($row['daily_dates_str']);
        }
        
        return $results;
    }

    /**
     * ✅ HÀM MỚI: PHÂN TÍCH RISK DỰA TRÊN NGƯỠNG N
     * Quét từng ngày để tìm vi phạm
     */
    private function analyzeRiskByThreshold($daily_customers, $threshold_n, $daily_dates = []) {
        $violation_days = [];
        $violation_count = 0;
        $max_violation = 0;
        
        foreach ($daily_customers as $idx => $customers_per_day) {
            if ($customers_per_day > $threshold_n) {
                $violation_count++;
                $violation_amount = $customers_per_day - $threshold_n;
                $max_violation = max($max_violation, $violation_amount);
                
                $violation_days[] = [
                    'date' => $daily_dates[$idx] ?? "Ngày $idx",
                    'customers' => $customers_per_day,
                    'threshold' => $threshold_n,
                    'violation' => $violation_amount,
                    'ratio' => round(($customers_per_day / $threshold_n) * 100, 1)
                ];
            }
        }
        
        // Tính risk score dựa trên số ngày vi phạm và mức độ vi phạm
        $total_days = count($daily_customers);
        $violation_rate = $total_days > 0 ? ($violation_count / $total_days) * 100 : 0;
        
        // Logic tính điểm
        $risk_score = 0;
        
        // Dựa trên tỷ lệ ngày vi phạm
        if ($violation_rate >= 80) {
            $risk_score += 40;
        } elseif ($violation_rate >= 60) {
            $risk_score += 30;
        } elseif ($violation_rate >= 40) {
            $risk_score += 20;
        } elseif ($violation_rate >= 20) {
            $risk_score += 10;
        }
        
        // Dựa trên mức độ vi phạm cao nhất
        if ($max_violation >= $threshold_n * 3) {
            $risk_score += 40;
        } elseif ($max_violation >= $threshold_n * 2) {
            $risk_score += 30;
        } elseif ($max_violation >= $threshold_n * 1.5) {
            $risk_score += 20;
        } elseif ($max_violation >= $threshold_n) {
            $risk_score += 10;
        }
        
        // Dựa trên số ngày vi phạm liên tiếp
        $consecutive = $this->countConsecutiveViolations($daily_customers, $threshold_n);
        if ($consecutive >= 3) {
            $risk_score += 20;
        } elseif ($consecutive >= 1) {
            $risk_score += 10;
        }
        
        // Xác định risk level
        if ($risk_score >= 75) {
            $risk_level = 'critical';
        } elseif ($risk_score >= 50) {
            $risk_level = 'warning';
        } else {
            $risk_level = 'normal';
        }
        
        return [
            'risk_score' => min(100, $risk_score),
            'risk_level' => $risk_level,
            'violation_count' => $violation_count,
            'total_days' => $total_days,
            'violation_rate' => round($violation_rate, 1),
            'max_violation' => $max_violation,
            'consecutive_violations' => $consecutive,
            'violation_days' => $violation_days
        ];
    }

    /**
     * Đếm số ngày vi phạm liên tiếp
     */
    private function countConsecutiveViolations($daily_customers, $threshold_n) {
        $max_consecutive = 0;
        $current_consecutive = 0;
        
        foreach ($daily_customers as $customers) {
            if ($customers > $threshold_n) {
                $current_consecutive++;
                $max_consecutive = max($max_consecutive, $current_consecutive);
            } else {
                $current_consecutive = 0;
            }
        }
        
        return $max_consecutive;
    }

    /**
     * ✅ LẤY CHI TIẾT KHÁCH HÀNG CỦA NHÂN VIÊN
     */
    public function getEmployeeCustomerDetails($dsr_code, $tu_ngay, $den_ngay, $product_filter = '') {
        $sql = "SELECT 
                    o.CustCode,
                    d.TenKH as customer_name,
                    d.DiaChi as customer_address,
                    d.Tinh as customer_province,
                    COUNT(DISTINCT o.OrderNumber) as order_count,
                    SUM(o.TotalNetAmount) as total_amount,
                    GROUP_CONCAT(DISTINCT DATE(o.OrderDate) ORDER BY o.OrderDate) as order_dates,
                    GROUP_CONCAT(DISTINCT o.OrderNumber ORDER BY o.OrderDate) as order_numbers,
                    GROUP_CONCAT(o.TotalNetAmount ORDER BY o.OrderDate) as order_amounts
                FROM orderdetail o
                LEFT JOIN dskh d ON o.CustCode = d.MaKH
                WHERE o.DSRCode = ?
                AND DATE(o.OrderDate) >= ?
                AND DATE(o.OrderDate) <= ?
                " . (!empty($product_filter) ? "AND o.ProductCode LIKE ?" : "") . "
                GROUP BY o.CustCode, d.TenKH, d.DiaChi, d.Tinh
                ORDER BY total_amount DESC";
        
        $params = [$dsr_code, $tu_ngay, $den_ngay];
        if (!empty($product_filter)) {
            $params[] = $product_filter . '%';
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Parse order details
        foreach ($results as &$row) {
            $dates = explode(',', $row['order_dates']);
            $numbers = explode(',', $row['order_numbers']);
            $amounts = explode(',', $row['order_amounts']);
            
            $row['orders'] = [];
            for ($i = 0; $i < count($dates); $i++) {
                $row['orders'][] = [
                    'date' => $dates[$i],
                    'order_number' => $numbers[$i],
                    'amount' => floatval($amounts[$i])
                ];
            }
            
            unset($row['order_dates']);
            unset($row['order_numbers']);
            unset($row['order_amounts']);
        }
        
        return $results;
    }

    /**
     * ✅ LẤY THỐNG KÊ HỆ THỐNG
     */
    public function getSystemMetrics($tu_ngay, $den_ngay, $product_filter = '') {
        $sql = "SELECT 
                    COUNT(DISTINCT o.DSRCode) as emp_count,
                    COUNT(DISTINCT o.OrderNumber) as total_orders,
                    COUNT(DISTINCT o.CustCode) as total_customers,
                    COALESCE(SUM(o.TotalNetAmount), 0) as total_amount,
                    COUNT(DISTINCT DATE(o.OrderDate)) as total_working_days,
                    MAX(daily_stats.max_orders) as max_daily_orders,
                    MAX(daily_stats.max_amount) as max_daily_amount
                FROM orderdetail o
                LEFT JOIN (
                    SELECT 
                        DSRCode,
                        DATE(OrderDate) as order_date,
                        COUNT(DISTINCT OrderNumber) as max_orders,
                        SUM(TotalNetAmount) as max_amount
                    FROM orderdetail
                    WHERE DSRCode IS NOT NULL 
                    AND DSRCode != ''
                    AND DATE(OrderDate) >= ?
                    AND DATE(OrderDate) <= ?
                    " . (!empty($product_filter) ? "AND ProductCode LIKE ?" : "") . "
                    GROUP BY DSRCode, DATE(OrderDate)
                ) daily_stats ON o.DSRCode = daily_stats.DSRCode
                WHERE o.DSRCode IS NOT NULL 
                AND o.DSRCode != ''
                AND DATE(o.OrderDate) >= ?
                AND DATE(o.OrderDate) <= ?
                " . (!empty($product_filter) ? "AND o.ProductCode LIKE ?" : "") . "";
        
        $params = [$tu_ngay, $den_ngay];
        if (!empty($product_filter)) {
            $params[] = $product_filter . '%';
        }
        $params[] = $tu_ngay;
        $params[] = $den_ngay;
        if (!empty($product_filter)) {
            $params[] = $product_filter . '%';
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy danh sách tháng có sẵn
     */
    public function getAvailableMonths() {
        $sql = "SELECT DISTINCT CONCAT(RptYear, '-', LPAD(RptMonth, 2, '0')) as thang
                FROM orderdetail
                WHERE RptYear IS NOT NULL AND RptMonth IS NOT NULL
                ORDER BY RptYear DESC, RptMonth DESC LIMIT 24";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Lấy danh sách nhóm sản phẩm
     */
    public function getAvailableProducts() {
        $sql = "SELECT DISTINCT SUBSTRING(ProductCode, 1, 2) as product_prefix
                FROM orderdetail 
                WHERE ProductCode IS NOT NULL AND ProductCode != ''
                ORDER BY product_prefix
                LIMIT 50";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
}