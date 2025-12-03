<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Danh sách Khách hàng</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f5f7fa; }
        .navbar-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        .pagination-info {
            text-align: center;
            margin: 15px 0;
            color: #666;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-custom navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand"><i class="fas fa-users me-2"></i>Danh sách Khách hàng</span>
            <a href="dskh.php" class="btn btn-light">
                <i class="fas fa-upload me-2"></i>Import
            </a>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="filter-card">
            <h5><i class="fas fa-filter me-2"></i>Bộ lọc</h5>
            <form method="GET" action="dskh.php">
                <input type="hidden" name="action" value="list">
                <input type="hidden" name="page" value="1">
                <div class="row g-3 mt-2">
                    <div class="col-md-3">
                        <select name="tinh" class="form-select">
                            <option value="">-- Tất cả tỉnh --</option>
                            <?php foreach ($provinces as $p): ?>
                                <option value="<?= $p ?>" <?= $filters['tinh'] === $p ? 'selected' : '' ?>><?= $p ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="quan_huyen" class="form-select">
                            <option value="">-- Tất cả quận/huyện --</option>
                            <?php foreach ($districts as $d): ?>
                                <option value="<?= $d ?>" <?= $filters['quan_huyen'] === $d ? 'selected' : '' ?>><?= $d ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="loai_kh" class="form-select">
                            <option value="">-- Loại KH --</option>
                            <?php foreach ($customerTypes as $t): ?>
                                <option value="<?= $t ?>" <?= $filters['loai_kh'] === $t ? 'selected' : '' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="ma_kh" class="form-control" placeholder="Mã KH" value="<?= $filters['ma_kh'] ?>">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i></button>
                    </div>
                </div>
            </form>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <div class="stat-box">
                    <h3><?= number_format($totalCount) ?></h3>
                    <p class="mb-0">Khách hàng (theo bộ lọc)</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stat-box">
                    <h3><?= number_format($totalCountAll) ?></h3>
                    <p class="mb-0">Tổng số khách hàng</p>
                </div>
            </div>
        </div>

        <?php if ($totalCount > 0): ?>
            <div class="pagination-info">
                📄 Trang <strong><?= $page ?></strong> / <strong><?= $totalPages ?></strong> 
                | Hiển thị <strong><?= count($data) ?></strong> / <strong><?= $totalCount ?></strong> bản ghi
            </div>
        <?php endif; ?>

        <div class="data-card">
            <table class="table table-hover table-sm">
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Mã KH</th>
                        <th>Tên KH</th>
                        <th>Loại</th>
                        <th>Địa chỉ</th>
                        <th>Quận/Huyện</th>
                        <th>Tỉnh</th>
                        <th>Mã số thuế</th>
                        <th>NVBH</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                Không tìm thấy dữ liệu
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $startNum = ($page - 1) * 50 + 1;
                        foreach ($data as $i => $row): 
                        ?>
                            <tr>
                                <td><?= $startNum + $i ?></td>
                                <td><strong><?= htmlspecialchars($row['MaKH']) ?></strong></td>
                                <td><?= htmlspecialchars($row['TenKH']) ?></td>
                                <td><span class="badge bg-info"><?= htmlspecialchars($row['LoaiKH']) ?></span></td>
                                <td><?= htmlspecialchars($row['DiaChi']) ?></td>
                                <td><?= htmlspecialchars($row['QuanHuyen']) ?></td>
                                <td><?= htmlspecialchars($row['Tinh']) ?></td>
                                <td><?= htmlspecialchars($row['MaSoThue'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['TenNVBH']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?action=list&page=1&tinh=<?= urlencode($filters['tinh']) ?>&quan_huyen=<?= urlencode($filters['quan_huyen']) ?>&ma_kh=<?= urlencode($filters['ma_kh']) ?>&loai_kh=<?= urlencode($filters['loai_kh']) ?>">
                                <i class="fas fa-step-backward"></i> Đầu
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?action=list&page=<?= $page - 1 ?>&tinh=<?= urlencode($filters['tinh']) ?>&quan_huyen=<?= urlencode($filters['quan_huyen']) ?>&ma_kh=<?= urlencode($filters['ma_kh']) ?>&loai_kh=<?= urlencode($filters['loai_kh']) ?>">
                                <i class="fas fa-chevron-left"></i> Trước
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php 
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    
                    if ($start > 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>

                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?action=list&page=<?= $i ?>&tinh=<?= urlencode($filters['tinh']) ?>&quan_huyen=<?= urlencode($filters['quan_huyen']) ?>&ma_kh=<?= urlencode($filters['ma_kh']) ?>&loai_kh=<?= urlencode($filters['loai_kh']) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($end < $totalPages): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>

                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?action=list&page=<?= $page + 1 ?>&tinh=<?= urlencode($filters['tinh']) ?>&quan_huyen=<?= urlencode($filters['quan_huyen']) ?>&ma_kh=<?= urlencode($filters['ma_kh']) ?>&loai_kh=<?= urlencode($filters['loai_kh']) ?>">
                                Tiếp <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?action=list&page=<?= $totalPages ?>&tinh=<?= urlencode($filters['tinh']) ?>&quan_huyen=<?= urlencode($filters['quan_huyen']) ?>&ma_kh=<?= urlencode($filters['ma_kh']) ?>&loai_kh=<?= urlencode($filters['loai_kh']) ?>">
                                Cuối <i class="fas fa-step-forward"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>