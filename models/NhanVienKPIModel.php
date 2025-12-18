<?php
/**
 * ✅ MODEL TỐI ƯU - KPI Nhân Viên
 * Query 1 lần cho TẤT CẢ nhân viên
 */

require_once 'config/database.php';

class NhanVienKPIModel {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * ✅ LẤY TẤT CẢ NHÂN VIÊN KÈM KPI TRONG 1 QUERY (FIX CHO MARIADB)
     */
    public function getAllEmployeesWithKPI($tu_ngay, $den_ngay, $product_filter = '') {
        $sql = "SELECT 
                    o.DSRCode,
                    o.DSRTypeProvince,
                    
                    -- Tổng đơn hàng
                    COUNT(DISTINCT o.OrderNumber) as total_orders,
                    
                    -- Tổng tiền
                    COALESCE(SUM(o.TotalNetAmount), 0) as total_amount,
                    
                    -- Số ngày hoạt động
                    COUNT(DISTINCT DATE(o.OrderDate)) as working_days,
                    
                    -- Lấy từ bảng tạm daily_stats
                    MAX(ds.max_day_orders) as max_day_orders,
                    MAX(ds.max_day_amount) as max_day_amount,
                    MIN(ds.min_day_orders) as min_day_orders,
                    MIN(ds.min_day_amount) as min_day_amount,
                    ds.daily_orders_str,
                    ds.daily_amounts_str
                    
                FROM orderdetail o
                INNER JOIN (
                    -- Tính daily stats trong subquery riêng
                    SELECT 
                        DSRCode,
                        MAX(order_count_per_day) as max_day_orders,
                        MAX(amount_per_day) as max_day_amount,
                        MIN(order_count_per_day) as min_day_orders,
                        MIN(amount_per_day) as min_day_amount,
                        GROUP_CONCAT(order_count_per_day ORDER BY order_date) as daily_orders_str,
                        GROUP_CONCAT(amount_per_day ORDER BY order_date) as daily_amounts_str
                    FROM (
                        SELECT 
                            DSRCode,
                            DATE(OrderDate) as order_date,
                            COUNT(DISTINCT OrderNumber) as order_count_per_day,
                            SUM(TotalNetAmount) as amount_per_day
                        FROM orderdetail
                        WHERE DSRCode IS NOT NULL 
                        AND DSRCode != ''
                        AND DATE(OrderDate) >= ?
                        AND DATE(OrderDate) <= ?
                        " . (!empty($product_filter) ? "AND ProductCode LIKE ?" : "") . "
                        GROUP BY DSRCode, DATE(OrderDate)
                    ) daily_grouped
                    GROUP BY DSRCode
                ) ds ON o.DSRCode = ds.DSRCode
                WHERE o.DSRCode IS NOT NULL 
                AND o.DSRCode != ''
                AND DATE(o.OrderDate) >= ?
                AND DATE(o.OrderDate) <= ?
                " . (!empty($product_filter) ? "AND o.ProductCode LIKE ?" : "") . "
                GROUP BY o.DSRCode, o.DSRTypeProvince, ds.daily_orders_str, ds.daily_amounts_str
                HAVING total_orders > 0
                ORDER BY o.DSRCode";
        
        $params = [$tu_ngay, $den_ngay];
        if (!empty($product_filter)) {
            $params[] = $product_filter . '%';
        }
        $params[] = $tu_ngay;
        $params[] = $den_ngay;
        if (!empty($product_filter)) {
            $params[] = $product_filter . '%';
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Parse daily orders string thành array
        foreach ($results as &$row) {
            $row['daily_orders'] = !empty($row['daily_orders_str']) 
                ? array_map('intval', explode(',', $row['daily_orders_str'])) 
                : [];
            $row['daily_amounts'] = !empty($row['daily_amounts_str']) 
                ? array_map('floatval', explode(',', $row['daily_amounts_str'])) 
                : [];
            
            // Tính avg
            $row['avg_daily_orders'] = $row['working_days'] > 0 
                ? round($row['total_orders'] / $row['working_days'], 2) 
                : 0;
            $row['avg_daily_amount'] = $row['working_days'] > 0 
                ? round($row['total_amount'] / $row['working_days'], 0) 
                : 0;
            
            // Thêm tên nhân viên
            $row['ten_nv'] = 'NV_' . $row['DSRCode'];
            
            unset($row['daily_orders_str']);
            unset($row['daily_amounts_str']);
        }
        
        return $results;
    }

    /**
     * ✅ LẤY THỐNG KÊ HỆ THỐNG - 1 QUERY DUY NHẤT (FIX CHO MARIADB)
     */
    public function getSystemMetrics($tu_ngay, $den_ngay, $product_filter = '') {
        $sql = "SELECT 
                    COUNT(DISTINCT o.DSRCode) as emp_count,
                    COUNT(DISTINCT o.OrderNumber) as total_orders,
                    COALESCE(SUM(o.TotalNetAmount), 0) as total_amount,
                    COUNT(DISTINCT DATE(o.OrderDate)) as total_working_days,
                    MAX(daily_stats.max_orders) as max_daily_orders,
                    MAX(daily_stats.max_amount) as max_daily_amount
                FROM orderdetail o
                LEFT JOIN (
                    SELECT 
                        DSRCode,
                        DATE(OrderDate) as order_date,
                        COUNT(DISTINCT OrderNumber) as max_orders,
                        SUM(TotalNetAmount) as max_amount
                    FROM orderdetail
                    WHERE DSRCode IS NOT NULL 
                    AND DSRCode != ''
                    AND DATE(OrderDate) >= ?
                    AND DATE(OrderDate) <= ?
                    " . (!empty($product_filter) ? "AND ProductCode LIKE ?" : "") . "
                    GROUP BY DSRCode, DATE(OrderDate)
                ) daily_stats ON o.DSRCode = daily_stats.DSRCode
                WHERE o.DSRCode IS NOT NULL 
                AND o.DSRCode != ''
                AND DATE(o.OrderDate) >= ?
                AND DATE(o.OrderDate) <= ?
                " . (!empty($product_filter) ? "AND o.ProductCode LIKE ?" : "") . "";
        
        $params = [$tu_ngay, $den_ngay];
        if (!empty($product_filter)) {
            $params[] = $product_filter . '%';
        }
        $params[] = $tu_ngay;
        $params[] = $den_ngay;
        if (!empty($product_filter)) {
            $params[] = $product_filter . '%';
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * ✅ LẤY TẤT CẢ DAILY ORDERS ĐỂ TÍNH STD DEV - 1 QUERY
     */
    public function getAllDailyOrdersForStdDev($tu_ngay, $den_ngay, $product_filter = '') {
        $sql = "SELECT daily_counts.daily_order_count
                FROM (
                    SELECT 
                        COUNT(DISTINCT o.OrderNumber) as daily_order_count
                    FROM orderdetail o
                    WHERE o.DSRCode IS NOT NULL 
                    AND o.DSRCode != ''
                    AND DATE(o.OrderDate) >= ?
                    AND DATE(o.OrderDate) <= ?
                    " . (!empty($product_filter) ? "AND o.ProductCode LIKE ?" : "") . "
                    GROUP BY o.DSRCode, DATE(o.OrderDate)
                ) daily_counts";
        
        $params = [$tu_ngay, $den_ngay];
        if (!empty($product_filter)) {
            $params[] = $product_filter . '%';
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * ✅ Lấy danh sách tháng có sẵn
     */
    public function getAvailableMonths() {
        $sql = "SELECT DISTINCT CONCAT(RptYear, '-', LPAD(RptMonth, 2, '0')) as thang
                FROM orderdetail
                WHERE RptYear IS NOT NULL AND RptMonth IS NOT NULL
                ORDER BY RptYear DESC, RptMonth DESC LIMIT 24";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * ✅ Lấy danh sách nhóm sản phẩm
     */
    public function getAvailableProducts() {
        $sql = "SELECT DISTINCT SUBSTRING(ProductCode, 1, 2) as product_prefix
                FROM orderdetail 
                WHERE ProductCode IS NOT NULL AND ProductCode != ''
                ORDER BY product_prefix
                LIMIT 50";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
}
?>