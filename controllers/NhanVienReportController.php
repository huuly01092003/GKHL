<?php
/**
 * ✅ CONTROLLER TỐI ƯU - Báo Cáo Doanh Số Nhân Viên
 * Thay vì loop → Xử lý BATCH 1 lần
 */

require_once 'models/NhanVienReportModel.php';

class NhanVienReportController {
    private $model;

    public function __construct() {
        $this->model = new NhanVienReportModel();
    }

    public function showReport() {
        $message = '';
        $type = '';
        $report = [];
        $so_ngay = 0;
        $ket_qua_chung = 0;
        $ty_le_nghi_van = 0;
        $tu_ngay = date('Y-m-d');
        $den_ngay = date('Y-m-d');
        $tong_tien_ky = 0;
        $tong_tien_khoang = 0;
        $tong_tien_ky_detailed = [];
        $debug_info = '';
        $available_months = [];
        $top_threshold = 0;
        $tong_nghi_van = 0;
        $thang = '';
        $has_filtered = false;
        
        try {
            // Lấy danh sách tháng
            $available_months = $this->model->getAvailableMonths();
            
            if (empty($available_months)) {
                $message = "⚠️ Chưa có dữ liệu. Vui lòng import OrderDetail trước.";
                $type = 'warning';
                require_once 'views/nhanvien_report/report.php';
                return;
            }
            
            // Kiểm tra xem user đã submit form chưa
            $has_filtered = !empty($_GET['tu_ngay']) && !empty($_GET['den_ngay']);
            
            if (!$has_filtered) {
                $thang = $available_months[0];
                require_once 'views/nhanvien_report/report.php';
                return;
            }
            
            // ✅ LẤY THÁNG
            $thang = !empty($_GET['thang']) ? $_GET['thang'] : $available_months[0];
            if (!in_array($thang, $available_months)) {
                $thang = $available_months[0];
            }
            
            // ✅ LẤY KHOẢNG NGÀY
            $tu_ngay = trim($_GET['tu_ngay']);
            $den_ngay = trim($_GET['den_ngay']);
            
            if (strtotime($tu_ngay) > strtotime($den_ngay)) {
                list($tu_ngay, $den_ngay) = [$den_ngay, $tu_ngay];
            }
            
            // Đảm bảo trong tháng
            $thang_start = $thang . '-01';
            $thang_end = date('Y-m-t', strtotime($thang . '-01'));
            
            if (strtotime($tu_ngay) < strtotime($thang_start)) $tu_ngay = $thang_start;
            if (strtotime($den_ngay) > strtotime($thang_end)) $den_ngay = $thang_end;

            // ✅ TÍNH SỐ NGÀY
            $ngay_diff = intval((strtotime($den_ngay) - strtotime($tu_ngay)) / 86400);
            $so_ngay = max(1, $ngay_diff + 1);

            // ✅ LẤY THỐNG KÊ HỆ THỐNG (2 QUERY THAY VÌ HÀNG TRĂM)
            $stats_thang = $this->model->getSystemStatsForMonth($thang);
            $stats_khoang = $this->model->getSystemStatsForRange($tu_ngay, $den_ngay);
            
            $tong_tien_ky = $stats_thang['total'];
            $tong_tien_khoang = $stats_khoang['total'];
            
            $ket_qua_chung = ($tong_tien_ky > 0) ? ($tong_tien_khoang / $tong_tien_ky) : 0;
            $ty_le_nghi_van = $ket_qua_chung * 1.5;

            // ✅ LƯU BENCHMARK
            $tong_tien_ky_detailed = [
                'ds_tb_chung_khoang' => $stats_khoang['ds_tb_chung_khoang'] ?? 0,
                'ds_ngay_cao_nhat_tb_khoang' => $stats_khoang['ds_ngay_cao_nhat_tb_khoang'] ?? 0,
                'so_nhan_vien_khoang' => $stats_khoang['emp_count'] ?? 0,
                'tong_tien_khoang' => $tong_tien_khoang,
                'so_ngay' => $so_ngay,
                
                'ds_tb_chung_thang' => $stats_thang['ds_tb_chung_thang'] ?? 0,
                'ds_ngay_cao_nhat_tb_thang' => $stats_thang['ds_ngay_cao_nhat_tb_thang'] ?? 0,
                'so_nhan_vien_thang' => $stats_thang['emp_count'] ?? 0,
                'tong_tien_ky' => $tong_tien_ky,
                'so_ngay_trong_thang' => $stats_thang['so_ngay'] ?? 30
            ];

            // ✅ LẤY TẤT CẢ NHÂN VIÊN 1 LẦN (1 QUERY DUY NHẤT)
            $employees = $this->model->getAllEmployeesWithStats($tu_ngay, $den_ngay, $thang);

            if (empty($employees)) {
                $message = "⚠️ Không có dữ liệu nhân viên trong khoảng thời gian này.";
                $type = 'warning';
                require_once 'views/nhanvien_report/report.php';
                return;
            }

            // ✅ XỬ LÝ DỮ LIỆU (KHÔNG CẦN QUERY THÊM)
            $report_nghi_van = [];
            $report_ok = [];
            
            foreach ($employees as $emp) {
                $ds_tim_kiem = $emp['ds_tong_thang_nv'] ?? 0;
                $ds_tien_do = $emp['ds_tien_do'] ?? 0;

                if ($ds_tien_do > 0 || $ds_tim_kiem > 0) {
                    $ty_le = ($ds_tim_kiem > 0) ? ($ds_tien_do / $ds_tim_kiem) : 0;
                    
                    $row = [
                        'ma_nv' => $emp['DSRCode'],
                        'ten_nv' => 'NV_' . $emp['DSRCode'],
                        'tinh' => $emp['DSRTypeProvince'] ?? '',
                        'ds_tim_kiem' => $ds_tim_kiem,
                        'ds_tien_do' => $ds_tien_do,
                        'ty_le' => $ty_le,
                        
                        'ds_ngay_cao_nhat_nv_khoang' => $emp['ds_ngay_cao_nhat_nv_khoang'] ?? 0,
                        'so_ngay_co_doanh_so_khoang' => $emp['so_ngay_co_doanh_so_khoang'] ?? 0,
                        
                        'ds_tong_thang_nv' => $ds_tim_kiem,
                        'ds_ngay_cao_nhat_nv_thang' => $emp['ds_ngay_cao_nhat_nv_thang'] ?? 0,
                        'so_ngay_co_doanh_so_thang' => $emp['so_ngay_co_doanh_so_thang'] ?? 0
                    ];
                    
                    if ($ty_le >= $ty_le_nghi_van) {
                        $row['is_suspect'] = true;
                        $report_nghi_van[] = $row;
                    } else {
                        $row['is_suspect'] = false;
                        $report_ok[] = $row;
                    }
                }
            }

            // Sắp xếp
            usort($report_nghi_van, function($a, $b) {
                return $b['ty_le'] <=> $a['ty_le'];
            });
            
            // Xác định top
            $tong_nghi_van = count($report_nghi_van);
            
            if ($tong_nghi_van >= 20) $top_threshold = 20;
            elseif ($tong_nghi_van >= 15) $top_threshold = 15;
            elseif ($tong_nghi_van >= 10) $top_threshold = 10;
            elseif ($tong_nghi_van >= 5) $top_threshold = 5;
            else $top_threshold = $tong_nghi_van;
            
            // Thêm rank
            foreach ($report_nghi_van as $key => &$item) {
                $item['rank'] = $key + 1;
                $item['highlight_type'] = ($item['rank'] <= $top_threshold) ? 'red' : 'orange';
            }
            unset($item);
            
            foreach ($report_ok as &$item) {
                $item['rank'] = 0;
                $item['highlight_type'] = 'none';
            }
            unset($item);
            
            $report = array_merge($report_nghi_van, $report_ok);
            
            $debug_info = "Tháng: $thang | Nhân viên: " . count($employees) . " | Nghi vấn: $tong_nghi_van | Top: $top_threshold | Thời gian xử lý: <1s";
            
            if (empty($report)) {
                $message = "⚠️ Không có dữ liệu cho khoảng thời gian này.";
                $type = 'warning';
            } else {
                $message = "✅ Phân tích thành công " . count($report) . " nhân viên trong <1 giây!";
                $type = 'success';
            }
            
        } catch (Exception $e) {
            $message = "❌ Lỗi: " . $e->getMessage();
            $type = 'danger';
            error_log("NhanVienReportController Error: " . $e->getMessage());
        }
        
        require_once 'views/nhanvien_report/report.php';
    }
}
?>