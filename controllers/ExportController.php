<?php
// controllers/ExportController.php
require_once 'models/ExportModel.php';

class ExportController {
    private $model;

    public function __construct() {
        $this->model = new ExportModel();
    }

    public function exportCSV() {
        // Lấy các tham số lọc
        $thangNam = $_GET['thang_nam'] ?? '';
        $maTinh = $_GET['ma_tinh_tp'] ?? '';
        $gkhlStatus = $_GET['gkhl_status'] ?? '';
        
        // Validate tháng/năm
        if (empty($thangNam)) {
            $_SESSION['error'] = 'Vui lòng chọn tháng/năm để export';
            header('Location: report.php');
            exit;
        }

        // Parse tháng/năm
        $parts = explode('/', $thangNam);
        if (count($parts) !== 2) {
            $_SESSION['error'] = 'Định dạng tháng/năm không hợp lệ';
            header('Location: report.php');
            exit;
        }
        
        $rptMonth = (int)$parts[0];
        $rptYear = (int)$parts[1];

        // Lấy dữ liệu
        $filters = [
            'ma_tinh_tp' => $maTinh,
            'gkhl_status' => $gkhlStatus
        ];

        $data = $this->model->getExportData($rptMonth, $rptYear, $filters);

        if (empty($data)) {
            $_SESSION['error'] = 'Không có dữ liệu để export';
            header('Location: report.php?thang_nam=' . urlencode($thangNam));
            exit;
        }

        // Tạo tên file
        $fileName = $this->generateFileName($rptMonth, $rptYear, $maTinh, $gkhlStatus);

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
            'Tháng báo cáo',
            'Năm báo cáo',
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
                $rptMonth,
                $rptYear,
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

    private function generateFileName($month, $year, $province, $gkhlStatus) {
        $fileName = "BaoCao_KhachHang_{$month}_{$year}";
        
        if (!empty($province)) {
            $fileName .= "_" . $this->slugify($province);
        }
        
        if ($gkhlStatus === '1') {
            $fileName .= "_CoGKHL";
        } elseif ($gkhlStatus === '0') {
            $fileName .= "_ChuaCoGKHL";
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