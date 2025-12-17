    <?php
    require_once 'config/database.php';

    /**
     * ✅ PHIÊN BẢN HOÀN CHỈNH - PHÁT HIỆN BẤT THƯỜNG
     * 
     * 8 DẤU HIỆU CHÍNH:
     * 1. Mua đột biến so với tháng trước
     * 2. Quay lại sau nghỉ dài + mua đột biến
     * 3. Mua tập trung checkpoint (12-14, 26-28)
     * 4. Chỉ mua 1 loại sản phẩm
     * 5. Mua sản phẩm khác lạ
     * 6. Mua dồn dập trong thời gian ngắn
     * 7. Giá trị đơn >3σ
     * 8. Không mua sau spike
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
         * ✅ HÀM CHÍNH - TÍNH ĐIỂM BẤT THƯỜNG
         */
        public function calculateAnomalyScores($years, $months, $filters = []) {
            $customers = $this->getCustomersWithHistory($years, $months, $filters);
            
            if (empty($customers)) {
                return [];
            }
            
            $customerCodes = array_column($customers, 'ma_khach_hang');
            $metricsData = $this->getBatchMetrics($customerCodes, $years, $months);
            
            $results = [];
            
            foreach ($customers as $customer) {
                $custCode = $customer['ma_khach_hang'];
                $metrics = $metricsData[$custCode] ?? [];
                
                if (empty($metrics)) {
                    continue;
                }
                
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
                        $details[$type] = [
                            'raw_score' => $score,
                            'weighted_score' => $weightedScore,
                            'description' => $this->getAnomalyDescription($type, $score, $metrics)
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

        /**
         * ✅ LẤY DANH SÁCH KHÁCH HÀNG
         */
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
            
            if (!empty($filters['ma_tinh_tp'])) {
                $sql .= " AND d.Tinh = ?";
                $params[] = $filters['ma_tinh_tp'];
            }
            
            if (isset($filters['gkhl_status']) && $filters['gkhl_status'] !== '') {
                if ($filters['gkhl_status'] === '1') {
                    $sql .= " AND g.MaKHDMS IS NOT NULL";
                } elseif ($filters['gkhl_status'] === '0') {
                    $sql .= " AND g.MaKHDMS IS NULL";
                }
            }
            
            $sql .= " GROUP BY o.CustCode, d.TenKH
                    HAVING total_doanh_so > 0
                    ORDER BY total_doanh_so DESC
                    LIMIT 500";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        /**
         * ✅ LẤY METRICS CHI TIẾT
         */
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
                        
                        MIN(o.OrderDate) as first_order_date,
                        MAX(o.OrderDate) as last_order_date,
                        COUNT(DISTINCT DATE(o.OrderDate)) as distinct_order_days,
                        
                        GROUP_CONCAT(DISTINCT CONCAT(o.RptYear, '-', LPAD(o.RptMonth, 2, '0')) 
                                    ORDER BY o.RptYear, o.RptMonth) as months_active
                        
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

        /**
         * ✅ 1. MUA ĐỘT BIẾN
         */
        private function checkSuddenSpike($metrics) {
            $currentSales = $metrics['total_sales'] ?? 0;
            $avgHistoricalSales = $metrics['historical']['avg_sales'] ?? 0;
            
            if ($currentSales == 0 || $avgHistoricalSales == 0) {
                return 0;
            }
            
            $increaseRate = (($currentSales - $avgHistoricalSales) / $avgHistoricalSales) * 100;
            
            if ($increaseRate >= 500) return 100;
            if ($increaseRate >= 400) return 90;
            if ($increaseRate >= 300) return 80;
            if ($increaseRate >= 200) return 65;
            if ($increaseRate >= 150) return 50;
            
            return 0;
        }

        /**
         * ✅ 2. QUAY LẠI SAU NGHỈ DÀI
         */
        private function checkReturnAfterLongBreak($metrics) {
            $historical = $metrics['historical'];
            $monthsGap = $historical['months_since_last_order'] ?? 0;
            
            if ($monthsGap < 3) {
                return 0;
            }
            
            $currentSales = $metrics['total_sales'] ?? 0;
            $lastSales = $historical['last_period_sales'] ?? 0;
            
            if ($lastSales == 0) {
                if ($currentSales >= 10000000) return 100;
                if ($currentSales >= 5000000) return 80;
                if ($currentSales >= 3000000) return 60;
                return 40;
            }
            
            $increaseRate = (($currentSales - $lastSales) / $lastSales) * 100;
            
            $score = 0;
            if ($monthsGap >= 6 && $increaseRate >= 200) {
                $score = 100;
            } elseif ($monthsGap >= 4 && $increaseRate >= 150) {
                $score = 80;
            } elseif ($monthsGap >= 3 && $increaseRate >= 100) {
                $score = 60;
            }
            
            return $score;
        }

        /**
         * ✅ 3. MUA TẬP TRUNG CHECKPOINT
         */
        private function checkCheckpointRush($metrics) {
            $checkpointOrders = $metrics['total_checkpoint_orders'] ?? 0;
            $totalOrders = $metrics['total_orders'] ?? 0;
            
            if ($totalOrders == 0) {
                return 0;
            }
            
            $checkpointRatio = ($checkpointOrders / $totalOrders) * 100;
            $checkpointAmount = $metrics['checkpoint_amount'] ?? 0;
            $totalSales = $metrics['total_sales'] ?? 0;
            $amountRatio = $totalSales > 0 ? ($checkpointAmount / $totalSales) * 100 : 0;
            
            if ($checkpointRatio >= 80 && $amountRatio >= 80) {
                return 100;
            } elseif ($checkpointRatio >= 70 && $amountRatio >= 70) {
                return 85;
            } elseif ($checkpointRatio >= 60 && $amountRatio >= 60) {
                return 70;
            } elseif ($checkpointRatio >= 50 || $amountRatio >= 50) {
                return 55;
            }
            
            return 0;
        }

        /**
         * ✅ 4. CHỈ MUA 1 LOẠI SẢN PHẨM
         */
        private function checkProductConcentration($metrics) {
            $distinctTypes = $metrics['distinct_product_types'] ?? 0;
            $productDetails = $metrics['product_details'] ?? [];
            
            if ($distinctTypes <= 1 && !empty($productDetails)) {
                $topProduct = $productDetails[0] ?? [];
                $topQty = $topProduct['total_qty'] ?? 0;
                $totalQty = $metrics['total_qty'] ?? 0;
                
                if ($totalQty > 0) {
                    $concentration = ($topQty / $totalQty) * 100;
                    
                    if ($concentration >= 95 && $topQty >= 100) {
                        return 100;
                    } elseif ($concentration >= 90) {
                        return 85;
                    } elseif ($concentration >= 80) {
                        return 70;
                    }
                }
            } elseif ($distinctTypes == 2) {
                if (!empty($productDetails)) {
                    $topProduct = $productDetails[0] ?? [];
                    $topQty = $topProduct['total_qty'] ?? 0;
                    $totalQty = $metrics['total_qty'] ?? 0;
                    
                    if ($totalQty > 0) {
                        $concentration = ($topQty / $totalQty) * 100;
                        
                        if ($concentration >= 85) {
                            return 60;
                        } elseif ($concentration >= 75) {
                            return 45;
                        }
                    }
                }
            }
            
            return 0;
        }

        /**
         * ✅ 5. MUA SẢN PHẨM KHÁC LẠ
         */
        private function checkUnusualProductPattern($metrics) {
            $usualProducts = $metrics['usual_products'] ?? [];
            $productDetails = $metrics['product_details'] ?? [];
            
            if (empty($usualProducts) || empty($productDetails)) {
                return 0;
            }
            
            $currentTypes = array_column($productDetails, 'product_type');
            
            $usualTypes = array_map(function($code) {
                return substr($code, 0, 2);
            }, $usualProducts);
            $usualTypes = array_unique($usualTypes);
            
            $newTypes = array_diff($currentTypes, $usualTypes);
            
            if (empty($newTypes)) {
                return 0;
            }
            
            $newRatio = (count($newTypes) / count($currentTypes)) * 100;
            
            $newProductSales = 0;
            $totalSales = $metrics['total_sales'] ?? 0;
            
            foreach ($productDetails as $product) {
                if (in_array($product['product_type'], $newTypes)) {
                    $newProductSales += $product['total_amount'];
                }
            }
            
            $newSalesRatio = $totalSales > 0 ? ($newProductSales / $totalSales) * 100 : 0;
            
            if ($newRatio >= 80 && $newSalesRatio >= 70) {
                return 100;
            } elseif ($newRatio >= 60 && $newSalesRatio >= 50) {
                return 80;
            } elseif ($newRatio >= 40 || $newSalesRatio >= 40) {
                return 60;
            }
            
            return 0;
        }

        /**
         * ✅ 6. MUA DỒN DẬP
         */
        private function checkBurstOrders($metrics) {
            $dailyOrders = $metrics['daily_orders'] ?? [];
            
            if (empty($dailyOrders)) {
                return 0;
            }
            
            $maxOrdersInDay = 0;
            $consecutiveDays = 0;
            $maxConsecutive = 0;
            
            foreach ($dailyOrders as $day => $data) {
                $ordersCount = $data['order_count'];
                
                if ($ordersCount > $maxOrdersInDay) {
                    $maxOrdersInDay = $ordersCount;
                }
                
                if ($ordersCount >= 3) {
                    $consecutiveDays++;
                    if ($consecutiveDays > $maxConsecutive) {
                        $maxConsecutive = $consecutiveDays;
                    }
                } else {
                    $consecutiveDays = 0;
                }
            }
            
            $totalOrders = $metrics['total_orders'] ?? 0;
            $distinctDays = $metrics['distinct_order_days'] ?? 1;
            $avgOrdersPerDay = $totalOrders / $distinctDays;
            
            if ($maxOrdersInDay >= 10 && $maxConsecutive >= 3) {
                return 100;
            } elseif ($maxOrdersInDay >= 8 && $maxConsecutive >= 2) {
                return 85;
            } elseif ($maxOrdersInDay >= 6) {
                return 70;
            } elseif ($maxOrdersInDay >= 5 && $maxOrdersInDay > $avgOrdersPerDay * 3) {
                return 60;
            }
            
            return 0;
        }

        /**
         * ✅ 7. GIÁ TRỊ ĐƠN >3σ
         */
        private function checkHighValueOutlier($metrics) {
            $maxOrderValue = $metrics['max_order_value'] ?? 0;
            $avgOrderValue = $metrics['avg_order_value'] ?? 0;
            $stddevOrderValue = $metrics['stddev_order_value'] ?? 0;
            
            if ($avgOrderValue == 0 || $stddevOrderValue == 0) {
                return 0;
            }
            
            $sigmaCount = ($maxOrderValue - $avgOrderValue) / $stddevOrderValue;
            
            if ($sigmaCount >= 5) {
                return 100;
            } elseif ($sigmaCount >= 4) {
                return 85;
            } elseif ($sigmaCount >= 3) {
                return 70;
            } elseif ($sigmaCount >= 2.5) {
                return 50;
            }
            
            return 0;
        }

        /**
         * ✅ 8. KHÔNG MUA SAU SPIKE
         */
        private function checkNoPurchaseAfterSpike($metrics) {
            $spikeScore = $this->checkSuddenSpike($metrics);
            
            if ($spikeScore < 50) {
                return 0;
            }
            
            $futureActivity = $metrics['future_activity'] ?? [];
            $hasActivity = $futureActivity['has_orders'] ?? false;
            
            if (!$hasActivity) {
                return 100;
            }
            
            $currentSales = $metrics['total_sales'] ?? 0;
            $futureSales = $futureActivity['total_sales'] ?? 0;
            
            if ($currentSales > 0) {
                $dropRate = (($currentSales - $futureSales) / $currentSales) * 100;
                
                if ($dropRate >= 90) {
                    return 85;
                } elseif ($dropRate >= 80) {
                    return 70;
                } elseif ($dropRate >= 70) {
                    return 55;
                }
            }
            
            return 0;
        }

        // ============================================
        // HÀM HỖ TRỢ
        // ============================================

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
         * ✅ LẤY METRICS CHI TIẾT CHO MODAL - THỰC TẾ TỪ DATABASE
         */
        private function getDetailMetrics($type, $metrics) {
            switch ($type) {
                case 'sudden_spike':
                    $current = $metrics['total_sales'] ?? 0;
                    $historical = $metrics['historical']['avg_sales'] ?? 0;
                    $increasePercent = $historical > 0 ? round((($current - $historical) / $historical) * 100, 1) : 0;
                    
                    return [
                        'current_sales' => $current,
                        'historical_avg' => $historical,
                        'increase_percent' => $increasePercent,
                        'difference' => $current - $historical
                    ];
                    
                case 'return_after_long_break':
                    $monthsGap = $metrics['historical']['months_since_last_order'] ?? 0;
                    $currentSales = $metrics['total_sales'] ?? 0;
                    $lastSales = $metrics['historical']['last_period_sales'] ?? 0;
                    $increasePercent = $lastSales > 0 ? round((($currentSales - $lastSales) / $lastSales) * 100, 1) : 0;
                    
                    return [
                        'months_gap' => $monthsGap,
                        'current_sales' => $currentSales,
                        'last_sales' => $lastSales,
                        'increase_percent' => $increasePercent
                    ];
                    
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
                        'end_checkpoint' => $metrics['end_checkpoint_orders'] ?? 0
                    ];
                    
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
                        'concentration_percent' => round(($topQty / $totalQty) * 100, 1)
                    ];
                    
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
                        'new_product_types' => implode(', ', $newTypes)
                    ];
                    
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
                        'avg_orders_per_day' => count($daily) > 0 ? round(($metrics['total_orders'] ?? 0) / count($daily), 2) : 0
                    ];
                    
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
                        'threshold_3sigma' => $avg + (3 * $std)
                    ];
                    
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
                        'has_activity' => $future['has_orders'] ?? false
                    ];
                    
                default:
                    return [];
            }
        }
    }