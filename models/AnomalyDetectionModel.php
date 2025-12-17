<?php
require_once 'config/database.php';

/**
 * ✅ PHIÊN BẢN HOÀN CHỈNH - CÓ MINH CHỨNG CHI TIẾT CHO TẤT CẢ 8 DẤU HIỆU
 */

class AnomalyDetectionModel {
    private $conn;
    
    private const WEIGHTS = [
        'sudden_spike' => 20,
        'return_after_long_break' => 18,
        'checkpoint_rush' => 16,
        'product_concentration' => 14,
        'unusual_product_pattern' => 12,
        'burst_orders' => 15,
        'high_value_outlier' => 13,
        'no_purchase_after_spike' => 10
    ];

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * ✅ LẤY CHI TIẾT BẤT THƯỜNG CHO 1 KHÁCH HÀNG
     */
    public function getCustomerAnomalyDetail($custCode, $years, $months) {
        $metricsData = $this->getBatchMetrics([$custCode], $years, $months);
        $metrics = $metricsData[$custCode] ?? null;
        
        if (!$metrics) {
            return [
                'total_score' => 0,
                'risk_level' => 'normal',
                'anomaly_count' => 0,
                'details' => []
            ];
        }
        
        // Lưu lại years và months để dùng trong getDetailMetrics
        $metrics['years'] = $years;
        $metrics['months'] = $months;
        
        $scores = [
            'sudden_spike' => $this->checkSuddenSpike($metrics),
            'return_after_long_break' => $this->checkReturnAfterLongBreak($metrics),
            'checkpoint_rush' => $this->checkCheckpointRush($metrics),
            'product_concentration' => $this->checkProductConcentration($metrics),
            'unusual_product_pattern' => $this->checkUnusualProductPattern($metrics),
            'burst_orders' => $this->checkBurstOrders($metrics),
            'high_value_outlier' => $this->checkHighValueOutlier($metrics),
            'no_purchase_after_spike' => $this->checkNoPurchaseAfterSpike($metrics)
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
                    'description' => $this->getAnomalyDescription($type, $score, $metrics),
                    'weight' => self::WEIGHTS[$type],
                    'metrics' => $this->getDetailMetrics($type, $metrics)
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

    /**
     * ✅ LẤY METRICS CHI TIẾT + MINH CHỨNG CHO TỪNG DẤU HIỆU
     */
    private function getDetailMetrics($type, $metrics) {
        switch ($type) {
            // ========================================
            // 1️⃣ DOANH SỐ TĂNG ĐỘT BIẾN
            // ========================================
            case 'sudden_spike':
                $current = $metrics['total_sales'] ?? 0;
                $historical = $metrics['historical']['avg_sales'] ?? 0;
                $increasePercent = $historical > 0 ? round((($current - $historical) / $historical) * 100, 1) : 0;
                
                return [
                    'current_sales' => $current,
                    'historical_avg' => $historical,
                    'increase_percent' => $increasePercent,
                    'difference' => $current - $historical,
                    'historical_months' => $metrics['historical']['months_count'] ?? 0,
                    
                    // ✅ MINH CHỨNG: Dữ liệu từng tháng
                    'evidence' => $this->getMonthlyBreakdown(
                        $metrics['CustCode'], 
                        $metrics['years'], 
                        $metrics['months']
                    )
                ];
            
            // ========================================
            // 2️⃣ QUAY LẠI SAU NGHỈ DÀI
            // ========================================
            case 'return_after_long_break':
                $monthsGap = $metrics['historical']['months_since_last_order'] ?? 0;
                $currentSales = $metrics['total_sales'] ?? 0;
                $lastSales = $metrics['historical']['last_period_sales'] ?? 0;
                $increasePercent = $lastSales > 0 ? round((($currentSales - $lastSales) / $lastSales) * 100, 1) : 0;
                
                return [
                    'months_gap' => $monthsGap,
                    'current_sales' => $currentSales,
                    'last_sales' => $lastSales,
                    'increase_percent' => $increasePercent,
                    
                    // ✅ MINH CHỨNG: Lịch sử giao dịch với khoảng trống
                    'evidence' => $this->getPurchaseHistory(
                        $metrics['CustCode'],
                        24 // Lấy 24 tháng để thấy rõ khoảng trống
                    )
                ];
            
            // ========================================
            // 3️⃣ TẬP TRUNG CHECKPOINT
            // ========================================
            case 'checkpoint_rush':
                $total = $metrics['total_orders'] ?? 1;
                $checkpointOrders = $metrics['total_checkpoint_orders'] ?? 0;
                $checkpointAmount = $metrics['checkpoint_amount'] ?? 0;
                $totalAmount = $metrics['total_sales'] ?? 1;
                
                return [
                    'checkpoint_orders' => $checkpointOrders,
                    'total_orders' => $total,
                    'checkpoint_ratio' => round(($checkpointOrders / $total) * 100, 1),
                    'checkpoint_amount' => $checkpointAmount,
                    'total_amount' => $totalAmount,
                    'amount_ratio' => round(($checkpointAmount / $totalAmount) * 100, 1),
                    'mid_checkpoint' => $metrics['mid_checkpoint_orders'] ?? 0,
                    'end_checkpoint' => $metrics['end_checkpoint_orders'] ?? 0,
                    
                    // ✅ MINH CHỨNG: Chi tiết đơn hàng theo ngày trong tháng
                    'evidence' => $this->getOrdersByDayOfMonth(
                        $metrics['CustCode'],
                        $metrics['years'],
                        $metrics['months']
                    )
                ];
            
            // ========================================
            // 4️⃣ CHỈ MUA 1 LOẠI SẢN PHẨM
            // ========================================
            case 'product_concentration':
                $products = $metrics['product_details'] ?? [];
                $top = $products[0] ?? [];
                $totalQty = $metrics['total_qty'] ?? 1;
                $topQty = $top['total_qty'] ?? 0;
                
                return [
                    'distinct_types' => $metrics['distinct_product_types'] ?? 0,
                    'top_product_type' => $top['product_type'] ?? '',
                    'top_product_qty' => $topQty,
                    'total_qty' => $totalQty,
                    'concentration_percent' => round(($topQty / $totalQty) * 100, 1),
                    
                    // ✅ MINH CHỨNG: Phân bổ sản phẩm
                    'evidence' => $this->getProductDistribution(
                        $metrics['CustCode'],
                        $metrics['years'],
                        $metrics['months']
                    )
                ];
            
            // ========================================
            // 5️⃣ MUA SẢN PHẨM KHÁC LẠ
            // ========================================
            case 'unusual_product_pattern':
                $usualProducts = $metrics['usual_products'] ?? [];
                $productDetails = $metrics['product_details'] ?? [];
                $currentTypes = array_column($productDetails, 'product_type');
                $usualTypes = array_unique(array_map(function($code) {
                    return substr($code, 0, 2);
                }, $usualProducts));
                $newTypes = array_diff($currentTypes, $usualTypes);
                
                $newProductSales = 0;
                $totalSales = $metrics['total_sales'] ?? 1;
                foreach ($productDetails as $product) {
                    if (in_array($product['product_type'], $newTypes)) {
                        $newProductSales += $product['total_amount'];
                    }
                }
                
                return [
                    'new_products' => count($newTypes),
                    'total_products' => count($currentTypes),
                    'new_ratio' => count($currentTypes) > 0 ? round((count($newTypes) / count($currentTypes)) * 100, 1) : 0,
                    'new_sales' => $newProductSales,
                    'total_sales' => $totalSales,
                    'new_sales_ratio' => round(($newProductSales / $totalSales) * 100, 1),
                    'new_product_types' => implode(', ', $newTypes),
                    
                    // ✅ MINH CHỨNG: So sánh sản phẩm cũ vs mới
                    'evidence' => [
                        'usual_products' => $this->getUsualProductsDetail($metrics['CustCode']),
                        'new_products' => $this->getNewProductsDetail(
                            $metrics['CustCode'],
                            $metrics['years'],
                            $metrics['months'],
                            $newTypes
                        )
                    ]
                ];
            
            // ========================================
            // 6️⃣ MUA DỒN DẬP
            // ========================================
            case 'burst_orders':
                $daily = $metrics['daily_orders'] ?? [];
                $max = 0;
                $maxDate = '';
                $consecutiveDays = 0;
                $maxConsecutive = 0;
                
                foreach ($daily as $date => $data) {
                    if ($data['order_count'] > $max) {
                        $max = $data['order_count'];
                        $maxDate = $date;
                    }
                    
                    if ($data['order_count'] >= 3) {
                        $consecutiveDays++;
                        $maxConsecutive = max($maxConsecutive, $consecutiveDays);
                    } else {
                        $consecutiveDays = 0;
                    }
                }
                
                return [
                    'max_orders_in_day' => $max,
                    'max_order_date' => $maxDate,
                    'total_days' => count($daily),
                    'max_consecutive_days' => $maxConsecutive,
                    'avg_orders_per_day' => count($daily) > 0 ? round(($metrics['total_orders'] ?? 0) / count($daily), 2) : 0,
                    
                    // ✅ MINH CHỨNG: Danh sách đơn hàng theo ngày
                    'evidence' => $this->getDailyOrdersDetail(
                        $metrics['CustCode'],
                        $metrics['years'],
                        $metrics['months']
                    )
                ];
            
            // ========================================
            // 7️⃣ GIÁ TRỊ ĐỠN >3σ
            // ========================================
            case 'high_value_outlier':
                $avg = $metrics['avg_order_value'] ?? 0;
                $std = $metrics['stddev_order_value'] ?? 1;
                $max = $metrics['max_order_value'] ?? 0;
                $sigmaCount = $std > 0 ? ($max - $avg) / $std : 0;
                
                return [
                    'max_order_value' => $max,
                    'avg_order_value' => $avg,
                    'stddev' => $std,
                    'sigma_count' => round($sigmaCount, 2),
                    'threshold_3sigma' => $avg + (3 * $std),
                    
                    // ✅ MINH CHỨNG: Phân bố giá trị đơn hàng
                    'evidence' => $this->getOrderValueDistribution(
                        $metrics['CustCode'],
                        $metrics['years'],
                        $metrics['months']
                    )
                ];
            
            // ========================================
            // 8️⃣ KHÔNG MUA SAU SPIKE
            // ========================================
            case 'no_purchase_after_spike':
                $current = $metrics['total_sales'] ?? 0;
                $future = $metrics['future_activity'] ?? [];
                $futureAmount = $future['total_sales'] ?? 0;
                $futureOrders = $future['order_count'] ?? 0;
                $dropPercent = $current > 0 ? round((($current - $futureAmount) / $current) * 100, 1) : 0;
                
                return [
                    'spike_sales' => $current,
                    'after_sales' => $futureAmount,
                    'after_orders' => $futureOrders,
                    'drop_percent' => $dropPercent,
                    'has_activity' => $future['has_orders'] ?? false,
                    
                    // ✅ MINH CHỨNG: So sánh trước và sau spike
                    'evidence' => $this->getSpikeComparison(
                        $metrics['CustCode'],
                        $metrics['years'],
                        $metrics['months']
                    )
                ];
            
            default:
                return [];
        }
    }

    // ============================================
    // HÀM LẤY MINH CHỨNG CHO TỪNG DẤU HIỆU
    // ============================================

    /**
     * 1️⃣ Dữ liệu từng tháng (cho Sudden Spike)
     */
    private function getMonthlyBreakdown($custCode, $years, $months) {
        $minYear = min($years);
        $minMonth = min($months);
        
        $sql = "SELECT 
                    RptYear,
                    RptMonth,
                    SUM(TotalNetAmount) as monthly_sales,
                    COUNT(DISTINCT OrderNumber) as monthly_orders
                FROM orderdetail
                WHERE CustCode = ?
                AND (
                    (RptYear = ? AND RptMonth <= ?)
                    OR (RptYear = ? - 1 AND RptMonth > ?)
                )
                GROUP BY RptYear, RptMonth
                ORDER BY RptYear, RptMonth
                LIMIT 6";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$custCode, $minYear, $minMonth, $minYear, $minMonth]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($results)) return [];
        
        $baseline = $results[0]['monthly_sales'] ?? 0;
        
        foreach ($results as &$row) {
            $row['period'] = "Tháng {$row['RptMonth']}/{$row['RptYear']}";
            $row['value'] = number_format($row['monthly_sales'], 0, ',', '.');
            
            if ($baseline > 0 && $row['monthly_sales'] != $baseline) {
                $change = (($row['monthly_sales'] - $baseline) / $baseline) * 100;
                $row['comparison'] = ($change >= 0 ? '+' : '') . round($change, 1) . '%';
            } else {
                $row['comparison'] = 'Baseline';
            }
        }
        
        return $results;
    }

    /**
     * 2️⃣ Lịch sử giao dịch với khoảng trống (Return After Long Break)
     */
    private function getPurchaseHistory($custCode, $monthsBack = 24) {
        $sql = "SELECT 
                    RptYear,
                    RptMonth,
                    SUM(TotalNetAmount) as monthly_sales,
                    COUNT(DISTINCT OrderNumber) as monthly_orders
                FROM orderdetail
                WHERE CustCode = ?
                AND OrderDate >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                GROUP BY RptYear, RptMonth
                ORDER BY RptYear DESC, RptMonth DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$custCode, $monthsBack]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as &$row) {
            $row['period'] = "Tháng {$row['RptMonth']}/{$row['RptYear']}";
            $row['value'] = number_format($row['monthly_sales'], 0, ',', '.');
            $row['orders'] = $row['monthly_orders'];
        }
        
        return $results;
    }

