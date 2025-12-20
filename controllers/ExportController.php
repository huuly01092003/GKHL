<?php
/**
 * FIXED: Export Controller
 * File: controllers/ExportController.php
 * 
 * Bug Fixes:
 * 1. Added proper error handling
 * 2. Fixed CSV headers to force download
 * 3. Added memory optimization for large datasets
 * 4. Included proper BOM for UTF-8
 */

require_once 'models/ExportModel.php';

class ExportController {
    private $model;

    public function __construct() {
        $this->model = new ExportModel();
    }

    public function exportCSV() {
        try {
            // Validate input
            $selectedYears = isset($_GET['years']) ? (array)$_GET['years'] : [];
            $selectedMonths = isset($_GET['months']) ? (array)$_GET['months'] : [];
            
            $selectedYears = array_map('intval', array_filter($selectedYears));
            $selectedMonths = array_map('intval', array_filter($selectedMonths));
            
            if (empty($selectedYears) || empty($selectedMonths)) {
                $_SESSION['error'] = 'Vui lòng chọn năm và tháng để export';
                header('Location: report.php');
                exit;
            }

            $filters = [
                'ma_tinh_tp' => $_GET['ma_tinh_tp'] ?? '',
                'ma_khach_hang' => $_GET['ma_khach_hang'] ?? '',
                'gkhl_status' => $_GET['gkhl_status'] ?? ''
            ];

            // Get data
            $data = $this->model->getExportData($selectedYears, $selectedMonths, $filters);

            if (empty($data)) {
                $_SESSION['error'] = 'Không có dữ liệu để export';
                $redirectUrl = $this->buildRedirectUrl($selectedYears, $selectedMonths, $filters);
                header("Location: $redirectUrl");
                exit;
            }

            // Generate filename
            $fileName = $this->generateFileName($selectedYears, $selectedMonths, $filters);

            // Disable output buffering
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Set headers for CSV download
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Cache-Control: private', false);

            // Open output stream
            $output = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for Excel compatibility
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            // Write headers
            $headers = [
                'STT',
                'Mã KH',
                'Tên KH',
                'Loại KH',
                'Địa chỉ',
                'Quận/Huyện',
                'Tỉnh/TP',
                'Mã số thuế',
                'Area',
                'Mã GSBH',
                'Phân loại nhóm KH',
                'Mã NPP',
                'Mã NVBH',
                'Tên NVBH',
                'Location',
                'Năm báo cáo',
                'Tháng báo cáo',
                'Tổng số đơn hàng',
                'Tổng sản lượng',
                'Tổng doanh số trước CK',
                'Tổng chiết khấu',
                'Tổng doanh số sau CK',
                'Có GKHL',
                'Tên quầy (GKHL)',
                'Tên chủ cửa hàng (GKHL)',
                'Ngày sinh',
                'Tháng sinh',
                'Năm sinh',
                'SĐT Zalo',
                'SĐT định danh',
                'Khớp SĐT',
                'ĐK Chương trình',
                'ĐK Mục doanh số',
                'ĐK Trưng bày',
                '⚠️ ĐIỂM BẤT THƯỜNG',
                '⚠️ MỨC ĐỘ NGUY CƠ',
                '⚠️ SỐ DẤU HIỆU',
                '⚠️ CHI TIẾT BẤT THƯỜNG'
            ];
            
            fputcsv($output, $headers);

            // Write data with memory optimization
            foreach ($data as $index => $row) {
                $riskLevelText = $this->getRiskLevelText($row['anomaly_risk_level']);
                
                $csvRow = [
                    $index + 1,
                    $this->sanitizeValue($row['ma_khach_hang']),
                    $this->sanitizeValue($row['ten_khach_hang']),
                    $this->sanitizeValue($row['loai_kh']),
                    $this->sanitizeValue($row['dia_chi']),
                    $this->sanitizeValue($row['quan_huyen']),
                    $this->sanitizeValue($row['ma_tinh_tp']),
                    $this->sanitizeValue($row['ma_so_thue']),
                    $this->sanitizeValue($row['area']),
                    $this->sanitizeValue($row['ma_gsbh']),
                    $this->sanitizeValue($row['phan_loai_nhom_kh']),
                    $this->sanitizeValue($row['ma_npp']),
                    $this->sanitizeValue($row['ma_nvbh']),
                    $this->sanitizeValue($row['ten_nvbh']),
                    $this->sanitizeValue($row['location']),
                    implode(', ', $selectedYears),
                    implode(', ', $selectedMonths),
                    $row['so_don_hang'] ?? 0,
                    $row['total_san_luong'] ?? 0,
                    $row['total_doanh_so_truoc_ck'] ?? 0,
                    $row['total_chiet_khau'] ?? 0,
                    $row['total_doanh_so'] ?? 0,
                    !empty($row['has_gkhl']) ? 'Có' : 'Không',
                    $this->sanitizeValue($row['gkhl_ten_quay']),
                    $this->sanitizeValue($row['gkhl_ten_chu']),
                    $this->sanitizeValue($row['gkhl_ngay_sinh']),
                    $this->sanitizeValue($row['gkhl_thang_sinh']),
                    $this->sanitizeValue($row['gkhl_nam_sinh']),
                    $this->sanitizeValue($row['gkhl_sdt_zalo']),
                    $this->sanitizeValue($row['gkhl_sdt_dinh_danh']),
                    $this->sanitizeValue($row['gkhl_khop_sdt']),
                    $this->sanitizeValue($row['gkhl_dk_chuong_trinh']),
                    $this->sanitizeValue($row['gkhl_dk_muc_doanh_so']),
                    $this->sanitizeValue($row['gkhl_dk_trung_bay']),
                    number_format($row['anomaly_score'], 1),
                    $riskLevelText,
                    $row['anomaly_count'],
                    $this->sanitizeValue($row['anomaly_details'])
                ];
                
                fputcsv($output, $csvRow);
                
                // Flush output buffer periodically for large datasets
                if ($index % 100 === 0) {
                    flush();
                }
            }
            
            fclose($output);
            exit;
            
        } catch (Exception $e) {
            // Log error
            error_log("Export Error: " . $e->getMessage());
            
            $_SESSION['error'] = "Lỗi export: " . $e->getMessage();
            header('Location: report.php');
            exit;
        }
    }

