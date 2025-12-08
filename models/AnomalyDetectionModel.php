<?php
require_once 'config/database.php';

class AnomalyDetectionModel {
    private $conn;
    
    // Trọng số cho các loại bất thường (tổng = 100)
    private const WEIGHTS = [
        'sudden_spike' => 15,           
        'frequency_spike' => 12,        
        'large_order' => 10,            
        'product_hoarding' => 8,        
        'end_month_rush' => 7,          
        'mid_month_rush' => 6,          // ✅ MỚI: Mua giữa tháng
        'unusual_time' => 5,            
        'unusual_product' => 8,         
        'high_value_order' => 10,       
        'return_after_long' => 6,       
        'stop_after_target' => 9,       
        'sudden_drop' => 7,             
        'gkhl_no_orders' => 8,          
        'gkhl_first_month_spike' => 10, 
        'gkhl_checkpoint_spike' => 8    
    ];

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * ✅ CẬP NHẬT: Thêm filters cho tỉnh và GKHL
     */
    public function calculateAnomalyScores($years, $months, $filters = []) {
        $customers = $this->getCustomersWithHistory($years, $months, $filters);
        $results = [];
        
        foreach ($customers as $customer) {
            $custCode = $customer['ma_khach_hang'];
            
            $scores = [
                'sudden_spike' => $this->checkSuddenSpike($custCode, $years, $months),
                'frequency_spike' => $this->checkFrequencySpike($custCode, $years, $months),
                'large_order' => $this->checkLargeOrders($custCode, $years, $months),
                'product_hoarding' => $this->checkProductHoarding($custCode, $years, $months),
                'end_month_rush' => $this->checkEndMonthRush($custCode, $years, $months),
                'mid_month_rush' => $this->checkMidMonthRush($custCode, $years, $months), // ✅ MỚI
                'unusual_time' => $this->checkUnusualTime($custCode, $years, $months),
                'unusual_product' => $this->checkUnusualProduct($custCode, $years, $months),
                'high_value_order' => $this->checkHighValueOrder($custCode, $years, $months),
                'return_after_long' => $this->checkReturnAfterLong($custCode, $years, $months),
                'stop_after_target' => $this->checkStopAfterTarget($custCode, $years, $months),
                'sudden_drop' => $this->checkSuddenDrop($custCode, $years, $months),
                'gkhl_no_orders' => $this->checkGkhlNoOrders($custCode, $years, $months),
                'gkhl_first_month_spike' => $this->checkGkhlFirstMonthSpike($custCode, $years, $months),
                'gkhl_checkpoint_spike' => $this->checkGkhlCheckpointSpike($custCode, $years, $months)
            ];
            
            $totalScore = 0;
            $details = [];
            
            foreach ($scores as $type => $score) {
                if ($score > 0) {
                    $weightedScore = ($score / 100) * self::WEIGHTS[$type];
                    $totalScore += $weightedScore;
                    $details[$type] = [
                        'raw_score' => $score,
                        'weighted_score' => $weightedScore,
                        'description' => $this->getAnomalyDescription($type, $score)
                    ];
                }
            }
            
            if ($totalScore > 0) {
                $results[$custCode] = [
                    'customer_code' => $custCode,
                    'customer_name' => $customer['ten_khach_hang'],
                    'total_score' => round($totalScore, 2),
                    'risk_level' => $this->getRiskLevel($totalScore),
                    'details' => $details,
                    'total_sales' => $customer['total_doanh_so'],
                    'total_orders' => $customer['total_orders']
                ];
            }
        }
        
        usort($results, function($a, $b) {
            return $b['total_score'] <=> $a['total_score'];
        });
        
        return $results;
    }