    /**
     * 3️⃣ Chi tiết đơn hàng theo ngày trong tháng (Checkpoint Rush)
     */
    private function getOrdersByDayOfMonth($custCode, $years, $months) {
        $yearPlaceholders = implode(',', array_fill(0, count($years), '?'));
        $monthPlaceholders = implode(',', array_fill(0, count($months), '?'));
        
        $sql = "SELECT 
                    DAY(OrderDate) as day_of_month,
                    COUNT(DISTINCT OrderNumber) as order_count,
                    SUM(TotalNetAmount) as daily_amount,
                    CASE 
                        WHEN DAY(OrderDate) BETWEEN 12 AND 14 THEN 'Giữa tháng'
                        WHEN DAY(OrderDate) BETWEEN 26 AND 28 THEN 'Cuối tháng'
                        ELSE 'Ngày thường'
                    END as checkpoint_type
                FROM orderdetail
                WHERE CustCode = ?
                AND RptYear IN ($yearPlaceholders)
                AND RptMonth IN ($monthPlaceholders)
                GROUP BY DAY(OrderDate)
                ORDER BY order_count DESC";
        
        $params = array_merge([$custCode], $years, $months);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as &$row) {
            $row['period'] = "Ngày {$row['day_of_month']}";
            $row['value'] = number_format($row['daily_amount'], 0, ',', '.');
            $row['comparison'] = "{$row['order_count']} đơn - {$row['checkpoint_type']}";
        }
        