    /**
     * Sanitize CSV values to prevent injection and handle special characters
     */
    private function sanitizeValue($value) {
        if (is_null($value) || $value === '') {
            return '';
        }
        
        // Convert to string
        $value = (string)$value;
        
        // Remove potential CSV injection characters
        if (in_array($value[0], ['=', '+', '-', '@', "\t", "\r"])) {
            $value = "'" . $value;
        }
        
        return $value;
    }

    /**
     * Build redirect URL with parameters
     */
    private function buildRedirectUrl($years, $months, $filters) {
        $params = [];
        
        foreach ($years as $year) {
            $params[] = 'years[]=' . $year;
        }
        
        foreach ($months as $month) {
            $params[] = 'months[]=' . $month;
        }
        
        if (!empty($filters['ma_tinh_tp'])) {
            $params[] = 'ma_tinh_tp=' . urlencode($filters['ma_tinh_tp']);
        }
        
        if (!empty($filters['ma_khach_hang'])) {
            $params[] = 'ma_khach_hang=' . urlencode($filters['ma_khach_hang']);
        }
        
        if (isset($filters['gkhl_status']) && $filters['gkhl_status'] !== '') {
            $params[] = 'gkhl_status=' . urlencode($filters['gkhl_status']);
        }
        
        return 'report.php?' . implode('&', $params);
    }

    private function generateFileName($years, $months, $filters) {
        $fileName = "BaoCao_KhachHang";
        
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
        
        if (!empty($filters['ma_tinh_tp'])) {
            $fileName .= "_" . $this->slugify($filters['ma_tinh_tp']);
        }
        
        if (isset($filters['gkhl_status']) && $filters['gkhl_status'] !== '') {
            if ($filters['gkhl_status'] === '1') {
                $fileName .= "_CoGKHL";
            } elseif ($filters['gkhl_status'] === '0') {
                $fileName .= "_ChuaCoGKHL";
            }
        }
        
        $fileName .= "_" . date('YmdHis') . ".csv";
        
        return $fileName;
    }

    private function slugify($text) {
        $text = strtolower($text);
        $text = preg_replace('/[àáảãạăằắẳẵặâầấẩẫậ]/u', 'a', $text);
        $text = preg_replace('/[èéẻẽẹêềếểễệ]/u', 'e', $text);
        $text = preg_replace('/[ìíỉĩị]/u', 'i', $text);
        $text = preg_replace('/[òóỏõọôồốổỗộơờớởỡợ]/u', 'o', $text);
        $text = preg_replace('/[ùúủũụưừứửữự]/u', 'u', $text);
        $text = preg_replace('/[ỳýỷỹỵ]/u', 'y', $text);
        $text = preg_replace('/[đ]/u', 'd', $text);
        $text = preg_replace('/[^a-z0-9]+/', '_', $text);
        $text = trim($text, '_');
        return $text;
    }

    private function getRiskLevelText($level) {
        $levels = [
            'critical' => '🔴 CỰC KỲ NGHIÊM TRỌNG',
            'high' => '🟠 NGHI VẤN CAO',
            'medium' => '🟡 Nghi vấn trung bình',
            'low' => '🟢 Nghi vấn thấp',
            'normal' => '✅ Bình thường'
        ];
        
        return $levels[$level] ?? 'Không xác định';
    }
}
?>