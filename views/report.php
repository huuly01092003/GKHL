<?php
$thangNam = $_GET['thang_nam'] ?? '';
$currentPage = 'report';

require_once __DIR__ . '/components/navbar.php';
renderNavbar($currentPage, $thangNam);
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
    </style>
</head>
<body>
    <nav class="navbar navbar-custom navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="fas fa-chart-bar me-2"></i>Báo cáo Khách hàng
            </span>
            <a href="index.php" class="btn btn-light">
                <i class="fas fa-upload me-2"></i>Import Dữ liệu
            </a>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="filter-card">
            <h5 class="mb-4"><i class="fas fa-filter me-2"></i>Bộ lọc dữ liệu</h5>
            <form method="GET" action="report.php">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Tháng/Năm</label>
                        <select name="thang_nam" class="form-select" required>
                            <option value="">-- Chọn tháng/năm --</option>
                            <?php foreach ($monthYears as $my): ?>
                                <option value="<?= $my ?>" <?= ($thangNam === $my) ? 'selected' : '' ?>>
                                    Tháng <?= $my ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Tỉnh/Thành phố</label>
                        <select name="ma_tinh_tp" class="form-select">
                            <option value="">-- Tất cả --</option>
                            <?php foreach ($provinces as $province): ?>
                                <option value="<?= $province ?>" <?= ($filters['ma_tinh_tp'] === $province) ? 'selected' : '' ?>>
                                    <?= $province ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Mã khách hàng</label>
                        <input type="text" name="ma_khach_hang" class="form-control" 
                               placeholder="Nhập mã KH..." value="<?= htmlspecialchars($filters['ma_khach_hang']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-handshake me-1"></i>Trạng thái GKHL
                        </label>
                        <select name="gkhl_status" class="form-select">
                            <option value="" <?= ($filters['gkhl_status'] === '') ? 'selected' : '' ?>>-- Tất cả --</option>
                            <option value="1" <?= ($filters['gkhl_status'] === '1') ? 'selected' : '' ?>>
                                ✅ Đã tham gia GKHL
                            </option>
                            <option value="0" <?= ($filters['gkhl_status'] === '0') ? 'selected' : '' ?>>
                                ❌ Chưa tham gia GKHL
                            </option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Tìm kiếm
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <?php if (!empty($data)): ?>
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
                    <?php if (!empty($filters['gkhl_status'])): ?>
                        <?php if ($filters['gkhl_status'] === '1'): ?>
                            <span class="badge badge-gkhl ms-2">
                                <i class="fas fa-check-circle"></i> Lọc: Đã tham gia GKHL
                            </span>
                        <?php elseif ($filters['gkhl_status'] === '0'): ?>
                            <span class="badge badge-no-gkhl ms-2">
                                <i class="fas fa-times-circle"></i> Lọc: Chưa tham gia GKHL
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
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
                                    <td title="<?= htmlspecialchars($row['ten_khach_hang']) ?>">
                                        <?= htmlspecialchars($row['ten_khach_hang']) ?>
                                    </td>
                                    <td title="<?= htmlspecialchars($row['dia_chi_khach_hang']) ?>">
                                        <?= htmlspecialchars($row['dia_chi_khach_hang']) ?>
                                    </td>
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
                                        <a href="report.php?action=detail&ma_khach_hang=<?= urlencode($row['ma_khach_hang']) ?>&thang_nam=<?= urlencode($thangNam) ?>" 
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
        <?php elseif (!empty($thangNam)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-info-circle me-2"></i>Không tìm thấy dữ liệu phù hợp với bộ lọc.
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Vui lòng chọn tháng/năm để xem báo cáo.
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
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
    </script>
</body>
</html>