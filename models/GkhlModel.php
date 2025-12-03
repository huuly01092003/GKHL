<?php
require_once 'config/database.php';

class GkhlModel {
    private $conn;
    private $table = "gkhl";
    private const PAGE_SIZE = 1000;
    private const BATCH_SIZE = 100;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function importCSV($filePath) {
        try {
            if (!file_exists($filePath)) {
                return ['success' => false, 'error' => 'File không tồn tại'];
            }

            // Tắt FK checks
            $this->conn->exec("SET FOREIGN_KEY_CHECKS=0");
            $this->conn->beginTransaction();
            
            $fileContent = file_get_contents($filePath);
            if (!mb_check_encoding($fileContent, 'UTF-8')) {
                $fileContent = mb_convert_encoding($fileContent, 'UTF-8', 'auto');
            }
            
            $rows = array_map(function($line) {
                return str_getcsv($line, ',', '"');
            }, explode("\n", $fileContent));
            
            if (empty($rows)) {
                $this->conn->exec("SET FOREIGN_KEY_CHECKS=1");
                return ['success' => false, 'error' => 'File CSV rỗng'];
            }

            // Parse header
            $headerRow = $rows[0];
            $columnIndices = $this->parseGkhlHeader($headerRow);
            
            if (empty($columnIndices['MaKHDMS'])) {
                $this->conn->exec("SET FOREIGN_KEY_CHECKS=1");
                return ['success' => false, 'error' => 'Không tìm thấy cột "Mã KH DMS" hoặc tương đương trong file CSV'];
            }

            $isFirstRow = true;
            $insertedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;
            $batch = [];
            $batchSize = self::BATCH_SIZE;

            $sql = "INSERT INTO {$this->table} (
                MaNVBH, TenNVBH, MaKHDMS, TenQuay, TenChuCuaHang,
                NgaySinh, ThangSinh, NamSinh, SDTZalo, SDTDaDinhDanh,
                KhopSDT, DangKyChuongTrinh, DangKyMucDoanhSo, DangKyTrungBay
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                $this->conn->exec("SET FOREIGN_KEY_CHECKS=1");
                return ['success' => false, 'error' => 'Lỗi prepare SQL: ' . $this->conn->error];
            }

            foreach ($rows as $rowNum => $row) {
                if (empty($row)) {
                    continue;
                }
                
                if ($isFirstRow) {
                    $isFirstRow = false;
                    continue;
                }

                $maNVBH = isset($columnIndices['MaNVBH']) && !empty(trim($row[$columnIndices['MaNVBH']] ?? '')) ? trim($row[$columnIndices['MaNVBH']]) : null;
                $tenNVBH = isset($columnIndices['TenNVBH']) && !empty(trim($row[$columnIndices['TenNVBH']] ?? '')) ? trim($row[$columnIndices['TenNVBH']]) : null;
                $maKHDMS = isset($columnIndices['MaKHDMS']) && !empty(trim($row[$columnIndices['MaKHDMS']] ?? '')) ? trim($row[$columnIndices['MaKHDMS']]) : null;
                $tenQuay = isset($columnIndices['TenQuay']) && !empty(trim($row[$columnIndices['TenQuay']] ?? '')) ? trim($row[$columnIndices['TenQuay']]) : null;
                $tenChuCuaHang = isset($columnIndices['TenChuCuaHang']) && !empty(trim($row[$columnIndices['TenChuCuaHang']] ?? '')) ? trim($row[$columnIndices['TenChuCuaHang']]) : null;
                $ngaySinh = isset($columnIndices['NgaySinh']) ? $this->cleanNumber($row[$columnIndices['NgaySinh']] ?? '', true) : null;
                $thangSinh = isset($columnIndices['ThangSinh']) ? $this->cleanNumber($row[$columnIndices['ThangSinh']] ?? '', true) : null;
                $namSinh = isset($columnIndices['NamSinh']) ? $this->cleanNumber($row[$columnIndices['NamSinh']] ?? '') : null;
                $sdtZalo = isset($columnIndices['SDTZalo']) && !empty(trim($row[$columnIndices['SDTZalo']] ?? '')) ? trim($row[$columnIndices['SDTZalo']]) : null;
                $sdtDaDinhDanh = isset($columnIndices['SDTDaDinhDanh']) && !empty(trim($row[$columnIndices['SDTDaDinhDanh']] ?? '')) ? trim($row[$columnIndices['SDTDaDinhDanh']]) : null;
                $khopSDT = isset($columnIndices['KhopSDT']) ? $this->convertYN($row[$columnIndices['KhopSDT']] ?? '') : null;
                $dangKyChuongTrinh = isset($columnIndices['DangKyChuongTrinh']) && !empty(trim($row[$columnIndices['DangKyChuongTrinh']] ?? '')) ? trim($row[$columnIndices['DangKyChuongTrinh']]) : null;
                $dangKyMucDoanhSo = isset($columnIndices['DangKyMucDoanhSo']) && !empty(trim($row[$columnIndices['DangKyMucDoanhSo']] ?? '')) ? trim($row[$columnIndices['DangKyMucDoanhSo']]) : null;
                $dangKyTrungBay = isset($columnIndices['DangKyTrungBay']) && !empty(trim($row[$columnIndices['DangKyTrungBay']] ?? '')) ? trim($row[$columnIndices['DangKyTrungBay']]) : null;

                if (empty($maKHDMS)) {
                    $skippedCount++;
                    continue;
                }

                $data = [
                    $maNVBH, $tenNVBH, $maKHDMS, $tenQuay, $tenChuCuaHang,
                    $ngaySinh, $thangSinh, $namSinh, $sdtZalo, $sdtDaDinhDanh,
                    $khopSDT, $dangKyChuongTrinh, $dangKyMucDoanhSo, $dangKyTrungBay
                ];

                $batch[] = $data;

                if (count($batch) >= $batchSize) {
                    $result = $this->executeBatch($stmt, $batch);
                    $insertedCount += $result['inserted'];
                    $errorCount += $result['errors'];
                    $batch = [];
                    gc_collect_cycles();
                }
            }

            if (!empty($batch)) {
                $result = $this->executeBatch($stmt, $batch);
                $insertedCount += $result['inserted'];
                $errorCount += $result['errors'];
            }

            $this->conn->commit();
            
            // Bật FK checks lại
            $this->conn->exec("SET FOREIGN_KEY_CHECKS=1");

            return [
                'success' => true,
                'inserted' => $insertedCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount
            ];
        } catch (Exception $e) {
            $this->conn->exec("SET FOREIGN_KEY_CHECKS=1");
            $this->conn->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function parseGkhlHeader($headerRow) {
        $indices = [];
        
        // Map theo thứ tự cột (index 0-13)
        // Dựa trên cấu trúc: Mã NVBH, Tên NVBH, Mã KH DMS, ... (14 cột)
        $expectedColumns = [
            'MaNVBH',           // 0
            'TenNVBH',          // 1
            'MaKHDMS',          // 2
            'TenQuay',          // 3
            'TenChuCuaHang',    // 4
            'NgaySinh',         // 5
            'ThangSinh',        // 6
            'NamSinh',          // 7
            'SDTZalo',          // 8
            'SDTDaDinhDanh',    // 9
            'KhopSDT',          // 10
            'DangKyChuongTrinh', // 11
            'DangKyMucDoanhSo',  // 12
            'DangKyTrungBay'     // 13
        ];
        
        // Nếu file có đúng 14 cột, map trực tiếp theo index
        if (count($headerRow) >= 14) {
            foreach ($expectedColumns as $idx => $columnName) {
                $indices[$columnName] = $idx;
            }
            error_log("Using direct column mapping (14 columns detected)");
            return $indices;
        }
        
        // Fallback: Tìm kiếm theo tên cột
        foreach ($headerRow as $index => $header) {
            $normalized = $this->normalizeHeader($header);
            error_log("Column $index: '$header' -> '$normalized'");
            
            if (preg_match('/ma.*nvbh/', $normalized)) {
                $indices['MaNVBH'] = $index;
            }
            if (preg_match('/ten.*nvbh/', $normalized)) {
                $indices['TenNVBH'] = $index;
            }
            if (preg_match('/ma.*kh.*dms/', $normalized)) {
                $indices['MaKHDMS'] = $index;
            }
            if (preg_match('/ten.*quay/', $normalized)) {
                if (!isset($indices['TenQuay'])) $indices['TenQuay'] = $index;
            }
            if (preg_match('/ten.*chu/', $normalized)) {
                $indices['TenChuCuaHang'] = $index;
            }
            if (preg_match('/^ngay.*sinh$/', $normalized)) {
                $indices['NgaySinh'] = $index;
            }
            if (preg_match('/^thang.*sinh$/', $normalized)) {
                $indices['ThangSinh'] = $index;
            }
            if (preg_match('/^nam.*sinh$/', $normalized)) {
                $indices['NamSinh'] = $index;
            }
            if (preg_match('/zalo/', $normalized)) {
                if (!isset($indices['SDTZalo'])) $indices['SDTZalo'] = $index;
            }
            if (preg_match('/dinh.*danh/', $normalized) && !preg_match('/khop/', $normalized)) {
                $indices['SDTDaDinhDanh'] = $index;
            }
            if (preg_match('/khop/', $normalized)) {
                $indices['KhopSDT'] = $index;
            }
            if (preg_match('/chuong.*trinh/', $normalized)) {
                $indices['DangKyChuongTrinh'] = $index;
            }
            if (preg_match('/muc.*doanh/', $normalized)) {
                $indices['DangKyMucDoanhSo'] = $index;
            }
            if (preg_match('/trung.*bay/', $normalized) || preg_match('/quang.*cao/', $normalized)) {
                $indices['DangKyTrungBay'] = $index;
            }
        }

        error_log("Column mapping result: " . json_encode($indices));
        return $indices;
    }

    private function normalizeHeader($header) {
        $normalized = strtolower(trim($header));
        // Chuẩn hóa các ký tự có dấu tiếng Việt
        $normalized = preg_replace('/[àáảãạăằắẳẵặâầấẩẫậ]/u', 'a', $normalized);
        $normalized = preg_replace('/[èéẻẽẹêềếểễệ]/u', 'e', $normalized);
        $normalized = preg_replace('/[ìíỉĩị]/u', 'i', $normalized);
        $normalized = preg_replace('/[òóỏõọôồốổỗộơờớởỡợ]/u', 'o', $normalized);
        $normalized = preg_replace('/[ùúủũụưừứửữự]/u', 'u', $normalized);
        $normalized = preg_replace('/[ỳýỷỹỵ]/u', 'y', $normalized);
        $normalized = preg_replace('/[đ]/u', 'd', $normalized);
        // Chuẩn hóa khoảng trắng
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        return $normalized;
    }

    private function executeBatch(&$stmt, $batch) {
        $inserted = 0;
        $errors = 0;
        
        foreach ($batch as $data) {
            try {
                if (!$stmt->execute($data)) {
                    $errors++;
                    error_log("GKHL Insert Error: " . $stmt->error);
                } else {
                    $inserted++;
                }
            } catch (Exception $e) {
                $errors++;
                error_log("GKHL Exception: " . $e->getMessage());
            }
        }
        
        return ['inserted' => $inserted, 'errors' => $errors];
    }

    private function convertYN($value) {
        if (empty($value) || $value === '' || $value === 'NULL') {
            return null;
        }
        
        $cleaned = strtoupper(trim($value));
        
        if ($cleaned === 'Y' || $cleaned === 'YES' || $cleaned === '1') {
            return 'Y';
        }
        
        if ($cleaned === 'N' || $cleaned === 'NO' || $cleaned === '0') {
            return 'N';
        }
        
        return null;
    }

    private function cleanNumber($value, $asTinyInt = false) {
        if (empty($value) || $value === '' || $value === 'NULL') {
            return null;
        }
        
        $cleaned = str_replace([',', ' ', '.'], '', trim($value));
        
        if (is_numeric($cleaned)) {
            if ($asTinyInt) {
                return (int)$cleaned;
            }
            return $cleaned;
        }
        
        return null;
    }

    public function getAll($filters = []) {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if (!empty($filters['ma_nvbh'])) {
            $sql .= " AND MaNVBH = :ma_nvbh";
            $params[':ma_nvbh'] = $filters['ma_nvbh'];
        }
        
        if (!empty($filters['ma_kh_dms'])) {
            $sql .= " AND MaKHDMS LIKE :ma_kh_dms";
            $params[':ma_kh_dms'] = '%' . $filters['ma_kh_dms'] . '%';
        }
        
        if (isset($filters['khop_sdt']) && $filters['khop_sdt'] !== '') {
            $khopValue = $filters['khop_sdt'] === '1' ? 'Y' : 'N';
            $sql .= " AND KhopSDT = :khop_sdt";
            $params[':khop_sdt'] = $khopValue;
        }
        
        if (!empty($filters['nam_sinh'])) {
            $sql .= " AND NamSinh = :nam_sinh";
            $params[':nam_sinh'] = $filters['nam_sinh'];
        }
        
        $sql .= " ORDER BY MaKHDMS LIMIT 5000";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSaleStaff() {
        $sql = "SELECT DISTINCT MaNVBH, TenNVBH FROM {$this->table} 
                WHERE MaNVBH IS NOT NULL ORDER BY MaNVBH LIMIT 1000";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBirthYears() {
        $sql = "SELECT DISTINCT NamSinh FROM {$this->table} 
                WHERE NamSinh IS NOT NULL ORDER BY NamSinh DESC LIMIT 100";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getTotalCount() {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getPhoneMatchCount() {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE KhopSDT = 'Y'";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchColumn();
    }
}
?>