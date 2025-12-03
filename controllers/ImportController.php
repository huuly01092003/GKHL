<?php
require_once 'models/OrderDetailModel.php';

class ImportController {
    private $model;

    public function __construct() {
        $this->model = new OrderDetailModel();
    }

    public function index() {
        require_once 'views/import.php';
    }

    public function upload() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php');
            exit;
        }

        // ✅ Không cần nhập tháng/năm vì đã có trong data
        
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = 'Vui lòng chọn file CSV';
            header('Location: index.php');
            exit;
        }

        $file = $_FILES['csv_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($ext !== 'csv') {
            $_SESSION['error'] = 'Chỉ chấp nhận file CSV';
            header('Location: index.php');
            exit;
        }

        $result = $this->model->importCSV($file['tmp_name']);
        
        if ($result['success']) {
            $_SESSION['success'] = "Import thành công {$result['inserted']} dòng dữ liệu OrderDetail";
        } else {
            $_SESSION['error'] = "Import thất bại: {$result['error']}";
        }

        header('Location: index.php');
        exit;
    }
}
?>