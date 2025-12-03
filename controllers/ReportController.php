<?php
require_once 'models/OrderDetailModel.php';

class ReportController {
    private $model;

    public function __construct() {
        $this->model = new OrderDetailModel();
    }

    public function index() {
        // Lấy tháng/năm từ query string (format: "11/2025")
        $thangNam = $_GET['thang_nam'] ?? '';
        
        $filters = [
            'ma_tinh_tp' => $_GET['ma_tinh_tp'] ?? '',
            'ma_khach_hang' => $_GET['ma_khach_hang'] ?? '',
            'gkhl_status' => $_GET['gkhl_status'] ?? ''
        ];

        $data = [];
        $summary = [
            'total_khach_hang' => 0,
            'total_doanh_so' => 0,
            'total_san_luong' => 0,
            'total_gkhl' => 0
        ];

        $provinces = $this->model->getProvinces();
        $monthYears = $this->model->getMonthYears();

        // Nếu đã chọn tháng/năm
        if (!empty($thangNam)) {
            // Parse tháng/năm (format: "11/2025")
            $parts = explode('/', $thangNam);
            if (count($parts) === 2) {
                $rptMonth = (int)$parts[0];
                $rptYear = (int)$parts[1];
                
                $data = $this->model->getCustomerSummary($rptMonth, $rptYear, $filters);
                $summary = $this->model->getSummaryStats($rptMonth, $rptYear, $filters);
            }
        }

        require_once 'views/report.php';
    }

    public function detail() {
        $maKhachHang = $_GET['ma_khach_hang'] ?? '';
        $thangNam = $_GET['thang_nam'] ?? '';

        if (empty($maKhachHang) || empty($thangNam)) {
            header('Location: report.php');
            exit;
        }

        // Parse tháng/năm
        $parts = explode('/', $thangNam);
        if (count($parts) !== 2) {
            header('Location: report.php');
            exit;
        }
        
        $rptMonth = (int)$parts[0];
        $rptYear = (int)$parts[1];

        $data = $this->model->getCustomerDetail($maKhachHang, $rptMonth, $rptYear);
        $location = $this->model->getCustomerLocation($maKhachHang);
        $gkhlInfo = $this->model->getGkhlInfo($maKhachHang);
        
        require_once 'views/detail.php';
    }
}
?>