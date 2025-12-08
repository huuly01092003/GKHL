<?php
require_once 'config/database.php';

class ExportModel {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * ✅ CẬP NHẬT: Hỗ trợ nhiều năm và nhiều tháng
     * Lấy dữ liệu đầy đủ để export
     */
    public function getExportData($years = [], $months = [], $filters = []) {
        $sql = "SELECT 
                    o.CustCode as ma_khach_hang,
                    d.TenKH as ten_khach_hang,
                    d.LoaiKH as loai_kh,
                    d.DiaChi as dia_chi,
                    d.QuanHuyen as quan_huyen,
                    d.Tinh as ma_tinh_tp,
                    d.MaSoThue as ma_so_thue,
                    d.Area as area,
                    d.MaGSBH as ma_gsbh,
                    d.PhanLoaiNhomKH as phan_loai_nhom_kh,
                    d.MaNPP as ma_npp,
                    d.MaNVBH as ma_nvbh,
                    d.TenNVBH as ten_nvbh,
                    d.Location as location,
                    
                    COUNT(DISTINCT o.OrderNumber) as so_don_hang,
                    SUM(o.Qty) as total_san_luong,
                    SUM(o.TotalGrossAmount) as total_doanh_so_truoc_ck,
                    SUM(o.TotalSchemeAmount) as total_chiet_khau,
                    SUM(o.TotalNetAmount) as total_doanh_so,
                    
                    MAX(CASE WHEN g.MaKHDMS IS NOT NULL THEN 1 ELSE 0 END) as has_gkhl,
                    MAX(g.TenQuay) as gkhl_ten_quay,
                    MAX(g.TenChuCuaHang) as gkhl_ten_chu,
                    MAX(g.NgaySinh) as gkhl_ngay_sinh,
                    MAX(g.ThangSinh) as gkhl_thang_sinh,
                    MAX(g.NamSinh) as gkhl_nam_sinh,
                    MAX(g.SDTZalo) as gkhl_sdt_zalo,
                    MAX(g.SDTDaDinhDanh) as gkhl_sdt_dinh_danh,
                    MAX(g.KhopSDT) as gkhl_khop_sdt,
                    MAX(g.DangKyChuongTrinh) as gkhl_dk_chuong_trinh,
                    MAX(g.DangKyMucDoanhSo) as gkhl_dk_muc_doanh_so,
                    MAX(g.DangKyTrungBay) as gkhl_dk_trung_bay
                    
                FROM orderdetail o
                LEFT JOIN dskh d ON o.CustCode = d.MaKH
                LEFT JOIN gkhl g ON g.MaKHDMS = o.CustCode
                WHERE 1=1";
        
        $params = [];
        
        // ✅ Filter theo nhiều năm
        if (!empty($years)) {
            $placeholders = [];
            foreach ($years as $idx => $year) {
                $key = ":year_$idx";
                $placeholders[] = $key;
                $params[$key] = $year;
            }
            $sql .= " AND o.RptYear IN (" . implode(',', $placeholders) . ")";
        }
        
        // ✅ Filter theo nhiều tháng
        if (!empty($months)) {
            $placeholders = [];
            foreach ($months as $idx => $month) {
                $key = ":month_$idx";
                $placeholders[] = $key;
                $params[$key] = $month;
            }
            $sql .= " AND o.RptMonth IN (" . implode(',', $placeholders) . ")";
        }
        
        // Lọc theo tỉnh
        if (!empty($filters['ma_tinh_tp'])) {
            $sql .= " AND d.Tinh = :ma_tinh_tp";
            $params[':ma_tinh_tp'] = $filters['ma_tinh_tp'];
        }
        
        // Lọc theo mã KH
        if (!empty($filters['ma_khach_hang'])) {
            $sql .= " AND o.CustCode LIKE :ma_khach_hang";
            $params[':ma_khach_hang'] = '%' . $filters['ma_khach_hang'] . '%';
        }
        
        // Lọc theo trạng thái GKHL
        if (isset($filters['gkhl_status']) && $filters['gkhl_status'] !== '') {
            if ($filters['gkhl_status'] === '1') {
                $sql .= " AND g.MaKHDMS IS NOT NULL";
            } elseif ($filters['gkhl_status'] === '0') {
                $sql .= " AND g.MaKHDMS IS NULL";
            }
        }
        
        $sql .= " GROUP BY o.CustCode, d.TenKH, d.LoaiKH, d.DiaChi, d.QuanHuyen, 
                          d.Tinh, d.MaSoThue, d.Area, d.MaGSBH, d.PhanLoaiNhomKH, 
                          d.MaNPP, d.MaNVBH, d.TenNVBH, d.Location
                  ORDER BY total_doanh_so DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy danh sách năm có dữ liệu
     */
    public function getAvailableYears() {
        $sql = "SELECT DISTINCT RptYear 
                FROM orderdetail
                WHERE RptYear IS NOT NULL
                ORDER BY RptYear DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Lấy danh sách tháng (1-12)
     */
    public function getAvailableMonths() {
        return range(1, 12);
    }

    /**
     * Lấy danh sách tỉnh
     */
    public function getProvinces() {
        $sql = "SELECT DISTINCT d.Tinh 
                FROM dskh d
                WHERE d.Tinh IS NOT NULL AND d.Tinh != ''
                ORDER BY d.Tinh";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
?>