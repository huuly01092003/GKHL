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
            $skippedCount = 0;
            $errorCount = 0;
            $batch = [];
            $batchSize = self::BATCH_SIZE;

            // SQL statement - sử dụng INSERT IGNORE để bỏ qua FK constraint violation
            $sql = "INSERT INTO {$this->table} (
                MaNVBH, TenNVBH, MaKHDMS, TenQuay, TenChuCuaHang,
                NgaySinh, ThangSinh, NamSinh, SDTZalo, SDTDaDinhDanh,
                KhopSDT, DangKyChuongTrinh, DangKyMucDoanhSo, DangKyTrungBay
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                return ['success' => false, 'error' => 'Lỗi prepare SQL: ' . $this->conn->error];
            }

            foreach ($rows as $rowNum => $row) {
                if (empty($row) || count($row) < 14) {
                    continue;
                }
                
                if ($isFirstRow) {
                    $isFirstRow = false;
                    continue;
                }

                // Chuẩn bị dữ liệu
                $maNVBH = !empty(trim($row[0])) ? trim($row[0]) : null;
                $tenNVBH = !empty(trim($row[1])) ? trim($row[1]) : null;
                $maKHDMS = !empty(trim($row[2])) ? trim($row[2]) : null;
                $tenQuay = !empty(trim($row[3])) ? trim($row[3]) : null;
                $tenChuCuaHang = !empty(trim($row[4])) ? trim($row[4]) : null;
                $ngaySinh = $this->cleanNumber($row[5], true);
                $thangSinh = $this->cleanNumber($row[6], true);
                $namSinh = $this->cleanNumber($row[7]);
                $sdtZalo = !empty(trim($row[8])) ? trim($row[8]) : null;
                $sdtDaDinhDanh = !empty(trim($row[9])) ? trim($row[9]) : null;
                $khopSDT = $this->convertYN($row[10]);
                $dangKyChuongTrinh = !empty(trim($row[11])) ? trim($row[11]) : null;
                $dangKyMucDoanhSo = !empty(trim($row[12])) ? trim($row[12]) : null;
                $dangKyTrungBay = !empty(trim($row[13])) ? trim($row[13]) : null;

                // Validation: MaKHDMS không được trống
                if (empty($maKHDMS)) {
                    $skippedCount++;
                    continue;
                }

                // Kiểm tra MaKHDMS có tồn tại trong DSKH không
                if (!$this->customerExists($maKHDMS)) {
                    $errorCount++;
                    error_log("GKHL Import - Row " . ($rowNum + 1) . ": MaKHDMS '$maKHDMS' không tồn tại trong DSKH");
                    continue;
                }

                $data = [
                    $maNVBH,
                    $tenNVBH,
                    $maKHDMS,
                    $tenQuay,
                    $tenChuCuaHang,
                    $ngaySinh,
                    $thangSinh,
                    $namSinh,
                    $sdtZalo,
                    $sdtDaDinhDanh,
                    $khopSDT,
                    $dangKyChuongTrinh,
                    $dangKyMucDoanhSo,
                    $dangKyTrungBay
                ];

                $batch[] = $data;

                if (count($batch) >= $batchSize) {
                    $result = $this->executeBatch($stmt, $batch);
                    $insertedCount += $result['inserted'];
                    $batch = [];
                    gc_collect_cycles();
                }
            }

            // Xử lý batch cuối cùng
            if (!empty($batch)) {
                $result = $this->executeBatch($stmt, $batch);
                $insertedCount += $result['inserted'];
            }

            $this->conn->commit();

            return [
                'success' => true,
                'inserted' => $insertedCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount
            ];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Kiểm tra khách hàng có tồn tại trong DSKH không
     */
    private function customerExists($maKH) {
        $sql = "SELECT COUNT(*) as cnt FROM dskh WHERE MaKH = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$maKH]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['cnt'] ?? 0) > 0;
    }

    /**
     * Thực hiện batch insert
     */
    private function executeBatch(&$stmt, $batch) {
        $inserted = 0;
        
        foreach ($batch as $data) {
            try {
                if (!$stmt->execute($data)) {
                    error_log("GKHL Insert Error: " . $stmt->error . " | Data: " . json_encode($data));
                } else {
                    $inserted++;
                }
            } catch (Exception $e) {
                error_log("GKHL Exception: " . $e->getMessage());
            }
        }
        
        return ['inserted' => $inserted];
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