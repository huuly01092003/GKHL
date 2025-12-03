<?php
require_once 'models/GkhlModel.php';

class GkhlController {
    private $model;

    public function __construct() {
        $this->model = new GkhlModel();
    }

    public function showImportForm() {
        require_once 'views/gkhl/import.php';
    }

    public function handleUpload() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: gkhl.php');
            exit;
        }

        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = 'Vui lòng chọn file CSV';
            header('Location: gkhl.php');
            exit;
        }

        $file = $_FILES['csv_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($ext !== 'csv') {
            $_SESSION['error'] = 'Chỉ chấp nhận file CSV';
            header('Location: gkhl.php');
            exit;
        }

        $result = $this->model->importCSV($file['tmp_name']);
        
        if ($result['success']) {
            $_SESSION['success'] = "Import thành công {$result['inserted']} bản ghi vào GKHL";
        } else {
            $_SESSION['error'] = "Import thất bại: {$result['error']}";
        }

        header('Location: gkhl.php');
        exit;
    }

    public function showList() {
        $filters = [
            'ma_nvbh' => $_GET['ma_nvbh'] ?? '',
            'ma_kh_dms' => $_GET['ma_kh_dms'] ?? '',
            'khop_sdt' => $_GET['khop_sdt'] ?? '',
            'nam_sinh' => $_GET['nam_sinh'] ?? ''
        ];

        // Tối ưu: load dữ liệu với LIMIT để tránh tăng RAM quá mức
        $data = $this->model->getAll($filters);
        $saleStaff = $this->model->getSaleStaff();
        $birthYears = $this->model->getBirthYears();
        $totalCount = $this->model->getTotalCount();
        $phoneMatchCount = $this->model->getPhoneMatchCount();

        require_once 'views/gkhl/list.php';
    }
}