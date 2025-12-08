<?php
require_once 'models/AnomalyDetectionModel.php';
require_once 'models/OrderDetailModel.php';

class AnomalyController {
    private $model;
    private $orderModel;

    public function __construct() {
        $this->model = new AnomalyDetectionModel();
        $this->orderModel = new OrderDetailModel();
    }

    /**
     * Hiển thị báo cáo khách hàng bất thường
     */
    public function index() {
        $selectedYears = isset($_GET['years']) ? (array)$_GET['years'] : [];
        $selectedMonths = isset($_GET['months']) ? (array)$_GET['months'] : [];
        
        $selectedYears = array_map('intval', array_filter($selectedYears));
        $selectedMonths = array_map('intval', array_filter($selectedMonths));
        
        $availableYears = $this->orderModel->getAvailableYears();
        $availableMonths = $this->orderModel->getAvailableMonths();
        
        $data = [];
        $summary = [
            'total_customers' => 0,
            'critical_count' => 0,
            'high_count' => 0,
            'medium_count' => 0,
            'low_count' => 0
        ];
        
        if (!empty($selectedYears) && !empty($selectedMonths)) {
            $data = $this->model->calculateAnomalyScores($selectedYears, $selectedMonths);
            
            // Tính summary
            $summary['total_customers'] = count($data);
            foreach ($data as $item) {
                switch ($item['risk_level']) {
                    case 'critical':
                        $summary['critical_count']++;
                        break;
                    case 'high':
                        $summary['high_count']++;
                        break;
                    case 'medium':
                        $summary['medium_count']++;
                        break;
                    case 'low':
                        $summary['low_count']++;
                        break;
                }
            }
            
            // Giới hạn top 100 cho hiển thị
            $data = array_slice($data, 0, 100);
        }
        
        $periodDisplay = $this->generatePeriodDisplay($selectedYears, $selectedMonths);
        
        require_once 'views/anomaly/report.php';
    }

    /**
     * Export CSV khách hàng bất thường
     */
    public function exportCSV() {
        $selectedYears = isset($_GET['years']) ? (array)$_GET['years'] : [];
        $selectedMonths = isset($_GET['months']) ? (array)$_GET['months'] : [];
        
        $selectedYears = array_map('intval', array_filter($selectedYears));
        $selectedMonths = array_map('intval', array_filter($selectedMonths));
        
        if (empty($selectedYears) || empty($selectedMonths)) {
            $_SESSION['error'] = 'Vui lòng chọn năm và tháng để export';
            header('Location: anomaly.php');
            exit;
        }
        
        $data = $this->model->calculateAnomalyScores($selectedYears, $selectedMonths);
        
        if (empty($data)) {
            $_SESSION['error'] = 'Không có dữ liệu bất thường để export';
            header('Location: anomaly.php');
            exit;
        }
        
        $fileName = $this->generateFileName($selectedYears, $selectedMonths);
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        $headers = [
            'STT',
            'Mã KH',
            'Tên KH',
            'Điểm bất thường',
            'Mức độ nguy cơ',
            'Số dấu hiệu phát hiện',
            'Tổng doanh số',
            'Tổng đơn hàng',
            'Chi tiết bất thường'
        ];
        
        fputcsv($output, $headers);
        
        foreach ($data as $index => $row) {
            $anomalyDetails = [];
            foreach ($row['details'] as $detail) {
                $anomalyDetails[] = $detail['description'];
            }
            
            $riskLevelText = $this->getRiskLevelText($row['risk_level']);
            
            $csvRow = [
                $index + 1,
                $row['customer_code'],
                $row['customer_name'],
                $row['total_score'],
                $riskLevelText,
                count($row['details']),
                $row['total_sales'],
                $row['total_orders'],
                implode(' | ', $anomalyDetails)
            ];
            
            fputcsv($output, $csvRow);
        }
        
        fclose($output);
        exit;
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

    private function generateFileName($years, $months) {
        $fileName = "BaoCao_BatThuong";
        
        if (count($years) > 1) {
            $fileName .= "_Nam" . min($years) . "-" . max($years);
        } else {
            $fileName .= "_Nam" . $years[0];
        }
        
        if (count($months) == 12) {
            $fileName .= "_TatCaThang";
        } elseif (count($months) > 1) {
            $fileName .= "_Thang" . min($months) . "-" . max($months);
        } else {
            $fileName .= "_Thang" . $months[0];
        }
        
        $fileName .= "_" . date('YmdHis') . ".csv";
        
        return $fileName;
    }

    private function getRiskLevelText($level) {
        $levels = [
            'critical' => 'CỰC KỲ NGHIÊM TRỌNG',
            'high' => 'NGHI VẤN CAO',
            'medium' => 'Nghi vấn trung bình',
            'low' => 'Nghi vấn thấp',
            'normal' => 'Bình thường'
        ];
        
        return $levels[$level] ?? 'Không xác định';
    }
}
?>