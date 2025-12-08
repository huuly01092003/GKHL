<?php
require_once 'config/database.php';

class OrderDetailModel {
    private $conn;
    private $table = "orderdetail";
    private const PAGE_SIZE = 100;
    private const BATCH_SIZE = 500;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function importCSV($filePath) {
        try {
            if (!file_exists($filePath)) {
                return ['success' => false, 'error' => 'File không tồn tại'];
            }

            $this->conn->beginTransaction();
            
            $insertedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;
            $batch = [];
            $isFirstRow = true;
            $lineCount = 0;

            $handle = fopen($filePath, 'r');
            if ($handle === false) {
                return ['success' => false, 'error' => 'Không thể mở file'];
            }

            $sql = "INSERT IGNORE INTO {$this->table} (
                OrderNumber, OrderDate, CustCode, CustType, DistCode, DSRCode,
                DistGroup, DSRTypeProvince, ProductSaleType, ProductCode, Qty,
                TotalSchemeAmount, TotalGrossAmount, TotalNetAmount, RptMonth, RptYear
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                return ['success' => false, 'error' => 'Lỗi prepare SQL: ' . implode(' | ', $this->conn->errorInfo())];

            }

            while (($line = fgets($handle)) !== false) {
                $lineCount++;
                $line = trim($line);

                if (empty($line)) continue;

                $row = str_getcsv($line, ',', '"');

                if ($isFirstRow) {
                    $isFirstRow = false;
                    continue;
                }

                if (count($row) < 16) {
                    $skippedCount++;
                    continue;
                }

                $startIndex = 0;
                if (!empty($row[0]) && is_numeric(trim($row[0])) && trim($row[0]) < 10000) {
                    $startIndex = 0;
                }

                $orderNumber = !empty(trim($row[$startIndex + 0])) ? trim($row[$startIndex + 0]) : null;
                $orderDate = $this->convertDate($row[$startIndex + 1]);
                $custCode = !empty(trim($row[$startIndex + 2])) ? trim($row[$startIndex + 2]) : null;
                $custType = !empty(trim($row[$startIndex + 3])) ? trim($row[$startIndex + 3]) : null;
                $distCode = !empty(trim($row[$startIndex + 4])) ? trim($row[$startIndex + 4]) : null;
                $dsrCode = !empty(trim($row[$startIndex + 5])) ? trim($row[$startIndex + 5]) : null;
                $distGroup = !empty(trim($row[$startIndex + 6])) ? trim($row[$startIndex + 6]) : null;
                $dsrTypeProvince = !empty(trim($row[$startIndex + 7])) ? trim($row[$startIndex + 7]) : null;
                $productSaleType = !empty(trim($row[$startIndex + 8])) ? trim($row[$startIndex + 8]) : null;
                $productCode = !empty(trim($row[$startIndex + 9])) ? trim($row[$startIndex + 9]) : null;
                $qty = $this->cleanNumber($row[$startIndex + 10], true);
                $totalSchemeAmount = $this->cleanNumber($row[$startIndex + 11]);
                $totalGrossAmount = $this->cleanNumber($row[$startIndex + 12]);
                $totalNetAmount = $this->cleanNumber($row[$startIndex + 13]);
                $rptMonth = $this->cleanNumber($row[$startIndex + 14], true);
                $rptYear = $this->cleanNumber($row[$startIndex + 15], true);

                if (empty($orderNumber) || empty($custCode) || empty($orderDate)) {
                    $skippedCount++;
                    continue;
                }

                if (empty($rptMonth) || empty($rptYear) || $rptMonth < 1 || $rptMonth > 12) {
                    $skippedCount++;
                    continue;
                }

                $data = [
                    $orderNumber, $orderDate, $custCode, $custType, $distCode, $dsrCode,
                    $distGroup, $dsrTypeProvince, $productSaleType, $productCode, $qty,
                    $totalSchemeAmount, $totalGrossAmount, $totalNetAmount, $rptMonth, $rptYear
                ];

                $batch[] = $data;

                if (count($batch) >= self::BATCH_SIZE) {
                    $result = $this->executeBatch($stmt, $batch);
                    $insertedCount += $result['inserted'];
                    $errorCount += $result['errors'];
                    $batch = [];
                    
                    if ($lineCount % 5000 === 0) {
                        gc_collect_cycles();
                    }
                }
            }

            fclose($handle);

            if (!empty($batch)) {
                $result = $this->executeBatch($stmt, $batch);
                $insertedCount += $result['inserted'];
                $errorCount += $result['errors'];
            }

            $this->conn->commit();
            
            return [
                'success' => true, 
                'inserted' => $insertedCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount,
                'total_lines' => $lineCount
            ];
        } catch (Exception $e) {
            if (isset($handle)) fclose($handle);
            $this->conn->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ✅ TỐI ƯU: Thay subquery EXISTS bằng LEFT JOIN + GROUP BY có điều kiện
    // ✅ CẬP NHẬT: Hỗ trợ filter theo nhiều năm và nhiều tháng
    public function getCustomerSummary($years = [], $months = [], $filters = []) {
        $sql = "SELECT 
                    o.CustCode as ma_khach_hang,
                    d.TenKH as ten_khach_hang,
                    d.DiaChi as dia_chi_khach_hang,
                    d.Tinh as ma_tinh_tp,
                    d.LoaiKH as loai_kh,
                    SUM(o.Qty) as total_san_luong,
                    SUM(o.TotalGrossAmount) as total_doanh_so_truoc_ck,
                    SUM(o.TotalSchemeAmount) as total_chiet_khau,
                    SUM(o.TotalNetAmount) as total_doanh_so,
                    MAX(CASE WHEN g.MaKHDMS IS NOT NULL THEN 1 ELSE 0 END) AS has_gkhl
                FROM {$this->table} o
                LEFT JOIN dskh d ON o.CustCode = d.MaKH
                LEFT JOIN gkhl g ON g.MaKHDMS = o.CustCode
                WHERE 1=1";
        
        $params = [];
        
        // Filter theo năm (có thể nhiều năm)
        if (!empty($years)) {
            $placeholders = [];
            foreach ($years as $idx => $year) {
                $key = ":year_$idx";
                $placeholders[] = $key;
                $params[$key] = $year;
            }
            $sql .= " AND o.RptYear IN (" . implode(',', $placeholders) . ")";
        }
        
        // Filter theo tháng (có thể nhiều tháng)
        if (!empty($months)) {
            $placeholders = [];
            foreach ($months as $idx => $month) {
                $key = ":month_$idx";
                $placeholders[] = $key;
                $params[$key] = $month;
            }
            $sql .= " AND o.RptMonth IN (" . implode(',', $placeholders) . ")";
        }
        
        if (!empty($filters['ma_tinh_tp'])) {
            $sql .= " AND d.Tinh = :ma_tinh_tp";
            $params[':ma_tinh_tp'] = $filters['ma_tinh_tp'];
        }
        
        if (!empty($filters['ma_khach_hang'])) {
            $sql .= " AND o.CustCode LIKE :ma_khach_hang";
            $params[':ma_khach_hang'] = '%' . $filters['ma_khach_hang'] . '%';
        }
        
        if (isset($filters['gkhl_status']) && $filters['gkhl_status'] !== '') {
            if ($filters['gkhl_status'] == '1') {
                $sql .= " AND g.MaKHDMS IS NOT NULL";
            } else {
                $sql .= " AND g.MaKHDMS IS NULL";
            }
        }
        
        $sql .= " GROUP BY o.CustCode, d.TenKH, d.DiaChi, d.Tinh, d.LoaiKH
                  ORDER BY total_doanh_so DESC
                  LIMIT " . self::PAGE_SIZE;
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ✅ TỐI ƯU: Query summary đơn giản hơn
    public function getSummaryStats($years = [], $months = [], $filters = []) {
        $sql = "SELECT 
                    COUNT(DISTINCT o.CustCode) as total_khach_hang,
                    COALESCE(SUM(o.TotalNetAmount), 0) as total_doanh_so,
                    COALESCE(SUM(o.Qty), 0) as total_san_luong,
                    COUNT(DISTINCT g.MaKHDMS) as total_gkhl
                FROM {$this->table} o
                LEFT JOIN dskh d ON o.CustCode = d.MaKH
                LEFT JOIN gkhl g ON g.MaKHDMS = o.CustCode
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($years)) {
            $placeholders = [];
            foreach ($years as $idx => $year) {
                $key = ":year_$idx";
                $placeholders[] = $key;
                $params[$key] = $year;
            }
            $sql .= " AND o.RptYear IN (" . implode(',', $placeholders) . ")";
        }
        
        if (!empty($months)) {
            $placeholders = [];
            foreach ($months as $idx => $month) {
                $key = ":month_$idx";
                $placeholders[] = $key;
                $params[$key] = $month;
            }
            $sql .= " AND o.RptMonth IN (" . implode(',', $placeholders) . ")";
        }
        
        if (!empty($filters['ma_tinh_tp'])) {
            $sql .= " AND d.Tinh = :ma_tinh_tp";
            $params[':ma_tinh_tp'] = $filters['ma_tinh_tp'];
        }
        
        if (!empty($filters['ma_khach_hang'])) {
            $sql .= " AND o.CustCode LIKE :ma_khach_hang";
            $params[':ma_khach_hang'] = '%' . $filters['ma_khach_hang'] . '%';
        }
        
        if (isset($filters['gkhl_status']) && $filters['gkhl_status'] !== '') {
            if ($filters['gkhl_status'] === '1') {
                $sql .= " AND g.MaKHDMS IS NOT NULL";
            } elseif ($filters['gkhl_status'] === '0') {
                $sql .= " AND g.MaKHDMS IS NULL";
            }
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getCustomerDetail($custCode, $years = [], $months = []) {
        $sql = "SELECT 
                    o.*,
                    d.TenKH, d.DiaChi, d.Tinh, d.MaSoThue,
                    d.MaGSBH, d.Area, d.PhanLoaiNhomKH, d.LoaiKH,
                    d.QuanHuyen, d.MaNPP, d.MaNVBH, d.TenNVBH
                FROM {$this->table} o
                LEFT JOIN dskh d ON o.CustCode = d.MaKH
                WHERE o.CustCode = :cust_code";
        
        $params = [':cust_code' => $custCode];
        
        if (!empty($years)) {
            $placeholders = [];
            foreach ($years as $idx => $year) {
                $key = ":year_$idx";
                $placeholders[] = $key;
                $params[$key] = $year;
            }
            $sql .= " AND o.RptYear IN (" . implode(',', $placeholders) . ")";
        }
        
        if (!empty($months)) {
            $placeholders = [];
            foreach ($months as $idx => $month) {
                $key = ":month_$idx";
                $placeholders[] = $key;
                $params[$key] = $month;
            }
            $sql .= " AND o.RptMonth IN (" . implode(',', $placeholders) . ")";
        }
        
        $sql .= " ORDER BY o.OrderDate DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ✅ TỐI ƯU: Giới hạn 24 tháng gần nhất
    public function getMonthYears() {
        $sql = "SELECT DISTINCT 
                    CONCAT(RptMonth, '/', RptYear) as thang_nam
                FROM {$this->table}
                WHERE RptMonth IS NOT NULL AND RptYear IS NOT NULL
                ORDER BY RptYear DESC, RptMonth DESC
                LIMIT 24";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // ✅ CẬP NHẬT: Lấy tỉnh mới từ dskh (join với orderdetail)
    public function getProvinces() {
        $sql = "SELECT DISTINCT d.Tinh 
                FROM dskh d
                INNER JOIN {$this->table} o ON o.CustCode = d.MaKH
                WHERE d.Tinh IS NOT NULL AND d.Tinh != ''
                ORDER BY d.Tinh";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    public function getCustomerLocation($custCode) {
        $sql = "SELECT Location FROM dskh WHERE MaKH = :ma_kh LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':ma_kh' => $custCode]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['Location'] ?? null;
    }

    public function getGkhlInfo($custCode) {
        $sql = "SELECT 
                    MaKHDMS, TenQuay, SDTZalo, SDTDaDinhDanh,
                    DangKyChuongTrinh, DangKyMucDoanhSo, 
                    DangKyTrungBay, KhopSDT
                FROM gkhl 
                WHERE MaKHDMS = :ma_kh_dms 
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':ma_kh_dms' => $custCode]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function executeBatch(&$stmt, $batch) {
        $inserted = 0;
        $errors = 0;
        
        foreach ($batch as $data) {
            try {
                if (!$stmt->execute($data)) {
                    $errors++;
                } else {
                    $inserted++;
                }
            } catch (Exception $e) {
                $errors++;
                error_log("OrderDetail Exception: " . $e->getMessage());
            }
        }
        
        return ['inserted' => $inserted, 'errors' => $errors];
    }

    private function convertDate($dateValue) {
        if (empty($dateValue) || $dateValue === 'NULL') return null;
        
        $dateValue = trim($dateValue);
        
        if (is_numeric($dateValue)) {
            $unixDate = ($dateValue - 25569) * 86400;
            return date('Y-m-d', $unixDate);
        }
        
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dateValue, $matches)) {
            return $matches[3] . '-' . sprintf('%02d', $matches[1]) . '-' . sprintf('%02d', $matches[2]);
        }
        
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)) {
            return $dateValue;
        }
        
        $timestamp = strtotime($dateValue);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
        
        return null;
    }

    private function cleanNumber($value, $asInteger = false) {
        if (empty($value) || $value === '' || $value === 'NULL') {
            return null;
        }
        
        $cleaned = str_replace([',', ' '], '', trim($value));
        
        if (is_numeric($cleaned)) {
            return $asInteger ? (int)$cleaned : (float)$cleaned;
        }
        
        return null;
    }

     // ✅ MỚI: Lấy danh sách năm có sẵn
    public function getAvailableYears() {
        $sql = "SELECT DISTINCT RptYear 
                FROM {$this->table}
                WHERE RptYear IS NOT NULL
                ORDER BY RptYear DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // ✅ MỚI: Lấy danh sách tháng (1-12)
    public function getAvailableMonths() {
        return range(1, 12);
    }
}
?>