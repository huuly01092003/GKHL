<?php
require_once 'models/ExportModel.php';

class ExportController {
    private $model;

    public function __construct() {
        $this->model = new ExportModel();
    }

    public function exportCSV() {
        // ✅ CẬP NHẬT: Lấy nhiều năm và nhiều tháng
        $selectedYears = isset($_GET['years']) ? (array)$_GET['years'] : [];
        $selectedMonths = isset($_GET['months']) ? (array)$_GET['months'] : [];
        
        // Chuyển đổi sang array số nguyên
        $selectedYears = array_map('intval', array_filter($selectedYears));
        $selectedMonths = array_map('intval', array_filter($selectedMonths));
        
        // Validate
        if (empty($selectedYears) || empty($selectedMonths)) {
            $_SESSION['error'] = 'Vui lòng chọn năm và tháng để export';
            header('Location: report.php');
            exit;
        }

        // Lấy filters khác
        $filters = [
            'ma_tinh_tp' => $_GET['ma_tinh_tp'] ?? '',
            'ma_khach_hang' => $_GET['ma_khach_hang'] ?? '',
            'gkhl_status' => $_GET['gkhl_status'] ?? ''
        ];

        // Lấy dữ liệu
        $data = $this->model->getExportData($selectedYears, $selectedMonths, $filters);

        if (empty($data)) {
            $_SESSION['error'] = 'Không có dữ liệu để export';
            header('Location: report.php?years[]=' . implode('&years[]=', $selectedYears) . 
                   '&months[]=' . implode('&months[]=', $selectedMonths));
            exit;
        }

        // Tạo tên file
        $fileName = $this->generateFileName($selectedYears, $selectedMonths, $filters);

        // Set headers để download file
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Mở output stream
        $output = fopen('php://output', 'w');

        // Thêm BOM cho UTF-8 (để Excel hiển thị đúng tiếng Việt)
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Header CSV
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
            'ĐK Trưng bày'
        ];

        fputcsv($output, $headers);

        // Ghi dữ liệu
        foreach ($data as $index => $row) {
            $csvRow = [
                $index + 1,
                $row['ma_khach_hang'] ?? '',
                $row['ten_khach_hang'] ?? '',
                $row['loai_kh'] ?? '',
                $row['dia_chi'] ?? '',
                $row['quan_huyen'] ?? '',
                $row['ma_tinh_tp'] ?? '',
                $row['ma_so_thue'] ?? '',
                $row['area'] ?? '',
                $row['ma_gsbh'] ?? '',
                $row['phan_loai_nhom_kh'] ?? '',
                $row['ma_npp'] ?? '',
                $row['ma_nvbh'] ?? '',
                $row['ten_nvbh'] ?? '',
                $row['location'] ?? '',
                implode(', ', $selectedYears), // Năm
                implode(', ', $selectedMonths), // Tháng
                $row['so_don_hang'] ?? 0,
                $row['total_san_luong'] ?? 0,
                $row['total_doanh_so_truoc_ck'] ?? 0,
                $row['total_chiet_khau'] ?? 0,
                $row['total_doanh_so'] ?? 0,
                !empty($row['has_gkhl']) ? 'Có' : 'Không',
                $row['gkhl_ten_quay'] ?? '',
                $row['gkhl_ten_chu'] ?? '',
                $row['gkhl_ngay_sinh'] ?? '',
                $row['gkhl_thang_sinh'] ?? '',
                $row['gkhl_nam_sinh'] ?? '',
                $row['gkhl_sdt_zalo'] ?? '',
                $row['gkhl_sdt_dinh_danh'] ?? '',
                $row['gkhl_khop_sdt'] ?? '',
                $row['gkhl_dk_chuong_trinh'] ?? '',
                $row['gkhl_dk_muc_doanh_so'] ?? '',
                $row['gkhl_dk_trung_bay'] ?? ''
            ];

            fputcsv($output, $csvRow);
        }

        fclose($output);
        exit;
    }

    private function generateFileName($years, $months, $filters) {
        $fileName = "BaoCao_KhachHang";
        
        // Thêm năm
        if (count($years) > 1) {
            $fileName .= "_Nam" . min($years) . "-" . max($years);
        } else {
            $fileName .= "_Nam" . $years[0];
        }
        
        // Thêm tháng
        if (count($months) == 12) {
            $fileName .= "_TatCaThang";
        } elseif (count($months) > 1) {
            $fileName .= "_Thang" . min($months) . "-" . max($months);
        } else {
            $fileName .= "_Thang" . $months[0];
        }
        
        // Thêm tỉnh
        if (!empty($filters['ma_tinh_tp'])) {
            $fileName .= "_" . $this->slugify($filters['ma_tinh_tp']);
        }
        
        // Thêm GKHL status
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
        // Chuyển tiếng Việt không dấu
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
}
?>