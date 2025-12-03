<?php
require_once 'config/database.php';

class GkhlModel {
    private $conn;
    private $table = "gkhl";
    private const PAGE_SIZE = 100; // ✅ GIẢM từ 1000 xuống 100
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
                if (empty($row)) continue;
                
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

    // ✅ TỐI ƯU: Thêm pagination, giảm LIMIT
    public function getAll($filters = [], $page = 1) {
        $page = max(1, (int)$page);
        $offset = ($page - 1) * self::PAGE_SIZE;
        
        $conditions = [];
        $params = [];
        
        if (!empty($filters['ma_nvbh'])) {
            $conditions[] = "MaNVBH = :ma_nvbh";
            $params[':ma_nvbh'] = $filters['ma_nvbh'];
        }
        
        if (!empty($filters['ma_kh_dms'])) {
            $conditions[] = "MaKHDMS LIKE :ma_kh_dms";
            $params[':ma_kh_dms'] = '%' . $filters['ma_kh_dms'] . '%';
        }
        
        if (isset($filters['khop_sdt']) && $filters['khop_sdt'] !== '') {
            $khopValue = $filters['khop_sdt'] === '1' ? 'Y' : 'N';
            $conditions[] = "KhopSDT = :khop_sdt";
            $params[':khop_sdt'] = $khopValue;
        }
        
        if (!empty($filters['nam_sinh'])) {
            $conditions[] = "NamSinh = :nam_sinh";
            $params[':nam_sinh'] = $filters['nam_sinh'];
        }
        
        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        $sql = "SELECT * FROM {$this->table} 
                {$whereClause}
                ORDER BY MaKHDMS 
                LIMIT :limit OFFSET :offset";
        
        $params[':limit'] = self::PAGE_SIZE;
        $params[':offset'] = $offset;
        
        $stmt = $this->conn->prepare($sql);
        
        foreach ($params as $key => $value) {
            if ($key === ':limit' || $key === ':offset') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ✅ THÊM MỚI: Method để count filtered records
    public function getFilteredCount($filters = []) {
        $conditions = [];
        $params = [];
        
        if (!empty($filters['ma_nvbh'])) {
            $conditions[] = "MaNVBH = :ma_nvbh";
            $params[':ma_nvbh'] = $filters['ma_nvbh'];
        }
        
        if (!empty($filters['ma_kh_dms'])) {
            $conditions[] = "MaKHDMS LIKE :ma_kh_dms";
            $params[':ma_kh_dms'] = '%' . $filters['ma_kh_dms'] . '%';
        }
        
        if (isset($filters['khop_sdt']) && $filters['khop_sdt'] !== '') {
            $khopValue = $filters['khop_sdt'] === '1' ? 'Y' : 'N';
            $conditions[] = "KhopSDT = :khop_sdt";
            $params[':khop_sdt'] = $khopValue;
        }
        
        if (!empty($filters['nam_sinh'])) {
            $conditions[] = "NamSinh = :nam_sinh";
            $params[':nam_sinh'] = $filters['nam_sinh'];
        }
        
        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        $sql = "SELECT COUNT(*) as total FROM {$this->table} {$whereClause}";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    // ✅ TỐI ƯU: Giảm LIMIT xuống 200
    public function getSaleStaff() {
        $sql = "SELECT DISTINCT MaNVBH, TenNVBH FROM {$this->table} 
                WHERE MaNVBH IS NOT NULL 
                ORDER BY MaNVBH 
                LIMIT 200";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ✅ TỐI ƯU: Giảm LIMIT xuống 50
    public function getBirthYears() {
        $sql = "SELECT DISTINCT NamSinh FROM {$this->table} 
                WHERE NamSinh IS NOT NULL 
                ORDER BY NamSinh DESC 
                LIMIT 50";
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

    // ========== PRIVATE METHODS ==========

    private function parseGkhlHeader($headerRow) {
        $indices = [];
        
        $expectedColumns = [
            'MaNVBH', 'TenNVBH', 'MaKHDMS', 'TenQuay', 'TenChuCuaHang',
            'NgaySinh', 'ThangSinh', 'NamSinh', 'SDTZalo', 'SDTDaDinhDanh',
            'KhopSDT', 'DangKyChuongTrinh', 'DangKyMucDoanhSo', 'DangKyTrungBay'
        ];
        
        if (count($headerRow) >= 14) {
            foreach ($expectedColumns as $idx => $columnName) {
                $indices[$columnName] = $idx;
            }
            return $indices;
        }
        
        foreach ($headerRow as $index => $header) {
            $normalized = $this->normalizeHeader($header);
            
            if (preg_match('/ma.*nvbh/', $normalized)) $indices['MaNVBH'] = $index;
            if (preg_match('/ten.*nvbh/', $normalized)) $indices['TenNVBH'] = $index;
            if (preg_match('/ma.*kh.*dms/', $normalized)) $indices['MaKHDMS'] = $index;
            if (preg_match('/ten.*quay/', $normalized)) if (!isset($indices['TenQuay'])) $indices['TenQuay'] = $index;
            if (preg_match('/ten.*chu/', $normalized)) $indices['TenChuCuaHang'] = $index;
            if (preg_match('/^ngay.*sinh$/', $normalized)) $indices['NgaySinh'] = $index;
            if (preg_match('/^thang.*sinh$/', $normalized)) $indices['ThangSinh'] = $index;
            if (preg_match('/^nam.*sinh$/', $normalized)) $indices['NamSinh'] = $index;
            if (preg_match('/zalo/', $normalized)) if (!isset($indices['SDTZalo'])) $indices['SDTZalo'] = $index;
            if (preg_match('/dinh.*danh/', $normalized) && !preg_match('/khop/', $normalized)) $indices['SDTDaDinhDanh'] = $index;
            if (preg_match('/khop/', $normalized)) $indices['KhopSDT'] = $index;
            if (preg_match('/chuong.*trinh/', $normalized)) $indices['DangKyChuongTrinh'] = $index;
            if (preg_match('/muc.*doanh/', $normalized)) $indices['DangKyMucDoanhSo'] = $index;
            if (preg_match('/trung.*bay/', $normalized) || preg_match('/quang.*cao/', $normalized)) $indices['DangKyTrungBay'] = $index;
        }

        return $indices;
    }

    private function normalizeHeader($header) {
        $normalized = strtolower(trim($header));
        $normalized = preg_replace('/[àáảãạăằắẳẵặâầấẩẫậ]/u', 'a', $normalized);
        $normalized = preg_replace('/[èéẻẽẹêềếểễệ]/u', 'e', $normalized);
        $normalized = preg_replace('/[ìíỉĩị]/u', 'i', $normalized);
        $normalized = preg_replace('/[òóỏõọôồốổỗộơờớởỡợ]/u', 'o', $normalized);
        $normalized = preg_replace('/[ùúủũụưừứửữự]/u', 'u', $normalized);
        $normalized = preg_replace('/[ỳýỷỹỵ]/u', 'y', $normalized);
        $normalized = preg_replace('/[đ]/u', 'd', $normalized);
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
                } else {
                    $inserted++;
                }
            } catch (Exception $e) {
                $errors++;
            }
        }
        
        return ['inserted' => $inserted, 'errors' => $errors];
    }

    private function convertYN($value) {
        if (empty($value) || $value === '' || $value === 'NULL') return null;
        
        $cleaned = strtoupper(trim($value));
        
        if ($cleaned === 'Y' || $cleaned === 'YES' || $cleaned === '1') return 'Y';
        if ($cleaned === 'N' || $cleaned === 'NO' || $cleaned === '0') return 'N';
        
        return null;
    }

    private function cleanNumber($value, $asTinyInt = false) {
        if (empty($value) || $value === '' || $value === 'NULL') return null;
        
        $cleaned = str_replace([',', ' ', '.'], '', trim($value));
        
        if (is_numeric($cleaned)) {
            return $asTinyInt ? (int)$cleaned : $cleaned;
        }
        
        return null;
    }
}
?>