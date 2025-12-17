<?php


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
        $has_filtered = false; // ⭐ Flag để check xem user có filter không
        
        try {
            // ✅ Lấy danh sách tháng có sẵn
            $available_months = $this->model->getAvailableMonths();
            
            if (empty($available_months)) {
                $message = "⚠️ Chưa có dữ liệu. Vui lòng import OrderDetail trước.";
                $type = 'warning';
                require_once 'views/nhanvien_report/report.php';
                return;
            }
            
            // ⭐ KiỂM TRA XEM USER ĐÃ SUBMIT FORM KHÔNG
            $has_filtered = !empty($_GET['tu_ngay']) && !empty($_GET['den_ngay']);
            
            // Nếu chưa filter, chỉ show form trống
            if (!$has_filtered) {
                $thang = $available_months[0];
                require_once 'views/nhanvien_report/report.php';
                return;
            }
            
            // ✅ LẤY THÁNG TỪ GET
            $thang = !empty($_GET['thang']) ? $_GET['thang'] : $available_months[0];
            if (!in_array($thang, $available_months)) {
                $thang = $available_months[0];
            }
            
            // ✅ LẤY KHOẢNG NGÀY
            $tu_ngay = $_GET['tu_ngay'] ?? '';
            $den_ngay = $_GET['den_ngay'] ?? '';
            
            // Validate & swap nếu cần
            if (empty($tu_ngay) || empty($den_ngay)) {
                $message = "⚠️ Vui lòng chọn khoảng ngày.";
                $type = 'warning';
                require_once 'views/nhanvien_report/report.php';
                return;
            }
            
            if (strtotime($tu_ngay) > strtotime($den_ngay)) {
                list($tu_ngay, $den_ngay) = [$den_ngay, $tu_ngay];
            }
            
            // Đảm bảo trong tháng
            $thang_start = $thang . '-01';
            $thang_end = date('Y-m-t', strtotime($thang . '-01'));
            
            if (strtotime($tu_ngay) < strtotime($thang_start)) $tu_ngay = $thang_start;
            if (strtotime($den_ngay) > strtotime($thang_end)) $den_ngay = $thang_end;

            // ✅ TÍNH TOÁN SỐ NGÀY
            $ngay_diff = intval((strtotime($den_ngay) - strtotime($tu_ngay)) / 86400);
            $so_ngay = max(1, $ngay_diff + 1);
            $so_ngay_trong_thang = intval(date('t', strtotime($thang_start)));

            // ✅ TỔNG TIỀN KỲ (CẢ THÁNG)
            $tong_tien_ky = $this->model->getTotalByMonth($thang);
            
            // ✅ TỔNG TIỀN KHOẢNG (CHỈ TRONG KHOẢNG NGÀY CHỌN)
            $tong_tien_khoang = $this->model->getTotalByDateRange($tu_ngay, $den_ngay);
            
            // ✅ KẾT QUẢ CHUNG = Khoảng / Kỳ
            $ket_qua_chung = ($tong_tien_ky > 0) ? ($tong_tien_khoang / $tong_tien_ky) : 0;
            
            // ✅ TỈ LỆ NGHI VẤN = KẾT QUẢ CHUNG × 1.5
            $ty_le_nghi_van = $ket_qua_chung * 1.5;

            // ✅ LẤY BENCHMARK CHI TIẾT
            $tong_tien_ky_detailed = [
                // Khoảng
                'ds_tb_chung_khoang' => $this->model->getSystemRangeAveragePerDay($tu_ngay, $den_ngay),
                'ds_ngay_cao_nhat_tb_khoang' => $this->model->getSystemMaxDailyAveragePerEmployee($tu_ngay, $den_ngay),
                'so_nhan_vien_khoang' => $this->model->getEmployeeCountInRange($tu_ngay, $den_ngay),
                'tong_tien_khoang' => $tong_tien_khoang,
                'so_ngay' => $so_ngay,
                
                // Tháng
                'ds_tb_chung_thang' => $this->model->getSystemMonthlyAveragePerDay($thang),
                'ds_ngay_cao_nhat_tb_thang' => $this->model->getSystemMaxDailyAveragePerEmployeeByMonth($thang),
                'so_nhan_vien_thang' => $this->model->getEmployeeCountInMonth($thang),
                'tong_tien_ky' => $tong_tien_ky,
                'so_ngay_trong_thang' => $so_ngay_trong_thang
            ];

            // ✅ LẤY DANH SÁCH NHÂN VIÊN
            $employees = $this->model->getAllEmployees();

            if (empty($employees)) {
                $message = "⚠️ Không có dữ liệu nhân viên.";
                $type = 'warning';
                require_once 'views/nhanvien_report/report.php';
                return;
            }

            // ✅ TÍNH TOÁN CHO TỪNG NHÂN VIÊN
            $report_nghi_van = [];
            $report_ok = [];
            
            foreach ($employees as $emp) {
                $ma_nv = $emp['DSRCode'];
                
                // Doanh số tìm kiếm (cả tháng)
                $ds_tim_kiem = $this->model->getEmployeeTotalByMonth($ma_nv, $thang);
                
                // Doanh số tiến độ (khoảng ngày)
                $ds_tien_do = $this->model->getEmployeeTotalByDateRange($ma_nv, $tu_ngay, $den_ngay);

                if ($ds_tien_do > 0 || $ds_tim_kiem > 0) {
                    $ty_le = ($ds_tim_kiem > 0) ? ($ds_tien_do / $ds_tim_kiem) : 0;
                    
                    // Khoảng thời gian
                    $so_ngay_co_doanh_so_khoang = $this->model->getEmployeeDaysWithOrders($ma_nv, $tu_ngay, $den_ngay);
                    $ds_ngay_cao_nhat_nv_khoang = $this->model->getMaxDailyAmountByDateRange($ma_nv, $tu_ngay, $den_ngay);
                    
                    // Tháng
                    $so_ngay_co_doanh_so_thang = $this->model->getEmployeeDaysWithOrders($ma_nv, $thang_start, $thang_end);
                    $ds_ngay_cao_nhat_nv_thang = $this->model->getMaxDailyAmountByMonth($ma_nv, $thang);
                    $ds_tong_thang_nv = $ds_tim_kiem;
                    
                    $row = [
                        'ma_nv' => $ma_nv,
                        'ten_nv' => $emp['ten_nv'] ?? '',
                        'tinh' => $emp['DSRTypeProvince'] ?? '',
                        'ds_tim_kiem' => $ds_tim_kiem,
                        'ds_tien_do' => $ds_tien_do,
                        'ty_le' => $ty_le,
                        
                        // Khoảng
                        'ds_ngay_cao_nhat_nv_khoang' => $ds_ngay_cao_nhat_nv_khoang,
                        'so_ngay_co_doanh_so_khoang' => $so_ngay_co_doanh_so_khoang,
                        
                        // Tháng
                        'ds_tong_thang_nv' => $ds_tong_thang_nv,
                        'ds_ngay_cao_nhat_nv_thang' => $ds_ngay_cao_nhat_nv_thang,
                        'so_ngay_co_doanh_so_thang' => $so_ngay_co_doanh_so_thang
                    ];
                    
                    // So sánh với tỉ lệ nghi vấn
                    if ($ty_le >= $ty_le_nghi_van) {
                        $row['is_suspect'] = true;
                        $report_nghi_van[] = $row;
                    } else {
                        $row['is_suspect'] = false;
                        $report_ok[] = $row;
                    }
                }
            }

            // ✅ SẮP XẾP NHÓM NGHI VẤN
            usort($report_nghi_van, function($a, $b) {
                return $b['ty_le'] <=> $a['ty_le'];
            });
            
            // ✅ XÁC ĐỊNH TOP HIGHLIGHT
            $tong_nghi_van = count($report_nghi_van);
            
            if ($tong_nghi_van >= 20) {
                $top_threshold = 20;
            } elseif ($tong_nghi_van >= 15) {
                $top_threshold = 15;
            } elseif ($tong_nghi_van >= 10) {
                $top_threshold = 10;
            } elseif ($tong_nghi_van >= 5) {
                $top_threshold = 5;
            } else {
                $top_threshold = $tong_nghi_van;
            }
            
            // THÊM RANK & HIGHLIGHT
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
            
            // GỘP LẠI
            $report = array_merge($report_nghi_van, $report_ok);
            
            $debug_info = "Tháng: $thang | Nhân viên: " . count($employees) . " | Nghi vấn: $tong_nghi_van | Top: $top_threshold";
            
            if (empty($report)) {
                $message = "⚠️ Không có dữ liệu cho khoảng thời gian này.";
                $type = 'warning';
            }
        } catch (Exception $e) {
            $message = "❌ Lỗi: " . $e->getMessage();
            $type = 'danger';
        }
        
        require_once 'views/nhanvien_report/report.php';
    }
}