    // ✅ FIX: Sửa lỗi mixed parameters
    private function getSalesInPeriod($custCode, $years, $months) {
        $yearPlaceholders = implode(',', array_fill(0, count($years), '?'));
        $monthPlaceholders = implode(',', array_fill(0, count($months), '?'));
        
        $sql = "SELECT COALESCE(SUM(TotalNetAmount), 0) as total
                FROM orderdetail
                WHERE CustCode = ?
                AND RptYear IN ($yearPlaceholders)
                AND RptMonth IN ($monthPlaceholders)";
        
        $params = array_merge([$custCode], $years, $months);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    // ✅ FIX: Các helper methods khác
    private function getPreviousSales($custCode, $years, $months, $monthsBack = 3) {
        $minYear = min($years);
        $minMonth = min($months);
        
        $sql = "SELECT COALESCE(AVG(monthly_sales), 0) as avg_sales
                FROM (
                    SELECT 
                        RptYear, 
                        RptMonth,
                        SUM(TotalNetAmount) as monthly_sales
                    FROM orderdetail
                    WHERE CustCode = ?
                    AND (
                        RptYear < ?
                        OR (RptYear = ? AND RptMonth < ?)
                    )
                    GROUP BY RptYear, RptMonth
                    ORDER BY RptYear DESC, RptMonth DESC
                    LIMIT ?
                ) as prev_months";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$custCode, $minYear, $minYear, $minMonth, $monthsBack]);
        return $stmt->fetchColumn();
    }

    private function getOrderCountInPeriod($custCode, $years, $months) {
        $yearPlaceholders = implode(',', array_fill(0, count($years), '?'));
        $monthPlaceholders = implode(',', array_fill(0, count($months), '?'));
        
        $sql = "SELECT COUNT(DISTINCT OrderNumber) as total
                FROM orderdetail
                WHERE CustCode = ?
                AND RptYear IN ($yearPlaceholders)
                AND RptMonth IN ($monthPlaceholders)";
        
        $params = array_merge([$custCode], $years, $months);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function getPreviousOrderCount($custCode, $years, $months, $monthsBack = 3) {
        $minYear = min($years);
        $minMonth = min($months);
        
        $sql = "SELECT COALESCE(AVG(monthly_orders), 0) as avg_orders
                FROM (
                    SELECT 
                        RptYear, 
                        RptMonth,
                        COUNT(DISTINCT OrderNumber) as monthly_orders
                    FROM orderdetail
                    WHERE CustCode = ?
                    AND (
                        RptYear < ?
                        OR (RptYear = ? AND RptMonth < ?)
                    )
                    GROUP BY RptYear, RptMonth
                    ORDER BY RptYear DESC, RptMonth DESC
                    LIMIT ?
                ) as prev_months";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$custCode, $minYear, $minYear, $minMonth, $monthsBack]);
        return $stmt->fetchColumn();
    }

    // ✅ MỚI: Kiểm tra mua giữa tháng (ngày 10-20)
    private function checkMidMonthRush($custCode, $years, $months) {
        $yearPlaceholders = implode(',', array_fill(0, count($years), '?'));
        $monthPlaceholders = implode(',', array_fill(0, count($months), '?'));
        
        $sql = "SELECT 
                    COUNT(*) as mid_month_orders,
                    SUM(TotalNetAmount) as mid_month_amount
                FROM orderdetail
                WHERE CustCode = ?
                AND RptYear IN ($yearPlaceholders)
                AND RptMonth IN ($monthPlaceholders)
                AND DAY(OrderDate) >= 10 AND DAY(OrderDate) <= 20";
        
        $params = array_merge([$custCode], $years, $months);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $midMonthData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$midMonthData || $midMonthData['mid_month_orders'] == 0) return 0;
        
        $totalOrders = $this->getOrderCountInPeriod($custCode, $years, $months);
        
        if ($totalOrders == 0) return 0;
        
        $midMonthRatio = ($midMonthData['mid_month_orders'] / $totalOrders) * 100;
        
        if ($midMonthRatio >= 70) return 100;
        if ($midMonthRatio >= 60) return 80;
        if ($midMonthRatio >= 50) return 60;
        
        return 0;
    }

    // Các check methods khác (giữ nguyên logic, chỉ fix parameters)
    private function checkSuddenSpike($custCode, $years, $months) {
        $currentSales = $this->getSalesInPeriod($custCode, $years, $months);
        if ($currentSales == 0) return 0;
        
        $previousSales = $this->getPreviousSales($custCode, $years, $months, 3);
        if ($previousSales == 0) return 0;
        
        $increaseRate = (($currentSales - $previousSales) / $previousSales) * 100;
        
        if ($increaseRate >= 300) return 100;
        if ($increaseRate >= 250) return 85;
        if ($increaseRate >= 200) return 70;
        if ($increaseRate >= 150) return 50;
        
        return 0;
    }

    private function checkFrequencySpike($custCode, $years, $months) {
        $currentOrders = $this->getOrderCountInPeriod($custCode, $years, $months);
        if ($currentOrders < 5) return 0;
        
        $previousOrders = $this->getPreviousOrderCount($custCode, $years, $months, 3);
        if ($previousOrders == 0) return 0;
        
        $increaseRate = (($currentOrders - $previousOrders) / $previousOrders) * 100;
        
        if ($increaseRate >= 400) return 100;
        if ($increaseRate >= 300) return 80;
        if ($increaseRate >= 200) return 60;
        
        return 0;
    }

    private function checkLargeOrders($custCode, $years, $months) {
        $yearPlaceholders = implode(',', array_fill(0, count($years), '?'));
        $monthPlaceholders = implode(',', array_fill(0, count($months), '?'));
        
        $sql = "SELECT o.TotalNetAmount, o.Qty
                FROM orderdetail o
                WHERE o.CustCode = ?
                AND o.RptYear IN ($yearPlaceholders)
                AND o.RptMonth IN ($monthPlaceholders)
                ORDER BY o.TotalNetAmount DESC
                LIMIT 1";
        
        $params = array_merge([$custCode], $years, $months);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $largestOrder = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$largestOrder) return 0;
        
        $avgOrder = $this->getAverageOrderValue($custCode);
        if ($avgOrder == 0) return 0;
        
        $ratio = $largestOrder['TotalNetAmount'] / $avgOrder;
        
        if ($ratio >= 10) return 100;
        if ($ratio >= 7) return 80;
        if ($ratio >= 5) return 60;
        if ($ratio >= 3) return 40;
        
        return 0;
    }

