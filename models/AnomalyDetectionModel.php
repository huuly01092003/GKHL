<?php
require_once 'config/database.php';

class AnomalyDetectionModel {
    private $conn;
    
    // Trọng số cho các loại bất thường (tổng = 100)
    private const WEIGHTS = [
        'sudden_spike' => 15,           // Tăng đột biến
        'frequency_spike' => 12,        // Tần suất tăng đột biến
        'large_order' => 10,            // Đơn hàng lớn bất thường
        'product_hoarding' => 8,        // Gom hàng 1 sản phẩm
        'end_month_rush' => 7,          // Mua cuối tháng
        'unusual_time' => 5,            // Giờ bất thường
        'unusual_product' => 8,         // Sản phẩm lạ
        'high_value_order' => 10,       // Giá trị cao bất thường
        'return_after_long' => 6,       // Quay lại sau lâu
        'stop_after_target' => 9,       // Đạt target rồi dừng
        'sudden_drop' => 7,             // Giảm đột ngột
        'gkhl_no_orders' => 8,          // GKHL không mua
        'gkhl_first_month_spike' => 10, // GKHL tháng đầu bùng nổ
        'gkhl_checkpoint_spike' => 8    // Mua mạnh kỳ kiểm tra
    ];

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * Tính điểm bất thường cho tất cả khách hàng trong kỳ
     */
    public function calculateAnomalyScores($years, $months) {
        $customers = $this->getCustomersWithHistory($years, $months);
        $results = [];
        
        foreach ($customers as $customer) {
            $custCode = $customer['ma_khach_hang'];
            
            $scores = [
                'sudden_spike' => $this->checkSuddenSpike($custCode, $years, $months),
                'frequency_spike' => $this->checkFrequencySpike($custCode, $years, $months),
                'large_order' => $this->checkLargeOrders($custCode, $years, $months),
                'product_hoarding' => $this->checkProductHoarding($custCode, $years, $months),
                'end_month_rush' => $this->checkEndMonthRush($custCode, $years, $months),
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
            
            // Tính tổng điểm có trọng số
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
        
        // Sắp xếp theo điểm giảm dần
        usort($results, function($a, $b) {
            return $b['total_score'] <=> $a['total_score'];
        });
        
        return $results;
    }

    /**
     * 1. Kiểm tra tăng đột biến doanh số (150-300%)
     */
    private function checkSuddenSpike($custCode, $years, $months) {
        // Lấy doanh số kỳ hiện tại
        $currentSales = $this->getSalesInPeriod($custCode, $years, $months);
        
        if ($currentSales == 0) return 0;
        
        // Lấy doanh số 3 tháng trước
        $previousSales = $this->getPreviousSales($custCode, $years, $months, 3);
        
        if ($previousSales == 0) return 0;
        
        $increaseRate = (($currentSales - $previousSales) / $previousSales) * 100;
        
        if ($increaseRate >= 300) return 100;
        if ($increaseRate >= 250) return 85;
        if ($increaseRate >= 200) return 70;
        if ($increaseRate >= 150) return 50;
        
        return 0;
    }

    /**
     * 2. Kiểm tra tần suất đơn hàng tăng đột biến
     */
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

    /**
     * 3. Kiểm tra đơn hàng lớn bất thường
     */
    private function checkLargeOrders($custCode, $years, $months) {
        $sql = "SELECT o.TotalNetAmount, o.Qty
                FROM orderdetail o
                WHERE o.CustCode = :cust_code
                AND o.RptYear IN (" . implode(',', array_fill(0, count($years), '?')) . ")
                AND o.RptMonth IN (" . implode(',', array_fill(0, count($months), '?')) . ")
                ORDER BY o.TotalNetAmount DESC
                LIMIT 1";
        
        $params = array_merge([$custCode], $years, $months);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $largestOrder = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$largestOrder) return 0;
        
        // So với trung bình
        $avgOrder = $this->getAverageOrderValue($custCode);
        
        if ($avgOrder == 0) return 0;
        
        $ratio = $largestOrder['TotalNetAmount'] / $avgOrder;
        
        if ($ratio >= 10) return 100;
        if ($ratio >= 7) return 80;
        if ($ratio >= 5) return 60;
        if ($ratio >= 3) return 40;
        
        return 0;
    }

    /**
     * 4. Kiểm tra gom hàng 1 sản phẩm
     */
    private function checkProductHoarding($custCode, $years, $months) {
        $sql = "SELECT ProductCode, SUM(Qty) as total_qty, COUNT(*) as order_count
                FROM orderdetail
                WHERE CustCode = :cust_code
                AND RptYear IN (" . implode(',', array_fill(0, count($years), '?')) . ")
                AND RptMonth IN (" . implode(',', array_fill(0, count($months), '?')) . ")
                GROUP BY ProductCode
                ORDER BY total_qty DESC
                LIMIT 1";
        
        $params = array_merge([$custCode], $years, $months);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $topProduct = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$topProduct) return 0;
        
        // Tính % so với tổng
        $totalQty = $this->getTotalQuantity($custCode, $years, $months);
        
        if ($totalQty == 0) return 0;
        
        $concentration = ($topProduct['total_qty'] / $totalQty) * 100;
        
        if ($concentration >= 80) return 100;
        if ($concentration >= 70) return 80;
        if ($concentration >= 60) return 60;
        
        return 0;
    }

