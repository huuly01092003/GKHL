<?php
/**
 * ✅ MODEL KPI NHÂN VIÊN - Database GKHL
 */
require_once 'config/database.php';

class NhanVienKPIModel {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function getAvailableMonths() {
        $sql = "SELECT DISTINCT CONCAT(RptYear, '-', LPAD(RptMonth, 2, '0')) as thang
                FROM orderdetail
                WHERE RptYear IS NOT NULL AND RptMonth IS NOT NULL
                ORDER BY RptYear DESC, RptMonth DESC LIMIT 24";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    public function getAvailableProducts() {
        $sql = "SELECT DISTINCT SUBSTRING(ProductCode, 1, 2) as product_prefix
                FROM orderdetail 
                WHERE ProductCode IS NOT NULL AND ProductCode != ''
                ORDER BY product_prefix";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    public function getAllEmployees() {
        $sql = "SELECT DISTINCT 
                    o.DSRCode,
                    o.DSRTypeProvince,
                    CONCAT('NV_', o.DSRCode) as ten_nv
                FROM orderdetail o
                WHERE o.DSRCode IS NOT NULL AND o.DSRCode != ''
                ORDER BY o.DSRCode";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEmployeeDailyKPI($dsr_code, $tu_ngay, $den_ngay, $product_filter = '') {
        $sql = "SELECT 
                    DATE(OrderDate) as order_date,
                    COUNT(DISTINCT OrderNumber) as order_count,
                    COALESCE(SUM(TotalNetAmount), 0) as total_amount,
                    COUNT(DISTINCT CustCode) as unique_customers
                FROM orderdetail
                WHERE DSRCode = ?
                AND DATE(OrderDate) >= ? AND DATE(OrderDate) <= ?";
        
        $params = [$dsr_code, $tu_ngay, $den_ngay];
        
        if (!empty($product_filter)) {
            $sql .= " AND ProductCode LIKE ?";
            $params[] = $product_filter . '%';
        }
        
        $sql .= " GROUP BY DATE(OrderDate) ORDER BY order_date";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}