        return $results;
    }

    /**
     * 4️⃣ Phân bổ sản phẩm (Product Concentration)
     */
    private function getProductDistribution($custCode, $years, $months) {
        $yearPlaceholders = implode(',', array_fill(0, count($years), '?'));
        $monthPlaceholders = implode(',', array_fill(0, count($months), '?'));
        
        $sql = "SELECT 
                    SUBSTRING(ProductCode, 1, 2) as product_type,
                    SUM(Qty) as total_qty,
                    SUM(TotalNetAmount) as total_amount,
                    COUNT(DISTINCT OrderNumber) as order_count
                FROM orderdetail
                WHERE CustCode = ?
                AND RptYear IN ($yearPlaceholders)
                AND RptMonth IN ($monthPlaceholders)
                GROUP BY SUBSTRING(ProductCode, 1, 2)
                ORDER BY total_qty DESC";
        
        $params = array_merge([$custCode], $years, $months);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $totalQty = array_sum(array_column($results, 'total_qty'));
        
        foreach ($results as &$row) {
            $row['period'] = "Loại SP: {$row['product_type']}";
            $row['value'] = number_format($row['total_qty'], 0, ',', '.') . ' đơn vị';
            $percentage = $totalQty > 0 ? round(($row['total_qty'] / $totalQty) * 100, 1) : 0;
            $row['comparison'] = "{$percentage}% tổng SL";
        }
        
        return $results;
    }

    /**
     * 5️⃣ Chi tiết sản phẩm thường mua
     */
    private function getUsualProductsDetail($custCode) {
        $sql = "SELECT 
                    SUBSTRING(ProductCode, 1, 2) as product_type,
                    COUNT(*) as frequency,
                    SUM(TotalNetAmount) as total_amount
                FROM orderdetail
                WHERE CustCode = ?
                AND OrderDate >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY SUBSTRING(ProductCode, 1, 2)
                HAVING frequency >= 2
                ORDER BY frequency DESC
                LIMIT 10";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$custCode]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 5️⃣ Chi tiết sản phẩm mới
     */
    private function getNewProductsDetail($custCode, $years, $months, $newTypes) {
        if (empty($newTypes)) return [];
        
        $yearPlaceholders = implode(',', array_fill(0, count($years), '?'));
        $monthPlaceholders = implode(',', array_fill(0, count($months), '?'));
        $typePlaceholders = implode(',', array_fill(0, count($newTypes), '?'));
        
        $sql = "SELECT 
                    SUBSTRING(ProductCode, 1, 2) as product_type,
                    SUM(Qty) as total_qty,
                    SUM(TotalNetAmount) as total_amount,
                    COUNT(DISTINCT OrderNumber) as order_count
                FROM orderdetail
                WHERE CustCode = ?
                AND RptYear IN ($yearPlaceholders)
                AND RptMonth IN ($monthPlaceholders)
                AND SUBSTRING(ProductCode, 1, 2) IN ($typePlaceholders)
                GROUP BY SUBSTRING(ProductCode, 1, 2)
                ORDER BY total_amount DESC";
        
        $params = array_merge([$custCode], $years, $months, array_values($newTypes));
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as &$row) {
            $row['period'] = "SP mới: {$row['product_type']}";
            $row['value'] = number_format($row['total_amount'], 0, ',', '.');
            $row['comparison'] = "{$row['order_count']} đơn";
        }
        
        return $results;
    }

    /**
     * 6️⃣ Chi tiết đơn hàng theo ngày (Burst Orders)
     */
    private function getDailyOrdersDetail($custCode, $years, $months) {
        $yearPlaceholders = implode(',', array_fill(0, count($years), '?'));
        $monthPlaceholders = implode(',', array_fill(0, count($months), '?'));
        
        $sql = "SELECT 
                    DATE(OrderDate) as order_date,
                    COUNT(DISTINCT OrderNumber) as order_count,
                    SUM(TotalNetAmount) as daily_sales
                FROM orderdetail
                WHERE CustCode = ?
                AND RptYear IN ($yearPlaceholders)
                AND RptMonth IN ($monthPlaceholders)
                GROUP BY DATE(OrderDate)
                ORDER BY order_count DESC
                LIMIT 20";
        
        $params = array_merge([$custCode], $years, $months);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as &$row) {
            $row['period'] = date('d/m/Y', strtotime($row['order_date']));
            $row['value'] = number_format($row['daily_sales'], 0, ',', '.');
            $row['comparison'] = "{$row['order_count']} đơn";
        }
        
        return $results;
    }

    /**
     * 7️⃣ Phân bố giá trị đơn hàng (High Value Outlier)
     */
    private function getOrderValueDistribution($custCode, $years, $months) {
        $yearPlaceholders = implode(',', array_fill(0, count($years), '?'));
        $monthPlaceholders = implode(',', array_fill(0, count($months), '?'));
        
        $sql = "SELECT 
                    OrderNumber,
                    OrderDate,
                    TotalNetAmount
                FROM orderdetail
                WHERE CustCode = ?
                AND RptYear IN ($yearPlaceholders)
                AND RptMonth IN ($monthPlaceholders)
                GROUP BY OrderNumber, OrderDate, TotalNetAmount
                ORDER BY TotalNetAmount DESC
                LIMIT 10";
        
        $params = array_merge([$custCode], $years, $months);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as &$row) {
            $row['period'] = "Đơn: {$row['OrderNumber']}";
            $row['value'] = number_format($row['TotalNetAmount'], 0, ',', '.');
            $row['comparison'] = date('d/m/Y', strtotime($row['OrderDate']));
        }
        
        return $results;
    }

    /**
     * 8️⃣ So sánh trước và sau spike (No Purchase After Spike)
     */
    private function getSpikeComparison($custCode, $years, $months) {
        $maxYear = max($years);
        $maxMonth = max($months);
        
        // Lấy kỳ spike
        $sql1 = "SELECT 
                    RptYear,
                    RptMonth,
                    SUM(TotalNetAmount) as sales,
                    COUNT(DISTINCT OrderNumber) as orders
                FROM orderdetail
                WHERE CustCode = ?
                AND RptYear IN (" . implode(',', array_fill(0, count($years), '?')) . ")
                AND RptMonth IN (" . implode(',', array_fill(0, count($months), '?')) . ")
                GROUP BY RptYear, RptMonth";
        
        $params1 = array_merge([$custCode], $years, $months);
        $stmt1 = $this->conn->prepare($sql1);
        $stmt1->execute($params1);
        $spike = $stmt1->fetchAll(PDO::FETCH_ASSOC);
        
        // Lấy 3 tháng sau
        $sql2 = "SELECT 
                    RptYear,
                    RptMonth,
                    SUM(TotalNetAmount) as sales,
                    COUNT(DISTINCT OrderNumber) as orders
                FROM orderdetail
                WHERE CustCode = ?
                AND (
                    (RptYear = ? AND RptMonth > ?)
                    OR (RptYear = ? + 1 AND RptMonth <= 3)
                )
                GROUP BY RptYear, RptMonth
                ORDER BY RptYear, RptMonth
                LIMIT 3";
        
        $stmt2 = $this->conn->prepare($sql2);
        $stmt2->execute([$custCode, $maxYear, $maxMonth, $maxYear]);
        $after = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        $results = [];
        
        foreach ($spike as $row) {
            $results[] = [
                'period' => "Tháng {$row['RptMonth']}/{$row['RptYear']} (Spike)",
                'value' => number_format($row['sales'], 0, ',', '.'),
                'comparison' => "{$row['orders']} đơn"
            ];
        }
        
        foreach ($after as $row) {
            $results[] = [
                'period' => "Tháng {$row['RptMonth']}/{$row['RptYear']} (Sau spike)",
                'value' => number_format($row['sales'], 0, ',', '.'),
                'comparison' => "{$row['orders']} đơn"
            ];
        }
        
        if (empty($after)) {
            $results[] = [
                'period' => 'Các tháng sau spike',
                'value' => '0',
                'comparison' => 'Không có giao dịch'
            ];
        }
        
        return $results;
    }

    // ============================================
    // CÁC HÀM KIỂM TRA DẤU HIỆU (GIỮ NGUYÊN)
    // ============================================

    private function checkSuddenSpike($metrics) {
        $currentSales = $metrics['total_sales'] ?? 0;
        $avgHistoricalSales = $metrics['historical']['avg_sales'] ?? 0;
        
        if ($currentSales == 0 || $avgHistoricalSales == 0) return 0;
        
        $increaseRate = (($currentSales - $avgHistoricalSales) / $avgHistoricalSales) * 100;
        
        if ($increaseRate >= 500) return 100;
        if ($increaseRate >= 400) return 90;
        if ($increaseRate >= 300) return 80;
        if ($increaseRate >= 200) return 65;
        if ($increaseRate >= 150) return 50;
        
        return 0;
    }

    private function checkReturnAfterLongBreak($metrics) {
        $historical = $metrics['historical'];
        $monthsGap = $historical['months_since_last_order'] ?? 0;
        
        if ($monthsGap < 3) return 0;
        
        $currentSales = $metrics['total_sales'] ?? 0;
        $lastSales = $historical['last_period_sales'] ?? 0;
        
        if ($lastSales == 0) {
            if ($currentSales >= 10000000) return 100;
            if ($currentSales >= 5000000) return 80;
            if ($currentSales >= 3000000) return 60;
            return 40;
        }
        
        $increaseRate = (($currentSales - $lastSales) / $lastSales) * 100;
        
        if ($monthsGap >= 6 && $increaseRate >= 200) return 100;
        if ($monthsGap >= 4 && $increaseRate >= 150) return 80;
        if ($monthsGap >= 3 && $increaseRate >= 100) return 60;
        
        return 0;
    }

    private function checkCheckpointRush($metrics) {
        $checkpointOrders = $metrics['total_checkpoint_orders'] ?? 0;
        $totalOrders = $metrics['total_orders'] ?? 0;
        
        if ($totalOrders == 0) return 0;
        
        $checkpointRatio = ($checkpointOrders / $totalOrders) * 100;
        $checkpointAmount = $metrics['checkpoint_amount'] ?? 0;
        $totalSales = $metrics['total_sales'] ?? 0;
        $amountRatio = $totalSales > 0 ? ($checkpointAmount / $totalSales) * 100 : 0;
        
        if ($checkpointRatio >= 80 && $amountRatio >= 80) return 100;
        if ($checkpointRatio >= 70 && $amountRatio >= 70) return 85;
        if ($checkpointRatio >= 60 && $amountRatio >= 60) return 70;
        if ($checkpointRatio >= 50 || $amountRatio >= 50) return 55;
        
        return 0;
    }

    private function checkProductConcentration($metrics) {
        $distinctTypes = $metrics['distinct_product_types'] ?? 0;
        $productDetails = $metrics['product_details'] ?? [];
        
        if ($distinctTypes <= 1 && !empty($productDetails)) {
            $topProduct = $productDetails[0] ?? [];
            $topQty = $topProduct['total_qty'] ?? 0;
            $totalQty = $metrics['total_qty'] ?? 0;
            
            if ($totalQty > 0) {
                $concentration = ($topQty / $totalQty) * 100;
                
                if ($concentration >= 95 && $topQty >= 100) return 100;
                if ($concentration >= 90) return 85;
                if ($concentration >= 80) return 70;
            }
        } elseif ($distinctTypes == 2) {
            if (!empty($productDetails)) {
                $topProduct = $productDetails[0] ?? [];
                $topQty = $topProduct['total_qty'] ?? 0;
                $totalQty = $metrics['total_qty'] ?? 0;
                
                if ($totalQty > 0) {
                    $concentration = ($topQty / $totalQty) * 100;
                    if ($concentration >= 85) return 60;
                    if ($concentration >= 75) return 45;
                }
            }
        }
        
        return 0;
    }

    private function checkUnusualProductPattern($metrics) {
        $usualProducts = $metrics['usual_products'] ?? [];
        $productDetails = $metrics['product_details'] ?? [];
        
        if (empty($usualProducts) || empty($productDetails)) return 0;
        
        $currentTypes = array_column($productDetails, 'product_type');
        $usualTypes = array_map(function($code) {
            return substr($code, 0, 2);
        }, $usualProducts);
        $usualTypes = array_unique($usualTypes);
        
        $newTypes = array_diff($currentTypes, $usualTypes);
        if (empty($newTypes)) return 0;
        
        $newRatio = (count($newTypes) / count($currentTypes)) * 100;
        
        $newProductSales = 0;
        $totalSales = $metrics['total_sales'] ?? 0;
        foreach ($productDetails as $product) {
            if (in_array($product['product_type'], $newTypes)) {
                $newProductSales += $product['total_amount'];
            }
        }
        
        $newSalesRatio = $totalSales > 0 ? ($newProductSales / $totalSales) * 100 : 0;
        
        if ($newRatio >= 80 && $newSalesRatio >= 70) return 100;
        if ($newRatio >= 60 && $newSalesRatio >= 50) return 80;
        if ($newRatio >= 40 || $newSalesRatio >= 40) return 60;
        
        return 0;
    }

    private function checkBurstOrders($metrics) {
        $dailyOrders = $metrics['daily_orders'] ?? [];
        if (empty($dailyOrders)) return 0;
        
        $maxOrdersInDay = 0;
        $consecutiveDays = 0;
        $maxConsecutive = 0;
        
        foreach ($dailyOrders as $day => $data) {
            $ordersCount = $data['order_count'];
            if ($ordersCount > $maxOrdersInDay) $maxOrdersInDay = $ordersCount;
            
            if ($ordersCount >= 3) {
                $consecutiveDays++;
                if ($consecutiveDays > $maxConsecutive) $maxConsecutive = $consecutiveDays;
            } else {
                $consecutiveDays = 0;
            }
        }
        
        $totalOrders = $metrics['total_orders'] ?? 0;
        $distinctDays = $metrics['distinct_order_days'] ?? 1;
        $avgOrdersPerDay = $totalOrders / $distinctDays;
        
        if ($maxOrdersInDay >= 10 && $maxConsecutive >= 3) return 100;
        if ($maxOrdersInDay >= 8 && $maxConsecutive >= 2) return 85;
        if ($maxOrdersInDay >= 6) return 70;
        if ($maxOrdersInDay >= 5 && $maxOrdersInDay > $avgOrdersPerDay * 3) return 60;
        
        return 0;
    }

    private function checkHighValueOutlier($metrics) {
        $maxOrderValue = $metrics['max_order_value'] ?? 0;
        $avgOrderValue = $metrics['avg_order_value'] ?? 0;
        $stddevOrderValue = $metrics['stddev_order_value'] ?? 0;
        
        if ($avgOrderValue == 0 || $stddevOrderValue == 0) return 0;
        
        $sigmaCount = ($maxOrderValue - $avgOrderValue) / $stddevOrderValue;
        
        if ($sigmaCount >= 5) return 100;
        if ($sigmaCount >= 4) return 85;
        if ($sigmaCount >= 3) return 70;
        if ($sigmaCount >= 2.5) return 50;
        
        return 0;
    }

    private function checkNoPurchaseAfterSpike($metrics) {
        $spikeScore = $this->checkSuddenSpike($metrics);
        if ($spikeScore < 50) return 0;
        
        $futureActivity = $metrics['future_activity'] ?? [];
        $hasActivity = $futureActivity['has_orders'] ?? false;
        
        if (!$hasActivity) return 100;
        
        $currentSales = $metrics['total_sales'] ?? 0;
        $futureSales = $futureActivity['total_sales'] ?? 0;
        
        if ($currentSales > 0) {
            $dropRate = (($currentSales - $futureSales) / $currentSales) * 100;
            
            if ($dropRate >= 90) return 85;
            if ($dropRate >= 80) return 70;
            if ($dropRate >= 70) return 55;
        }
        
        return 0;
    }

    // ============================================
    // HÀM HỖ TRỢ CƠ BẢN
    // ============================================

    private function getBatchMetrics($customerCodes, $years, $months) {
        $placeholders = implode(',', array_fill(0, count($customerCodes), '?'));
        $yearPlaceholders = implode(',', array_fill(0, count($years), '?'));
        $monthPlaceholders = implode(',', array_fill(0, count($months), '?'));
        
        $sql = "SELECT 
                    o.CustCode,
                    COUNT(DISTINCT o.OrderNumber) as total_orders,
                    SUM(o.TotalNetAmount) as total_sales,
                    SUM(o.Qty) as total_qty,
                    AVG(o.TotalNetAmount) as avg_order_value,
                    MAX(o.TotalNetAmount) as max_order_value,
                    STDDEV(o.TotalNetAmount) as stddev_order_value,
                    COUNT(DISTINCT SUBSTRING(o.ProductCode, 1, 2)) as distinct_product_types,
                    
                    SUM(CASE WHEN DAY(o.OrderDate) BETWEEN 12 AND 14 THEN 1 ELSE 0 END) as mid_checkpoint_orders,
                    SUM(CASE WHEN DAY(o.OrderDate) BETWEEN 26 AND 28 THEN 1 ELSE 0 END) as end_checkpoint_orders,
                    SUM(CASE WHEN DAY(o.OrderDate) BETWEEN 12 AND 14 
                             OR DAY(o.OrderDate) BETWEEN 26 AND 28 THEN 1 ELSE 0 END) as total_checkpoint_orders,
                    SUM(CASE WHEN DAY(o.OrderDate) BETWEEN 12 AND 14 
                             OR DAY(o.OrderDate) BETWEEN 26 AND 28 
                             THEN o.TotalNetAmount ELSE 0 END) as checkpoint_amount,
                    
                    COUNT(DISTINCT DATE(o.OrderDate)) as distinct_order_days
                    
                FROM orderdetail o
                WHERE o.CustCode IN ($placeholders)
                AND o.RptYear IN ($yearPlaceholders)
                AND o.RptMonth IN ($monthPlaceholders)
                GROUP BY o.CustCode";
        
        $params = array_merge($customerCodes, $years, $months);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['historical'] = $this->getHistoricalData($row['CustCode'], $years, $months);
            $row['product_details'] = $this->getProductDetails($row['CustCode'], $years, $months);
            $row['usual_products'] = $this->getUsualProducts($row['CustCode']);
            $row['daily_orders'] = $this->getDailyOrders($row['CustCode'], $years, $months);
            $row['future_activity'] = $this->getFutureActivity($row['CustCode'], $years, $months);
            
            $results[$row['CustCode']] = $row;
        }
        
        return $results;
    }

    private function getHistoricalData($custCode, $years, $months) {
        $minYear = min($years);
        $minMonth = min($months);
        
        $sql = "SELECT 
                    COALESCE(AVG(monthly_sales), 0) as avg_sales,
                    COALESCE(MAX(monthly_sales), 0) as max_sales,
                    COALESCE(AVG(monthly_orders), 0) as avg_orders,
                    COUNT(*) as months_count,
                    MAX(order_date) as last_order_date,
                    SUM(monthly_sales) as last_period_sales
                FROM (
                    SELECT 
                        RptYear, 
                        RptMonth,
                        SUM(TotalNetAmount) as monthly_sales,
                        COUNT(DISTINCT OrderNumber) as monthly_orders,
                        MAX(OrderDate) as order_date
                    FROM orderdetail
                    WHERE CustCode = ?
                    AND (
                        RptYear < ?
                        OR (RptYear = ? AND RptMonth < ?)
                    )
                    GROUP BY RptYear, RptMonth
                    ORDER BY RptYear DESC, RptMonth DESC
                    LIMIT 6
                ) as prev_months";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$custCode, $minYear, $minYear, $minMonth]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!empty($result['last_order_date'])) {
            $lastDate = new DateTime($result['last_order_date']);
            $currentDate = new DateTime($minYear . '-' . str_pad($minMonth, 2, '0', STR_PAD_LEFT) . '-01');
            $interval = $lastDate->diff($currentDate);
            $result['months_since_last_order'] = $interval->m + ($interval->y * 12);
        } else {
            $result['months_since_last_order'] = 99;
        }
        
        return $result;
    }

    private function getProductDetails($custCode, $years, $months) {
        $yearPlaceholders = implode(',', array_fill(0, count($years), '?'));
        $monthPlaceholders = implode(',', array_fill(0, count($months), '?'));
        
        $sql = "SELECT 
                    SUBSTRING(ProductCode, 1, 2) as product_type,
                    SUM(Qty) as total_qty,
                    SUM(TotalNetAmount) as total_amount,
                    COUNT(DISTINCT OrderNumber) as order_count
                FROM orderdetail
                WHERE CustCode = ?
                AND RptYear IN ($yearPlaceholders)
                AND RptMonth IN ($monthPlaceholders)
                GROUP BY SUBSTRING(ProductCode, 1, 2)
                ORDER BY total_qty DESC";
        
        $params = array_merge([$custCode], $years, $months);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getUsualProducts($custCode) {
        $sql = "SELECT DISTINCT ProductCode
                FROM orderdetail
                WHERE CustCode = ?
                AND OrderDate >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY ProductCode
                HAVING COUNT(*) >= 2";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$custCode]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getDailyOrders($custCode, $years, $months) {
        $yearPlaceholders = implode(',', array_fill(0, count($years), '?'));
        $monthPlaceholders = implode(',', array_fill(0, count($months), '?'));
        
        $sql = "SELECT 
                    DATE(OrderDate) as order_date,
                    COUNT(DISTINCT OrderNumber) as order_count,
                    SUM(TotalNetAmount) as daily_sales
                FROM orderdetail
                WHERE CustCode = ?
                AND RptYear IN ($yearPlaceholders)
                AND RptMonth IN ($monthPlaceholders)
                GROUP BY DATE(OrderDate)
                ORDER BY order_date";
        
        $params = array_merge([$custCode], $years, $months);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[$row['order_date']] = $row;
        }
        return $results;
    }

    private function getFutureActivity($custCode, $years, $months) {
        $maxYear = max($years);
        $maxMonth = max($months);
        
        $sql = "SELECT 
                    COUNT(DISTINCT OrderNumber) as order_count,
                    SUM(TotalNetAmount) as total_sales
                FROM orderdetail
                WHERE CustCode = ?
                AND (
                    (RptYear = ? AND RptMonth > ?)
                    OR (RptYear > ? AND RptYear <= ?)
                )
                LIMIT 100";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$custCode, $maxYear, $maxMonth, $maxYear, $maxYear + 1]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'has_orders' => ($result['order_count'] ?? 0) > 0,
            'order_count' => $result['order_count'] ?? 0,
            'total_sales' => $result['total_sales'] ?? 0
        ];
    }

    private function getAnomalyDescription($type, $score, $metrics = []) {
        $descriptions = [
            'sudden_spike' => 'Doanh số tăng đột biến so với trung bình 3-6 tháng trước',
            'return_after_long_break' => 'Quay lại mua sau thời gian dài không hoạt động và mua với số lượng lớn',
            'checkpoint_rush' => 'Tập trung mua hàng vào thời điểm chốt số (12-14 và 26-28)',
            'product_concentration' => 'Chỉ mua 1 loại sản phẩm duy nhất',
            'unusual_product_pattern' => 'Mua sản phẩm mới khác lạ với thói quen trước đó',
            'burst_orders' => 'Mua dồn dập nhiều đơn trong thời gian rất ngắn',
            'high_value_outlier' => 'Có đơn hàng giá trị cao bất thường (>3σ)',
            'no_purchase_after_spike' => 'Không có hoạt động sau khi tăng đột biến'
        ];
        
        $severity = '';
        if ($score >= 90) $severity = ' (Mức độ: Cực kỳ nghiêm trọng)';
        elseif ($score >= 70) $severity = ' (Mức độ: Nghiêm trọng)';
        elseif ($score >= 50) $severity = ' (Mức độ: Cao)';
        
        return ($descriptions[$type] ?? 'Bất thường') . $severity;
    }


    private function getRiskLevel($score) {
        if ($score >= 60) return 'critical';
        if ($score >= 40) return 'high';
        if ($score >= 25) return 'medium';
        if ($score >= 10) return 'low';
        return 'normal';
    }

    /**
     * ✅ HÀM CHÍNH: TÍNH ĐIỂM BẤT THƯỜNG CHO TẤT CẢ KHÁCH HÀNG
     * Được gọi từ AnomalyController->index() và exportCSV()
     */
    public function calculateAnomalyScores($years, $months, $filters = []) {
        // Lấy danh sách khách hàng theo filters
        $customers = $this->getFilteredCustomers($years, $months, $filters);
        
        if (empty($customers)) {
            return [];
        }
        
        // Lấy metrics cho tất cả khách hàng (batch processing)
        $customerCodes = array_column($customers, 'CustCode');
        $metricsData = $this->getBatchMetrics($customerCodes, $years, $months);
        
        $results = [];
        
        foreach ($customers as $customer) {
            $custCode = $customer['CustCode'];
            $metrics = $metricsData[$custCode] ?? null;
            
            if (!$metrics) continue;
            
            // Lưu lại years và months để dùng trong getDetailMetrics
            $metrics['years'] = $years;
            $metrics['months'] = $months;
            $metrics['CustCode'] = $custCode;
            
            // Tính điểm cho từng dấu hiệu
            $scores = [
                'sudden_spike' => $this->checkSuddenSpike($metrics),
                'return_after_long_break' => $this->checkReturnAfterLongBreak($metrics),
                'checkpoint_rush' => $this->checkCheckpointRush($metrics),
                'product_concentration' => $this->checkProductConcentration($metrics),
                'unusual_product_pattern' => $this->checkUnusualProductPattern($metrics),
                'burst_orders' => $this->checkBurstOrders($metrics),
                'high_value_outlier' => $this->checkHighValueOutlier($metrics),
                'no_purchase_after_spike' => $this->checkNoPurchaseAfterSpike($metrics)
            ];
            
            // Tính tổng điểm có trọng số
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
                        'description' => $this->getAnomalyDescription($type, $score, $metrics),
                        'weight' => self::WEIGHTS[$type]
                    ];
                }
            }
            
            // Chỉ thêm vào kết quả nếu có bất thường
            if ($totalScore >= 10) {
                // Sắp xếp details theo điểm giảm dần
                usort($details, function($a, $b) {
                    return $b['weighted_score'] <=> $a['weighted_score'];
                });
                
                $results[] = [
                    'customer_code' => $custCode,
                    'customer_name' => $customer['TenKH'] ?? 'N/A',
                    'province' => $customer['Tinh'] ?? 'N/A',
                    'district' => $customer['QuanHuyen'] ?? 'N/A',
                    'total_score' => round($totalScore, 2),
                    'risk_level' => $this->getRiskLevel($totalScore),
                    'anomaly_count' => count($details),
                    'details' => $details,
                    'total_sales' => $metrics['total_sales'] ?? 0,
                    'total_orders' => $metrics['total_orders'] ?? 0,
                    'gkhl_status' => $customer['KhopSDT'] ?? null
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
     * ✅ LẤY DANH SÁCH KHÁCH HÀNG THEO FILTERS
     */
    private function getFilteredCustomers($years, $months, $filters = []) {
        $yearPlaceholders = implode(',', array_fill(0, count($years), '?'));
        $monthPlaceholders = implode(',', array_fill(0, count($months), '?'));
        
        $sql = "SELECT DISTINCT 
                    o.CustCode,
                    d.TenKH,
                    d.Tinh,
                    d.QuanHuyen,
                    g.KhopSDT
                FROM orderdetail o
                LEFT JOIN dskh d ON o.CustCode = d.MaKH
                LEFT JOIN gkhl g ON o.CustCode = g.MaKHDMS
                WHERE o.RptYear IN ($yearPlaceholders)
                AND o.RptMonth IN ($monthPlaceholders)";
        
        $params = array_merge($years, $months);
        
        // Filter theo tỉnh/thành phố
        if (!empty($filters['ma_tinh_tp'])) {
            $sql .= " AND d.Tinh = ?";
            $params[] = $filters['ma_tinh_tp'];
        }
        
        // Filter theo trạng thái GKHL
        if (isset($filters['gkhl_status']) && $filters['gkhl_status'] !== '') {
            if ($filters['gkhl_status'] === '1') {
                $sql .= " AND g.KhopSDT = 'Y'";
            } elseif ($filters['gkhl_status'] === '0') {
                $sql .= " AND (g.KhopSDT IS NULL OR g.KhopSDT != 'Y')";
            }
        }
        
        $sql .= " GROUP BY o.CustCode, d.TenKH, d.Tinh, d.QuanHuyen, g.KhopSDT
                  HAVING COUNT(DISTINCT o.OrderNumber) >= 3
                  ORDER BY o.CustCode";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}