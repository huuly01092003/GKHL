<?php
/**
 * ✅ MODEL TỐI ƯU - Báo Cáo Doanh Số Nhân Viên
 * ✅ UPDATED: Thêm function lấy chi tiết đơn hàng khách hàng
 */

require_once 'config/database.php';

class NhanVienReportModel {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * ✅ LẤY TẤT CẢ DỮ LIỆU NHÂN VIÊN 1 LẦN + TÊN + MÃ GSBH
     */
    public function getAllEmployeesWithStats($tu_ngay, $den_ngay, $thang) {
        list($year, $month) = explode('-', $thang);
        
        $sql = "SELECT 
                    o.DSRCode,
                    o.DSRTypeProvince,
                    
                    -- ✅ TÊN NHÂN VIÊN và MÃ GSBH từ bảng DSKH (dùng MAX để tránh duplicate)
                    MAX(nv_info.TenNVBH) as ten_nhan_vien,
                    MAX(nv_info.MaGSBH) as ma_gsbh,
                    
                    -- TRONG KHOẢNG NGÀY
                    SUM(CASE WHEN DATE(o.OrderDate) >= ? AND DATE(o.OrderDate) <= ? 
                        THEN o.TotalNetAmount ELSE 0 END) as ds_tien_do,
                    COUNT(DISTINCT CASE WHEN DATE(o.OrderDate) >= ? AND DATE(o.OrderDate) <= ? 
                        THEN DATE(o.OrderDate) END) as so_ngay_co_doanh_so_khoang,
                    COALESCE(MAX(ds_khoang.max_daily), 0) as ds_ngay_cao_nhat_nv_khoang,
                    
                    -- TRONG THÁNG
                    SUM(CASE WHEN o.RptYear = ? AND o.RptMonth = ? 
                        THEN o.TotalNetAmount ELSE 0 END) as ds_tong_thang_nv,
                    COUNT(DISTINCT CASE WHEN o.RptYear = ? AND o.RptMonth = ? 
                        THEN DATE(o.OrderDate) END) as so_ngay_co_doanh_so_thang,
                    COALESCE(MAX(ds_thang.max_daily), 0) as ds_ngay_cao_nhat_nv_thang
                    
                FROM orderdetail o
                
                -- ✅ LEFT JOIN để lấy thông tin nhân viên
                LEFT JOIN (
                    SELECT DISTINCT MaNVBH, TenNVBH, MaGSBH
                    FROM dskh
                    WHERE MaNVBH IS NOT NULL AND MaNVBH != ''
                ) nv_info ON o.DSRCode = nv_info.MaNVBH
                
                -- LEFT JOIN để lấy max daily cho KHOẢNG
                LEFT JOIN (
                    SELECT 
                        DSRCode,
                        MAX(daily_amount) as max_daily
                    FROM (
                        SELECT 
                            DSRCode,
                            DATE(OrderDate) as order_date,
                            SUM(TotalNetAmount) as daily_amount
                        FROM orderdetail
                        WHERE DSRCode IS NOT NULL 
                        AND DSRCode != ''
                        AND DATE(OrderDate) >= ?
                        AND DATE(OrderDate) <= ?
                        GROUP BY DSRCode, DATE(OrderDate)
                    ) daily_khoang
                    GROUP BY DSRCode
                ) ds_khoang ON o.DSRCode = ds_khoang.DSRCode
                
                -- LEFT JOIN để lấy max daily cho THÁNG
                LEFT JOIN (
                    SELECT 
                        DSRCode,
                        MAX(daily_amount) as max_daily
                    FROM (
                        SELECT 
                            DSRCode,
                            DATE(OrderDate) as order_date,
                            SUM(TotalNetAmount) as daily_amount
                        FROM orderdetail
                        WHERE DSRCode IS NOT NULL 
                        AND DSRCode != ''
                        AND RptYear = ?
                        AND RptMonth = ?
                        GROUP BY DSRCode, DATE(OrderDate)
                    ) daily_thang
                    GROUP BY DSRCode
                ) ds_thang ON o.DSRCode = ds_thang.DSRCode
                
                WHERE o.DSRCode IS NOT NULL 
                AND o.DSRCode != ''
                AND (
                    (DATE(o.OrderDate) >= ? AND DATE(o.OrderDate) <= ?)
                    OR (o.RptYear = ? AND o.RptMonth = ?)
                )
                GROUP BY o.DSRCode, o.DSRTypeProvince
                HAVING ds_tien_do > 0 OR ds_tong_thang_nv > 0
                ORDER BY o.DSRCode";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            // KHOẢNG NGÀY trong SELECT (4 lần)
            $tu_ngay, $den_ngay,
            $tu_ngay, $den_ngay,
            
            // THÁNG trong SELECT (4 lần)
            $year, $month,
            $year, $month,
            
            // LEFT JOIN KHOẢNG (2 lần)
            $tu_ngay, $den_ngay,
            
            // LEFT JOIN THÁNG (2 lần)
            $year, $month,
            
            // WHERE clause (4 lần)
            $tu_ngay, $den_ngay,
            $year, $month
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ✅ MỚI: LẤY CHI TIẾT ĐỖN HÀNG CỦA NHÂN VIÊN TRONG KHOẢNG THỜI GIAN
     */
    public function getEmployeeOrderDetails($dsr_code, $tu_ngay, $den_ngay) {
        $sql = "SELECT 
                    o.OrderNumber as ma_don,
                    o.OrderDate as ngay_dat,
                    o.CustCode as ma_kh,
                    COALESCE(d.TenKH, 'N/A') as ten_kh,
                    COALESCE(d.DiaChi, '') as dia_chi_kh,
                    COALESCE(d.Tinh, '') as tinh_kh,
                    o.TotalNetAmount as so_tien,
                    o.Qty as so_luong
                FROM orderdetail o
                LEFT JOIN dskh d ON o.CustCode = d.MaKH
                WHERE o.DSRCode = ?
                AND DATE(o.OrderDate) >= ?
                AND DATE(o.OrderDate) <= ?
                ORDER BY o.OrderDate DESC, o.OrderNumber DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$dsr_code, $tu_ngay, $den_ngay]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ✅ TỔNG THEO THÁNG - 1 QUERY DUY NHẤT
     */
    public function getSystemStatsForMonth($thang) {
        list($year, $month) = explode('-', $thang);
        
        $sql = "SELECT 
                    COALESCE(SUM(o.TotalNetAmount), 0) as total,
                    COUNT(DISTINCT o.DSRCode) as emp_count,
                    DAY(LAST_DAY(CONCAT(?, '-', LPAD(?, 2, '0'), '-01'))) as so_ngay,
                    
                    -- DS TB/Ngày/Nhân viên
                    COALESCE(SUM(o.TotalNetAmount), 0) / 
                    NULLIF(COUNT(DISTINCT o.DSRCode) * DAY(LAST_DAY(CONCAT(?, '-', LPAD(?, 2, '0'), '-01'))), 0) as ds_tb_chung_thang,
                    
                    -- DS Ngày Cao Nhất TB
                    AVG(emp_max.max_daily) as ds_ngay_cao_nhat_tb_thang
                    
                FROM orderdetail o
                LEFT JOIN (
                    SELECT 
                        DSRCode, 
                        MAX(daily_total) as max_daily
                    FROM (
                        SELECT 
                            DSRCode, 
                            DATE(OrderDate) as order_date,
                            SUM(TotalNetAmount) as daily_total
                        FROM orderdetail
                        WHERE RptYear = ? AND RptMonth = ?
                        AND DSRCode IS NOT NULL AND DSRCode != ''
                        GROUP BY DSRCode, DATE(OrderDate)
                    ) daily
                    GROUP BY DSRCode
                ) emp_max ON o.DSRCode = emp_max.DSRCode
                WHERE o.RptYear = ? AND o.RptMonth = ?
                AND o.DSRCode IS NOT NULL AND o.DSRCode != ''";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$year, $month, $year, $month, $year, $month, $year, $month]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * ✅ TỔNG THEO KHOẢNG - 1 QUERY DUY NHẤT
     */
    public function getSystemStatsForRange($tu_ngay, $den_ngay) {
        $sql = "SELECT 
                    COALESCE(SUM(o.TotalNetAmount), 0) as total,
                    COUNT(DISTINCT o.DSRCode) as emp_count,
                    DATEDIFF(?, ?) + 1 as so_ngay,
                    
                    -- DS TB/Ngày/Nhân viên
                    COALESCE(SUM(o.TotalNetAmount), 0) / 
                    NULLIF(COUNT(DISTINCT o.DSRCode) * (DATEDIFF(?, ?) + 1), 0) as ds_tb_chung_khoang,
                    
                    -- DS Ngày Cao Nhất TB
                    AVG(emp_max.max_daily) as ds_ngay_cao_nhat_tb_khoang
                    
                FROM orderdetail o
                LEFT JOIN (
                    SELECT 
                        DSRCode, 
                        MAX(daily_total) as max_daily
                    FROM (
                        SELECT 
                            DSRCode, 
                            DATE(OrderDate) as order_date,
                            SUM(TotalNetAmount) as daily_total
                        FROM orderdetail
                        WHERE DATE(OrderDate) >= ? AND DATE(OrderDate) <= ?
                        AND DSRCode IS NOT NULL AND DSRCode != ''
                        GROUP BY DSRCode, DATE(OrderDate)
                    ) daily
                    GROUP BY DSRCode
                ) emp_max ON o.DSRCode = emp_max.DSRCode
                WHERE DATE(o.OrderDate) >= ? AND DATE(o.OrderDate) <= ?
                AND o.DSRCode IS NOT NULL AND o.DSRCode != ''";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            $den_ngay, $tu_ngay,
            $den_ngay, $tu_ngay,
            $tu_ngay, $den_ngay,
            $tu_ngay, $den_ngay
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * ✅ CÁC HÀM CŨ GIỮ LẠI ĐỂ TƯƠNG THÍCH
     */
    public function getAvailableMonths() {
        $sql = "SELECT DISTINCT CONCAT(RptYear, '-', LPAD(RptMonth, 2, '0')) as thang
                FROM orderdetail
                WHERE RptYear IS NOT NULL AND RptMonth IS NOT NULL
                AND RptYear >= 2020
                ORDER BY RptYear DESC, RptMonth DESC
                LIMIT 24";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    // Deprecated - Không dùng nữa
    public function getTotalByMonth($thang) {
        list($year, $month) = explode('-', $thang);
        $sql = "SELECT COALESCE(SUM(TotalNetAmount), 0) as total
                FROM orderdetail WHERE RptYear = ? AND RptMonth = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$year, $month]);
        return floatval($stmt->fetch()['total'] ?? 0);
    }

    public function getTotalByDateRange($tu_ngay, $den_ngay) {
        $sql = "SELECT COALESCE(SUM(TotalNetAmount), 0) as total
                FROM orderdetail WHERE DATE(OrderDate) >= ? AND DATE(OrderDate) <= ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$tu_ngay, $den_ngay]);
        return floatval($stmt->fetch()['total'] ?? 0);
    }
}
?>