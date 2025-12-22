
<?php
$currentPage = 'import';
require_once dirname(__DIR__) . '/components/navbar.php';renderNavbar($currentPage);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phát hiện Khách hàng Bất thường</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        body { background: #f5f7fa; }
        .navbar-custom {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .filter-card, .data-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            padding: 25px;
            margin-bottom: 25px;
        }
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid;
        }
        .stat-box.critical { border-left-color: #dc3545; }
        .stat-box.high { border-left-color: #fd7e14; }
        .stat-box.medium { border-left-color: #ffc107; }
        .stat-box.low { border-left-color: #20c997; }
        .stat-box h2 { margin-bottom: 5px; font-weight: 700; }
        
        .table thead {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
        }
        
        .risk-critical {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            font-weight: 700;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        .risk-high {
            background: linear-gradient(135deg, #fd7e14 0%, #e8590c 100%);
            color: white;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        .risk-medium {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: #000;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        .risk-low {
            background: linear-gradient(135deg, #20c997 0%, #17a589 100%);
            color: white;
            font-weight: 500;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        tr.row-critical { background-color: rgba(220, 53, 69, 0.12) !important; }
        tr.row-high { background-color: rgba(253, 126, 20, 0.12) !important; }
        tr.row-medium { background-color: rgba(255, 193, 7, 0.12) !important; }
        tr.row-low { background-color: rgba(32, 201, 151, 0.08) !important; }
        
        .score-badge {
            font-size: 1.1rem;
            font-weight: 700;
            padding: 10px 18px;
            border-radius: 25px;
        }
        .score-critical { background: #dc3545; color: white; }
        .score-high { background: #fd7e14; color: white; }
        .score-medium { background: #ffc107; color: #000; }
        .score-low { background: #20c997; color: white; }
        
        .anomaly-detail {
            font-size: 0.85rem;
            color: #495057;
            line-height: 1.8;
        }
        .anomaly-detail li {
            margin-bottom: 5px;
            padding-left: 5px;
        }
        .anomaly-icon {
            color: #dc3545;
            margin-right: 5px;
        }
        
        .period-display {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .btn-export-anomaly {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
            padding: 10px 25px;
            border-radius: 25px;
            font-weight: 600;
        }
        .btn-export-anomaly:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
            color: white;
        }
        
        .quick-select-btn {
            padding: 4px 12px;
            font-size: 0.85rem;
            margin: 2px;
        }
        
        .warning-header {
            background: linear-gradient(135deg, #ff6b6b15 0%, #ee5a6f15 100%);
            padding: 20px;
            border-radius: 10px;
            border-left: 5px solid #dc3545;
            margin-bottom: 25px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-custom navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="fas fa-exclamation-triangle me-2"></i>Phát hiện Khách hàng Bất thường
            </span>
            <div>
                <a href="report.php" class="btn btn-light me-2">
                    <i class="fas fa-chart-bar me-2"></i>Báo cáo thường
                </a>
                <a href="index.php" class="btn btn-outline-light">
                    <i class="fas fa-home me-2"></i>Trang chủ
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="warning-header">
            <h5 class="mb-2">
                <i class="fas fa-shield-alt me-2"></i><strong>Phát hiện hành vi bất thường của khách hàng</strong>
            </h5>
            <p class="mb-0">
                Phân tích 15 chỉ số bất thường để phát hiện khách hàng có hành vi đáng ngờ. 
                Điểm càng cao thể hiện mức độ nghi vấn càng lớn.
            </p>
        </div>

        <div class="filter-card">
            <h5 class="mb-4"><i class="fas fa-filter me-2"></i>Chọn kỳ phân tích</h5>
            
            <form method="GET" action="anomaly.php" id="filterForm">
                <div class="row g-3">
                    <!-- Năm -->
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-calendar me-1"></i>Năm <span class="text-danger">*</span>
                        </label>
                        <select name="years[]" id="yearSelect" class="form-select" multiple required>
                            <?php foreach ($availableYears as $year): ?>
                                <option value="<?= $year ?>" 
                                    <?= in_array($year, $selectedYears) ? 'selected' : '' ?>>
                                    <?= $year ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-primary quick-select-btn" onclick="selectAllYears()">
                                Tất cả
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary quick-select-btn" onclick="clearYears()">
                                Xóa
                            </button>
                        </div>
                    </div>

                    <!-- Tháng -->
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-calendar-alt me-1"></i>Tháng <span class="text-danger">*</span>
                        </label>
                        <select name="months[]" id="monthSelect" class="form-select" multiple required>
                            <?php foreach ($availableMonths as $month): ?>
                                <option value="<?= $month ?>" 
                                    <?= in_array($month, $selectedMonths) ? 'selected' : '' ?>>
                                    Tháng <?= $month ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-primary quick-select-btn" onclick="selectAllMonths()">
                                Tất cả
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary quick-select-btn" onclick="clearMonths()">
                                Xóa
                            </button>
                        </div>
                    </div>

                    <!-- ✅ THÊM: Tỉnh -->
                    <div class="col-md-2">
                        <label class="form-label fw-bold">
                            <i class="fas fa-map-marker-alt me-1"></i>Tỉnh/TP
                        </label>
                        <select name="ma_tinh_tp" class="form-select">
                            <option value="">-- Tất cả --</option>
                            <?php foreach ($provinces as $province): ?>
                                <option value="<?= htmlspecialchars($province) ?>" 
                                    <?= (isset($filters['ma_tinh_tp']) && $filters['ma_tinh_tp'] === $province) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($province) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- ✅ THÊM: GKHL -->
                    <div class="col-md-2">
                        <label class="form-label fw-bold">
                            <i class="fas fa-handshake me-1"></i>GKHL
                        </label>
                        <select name="gkhl_status" class="form-select">
                            <option value="" <?= (isset($filters['gkhl_status']) && $filters['gkhl_status'] === '') ? 'selected' : '' ?>>
                                -- Tất cả --
                            </option>
                            <option value="1" <?= (isset($filters['gkhl_status']) && $filters['gkhl_status'] === '1') ? 'selected' : '' ?>>
                                ✅ Có GKHL
                            </option>
                            <option value="0" <?= (isset($filters['gkhl_status']) && $filters['gkhl_status'] === '0') ? 'selected' : '' ?>>
                                ❌ Chưa có GKHL
                            </option>
                        </select>
                    </div>

                    <!-- Nút phân tích -->
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-danger btn-lg w-100">
                            <i class="fas fa-search me-2"></i>Phân tích
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <?php if (!empty($periodDisplay)): ?>
            <div class="period-display">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-check me-2"></i>
                    Kỳ phân tích: <strong><?= htmlspecialchars($periodDisplay) ?></strong>
                    <?php if (!empty($filters['ma_tinh_tp'])): ?>
                        | <i class="fas fa-map-marker-alt me-1"></i> <?= htmlspecialchars($filters['ma_tinh_tp']) ?>
                    <?php endif; ?>
                    <?php if (isset($filters['gkhl_status']) && $filters['gkhl_status'] !== ''): ?>
                        | <i class="fas fa-handshake me-1"></i> 
                        <?= $filters['gkhl_status'] === '1' ? 'Chỉ KH có GKHL' : 'Chỉ KH chưa có GKHL' ?>
                    <?php endif; ?>
                </h5>
            </div>
        <?php endif; ?>

        <?php if (!empty($data)): ?>
            <div class="row mb-3">
                <div class="col-12">
                    <div class="alert alert-success d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-download me-2"></i>
                            <strong>Export báo cáo:</strong> 
                            Xuất <?= number_format($summary['total_customers']) ?> khách hàng có dấu hiệu bất thường
                        </div>
                        <?php 
                        $yearsParam = http_build_query(['years' => $selectedYears]);
                        $monthsParam = http_build_query(['months' => $selectedMonths]);
                        $exportUrl = "anomaly.php?action=export&{$yearsParam}&{$monthsParam}";
                        if (!empty($filters['ma_tinh_tp'])) {
                            $exportUrl .= '&ma_tinh_tp=' . urlencode($filters['ma_tinh_tp']);
                        }
                        if (isset($filters['gkhl_status']) && $filters['gkhl_status'] !== '') {
                            $exportUrl .= '&gkhl_status=' . urlencode($filters['gkhl_status']);
                        }
                        ?>
                        <a href="<?= $exportUrl ?>" class="btn btn-export-anomaly">
                            <i class="fas fa-file-csv me-2"></i>Export CSV
                        </a>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="stat-box critical">
                        <h2 style="color: #dc3545;"><?= number_format($summary['critical_count']) ?></h2>
                        <p class="mb-0"><i class="fas fa-exclamation-circle me-1"></i>Cực kỳ nghiêm trọng</p>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-box high">
                        <h2 style="color: #fd7e14;"><?= number_format($summary['high_count']) ?></h2>
                        <p class="mb-0"><i class="fas fa-exclamation-triangle me-1"></i>Nghi vấn cao</p>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-box medium">
                        <h2 style="color: #ffc107;"><?= number_format($summary['medium_count']) ?></h2>
                        <p class="mb-0"><i class="fas fa-exclamation me-1"></i>Nghi vấn TB</p>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stat-box low">
                        <h2 style="color: #20c997;"><?= number_format($summary['low_count']) ?></h2>
                        <p class="mb-0"><i class="fas fa-info-circle me-1"></i>Nghi vấn thấp</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-box" style="border-left-color: #6c757d;">
                        <h2 style="color: #495057;"><?= number_format($summary['total_customers']) ?></h2>
                        <p class="mb-0"><i class="fas fa-users me-1"></i>Tổng KH phát hiện</p>
                    </div>
                </div>
            </div>

            <div class="data-card">
                <h5 class="mb-4">
                    <i class="fas fa-list-ol me-2"></i>Top 100 Khách hàng có Dấu hiệu Bất thường
                </h5>
                <div class="table-responsive">
                    <table id="anomalyTable" class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th style="width: 40px;">Top</th>
                                <th style="width: 100px;">Mã KH</th>
                                <th style="width: 200px;">Tên khách hàng</th>
                                <th style="width: 100px; text-align: center;">Điểm BT</th>
                                <th style="width: 150px; text-align: center;">Mức độ nguy cơ</th>
                                <th style="width: 80px; text-align: center;">Số dấu hiệu</th>
                                <th style="width: 120px; text-align: right;">Doanh số</th>
                                <th style="width: 80px; text-align: right;">Đơn hàng</th>
                                <th>Chi tiết bất thường</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data as $index => $row): ?>
                                <tr class="row-<?= $row['risk_level'] ?>">
                                    <td class="text-center">
                                        <strong style="font-size: 1.1rem; color: #495057;">#<?= $index + 1 ?></strong>
                                    </td>
                                    <td><strong><?= htmlspecialchars($row['customer_code']) ?></strong></td>
                                    <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                    <td class="text-center">
                                        <span class="score-badge score-<?= $row['risk_level'] ?>">
                                            <?= number_format($row['total_score'], 1) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="risk-<?= $row['risk_level'] ?>">
                                            <?php
                                            $riskTexts = [
                                                'critical' => '🔴 CỰC KỲ NGHIÊM TRỌNG',
                                                'high' => '🟠 NGHI VẤN CAO',
                                                'medium' => '🟡 Nghi vấn TB',
                                                'low' => '🟢 Nghi vấn thấp'
                                            ];
                                            echo $riskTexts[$row['risk_level']] ?? 'Bình thường';
                                            ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-dark"><?= count($row['details']) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <strong><?= number_format($row['total_sales'], 0) ?></strong>
                                    </td>
                                    <td class="text-end">
                                        <?= number_format($row['total_orders'], 0) ?>
                                    </td>
                                    <td>
                                        <ul class="anomaly-detail mb-0 ps-3">
                                            <?php foreach (array_slice($row['details'], 0, 5) as $detail): ?>
                                                <li>
                                                    <i class="fas fa-exclamation-circle anomaly-icon"></i>
                                                    <?= htmlspecialchars($detail['description']) ?>
                                                    <small class="text-muted">(Điểm: <?= round($detail['weighted_score'], 1) ?>)</small>
                                                </li>
                                            <?php endforeach; ?>
                                            <?php if (count($row['details']) > 5): ?>
                                                <li class="text-muted">
                                                    <small><i>... và <?= count($row['details']) - 5 ?> dấu hiệu khác</i></small>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif (!empty($selectedYears) && !empty($selectedMonths)): ?>
            <div class="alert alert-info">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Tuyệt vời!</strong> Không phát hiện khách hàng có dấu hiệu bất thường trong kỳ này.
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-info-circle me-2"></i>
                Vui lòng chọn năm và tháng để phân tích hành vi bất thường.
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#yearSelect, #monthSelect').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Chọn...',
                allowClear: false
            });

            $('#anomalyTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/vi.json'
                },
                pageLength: 25,
                order: [[3, 'desc']],
                columnDefs: [
                    { orderable: false, targets: [8] }
                ],
                autoWidth: false,
                scrollX: false
            });
        });

        function selectAllYears() {
            $('#yearSelect option').prop('selected', true);
            $('#yearSelect').trigger('change');
        }

        function clearYears() {
            $('#yearSelect').val(null).trigger('change');
        }

        function selectAllMonths() {
            $('#monthSelect option').prop('selected', true);
            $('#monthSelect').trigger('change');
        }

        function clearMonths() {
            $('#monthSelect').val(null).trigger('change');
        }
    </script>
</body>
</html>