    /**
     * 5. Kiểm tra mua cuối tháng
     */
    private function checkEndMonthRush($custCode, $years, $months) {
        $sql = "SELECT 
                    DAY(OrderDate) as day_of_month,
                    COUNT(*) as order_count,
                    SUM(TotalNetAmount) as total_amount
                FROM orderdetail
                WHERE CustCode = :cust_code
                AND RptYear IN (" . implode(',', array_fill(0, count($years), '?')) . ")
                AND RptMonth IN (" . implode(',', array_fill(0, count($months), '?')) . ")
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

    /**
     * 6. Kiểm tra giờ bất thường (đêm khuya 22h-6h)
     */
    private function checkUnusualTime($custCode, $years, $months) {
        $sql = "SELECT COUNT(*) as night_orders
                FROM orderdetail
                WHERE CustCode = :cust_code
                AND RptYear IN (" . implode(',', array_fill(0, count($years), '?')) . ")
                AND RptMonth IN (" . implode(',', array_fill(0, count($months), '?')) . ")
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

    /**
     * 7. Kiểm tra mua sản phẩm lạ
     */
    private function checkUnusualProduct($custCode, $years, $months) {
        // Lấy sản phẩm thường mua (6 tháng trước)
        $usualProducts = $this->getUsualProducts($custCode);
        
        if (empty($usualProducts)) return 0;
        
        // Lấy sản phẩm mua trong kỳ hiện tại
        $sql = "SELECT DISTINCT ProductCode
                FROM orderdetail
                WHERE CustCode = :cust_code
                AND RptYear IN (" . implode(',', array_fill(0, count($years), '?')) . ")
                AND RptMonth IN (" . implode(',', array_fill(0, count($months), '?')) . ")";
        
        $params = array_merge([$custCode], $years, $months);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $currentProducts = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($currentProducts)) return 0;
        
        // Tính % sản phẩm mới
        $newProducts = array_diff($currentProducts, $usualProducts);
        $newRatio = (count($newProducts) / count($currentProducts)) * 100;
        
        if ($newRatio >= 70) return 100;
        if ($newRatio >= 50) return 75;
        if ($newRatio >= 30) return 50;
        
        return 0;
    }

    /**
     * 8. Kiểm tra đơn hàng giá trị cao bất thường
     */
    private function checkHighValueOrder($custCode, $years, $months) {
        return $this->checkLargeOrders($custCode, $years, $months);
    }

    /**
     * 9. Kiểm tra quay lại sau thời gian dài
     */
    private function checkReturnAfterLong($custCode, $years, $months) {
        // Lấy ngày đơn hàng cuối cùng trước kỳ này
        $sql = "SELECT MAX(OrderDate) as last_order
                FROM orderdetail
                WHERE CustCode = :cust_code
                AND (RptYear < :min_year 
                     OR (RptYear = :min_year AND RptMonth < :min_month))";
        
        $minYear = min($years);
        $minMonth = min($months);
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':cust_code' => $custCode,
            ':min_year' => $minYear,
            ':min_month' => $minMonth
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || !$result['last_order']) return 0;
        
        // Tính số tháng chênh lệch
        $lastOrderDate = new DateTime($result['last_order']);
        $currentPeriod = new DateTime("$minYear-$minMonth-01");
        $monthsDiff = $lastOrderDate->diff($currentPeriod)->m + 
                      ($lastOrderDate->diff($currentPeriod)->y * 12);
        
        if ($monthsDiff >= 6) return 100;
        if ($monthsDiff >= 4) return 70;
        if ($monthsDiff >= 3) return 50;
        
        return 0;
    }

