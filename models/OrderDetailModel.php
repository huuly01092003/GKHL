<?php
require_once 'config/database.php';

class OrderDetailModel {
    private $conn;
    private $table = "orderdetail";
    private const PAGE_SIZE = 100;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Import CSV từ file OrderDetail
    public function importCSV($filePath) {
        try {
            $this->conn->beginTransaction();
            
            $fileContent = file_get_contents($filePath);
            if (!mb_check_encoding($fileContent, 'UTF-8')) {
                $fileContent = mb_convert_encoding($fileContent, 'UTF-8', 'auto');
            }
            
            $rows = array_map(function($line) {
                return str_getcsv($line, ',', '"');
            }, explode("\n", $fileContent));
            
            $isFirstRow = true;
            $insertedCount = 0;
            
            $sql = "INSERT INTO {$this->table} (
                OrderNumber, OrderDate, CustCode, CustType, DistCode, DSRCode,
                DistGroup, DSRTypeProvince, ProductSaleType, ProductCode, Qty,
                TotalSchemeAmount, TotalGrossAmount, TotalNetAmount, RptMonth, RptYear
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                OrderDate = VALUES(OrderDate),
                CustType = VALUES(CustType),
                DistCode = VALUES(DistCode),
                DSRCode = VALUES(DSRCode),
                DistGroup = VALUES(DistGroup),
                DSRTypeProvince = VALUES(DSRTypeProvince),
                ProductSaleType = VALUES(ProductSaleType),
                ProductCode = VALUES(ProductCode),
                Qty = VALUES(Qty),
                TotalSchemeAmount = VALUES(TotalSchemeAmount),
                TotalGrossAmount = VALUES(TotalGrossAmount),
                TotalNetAmount = VALUES(TotalNetAmount),
                RptMonth = VALUES(RptMonth),
                RptYear = VALUES(RptYear)";
            
            $stmt = $this->conn->prepare($sql);
            
            foreach ($rows as $row) {
                if (empty($row) || count($row) < 17) {
                    continue;
                }
                
                if ($isFirstRow) {
                    $isFirstRow = false;
                    continue;
                }
                
                // Chuẩn bị dữ liệu
                $data = [
                    !empty(trim($row[1])) ? trim($row[1]) : null,  // OrderNumber
                    $this->convertDate($row[2]),                    // OrderDate
                    !empty(trim($row[3])) ? trim($row[3]) : null,  // CustCode
                    !empty(trim($row[4])) ? trim($row[4]) : null,  // CustType
                    !empty(trim($row[5])) ? trim($row[5]) : null,  // DistCode
                    !empty(trim($row[6])) ? trim($row[6]) : null,  // DSRCode
                    !empty(trim($row[7])) ? trim($row[7]) : null,  // DistGroup
                    !empty(trim($row[8])) ? trim($row[8]) : null,  // DSRTypeProvince
                    !empty(trim($row[9])) ? trim($row[9]) : null,  // ProductSaleType
                    !empty(trim($row[10])) ? trim($row[10]) : null, // ProductCode
                    $this->cleanNumber($row[11], true),             // Qty
                    $this->cleanNumber($row[12]),                   // TotalSchemeAmount
                    $this->cleanNumber($row[13]),                   // TotalGrossAmount
                    $this->cleanNumber($row[14]),                   // TotalNetAmount
                    $this->cleanNumber($row[15], true),             // RptMonth
                    $this->cleanNumber($row[16], true)              // RptYear
                ];
                
                // Bỏ qua nếu không có OrderNumber hoặc CustCode
                if (empty($data[0]) || empty($data[2])) {
                    continue;
                }
                
                $stmt->execute($data);
                
                if ($stmt->rowCount() > 0) {
                    $insertedCount++;
                }
            }
            
            $this->conn->commit();
            
            return ['success' => true, 'inserted' => $insertedCount];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Lấy danh sách khách hàng theo tháng/năm với tổng hợp
    public function getCustomerSummary($rptMonth, $rptYear, $filters = []) {
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
                    (CASE WHEN EXISTS (SELECT 1 FROM gkhl g WHERE g.MaKHDMS = o.CustCode) THEN 1 ELSE 0 END) AS has_gkhl
                FROM {$this->table} o
                LEFT JOIN dskh d ON o.CustCode = d.MaKH
                WHERE o.RptMonth = :rpt_month 
                AND o.RptYear = :rpt_year";
        
        $params = [
            ':rpt_month' => $rptMonth,
            ':rpt_year' => $rptYear
        ];
        
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
                $sql .= " AND EXISTS (SELECT 1 FROM gkhl g WHERE g.MaKHDMS = o.CustCode)";
            } else {
                $sql .= " AND NOT EXISTS (SELECT 1 FROM gkhl g WHERE g.MaKHDMS = o.CustCode)";
            }
        }
        
        $sql .= " GROUP BY o.CustCode
                  ORDER BY total_doanh_so DESC
                  LIMIT " . self::PAGE_SIZE;
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Lấy tổng số thống kê cho dashboard
    public function getSummaryStats($rptMonth, $rptYear, $filters = []) {
        $sql = "SELECT 
                    COUNT(DISTINCT o.CustCode) as total_khach_hang,
                    SUM(o.TotalNetAmount) as total_doanh_so,
                    SUM(o.Qty) as total_san_luong,
                    COUNT(DISTINCT CASE WHEN g.MaKHDMS IS NOT NULL THEN o.CustCode END) as total_gkhl
                FROM {$this->table} o
                LEFT JOIN dskh d ON o.CustCode = d.MaKH
                LEFT JOIN gkhl g ON g.MaKHDMS = o.CustCode
                WHERE o.RptMonth = :rpt_month 
                AND o.RptYear = :rpt_year";
        
        $params = [
            ':rpt_month' => $rptMonth,
            ':rpt_year' => $rptYear
        ];
        
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

    // Lấy chi tiết đơn hàng của khách hàng
    public function getCustomerDetail($custCode, $rptMonth, $rptYear) {
        $sql = "SELECT 
                    o.*,
                    d.TenKH as ten_khach_hang,
                    d.DiaChi as dia_chi_khach_hang,
                    d.Tinh as ma_tinh_tp
                FROM {$this->table} o
                LEFT JOIN dskh d ON o.CustCode = d.MaKH
                WHERE o.CustCode = :cust_code 
                AND o.RptMonth = :rpt_month
                AND o.RptYear = :rpt_year
                ORDER BY o.OrderDate DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':cust_code' => $custCode,
            ':rpt_month' => $rptMonth,
            ':rpt_year' => $rptYear
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Lấy danh sách tháng/năm có dữ liệu
    public function getMonthYears() {
        $sql = "SELECT DISTINCT 
                    CONCAT(RptMonth, '/', RptYear) as thang_nam,
                    RptYear, RptMonth
                FROM {$this->table}
                WHERE RptMonth IS NOT NULL AND RptYear IS NOT NULL
                ORDER BY RptYear DESC, RptMonth DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Lấy danh sách tỉnh
    public function getProvinces() {
        $sql = "SELECT DISTINCT d.Tinh 
                FROM dskh d
                WHERE d.Tinh IS NOT NULL AND d.Tinh != ''
                ORDER BY d.Tinh";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Lấy thông tin vị trí khách hàng
    public function getCustomerLocation($custCode) {
        $sql = "SELECT Location FROM dskh WHERE MaKH = :ma_kh LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':ma_kh' => $custCode]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['Location'] ?? null;
    }

    // Lấy thông tin GKHL
    public function getGkhlInfo($custCode) {
        $sql = "SELECT 
                    MaKHDMS, 
                    TenQuay,
                    DangKyChuongTrinh, 
                    DangKyMucDoanhSo, 
                    DangKyTrungBay,
                    KhopSDT
                FROM gkhl 
                WHERE MaKHDMS = :ma_kh_dms 
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':ma_kh_dms' => $custCode]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Hàm helper: Chuyển đổi ngày tháng
    private function convertDate($dateValue) {
        if (empty($dateValue) || $dateValue === 'NULL') return null;
        
        $dateValue = trim($dateValue);
        
        // Nếu là số (Excel serial date)
        if (is_numeric($dateValue)) {
            $unixDate = ($dateValue - 25569) * 86400;
            return date('Y-m-d', $unixDate);
        }
        
        // Nếu là định dạng M/D/YYYY
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dateValue, $matches)) {
            return $matches[3] . '-' . sprintf('%02d', $matches[1]) . '-' . sprintf('%02d', $matches[2]);
        }
        
        // Nếu đã là YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)) {
            return $dateValue;
        }
        
        $timestamp = strtotime($dateValue);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
        
        return null;
    }

    // Hàm helper: Làm sạch số
    private function cleanNumber($value, $asInteger = false) {
        if (empty($value) || $value === '' || $value === 'NULL') {
            return null;
        }
        
        $cleaned = str_replace([',', ' '], '', trim($value));
        
        if (is_numeric($cleaned)) {
            return $asInteger ? (int)$cleaned : $cleaned;
        }
        
        return null;
    }
}
?>