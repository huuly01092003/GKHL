<?php
$currentPage = 'report';
require_once __DIR__ . '/components/navbar.php';
renderNavbar($currentPage, $periodDisplay ?? '');
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo Khách hàng</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        body { background: #f5f7fa; }
        .navbar-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .badge-gkhl {
            background: #28a745;
            color: white;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-no-gkhl {
            background: #dc3545;
            color: white;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .btn-detail {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        .btn-detail:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .select2-container--bootstrap-5 .select2-selection {
            min-height: 38px;
        }
        .quick-select-btn {
            padding: 4px 12px;
            font-size: 0.85rem;
            margin: 2px;
        }
        .period-display {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-custom navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="fas fa-chart-bar me-2"></i>Báo cáo Khách hàng
            </span>
            <div class="col-md-1">
                        <a href="?action=nhanvien_report" class="btn btn-secondary w-100">
                            <i class="fas fa-sync"></i> Làm Mới
                        </a>
                    </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="filter-card">
            <h5 class="mb-4"><i class="fas fa-filter me-2"></i>Bộ lọc dữ liệu</h5>
            
            <form method="GET" action="report.php" id="filterForm">
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
                                <i class="fas fa-check-double"></i> Tất cả
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary quick-select-btn" onclick="clearYears()">
                                <i class="fas fa-times"></i> Xóa
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
                                <i class="fas fa-check-double"></i> Tất cả
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary quick-select-btn" onclick="clearMonths()">
                                <i class="fas fa-times"></i> Xóa
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info quick-select-btn" onclick="selectQuarter(1)">
                                Q1
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info quick-select-btn" onclick="selectQuarter(2)">
                                Q2
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info quick-select-btn" onclick="selectQuarter(3)">
                                Q3
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info quick-select-btn" onclick="selectQuarter(4)">
                                Q4
                            </button>
                        </div>
                    </div>

                    <!-- Tỉnh -->
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Tỉnh/Thành phố</label>
                        <select name="ma_tinh_tp" class="form-select">
                            <option value="">-- Tất cả --</option>
                            <?php foreach ($provinces as $province): ?>
                                <option value="<?= htmlspecialchars($province) ?>" 
                                    <?= ($filters['ma_tinh_tp'] === $province) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($province) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Mã KH -->
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Mã khách hàng</label>
                        <input type="text" name="ma_khach_hang" class="form-control" 
                               placeholder="Nhập mã KH..." value="<?= htmlspecialchars($filters['ma_khach_hang']) ?>">
                    </div>

                    <!-- GKHL -->
                    <div class="col-md-2">
                        <label class="form-label fw-bold">
                            <i class="fas fa-handshake me-1"></i>Trạng thái GKHL
                        </label>
                        <select name="gkhl_status" class="form-select">
                            <option value="" <?= ($filters['gkhl_status'] === '') ? 'selected' : '' ?>>-- Tất cả --</option>
                            <option value="1" <?= ($filters['gkhl_status'] === '1') ? 'selected' : '' ?>>
                                ✅ Đã tham gia
                            </option>
                            <option value="0" <?= ($filters['gkhl_status'] === '0') ? 'selected' : '' ?>>
                                ❌ Chưa tham gia
                            </option>
                        </select>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-search me-2"></i>Tìm kiếm
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <?php if (!empty($periodDisplay)): ?>
            <div class="period-display">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-check me-2"></i>
                    Kỳ báo cáo: <strong><?= htmlspecialchars($periodDisplay) ?></strong>
                </h5>
            </div>
        <?php endif; ?>

        <?php if (!empty($data)): ?>
            <!-- ✅ THÊM NÚT EXPORT -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="alert alert-success d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-download me-2"></i>
                            <strong>Xuất dữ liệu:</strong> 
                            Export <?= number_format($summary['total_khach_hang']) ?> khách hàng theo kết quả tìm kiếm
                        </div>
                        <?php 
                        $yearsParam = http_build_query(['years' => $selectedYears]);
                        $monthsParam = http_build_query(['months' => $selectedMonths]);
                        ?>
                        <a href="export.php?action=download&<?= $yearsParam ?>&<?= $monthsParam ?>&ma_tinh_tp=<?= urlencode($filters['ma_tinh_tp']) ?>&ma_khach_hang=<?= urlencode($filters['ma_khach_hang']) ?>&gkhl_status=<?= urlencode($filters['gkhl_status']) ?>" 
                           class="btn btn-success">
                            <i class="fas fa-file-csv me-2"></i>Export CSV
                        </a>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-box">
                        <h2><?= number_format($summary['total_khach_hang']) ?></h2>
                        <p class="mb-0"><i class="fas fa-users me-2"></i>Tổng số khách hàng</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <h2><?= number_format($summary['total_doanh_so'], 0) ?></h2>
                        <p class="mb-0"><i class="fas fa-dollar-sign me-2"></i>Tổng doanh số (sau CK)</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <h2><?= number_format($summary['total_san_luong'], 0) ?></h2>
                        <p class="mb-0"><i class="fas fa-boxes me-2"></i>Tổng sản lượng</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <h2><?= number_format($summary['total_gkhl'], 0) ?></h2>
                        <p class="mb-0"><i class="fas fa-handshake me-2"></i>KH có GKHL</p>
                    </div>
                </div>
            </div>

            <div class="data-card">
                <h5 class="mb-4">
                    <i class="fas fa-users me-2"></i>Danh sách khách hàng (Top 100)
                </h5>
                <div class="table-responsive">
                    <table id="customerTable" class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th style="width: 50px;">STT</th>
                                <th style="width: 120px;">Mã KH</th>
                                <th style="width: 250px;">Tên khách hàng</th>
                                <th style="width: 300px;">Địa chỉ</th>
                                <th style="width: 100px;">Tỉnh/TP</th>
                                <th style="width: 120px; text-align: right;">Doanh số</th>
                                <th style="width: 100px; text-align: right;">Sản lượng</th>
                                <th style="width: 110px; text-align: center;"><i class="fas fa-handshake me-1"></i>GKHL</th>
                                <th style="width: 100px; text-align: center;">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data as $index => $row): ?>
                                <tr <?php if (!empty($row['has_gkhl'])): ?>style="background-color: rgba(40, 167, 69, 0.05);"<?php endif; ?>>
                                    <td class="text-center"><?= $index + 1 ?></td>
                                    <td><strong><?= htmlspecialchars($row['ma_khach_hang']) ?></strong></td>
                                    <td><?= htmlspecialchars($row['ten_khach_hang']) ?></td>
                                    <td><?= htmlspecialchars($row['dia_chi_khach_hang']) ?></td>
                                    <td><?= htmlspecialchars($row['ma_tinh_tp']) ?></td>
                                    <td class="text-end"><strong><?= number_format($row['total_doanh_so'], 0) ?></strong></td>
                                    <td class="text-end"><?= number_format($row['total_san_luong'], 0) ?></td>
                                    <td class="text-center">
                                        <?php if (!empty($row['has_gkhl'])): ?>
                                            <span class="badge badge-gkhl">
                                                <i class="fas fa-check-circle"></i> GKHL
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-no-gkhl">
                                                <i class="fas fa-times-circle"></i> Chưa có
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php 
                                        $yearsParam = http_build_query(['years' => $selectedYears]);
                                        $monthsParam = http_build_query(['months' => $selectedMonths]);
                                        ?>
                                        <a href="report.php?action=detail&ma_khach_hang=<?= urlencode($row['ma_khach_hang']) ?>&<?= $yearsParam ?>&<?= $monthsParam ?>" 
                                           class="btn btn-detail btn-sm">
                                            <i class="fas fa-eye me-1"></i>Chi tiết
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif (!empty($selectedYears) && !empty($selectedMonths)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-info-circle me-2"></i>Không tìm thấy dữ liệu phù hợp với bộ lọc.
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Vui lòng chọn năm và tháng để xem báo cáo.
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
            // Initialize Select2
            $('#yearSelect, #monthSelect').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Chọn...',
                allowClear: false
            });

            // DataTable
            $('#customerTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/vi.json'
                },
                pageLength: 25,
                order: [[5, 'desc']],
                columnDefs: [
                    { orderable: false, targets: 8 },
                    { className: "text-center", targets: [0, 7, 8] }
                ],
                autoWidth: false,
                scrollX: false
            });
        });

        // Quick select functions for years
        function selectAllYears() {
            $('#yearSelect option').prop('selected', true);
            $('#yearSelect').trigger('change');
        }

        function clearYears() {
            $('#yearSelect').val(null).trigger('change');
        }

        // Quick select functions for months
        function selectAllMonths() {
            $('#monthSelect option').prop('selected', true);
            $('#monthSelect').trigger('change');
        }

        function clearMonths() {
            $('#monthSelect').val(null).trigger('change');
        }

        function selectQuarter(quarter) {
            const quarters = {
                1: [1, 2, 3],
                2: [4, 5, 6],
                3: [7, 8, 9],
                4: [10, 11, 12]
            };
            
            $('#monthSelect').val(quarters[quarter]).trigger('change');
        }
    </script>
</body>
</html>