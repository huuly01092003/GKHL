<?php
require_once 'models/OrderDetailModel.php';
require_once 'models/AnomalyDetectionModel.php';

class ReportController {
    private $model;
    private $anomalyModel;

    public function __construct() {
        $this->model = new OrderDetailModel();
        $this->anomalyModel = new AnomalyDetectionModel();
    }

    public function index() {
        $selectedYears = isset($_GET['years']) ? (array)$_GET['years'] : [];
        $selectedMonths = isset($_GET['months']) ? (array)$_GET['months'] : [];
        
        $selectedYears = array_map('intval', array_filter($selectedYears));
        $selectedMonths = array_map('intval', array_filter($selectedMonths));
        
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
        $availableYears = $this->model->getAvailableYears();
        $availableMonths = $this->model->getAvailableMonths();

        if (!empty($selectedYears) && !empty($selectedMonths)) {
            $data = $this->model->getCustomerSummary($selectedYears, $selectedMonths, $filters);
            $summary = $this->model->getSummaryStats($selectedYears, $selectedMonths, $filters);
        }

        $periodDisplay = $this->generatePeriodDisplay($selectedYears, $selectedMonths);

        require_once 'views/report.php';
    }

    public function detail() {
        $maKhachHang = $_GET['ma_khach_hang'] ?? '';
        $selectedYears = isset($_GET['years']) ? (array)$_GET['years'] : [];
        $selectedMonths = isset($_GET['months']) ? (array)$_GET['months'] : [];

        if (empty($maKhachHang) || empty($selectedYears) || empty($selectedMonths)) {
            header('Location: report.php');
            exit;
        }
        
        $selectedYears = array_map('intval', array_filter($selectedYears));
        $selectedMonths = array_map('intval', array_filter($selectedMonths));

        $data = $this->model->getCustomerDetail($maKhachHang, $selectedYears, $selectedMonths);
        $location = $this->model->getCustomerLocation($maKhachHang);
        $gkhlInfo = $this->model->getGkhlInfo($maKhachHang);
        
        // ✅ THÊM MỚI: Lấy thông tin bất thường
        $anomalyInfo = $this->anomalyModel->getCustomerAnomalyDetail($maKhachHang, $selectedYears, $selectedMonths);
        
        $periodDisplay = $this->generatePeriodDisplay($selectedYears, $selectedMonths);
        
        require_once 'views/detail.php';
    }

    private function generatePeriodDisplay($years, $months) {
        if (empty($years) || empty($months)) {
            return '';
        }

        $yearStr = count($years) > 1 ? 'Năm ' . implode(', ', $years) : 'Năm ' . $years[0];
        
        if (count($months) == 12) {
            $monthStr = 'Tất cả các tháng';
        } elseif (count($months) > 1) {
            $monthStr = 'Tháng ' . implode(', ', $months);
        } else {
            $monthStr = 'Tháng ' . $months[0];
        }

        return $monthStr . ' - ' . $yearStr;
    }
}
?>