    /**
     * 10. Kiểm tra đạt target rồi dừng
     */
    private function checkStopAfterTarget($custCode, $years, $months) {
        // Kiểm tra nếu đạt mốc doanh số rồi giảm mạnh ngay sau đó
        $monthlySales = $this->getMonthlySales($custCode, $years, $months);
        
        if (count($monthlySales) < 2) return 0;
        
        // Tìm pattern: tháng đạt cao → tháng sau giảm > 70%
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

    /**
     * 11. Kiểm tra giảm đột ngột
     */
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

    /**
     * 12. Kiểm tra GKHL không có đơn
     */
    private function checkGkhlNoOrders($custCode, $years, $months) {
        // Kiểm tra có trong GKHL không
        $sql = "SELECT MaKHDMS FROM gkhl WHERE MaKHDMS = :cust_code LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':cust_code' => $custCode]);
        $isGkhl = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$isGkhl) return 0;
        
        // Kiểm tra có đơn không
        $orders = $this->getOrderCountInPeriod($custCode, $years, $months);
        
        if ($orders == 0) return 100;
        if ($orders <= 1) return 70;
        
        return 0;
    }

    /**
     * 13. Kiểm tra GKHL tháng đầu bùng nổ
     */
    private function checkGkhlFirstMonthSpike($custCode, $years, $months) {
        // Kiểm tra tháng tham gia GKHL
        // (Cần thêm cột ngày tham gia vào bảng GKHL hoặc ước lượng)
        
        // Tạm thời return 0 vì thiếu data
        return 0;
    }

