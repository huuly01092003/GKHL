<?php
// views/export_form.php
require_once 'models/ExportModel.php';
$exportModel = new ExportModel();
$monthYears = $exportModel->getAvailableMonthYears();
$provinces = $exportModel->getProvinces();

$currentPage = 'export';
require_once __DIR__ . '/components/navbar.php';
renderNavbar($currentPage);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Báo cáo CSV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 0;
        }
        .export-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border: none;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .btn-export {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            padding: 12px 40px;
            border-radius: 25px;
            color: white;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(40, 167, 69, 0.4);
            color: white;
        }
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .preview-info {
            background: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%);
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="export-card">
                    <div class="card-header">
                        <h3 class="mb-0">
                            <i class="fas fa-file-download me-2"></i>Export Báo cáo Khách hàng (CSV)
                        </h3>
                        <p class="mb-0 mt-2">Xuất dữ liệu khách hàng chi tiết với các bộ lọc linh hoạt</p>
                    </div>
                    <div class="card-body p-5">
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-circle me-2"></i><?= $_SESSION['error'] ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php unset($_SESSION['error']); ?>
                        <?php endif; ?>

                        <div class="info-box">
                            <h6 class="mb-2">
                                <i class="fas fa-info-circle me-2"></i>Thông tin Export
                            </h6>
                            <ul class="mb-0">
                                <li>Mỗi khách hàng sẽ được xuất <strong>1 dòng duy nhất</strong> với tất cả thông tin tổng hợp</li>
                                <li>Bao gồm: Thông tin KH, Tổng đơn hàng, Doanh số, Sản lượng, Thông tin GKHL (nếu có)</li>
                                <li>File CSV tương thích với Excel và có thể mở trực tiếp</li>
                                <li>Hỗ trợ tiếng Việt có dấu (UTF-8 with BOM)</li>
                            </ul>
                        </div>

                        <form method="GET" action="export.php" id="exportForm">
                            <input type="hidden" name="action" value="download">
                            
                            <div class="filter-section">
                                <h5 class="mb-4">
                                    <i class="fas fa-filter me-2"></i>Bộ lọc dữ liệu
                                </h5>

                                <div class="row g-3">
                                    <!-- Tháng/Năm (Bắt buộc) -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">
                                            <i class="fas fa-calendar-alt me-1"></i>
                                            Tháng/Năm <span class="text-danger">*</span>
                                        </label>
                                        <select name="thang_nam" class="form-select" required id="thangNamSelect">
                                            <option value="">-- Chọn tháng/năm --</option>
                                            <?php foreach ($monthYears as $my): ?>
                                                <option value="<?= htmlspecialchars($my['thang_nam']) ?>">
                                                    Tháng <?= htmlspecialchars($my['thang_nam']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Bắt buộc chọn tháng/năm báo cáo</small>
                                    </div>

                                    <!-- Tỉnh/TP (Tùy chọn) -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            Tỉnh/Thành phố
                                        </label>
                                        <select name="ma_tinh_tp" class="form-select" id="tinhSelect">
                                            <option value="">-- Tất cả tỉnh/TP --</option>
                                            <?php foreach ($provinces as $province): ?>
                                                <option value="<?= htmlspecialchars($province) ?>">
                                                    <?= htmlspecialchars($province) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Để trống nếu muốn xuất tất cả</small>
                                    </div>

                                    <!-- Trạng thái GKHL (Tùy chọn) -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">
                                            <i class="fas fa-handshake me-1"></i>
                                            Trạng thái GKHL
                                        </label>
                                        <select name="gkhl_status" class="form-select" id="gkhlSelect">
                                            <option value="">-- Tất cả (Có & Chưa có GKHL) --</option>
                                            <option value="1">✅ Chỉ KH đã tham gia GKHL</option>
                                            <option value="0">❌ Chỉ KH chưa tham gia GKHL</option>
                                        </select>
                                        <small class="text-muted">Lọc theo trạng thái gắn kết</small>
                                    </div>

                                    <!-- Gợi ý tổ hợp -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">
                                            <i class="fas fa-magic me-1"></i>
                                            Gợi ý nhanh
                                        </label>
                                        <select class="form-select" id="quickFilter">
                                            <option value="">-- Chọn tổ hợp sẵn --</option>
                                            <option value="all">Tất cả khách hàng</option>
                                            <option value="gkhl_only">Chỉ KH có GKHL</option>
                                            <option value="no_gkhl">Chỉ KH chưa có GKHL</option>
                                        </select>
                                        <small class="text-muted">Chọn nhanh các tổ hợp thông dụng</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Preview thông tin -->
                            <div class="preview-info" id="previewInfo" style="display: none;">
                                <h6 class="mb-2">
                                    <i class="fas fa-eye me-2"></i>Thông tin sẽ export:
                                </h6>
                                <p class="mb-1" id="previewText"></p>
                                <small class="text-muted">
                                    <i class="fas fa-lightbulb me-1"></i>
                                    Click "Export CSV" để tải xuống file
                                </small>
                            </div>

                            <!-- Buttons -->
                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" class="btn btn-export btn-lg">
                                    <i class="fas fa-download me-2"></i>Export CSV
                                </button>
                                <a href="report.php" class="btn btn-outline-primary btn-lg">
                                    <i class="fas fa-chart-bar me-2"></i>Xem Báo cáo
                                </a>
                                <a href="index.php" class="btn btn-outline-secondary btn-lg">
                                    <i class="fas fa-home me-2"></i>Trang chủ
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Hướng dẫn sử dụng -->
                <div class="export-card mt-4">
                    <div class="card-body p-4">
                        <h5 class="mb-3">
                            <i class="fas fa-question-circle me-2"></i>Hướng dẫn sử dụng
                        </h5>
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-check-circle text-success me-2"></i>Các bước thực hiện:</h6>
                                <ol>
                                    <li>Chọn <strong>Tháng/Năm</strong> báo cáo (bắt buộc)</li>
                                    <li>Chọn <strong>Tỉnh/TP</strong> nếu cần (hoặc để trống để xuất tất cả)</li>
                                    <li>Chọn <strong>Trạng thái GKHL</strong> nếu cần lọc</li>
                                    <li>Click <strong>"Export CSV"</strong> để tải file</li>
                                </ol>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-file-csv text-primary me-2"></i>Nội dung file CSV:</h6>
                                <ul>
                                    <li>Thông tin khách hàng đầy đủ (34 cột)</li>
                                    <li>Tổng hợp đơn hàng, doanh số, sản lượng</li>
                                    <li>Thông tin GKHL (nếu khách hàng có tham gia)</li>
                                    <li>Mở được trực tiếp bằng Excel</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Quick filter presets
        document.getElementById('quickFilter').addEventListener('change', function() {
            const value = this.value;
            const gkhlSelect = document.getElementById('gkhlSelect');
            const tinhSelect = document.getElementById('tinhSelect');
            
            switch(value) {
                case 'all':
                    gkhlSelect.value = '';
                    tinhSelect.value = '';
                    break;
                case 'gkhl_only':
                    gkhlSelect.value = '1';
                    break;
                case 'no_gkhl':
                    gkhlSelect.value = '0';
                    break;
            }
            updatePreview();
        });

        // Update preview when filters change
        ['thangNamSelect', 'tinhSelect', 'gkhlSelect'].forEach(id => {
            document.getElementById(id).addEventListener('change', updatePreview);
        });

        function updatePreview() {
            const thangNam = document.getElementById('thangNamSelect').value;
            const tinh = document.getElementById('tinhSelect').value;
            const gkhl = document.getElementById('gkhlSelect').value;
            const previewDiv = document.getElementById('previewInfo');
            const previewText = document.getElementById('previewText');
            
            if (!thangNam) {
                previewDiv.style.display = 'none';
                return;
            }
            
            let text = `📅 <strong>Tháng ${thangNam}</strong>`;
            
            if (tinh) {
                text += ` | 📍 Tỉnh: <strong>${tinh}</strong>`;
            } else {
                text += ` | 📍 <strong>Tất cả tỉnh/TP</strong>`;
            }
            
            if (gkhl === '1') {
                text += ` | ✅ <strong>Chỉ KH có GKHL</strong>`;
            } else if (gkhl === '0') {
                text += ` | ❌ <strong>Chỉ KH chưa có GKHL</strong>`;
            } else {
                text += ` | 👥 <strong>Tất cả khách hàng</strong>`;
            }
            
            previewText.innerHTML = text;
            previewDiv.style.display = 'block';
        }

        // Validate form before submit
        document.getElementById('exportForm').addEventListener('submit', function(e) {
            const thangNam = document.getElementById('thangNamSelect').value;
            
            if (!thangNam) {
                e.preventDefault();
                alert('⚠️ Vui lòng chọn Tháng/Năm để export!');
                document.getElementById('thangNamSelect').focus();
                return false;
            }
            
            // Show loading indicator
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang xuất file...';
            submitBtn.disabled = true;
            
            // Re-enable after 3 seconds (file download should start)
            setTimeout(() => {
                submitBtn.innerHTML = '<i class="fas fa-download me-2"></i>Export CSV';
                submitBtn.disabled = false;
            }, 3000);
        });
    </script>
</body>
</html>