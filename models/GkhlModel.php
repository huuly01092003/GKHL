<?php
require_once 'config/database.php';

class GkhlModel {
    private $conn;
    private $table = "gkhl";
    private const PAGE_SIZE = 1000;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

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
            $batchSize = 100;
            $batch = [];
            
            // ✅ Sử dụng tên cột theo schema mới
            $sql = "INSERT INTO {$this->table} (
                MaNVBH, TenNVBH, MaKHDMS, TenQuay, TenChuCuaHang,
                NgaySinh, ThangSinh, NamSinh, SDTZalo, SDTDaDinhDanh,
                KhopSDT, DangKyChuongTrinh, DangKyMucDoanhSo, DangKyTrungBay
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                MaNVBH = VALUES(MaNVBH),
                TenNVBH = VALUES(TenNVBH),
                TenQuay = VALUES(TenQuay),
                TenChuCuaHang = VALUES(TenChuCuaHang),
                NgaySinh = VALUES(NgaySinh),
                ThangSinh = VALUES(ThangSinh),
                NamSinh = VALUES(NamSinh),
                SDTZalo = VALUES(SDTZalo),
                SDTDaDinhDanh = VALUES(SDTDaDinhDanh),
                KhopSDT = VALUES(KhopSDT),
                DangKyChuongTrinh = VALUES(DangKyChuongTrinh),
                DangKyMucDoanhSo = VALUES(DangKyMucDoanhSo),
                DangKyTrungBay = VALUES(DangKyTrungBay)";
            
            $stmt = $this->conn->prepare($sql);
            
            foreach ($rows as $row) {
                if (empty($row) || count($row) < 14) {
                    continue;
                }
                
                if ($isFirstRow) {
                    $isFirstRow = false;
                    continue;
                }
                
                $data = [
                    !empty(trim($row[0])) ? trim($row[0]) : null,
                    !empty(trim($row[1])) ? trim($row[1]) : null,
                    !empty(trim($row[2])) ? trim($row[2]) : null,
                    !empty(trim($row[3])) ? trim($row[3]) : null,
                    !empty(trim($row[4])) ? trim($row[4]) : null,
                    $this->cleanNumber($row[5], true),
                    $this->cleanNumber($row[6], true),
                    $this->cleanNumber($row[7]),
                    !empty(trim($row[8])) ? trim($row[8]) : null,
                    !empty(trim($row[9])) ? trim($row[9]) : null,
                    $this->convertYN($row[10]),
                    !empty(trim($row[11])) ? trim($row[11]) : null,
                    !empty(trim($row[12])) ? trim($row[12]) : null,
                    !empty(trim($row[13])) ? trim($row[13]) : null
                ];
                
                if (empty($data[2])) {
                    continue;
                }
                
                $batch[] = $data;
                
                if (count($batch) >= $batchSize) {
                    foreach ($batch as $batchData) {
                        $stmt->execute($batchData);
                        if ($stmt->rowCount() > 0) {
                            $insertedCount++;
                        }
                    }
                    $batch = [];
                    gc_collect_cycles();
                }
            }
            
            if (!empty($batch)) {
                foreach ($batch as $batchData) {
                    $stmt->execute($batchData);
                    if ($stmt->rowCount() > 0) {
                        $insertedCount++;
                    }
                }
            }
            
            $this->conn->commit();
            
            return ['success' => true, 'inserted' => $insertedCount];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
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