    /**
     * 14. Kiểm tra mua mạnh kỳ kiểm tra GKHL
     */
    private function checkGkhlCheckpointSpike($custCode, $years, $months) {
        // Giả sử checkpoint là ngày 15 và cuối tháng
        $sql = "SELECT COUNT(*) as checkpoint_orders
                FROM orderdetail
                WHERE CustCode = :cust_code
                AND RptYear IN (" . implode(',', array_fill(0, count($years), '?')) . ")
                AND RptMonth IN (" . implode(',', array_fill(0, count($months), '?')) . ")
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

    // ==================== HELPER METHODS ====================

    private function getCustomersWithHistory($years, $months) {
        $sql = "SELECT DISTINCT
                    o.CustCode as ma_khach_hang,
                    d.TenKH as ten_khach_hang,
                    SUM(o.TotalNetAmount) as total_doanh_so,
                    COUNT(DISTINCT o.OrderNumber) as total_orders
                FROM orderdetail o
                LEFT JOIN dskh d ON o.CustCode = d.MaKH
                WHERE o.RptYear IN (" . implode(',', array_fill(0, count($years), '?')) . ")
                AND o.RptMonth IN (" . implode(',', array_fill(0, count($months), '?')) . ")
                GROUP BY o.CustCode, d.TenKH
                HAVING total_doanh_so > 0";
        
        $params = array_merge($years, $months);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getSalesInPeriod($custCode, $years, $months) {
        $sql = "SELECT COALESCE(SUM(TotalNetAmount), 0) as total
                FROM orderdetail
                WHERE CustCode = :cust_code
                AND RptYear IN (" . implode(',', array_fill(0, count($years), '?')) . ")
                AND RptMonth IN (" . implode(',', array_fill(0, count($months), '?')) . ")";
        
        $params = array_merge([$custCode], $years, $months);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function getPreviousSales($custCode, $years, $months, $monthsBack = 3) {
        // Lấy doanh số N tháng trước
        $minYear = min($years);
        $minMonth = min($months);
        
        $sql = "SELECT COALESCE(AVG(monthly_sales), 0) as avg_sales
                FROM (
                    SELECT 
                        RptYear, 
                        RptMonth,
                        SUM(TotalNetAmount) as monthly_sales
                    FROM orderdetail
                    WHERE CustCode = :cust_code
                    AND (
                        RptYear < :min_year
                        OR (RptYear = :min_year AND RptMonth < :min_month)
                    )
                    GROUP BY RptYear, RptMonth
                    ORDER BY RptYear DESC, RptMonth DESC
                    LIMIT :months_back
                ) as prev_months";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':cust_code' => $custCode,
            ':min_year' => $minYear,
            ':min_month' => $minMonth,
            ':months_back' => $monthsBack
        ]);
        return $stmt->fetchColumn();
    }

    private function getOrderCountInPeriod($custCode, $years, $months) {
        $sql = "SELECT COUNT(DISTINCT OrderNumber) as total
                FROM orderdetail
                WHERE CustCode = :cust_code
                AND RptYear IN (" . implode(',', array_fill(0, count($years), '?')) . ")
                AND RptMonth IN (" . implode(',', array_fill(0, count($months), '?')) . ")";
        
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
                    WHERE CustCode = :cust_code
                    AND (
                        RptYear < :min_year
                        OR (RptYear = :min_year AND RptMonth < :min_month)
                    )
                    GROUP BY RptYear, RptMonth
                    ORDER BY RptYear DESC, RptMonth DESC
                    LIMIT :months_back
                ) as prev_months";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':cust_code' => $custCode,
            ':min_year' => $minYear,
            ':min_month' => $minMonth,
            ':months_back' => $monthsBack
        ]);
        return $stmt->fetchColumn();
    }

    private function getAverageOrderValue($custCode) {
        $sql = "SELECT COALESCE(AVG(TotalNetAmount), 0) as avg_order
                FROM orderdetail
                WHERE CustCode = :cust_code";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':cust_code' => $custCode]);
        return $stmt->fetchColumn();
    }

    private function getTotalQuantity($custCode, $years, $months) {
        $sql = "SELECT COALESCE(SUM(Qty), 0) as total
                FROM orderdetail
                WHERE CustCode = :cust_code
                AND RptYear IN (" . implode(',', array_fill(0, count($years), '?')) . ")
                AND RptMonth IN (" . implode(',', array_fill(0, count($months), '?')) . ")";
        
        $params = array_merge([$custCode], $years, $months);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function getUsualProducts($custCode) {
        $sql = "SELECT DISTINCT ProductCode
                FROM orderdetail
                WHERE CustCode = :cust_code
                AND OrderDate >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY ProductCode
                HAVING COUNT(*) >= 2";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':cust_code' => $custCode]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getMonthlySales($custCode, $years, $months) {
        $sql = "SELECT 
                    RptYear,
                    RptMonth,
                    SUM(TotalNetAmount) as monthly_sales
                FROM orderdetail
                WHERE CustCode = :cust_code
                AND RptYear IN (" . implode(',', array_fill(0, count($years), '?')) . ")
                AND RptMonth IN (" . implode(',', array_fill(0, count($months), '?')) . ")
                GROUP BY RptYear, RptMonth
                ORDER BY RptYear, RptMonth";
        
        $params = array_merge([$custCode], $years, $months);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'monthly_sales');
    }

    private function getRiskLevel($score) {
        if ($score >= 60) return 'critical';      // Top 20 - Màu đỏ
        if ($score >= 40) return 'high';          // Nghi vấn cao - Màu cam
        if ($score >= 25) return 'medium';        // Nghi vấn trung bình - Màu vàng
        if ($score >= 10) return 'low';           // Nghi vấn thấp - Màu xanh nhạt
        return 'normal';                          // Bình thường
    }

    private function getAnomalyDescription($type, $score) {
        $descriptions = [
            'sudden_spike' => 'Doanh số tăng đột biến so với trung bình',
            'frequency_spike' => 'Tần suất đặt hàng tăng bất thường',
            'large_order' => 'Có đơn hàng lớn bất thường',
            'product_hoarding' => 'Tập trung mua 1 sản phẩm',
            'end_month_rush' => 'Mua hàng tập trung cuối tháng',
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

    /**
     * Lấy chi tiết bất thường cho 1 khách hàng
     */
    public function getCustomerAnomalyDetail($custCode, $years, $months) {
        $scores = [
            'sudden_spike' => $this->checkSuddenSpike($custCode, $years, $months),
            'frequency_spike' => $this->checkFrequencySpike($custCode, $years, $months),
            'large_order' => $this->checkLargeOrders($custCode, $years, $months),
            'product_hoarding' => $this->checkProductHoarding($custCode, $years, $months),
            'end_month_rush' => $this->checkEndMonthRush($custCode, $years, $months),
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
        
        // Sắp xếp theo weighted_score giảm dần
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