    private function checkProductHoarding($custCode, $years, $months) {
        $yearPlaceholders = implode(',', array_fill(0, count($years), '?'));
        $monthPlaceholders = implode(',', array_fill(0, count($months), '?'));
        
        $sql = "SELECT ProductCode, SUM(Qty) as total_qty, COUNT(*) as order_count
                FROM orderdetail
                WHERE CustCode = ?
                AND RptYear IN ($yearPlaceholders)
                AND RptMonth IN ($monthPlaceholders)
                GROUP BY ProductCode
                ORDER BY total_qty DESC
                LIMIT 1";
        
        $params = array_merge([$custCode], $years, $months);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $topProduct = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$topProduct) return 0;
        
        $totalQty = $this->getTotalQuantity($custCode, $years, $months);
        if ($totalQty == 0) return 0;
        
        $concentration = ($topProduct['total_qty'] / $totalQty) * 100;
        
        if ($concentration >= 80) return 100;
        if ($concentration >= 70) return 80;
        if ($concentration >= 60) return 60;
        
        return 0;
    }

    private function checkEndMonthRush($custCode, $years, $months) {
        $yearPlaceholders = implode(',', array_fill(0, count($years), '?'));
        $monthPlaceholders = implode(',', array_fill(0, count($months), '?'));
        
        $sql = "SELECT 
                    COUNT(*) as order_count,
                    SUM(TotalNetAmount) as total_amount
                FROM orderdetail
                WHERE CustCode = ?
                AND RptYear IN ($yearPlaceholders)
                AND RptMonth IN ($monthPlaceholders)
                AND DAY(OrderDate) >= 25
                GROUP BY CustCode";
        
        $params = array_merge([$custCode], $years, $months);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $endMonthData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$endMonthData) return 0;
        
        $totalOrders = $this->getOrderCountInPeriod($custCode, $years, $months);
        if ($totalOrders == 0) return 0;
        
        $endMonthRatio = ($endMonthData['order_count'] / $totalOrders) * 100;
        
        if ($endMonthRatio >= 70) return 100;
        if ($endMonthRatio >= 60) return 80;
        if ($endMonthRatio >= 50) return 60;
        
        return 0;
    }

    private function checkUnusualTime($custCode, $years, $months) {
        $yearPlaceholders = implode(',', array_fill(0, count($years), '?'));
        $monthPlaceholders = implode(',', array_fill(0, count($months), '?'));
        
        $sql = "SELECT COUNT(*) as night_orders
                FROM orderdetail
                WHERE CustCode = ?
                AND RptYear IN ($yearPlaceholders)
                AND RptMonth IN ($monthPlaceholders)
                AND (HOUR(OrderDate) >= 22 OR HOUR(OrderDate) <= 6)";
        
        $params = array_merge([$custCode], $years, $months);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || $result['night_orders'] == 0) return 0;
        
        $totalOrders = $this->getOrderCountInPeriod($custCode, $years, $months);
        if ($totalOrders == 0) return 0;
        
        $nightRatio = ($result['night_orders'] / $totalOrders) * 100;
        
        if ($nightRatio >= 50) return 100;
        if ($nightRatio >= 30) return 70;
        if ($nightRatio >= 20) return 50;
        
        return 0;
    }

    private function checkUnusualProduct($custCode, $years, $months) {
        $usualProducts = $this->getUsualProducts($custCode);
        if (empty($usualProducts)) return 0;
        
        $yearPlaceholders = implode(',', array_fill(0, count($years), '?'));
        $monthPlaceholders = implode(',', array_fill(0, count($months), '?'));
        
        $sql = "SELECT DISTINCT ProductCode
                FROM orderdetail
                WHERE CustCode = ?
                AND RptYear IN ($yearPlaceholders)
                AND RptMonth IN ($monthPlaceholders)";
        
        $params = array_merge([$custCode], $years, $months);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $currentProducts = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($currentProducts)) return 0;
        
        $newProducts = array_diff($currentProducts, $usualProducts);
        $newRatio = (count($newProducts) / count($currentProducts)) * 100;
        
        if ($newRatio >= 70) return 100;
        if ($newRatio >= 50) return 75;
        if ($newRatio >= 30) return 50;
        
        return 0;
    }

    private function checkHighValueOrder($custCode, $years, $months) {
        return $this->checkLargeOrders($custCode, $years, $months);
    }

    private function checkReturnAfterLong($custCode, $years, $months) {
        $minYear = min($years);
        $minMonth = min($months);
        
        $sql = "SELECT MAX(OrderDate) as last_order
                FROM orderdetail
                WHERE CustCode = ?
                AND (RptYear < ? OR (RptYear = ? AND RptMonth < ?))";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$custCode, $minYear, $minYear, $minMonth]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || !$result['last_order']) return 0;
        
        $lastOrderDate = new DateTime($result['last_order']);
        $currentPeriod = new DateTime("$minYear-$minMonth-01");
        $monthsDiff = $lastOrderDate->diff($currentPeriod)->m + 
                      ($lastOrderDate->diff($currentPeriod)->y * 12);
        
        if ($monthsDiff >= 6) return 100;
        if ($monthsDiff >= 4) return 70;
        if ($monthsDiff >= 3) return 50;
        
        return 0;
    }

    private function checkStopAfterTarget($custCode, $years, $months) {
        $monthlySales = $this->getMonthlySales($custCode, $years, $months);
        if (count($monthlySales) < 2) return 0;
        
        $maxDrop = 0;
        for ($i = 0; $i < count($monthlySales) - 1; $i++) {
            if ($monthlySales[$i] > 0) {
                $dropRate = (($monthlySales[$i] - $monthlySales[$i+1]) / $monthlySales[$i]) * 100;
                $maxDrop = max($maxDrop, $dropRate);
            }
        }
        
        if ($maxDrop >= 90) return 100;
        if ($maxDrop >= 80) return 80;
        if ($maxDrop >= 70) return 60;
        
        return 0;
    }

    private function checkSuddenDrop($custCode, $years, $months) {
        $currentSales = $this->getSalesInPeriod($custCode, $years, $months);
        $previousSales = $this->getPreviousSales($custCode, $years, $months, 3);
        
        if ($previousSales == 0 || $currentSales >= $previousSales) return 0;
        
        $dropRate = (($previousSales - $currentSales) / $previousSales) * 100;
        
        if ($dropRate >= 80) return 100;
        if ($dropRate >= 70) return 80;
        if ($dropRate >= 60) return 60;
        
        return 0;
    }

    private function checkGkhlNoOrders($custCode, $years, $months) {
        $sql = "SELECT MaKHDMS FROM gkhl WHERE MaKHDMS = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$custCode]);
        $isGkhl = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$isGkhl) return 0;
        
        $orders = $this->getOrderCountInPeriod($custCode, $years, $months);
        
        if ($orders == 0) return 100;
        if ($orders <= 1) return 70;
        
        return 0;
    }

    private function checkGkhlFirstMonthSpike($custCode, $years, $months) {
        return 0;
    }

    private function checkGkhlCheckpointSpike($custCode, $years, $months) {
        $yearPlaceholders = implode(',', array_fill(0, count($years), '?'));
        $monthPlaceholders = implode(',', array_fill(0, count($months), '?'));
        
        $sql = "SELECT COUNT(*) as checkpoint_orders
                FROM orderdetail
                WHERE CustCode = ?
                AND RptYear IN ($yearPlaceholders)
                AND RptMonth IN ($monthPlaceholders)
                AND (DAY(OrderDate) IN (14, 15, 16) OR DAY(OrderDate) >= 28)";
        
        $params = array_merge([$custCode], $years, $months);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) return 0;
        
        $totalOrders = $this->getOrderCountInPeriod($custCode, $years, $months);
        if ($totalOrders == 0) return 0;
        
        $checkpointRatio = ($result['checkpoint_orders'] / $totalOrders) * 100;
        
        if ($checkpointRatio >= 70) return 100;
        if ($checkpointRatio >= 60) return 80;
        if ($checkpointRatio >= 50) return 60;
        
        return 0;
    }

    // ✅ CẬP NHẬT: Thêm filters
    private function getCustomersWithHistory($years, $months, $filters = []) {
        $yearPlaceholders = implode(',', array_fill(0, count($years), '?'));
        $monthPlaceholders = implode(',', array_fill(0, count($months), '?'));
        
        $sql = "SELECT DISTINCT
                    o.CustCode as ma_khach_hang,
                    d.TenKH as ten_khach_hang,
                    SUM(o.TotalNetAmount) as total_doanh_so,
                    COUNT(DISTINCT o.OrderNumber) as total_orders
                FROM orderdetail o
                LEFT JOIN dskh d ON o.CustCode = d.MaKH
                LEFT JOIN gkhl g ON o.CustCode = g.MaKHDMS
                WHERE o.RptYear IN ($yearPlaceholders)
                AND o.RptMonth IN ($monthPlaceholders)";
        
        $params = array_merge($years, $months);
        
        // ✅ Filter theo tỉnh
        if (!empty($filters['ma_tinh_tp'])) {
            $sql .= " AND d.Tinh = ?";
            $params[] = $filters['ma_tinh_tp'];
        }
        
        // ✅ Filter theo GKHL
        if (isset($filters['gkhl_status']) && $filters['gkhl_status'] !== '') {
            if ($filters['gkhl_status'] === '1') {
                $sql .= " AND g.MaKHDMS IS NOT NULL";
            } elseif ($filters['gkhl_status'] === '0') {
                $sql .= " AND g.MaKHDMS IS NULL";
            }
        }
        
        $sql .= " GROUP BY o.CustCode, d.TenKH
                  HAVING total_doanh_so > 0";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getAverageOrderValue($custCode) {
        $sql = "SELECT COALESCE(AVG(TotalNetAmount), 0) as avg_order
                FROM orderdetail
                WHERE CustCode = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$custCode]);
        return $stmt->fetchColumn();
    }

    private function getTotalQuantity($custCode, $years, $months) {
        $yearPlaceholders = implode(',', array_fill(0, count($years), '?'));
        $monthPlaceholders = implode(',', array_fill(0, count($months), '?'));
        
        $sql = "SELECT COALESCE(SUM(Qty), 0) as total
                FROM orderdetail
                WHERE CustCode = ?
                AND RptYear IN ($yearPlaceholders)
                AND RptMonth IN ($monthPlaceholders)";
        
        $params = array_merge([$custCode], $years, $months);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function getUsualProducts($custCode) {
        $sql = "SELECT DISTINCT ProductCode
                FROM orderdetail
                WHERE CustCode = ?
                AND OrderDate >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY ProductCode
                HAVING COUNT(*) >= 2";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$custCode]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getMonthlySales($custCode, $years, $months) {
        $yearPlaceholders = implode(',', array_fill(0, count($years), '?'));
        $monthPlaceholders = implode(',', array_fill(0, count($months), '?'));
        
        $sql = "SELECT 
                    RptYear,
                    RptMonth,
                    SUM(TotalNetAmount) as monthly_sales
                FROM orderdetail
                WHERE CustCode = ?
                AND RptYear IN ($yearPlaceholders)
                AND RptMonth IN ($monthPlaceholders)
                GROUP BY RptYear, RptMonth
                ORDER BY RptYear, RptMonth";
        
        $params = array_merge([$custCode], $years, $months);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'monthly_sales');
    }

    private function getRiskLevel($score) {
        if ($score >= 60) return 'critical';
        if ($score >= 40) return 'high';
        if ($score >= 25) return 'medium';
        if ($score >= 10) return 'low';
        return 'normal';
    }

    private function getAnomalyDescription($type, $score) {
        $descriptions = [
            'sudden_spike' => 'Doanh số tăng đột biến so với trung bình',
            'frequency_spike' => 'Tần suất đặt hàng tăng bất thường',
            'large_order' => 'Có đơn hàng lớn bất thường',
            'product_hoarding' => 'Tập trung mua 1 sản phẩm',
            'end_month_rush' => 'Mua hàng tập trung cuối tháng',
            'mid_month_rush' => 'Mua hàng tập trung giữa tháng (10-20)', // ✅ MỚI
            'unusual_time' => 'Đặt hàng vào giờ bất thường (đêm khuya)',
            'unusual_product' => 'Mua sản phẩm khác lạ so với thói quen',
            'high_value_order' => 'Đơn hàng giá trị cao bất thường',
            'return_after_long' => 'Quay lại mua sau thời gian dài không hoạt động',
            'stop_after_target' => 'Dừng mua sau khi đạt mốc doanh số',
            'sudden_drop' => 'Doanh số giảm đột ngột',
            'gkhl_no_orders' => 'Tham gia GKHL nhưng không có đơn hàng',
            'gkhl_first_month_spike' => 'Tháng đầu tham gia GKHL mua bùng nổ',
            'gkhl_checkpoint_spike' => 'Mua tập trung vào kỳ kiểm tra GKHL'
        ];
        
        $severity = '';
        if ($score >= 80) $severity = ' (Mức độ: Rất cao)';
        elseif ($score >= 60) $severity = ' (Mức độ: Cao)';
        elseif ($score >= 40) $severity = ' (Mức độ: Trung bình)';
        
        return ($descriptions[$type] ?? 'Bất thường') . $severity;
    }

    public function getCustomerAnomalyDetail($custCode, $years, $months) {
        $scores = [
            'sudden_spike' => $this->checkSuddenSpike($custCode, $years, $months),
            'frequency_spike' => $this->checkFrequencySpike($custCode, $years, $months),
            'large_order' => $this->checkLargeOrders($custCode, $years, $months),
            'product_hoarding' => $this->checkProductHoarding($custCode, $years, $months),
            'end_month_rush' => $this->checkEndMonthRush($custCode, $years, $months),
            'mid_month_rush' => $this->checkMidMonthRush($custCode, $years, $months),
            'unusual_time' => $this->checkUnusualTime($custCode, $years, $months),
            'unusual_product' => $this->checkUnusualProduct($custCode, $years, $months),
            'high_value_order' => $this->checkHighValueOrder($custCode, $years, $months),
            'return_after_long' => $this->checkReturnAfterLong($custCode, $years, $months),
            'stop_after_target' => $this->checkStopAfterTarget($custCode, $years, $months),
            'sudden_drop' => $this->checkSuddenDrop($custCode, $years, $months),
            'gkhl_no_orders' => $this->checkGkhlNoOrders($custCode, $years, $months),
            'gkhl_first_month_spike' => $this->checkGkhlFirstMonthSpike($custCode, $years, $months),
            'gkhl_checkpoint_spike' => $this->checkGkhlCheckpointSpike($custCode, $years, $months)
        ];
        
        $totalScore = 0;
        $details = [];
        
        foreach ($scores as $type => $score) {
            if ($score > 0) {
                $weightedScore = ($score / 100) * self::WEIGHTS[$type];
                $totalScore += $weightedScore;
                $details[] = [
                    'type' => $type,
                    'score' => $score,
                    'weighted_score' => round($weightedScore, 2),
                    'description' => $this->getAnomalyDescription($type, $score),
                    'weight' => self::WEIGHTS[$type]
                ];
            }
        }
        
        usort($details, function($a, $b) {
            return $b['weighted_score'] <=> $a['weighted_score'];
        });
        
        return [
            'total_score' => round($totalScore, 2),
            'risk_level' => $this->getRiskLevel($totalScore),
            'anomaly_count' => count($details),
            'details' => $details
        ];
    }
}
?>