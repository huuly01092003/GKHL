
<?php
$currentPage = 'import';
require_once dirname(__DIR__) . '/components/navbar.php';renderNavbar($currentPage);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách Gắn kết Hoa Linh</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        body { background: #f5f7fa; }
        .navbar-custom {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 15px;
        }
        .stat-box h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .table thead {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        .badge-yes {
            background: #28a745;
            padding: 5px 10px;
        }
        .badge-no {
            background: #dc3545;
            padding: 5px 10px;
        }
        .badge-null {
            background: #6c757d;
            padding: 5px 10px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-custom navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="fas fa-handshake me-2"></i>Gắn kết Hoa Linh (GKHL)
            </span>
            <a href="gkhl.php" class="btn btn-light">
                <i class="fas fa-upload me-2"></i>Import Dữ liệu
            </a>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="filter-card">
            <h5 class="mb-4"><i class="fas fa-filter me-2"></i>Bộ lọc dữ liệu</h5>
            <form method="GET" action="gkhl.php" id="filterForm">
                <input type="hidden" name="action" value="list">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Mã NVBH</label>
                        <select name="ma_nvbh" class="form-select">
                            <option value="">-- Tất cả nhân viên --</option>
                            <?php foreach ($saleStaff as $staff): ?>
                                <option value="<?= htmlspecialchars($staff['MaNVBH']) ?>" 
                                    <?= ($filters['ma_nvbh'] === $staff['MaNVBH']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($staff['MaNVBH']) ?> - <?= htmlspecialchars($staff['TenNVBH']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Năm sinh</label>
                        <select name="nam_sinh" class="form-select">
                            <option value="">-- Tất cả --</option>
                            <?php foreach ($birthYears as $year): ?>
                                <option value="<?= $year ?>" <?= ($filters['nam_sinh'] == $year) ? 'selected' : '' ?>>
                                    <?= $year ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Khớp SĐT</label>
                        <select name="khop_sdt" class="form-select">
                            <option value="">-- Tất cả --</option>
                            <option value="1" <?= ($filters['khop_sdt'] === '1') ? 'selected' : '' ?>>
                                <i class="fas fa-check"></i> Đã khớp (Y)
                            </option>
                            <option value="0" <?= ($filters['khop_sdt'] === '0') ? 'selected' : '' ?>>
                                <i class="fas fa-times"></i> Chưa khớp (N)
                            </option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Mã KH DMS</label>
                        <input type="text" name="ma_kh_dms" class="form-control" 
                               placeholder="Nhập mã khách hàng DMS..." 
                               value="<?= htmlspecialchars($filters['ma_kh_dms']) ?>">
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="stat-box">
                    <h2><?= number_format($totalCount) ?></h2>
                    <p class="mb-0"><i class="fas fa-users me-2"></i>Tổng số khách hàng GKHL</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stat-box">
                    <h2><?= number_format($phoneMatchCount) ?></h2>
                    <p class="mb-0"><i class="fas fa-check-circle me-2"></i>Số KH đã khớp SĐT</p>
                </div>
            </div>
        </div>

        <div class="data-card">
            <h5 class="mb-4">
                <i class="fas fa-list me-2"></i>Danh sách gắn kết 
                <span class="badge bg-secondary"><?= count($data) ?> bản ghi</span>
            </h5>
            
            <div class="table-responsive">
                <table id="gkhlTable" class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th style="width: 50px;">STT</th>
                            <th style="width: 120px;">Mã KH DMS</th>
                            <th style="width: 200px;">Tên quầy</th>
                            <th style="width: 180px;">Tên chủ cửa hàng</th>
                            <th style="width: 100px;">Ngày sinh</th>
                            <th style="width: 120px;">SĐT Zalo</th>
                            <th style="width: 120px;">SĐT định danh</th>
                            <th style="width: 100px;">Khớp SĐT</th>
                            <th style="width: 100px;">Mã NVBH</th>
                            <th style="width: 150px;">Tên NVBH</th>
                            <th style="width: 150px;">ĐK Chương trình</th>
                            <th style="width: 120px;">ĐK Mục DS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($data)): ?>
                            <tr>
                                <td colspan="12" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                    Không có dữ liệu
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($data as $index => $row): ?>
                                <tr>
                                    <td class="text-center"><?= $index + 1 ?></td>
                                    <td><strong><?= htmlspecialchars($row['MaKHDMS']) ?></strong></td>
                                    <td><?= htmlspecialchars($row['TenQuay']) ?></td>
                                    <td><?= htmlspecialchars($row['TenChuCuaHang']) ?></td>
                                    <td class="text-center">
                                        <?php if ($row['NgaySinh'] && $row['ThangSinh'] && $row['NamSinh']): ?>
                                            <?= sprintf('%02d/%02d/%04d', $row['NgaySinh'], $row['ThangSinh'], $row['NamSinh']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">--</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($row['SDTZalo']) ?></td>
                                    <td><?= htmlspecialchars($row['SDTDaDinhDanh']) ?></td>
                                    <td class="text-center">
                                        <?php 
                                        $khopSdt = $row['KhopSDT'];
                                        if ($khopSdt == 'Y'): ?>
                                            <span class="badge badge-yes">
                                                <i class="fas fa-check"></i> Đã khớp
                                            </span>
                                        <?php elseif ($khopSdt == 'N'): ?>
                                            <span class="badge badge-no">
                                                <i class="fas fa-times"></i> Chưa khớp
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-null">
                                                <i class="fas fa-question"></i> Chưa rõ
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($row['MaNVBH']) ?></td>
                                    <td><?= htmlspecialchars($row['TenNVBH']) ?></td>
                                    <td>
                                        <?php if (!empty($row['DangKyChuongTrinh'])): ?>
                                            <span class="badge bg-info"><?= htmlspecialchars($row['DangKyChuongTrinh']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">--</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['DangKyMucDoanhSo'])): ?>
                                            <span class="badge bg-warning text-dark"><?= htmlspecialchars($row['DangKyMucDoanhSo']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">--</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#gkhlTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/vi.json'
                },
                pageLength: 50,
                order: [[1, 'asc']],
                autoWidth: false,
                scrollX: false,
                columnDefs: [
                    { orderable: false, targets: 7 },
                    { className: "text-center", targets: [0, 4, 7] }
                ],
                deferRender: true,
                processing: true
            });
        });
    </script>
</body>
</html>