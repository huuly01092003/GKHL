<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết Khách hàng</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body { background: #f5f7fa; }
        .navbar-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .info-card, .data-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            padding: 25px;
            margin-bottom: 25px;
        }
        .summary-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 15px;
        }
        .summary-box h3 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }
        .table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .gkhl-info {
            background: linear-gradient(135deg, #04ff00ff 0%, #016310ff 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            height: 100%;
            min-height: 250px;
        }
        .gkhl-not-registered {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            color: white;
            height: 100%;
            min-height: 250px;
        }
        .location-info {
            background: #e7f3ff;
            padding: 20px;
            border-left: 4px solid #667eea;
            border-radius: 10px;
            height: 100%;
            min-height: 250px;
        }
        #map {
            height: 400px;
            width: 100%;
            border-radius: 10px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.1);
            margin-top: 15px;
        }
        .info-label {
            font-weight: 600;
            color: #666;
            min-width: 150px;
            display: inline-block;
        }
        .info-value {
            color: #333;
            font-weight: 500;
        }
        .section-header {
            background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-custom navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="fas fa-user me-2"></i>Chi tiết Khách hàng
            </span>
            <a href="report.php?thang_nam=<?= urlencode($thangNam) ?>" class="btn btn-light">
                <i class="fas fa-arrow-left me-2"></i>Quay lại
            </a>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <?php if (!empty($data)): ?>
            <?php
            // Tính tổng từ tất cả các order
            $totalQty = 0;
            $totalGrossAmount = 0;
            $totalSchemeAmount = 0;
            $totalNetAmount = 0;
            
            foreach ($data as $row) {
                $totalQty += $row['Qty'] ?? 0;
                $totalGrossAmount += $row['TotalGrossAmount'] ?? 0;
                $totalSchemeAmount += $row['TotalSchemeAmount'] ?? 0;
                $totalNetAmount += $row['TotalNetAmount'] ?? 0;
            }

            // Lấy thông tin DSKH
            $dskhInfo = $data[0]; // Thông tin từ bảng DSKH
            ?>

            <div class="info-card">
                <!-- THÔNG TIN KHÁCH HÀNG -->
                <div class="section-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Thông tin Khách hàng</h5>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <span class="info-label"><i class="fas fa-id-card me-2"></i>Mã KH:</span>
                            <span class="info-value"><strong><?= htmlspecialchars($dskhInfo['CustCode']) ?></strong></span>
                        </div>
                        <div class="mb-3">
                            <span class="info-label"><i class="fas fa-user me-2"></i>Tên KH:</span>
                            <span class="info-value"><?= htmlspecialchars($dskhInfo['ten_khach_hang'] ?? 'N/A') ?></span>
                        </div>
                        <div class="mb-3">
                            <span class="info-label"><i class="fas fa-tag me-2"></i>Loại KH:</span>
                            <span class="badge bg-info"><?= htmlspecialchars($dskhInfo['LoaiKH'] ?? $dskhInfo['CustType'] ?? 'N/A') ?></span>
                        </div>
                        <div class="mb-3">
                            <span class="info-label"><i class="fas fa-map-marker-alt me-2"></i>Địa chỉ:</span>
                            <span class="info-value"><?= htmlspecialchars($dskhInfo['dia_chi_khach_hang'] ?? 'N/A') ?></span>
                        </div>
                        <div class="mb-3">
                            <span class="info-label"><i class="fas fa-map-signs me-2"></i>Quận/Huyện:</span>
                            <span class="info-value"><?= htmlspecialchars($dskhInfo['QuanHuyen'] ?? 'N/A') ?></span>
                        </div>
                        <div class="mb-3">
                            <span class="info-label"><i class="fas fa-city me-2"></i>Tỉnh/TP:</span>
                            <span class="info-value"><?= htmlspecialchars($dskhInfo['ma_tinh_tp'] ?? 'N/A') ?></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <span class="info-label"><i class="fas fa-globe-asia me-2"></i>Khu vực (Area):</span>
                            <span class="badge bg-success" style="font-size: 0.9rem; padding: 6px 12px;">
                                <?= htmlspecialchars($dskhInfo['khu_vuc'] ?? $dskhInfo['Area'] ?? 'Chưa có') ?>
                            </span>
                        </div>
                        <div class="mb-3">
                            <span class="info-label"><i class="fas fa-id-badge me-2"></i>Mã GSBH:</span>
                            <span class="badge bg-warning text-dark" style="font-size: 0.9rem; padding: 6px 12px;">
                                <?= htmlspecialchars($dskhInfo['MaGSBH'] ?? 'Chưa có') ?>
                            </span>
                        </div>
                        <div class="mb-3">
                            <span class="info-label"><i class="fas fa-users-cog me-2"></i>Phân loại nhóm KH:</span>
                            <span class="info-value"><?= htmlspecialchars($dskhInfo['PhanLoaiNhomKH'] ?? 'Chưa có') ?></span>
                        </div>
                        <div class="mb-3">
                            <span class="info-label"><i class="fas fa-file-invoice me-2"></i>Mã số thuế:</span>
                            <span class="info-value"><?= htmlspecialchars($dskhInfo['MaSoThue'] ?? 'Chưa có') ?></span>
                        </div>
                        <div class="mb-3">
                            <span class="info-label"><i class="fas fa-building me-2"></i>Mã NPP:</span>
                            <span class="info-value"><?= htmlspecialchars($dskhInfo['MaNPP'] ?? 'Chưa có') ?></span>
                        </div>
                        <div class="mb-3">
                            <span class="info-label"><i class="fas fa-user-tie me-2"></i>NVBH:</span>
                            <span class="info-value">
                                <?php if (!empty($dskhInfo['MaNVBH'])): ?>
                                    <strong><?= htmlspecialchars($dskhInfo['MaNVBH']) ?></strong> - 
                                    <?= htmlspecialchars($dskhInfo['TenNVBH'] ?? '') ?>
                                <?php else: ?>
                                    Chưa có
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- THÔNG TIN DSR -->
                <div class="section-header mt-4">
                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Thông tin DSR & Báo cáo</h5>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <span class="info-label"><i class="fas fa-barcode me-2"></i>DistCode:</span>
                            <span class="info-value"><?= htmlspecialchars($dskhInfo['DistCode'] ?? 'N/A') ?></span>
                        </div>
                        <div class="mb-3">
                            <span class="info-label"><i class="fas fa-user-tie me-2"></i>DSRCode:</span>
                            <span class="info-value"><?= htmlspecialchars($dskhInfo['DSRCode'] ?? 'N/A') ?></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <span class="info-label"><i class="fas fa-layer-group me-2"></i>DistGroup:</span>
                            <span class="info-value"><?= htmlspecialchars($dskhInfo['DistGroup'] ?? 'N/A') ?></span>
                        </div>
                        <div class="mb-3">
                            <span class="info-label"><i class="fas fa-map me-2"></i>DSR Province:</span>
                            <span class="info-value"><?= htmlspecialchars($dskhInfo['DSRTypeProvince'] ?? 'N/A') ?></span>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <span class="info-label"><i class="fas fa-calendar-alt me-2"></i>Tháng/Năm báo cáo:</span>
                    <span class="badge bg-primary" style="font-size: 1rem; padding: 8px 15px;"><?= htmlspecialchars($thangNam) ?></span>
                </div>

                <!-- Tổng hợp doanh số -->
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="summary-box">
                            <h3><?= number_format($totalQty, 0) ?></h3>
                            <p class="mb-0"><i class="fas fa-boxes me-2"></i>Tổng sản lượng</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="summary-box">
                            <h3><?= number_format($totalGrossAmount, 0) ?></h3>
                            <p class="mb-0"><i class="fas fa-dollar-sign me-2"></i>DS trước CK</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="summary-box">
                            <h3><?= number_format($totalSchemeAmount, 0) ?></h3>
                            <p class="mb-0"><i class="fas fa-tags me-2"></i>Chiết khấu</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="summary-box">
                            <h3><?= number_format($totalNetAmount, 0) ?></h3>
                            <p class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>DS sau CK</p>
                        </div>
                    </div>
                </div>

                <!-- Location & GKHL -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <?php if (!empty($location)): ?>
                            <div class="location-info">
                                <h6 class="mb-3"><i class="fas fa-map-marker-alt me-2"></i>Thông tin Vị trí</h6>
                                <p class="mb-2"><strong>Location:</strong></p>
                                <p class="text-muted"><?= htmlspecialchars($location) ?></p>
                                <?php
                                    $coords = explode(',', $location);
                                    if (count($coords) === 2) {
                                        $lat = trim($coords[0]);
                                        $lng = trim($coords[1]);
                                        echo "<p class=\"mb-0 mt-3\"><small><i class=\"fas fa-crosshairs me-1\"></i> Lat: <code>$lat</code>, Lng: <code>$lng</code></small></p>";
                                    }
                                ?>
                            </div>
                        <?php else: ?>
                            <div class="location-info">
                                <h6 class="mb-3"><i class="fas fa-map-marker-alt me-2"></i>Thông tin Vị trí</h6>
                                <div class="text-center" style="padding-top: 40px;">
                                    <i class="fas fa-map-marked-alt fa-3x mb-3 d-block" style="opacity: 0.3;"></i>
                                    <p class="text-muted">Chưa có thông tin vị trí</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <?php if (!empty($gkhlInfo)): ?>
                            <div class="gkhl-info">
                                <h6 class="mb-3"><i class="fas fa-handshake me-2"></i>Gắn kết Hoa Linh</h6>
                                <div class="mt-3">
                                    <p class="mb-2"><strong>📌 Tên Quầy:</strong> <?= htmlspecialchars($gkhlInfo['TenQuay']) ?></p>
                                    
                                    <!-- THÊM SỐ ĐIỆN THOẠI -->
                                    <?php if (!empty($gkhlInfo['SDTZalo'])): ?>
                                        <p class="mb-2">
                                            <strong>📱 SĐT Zalo:</strong> 
                                            <a href="tel:<?= htmlspecialchars($gkhlInfo['SDTZalo']) ?>" 
                                               style="color: white; text-decoration: underline;">
                                                <?= htmlspecialchars($gkhlInfo['SDTZalo']) ?>
                                            </a>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($gkhlInfo['SDTDaDinhDanh'])): ?>
                                        <p class="mb-2">
                                            <strong>☎️ SĐT Định danh:</strong> 
                                            <a href="tel:<?= htmlspecialchars($gkhlInfo['SDTDaDinhDanh']) ?>" 
                                               style="color: white; text-decoration: underline;">
                                                <?= htmlspecialchars($gkhlInfo['SDTDaDinhDanh']) ?>
                                            </a>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <p class="mb-2"><strong>📋 ĐK Chương trình:</strong> <?= htmlspecialchars($gkhlInfo['DangKyChuongTrinh'] ?? 'Chưa có') ?></p>
                                    <p class="mb-2"><strong>💰 ĐK Mục Doanh số:</strong> <?= htmlspecialchars($gkhlInfo['DangKyMucDoanhSo'] ?? 'Chưa có') ?></p>
                                    <p class="mb-2"><strong>🎨 ĐK Trưng bày:</strong> <?= htmlspecialchars($gkhlInfo['DangKyTrungBay'] ?? 'Chưa có') ?></p>
                                    <p class="mb-0"><strong>✅ Khớp SĐT:</strong> 
                                        <?php if ($gkhlInfo['KhopSDT'] == 'Y'): ?>
                                            <i class="fas fa-check-circle"></i> Đã khớp
                                        <?php else: ?>
                                            <i class="fas fa-times-circle"></i> Chưa khớp
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="gkhl-not-registered">
                                <div style="padding-top: 50px;">
                                    <i class="fas fa-info-circle fa-3x mb-3"></i>
                                    <h5 class="mb-2">Chưa tham gia GKHL</h5>
                                    <p class="mb-0">Khách hàng chưa đăng ký chương trình Gắn kết Hoa Linh</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Map -->
                <?php if (!empty($location)): ?>
                    <?php
                        $coords = explode(',', $location);
                        if (count($coords) === 2) {
                            $lat = trim($coords[0]);
                            $lng = trim($coords[1]);
                    ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="section-header">
                                <h5 class="mb-0"><i class="fas fa-map me-2"></i>Bản đồ vị trí</h5>
                            </div>
                            <div id="map"></div>
                        </div>
                    </div>
                    <?php } ?>
                <?php endif; ?>
            </div>

            <!-- Chi tiết đơn hàng -->
            <div class="data-card">
                <div class="section-header">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Chi tiết đơn hàng <span class="badge bg-secondary"><?= count($data) ?> bản ghi</span></h5>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover table-sm detail-table">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>Số đơn</th>
                                <th>Ngày đặt</th>
                                <th>Mã SP</th>
                                <th>Loại bán</th>
                                <th class="text-end">Số lượng</th>
                                <th class="text-end">DS trước CK</th>
                                <th class="text-end">Chiết khấu</th>
                                <th class="text-end">DS sau CK</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data as $index => $row): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><strong><?= htmlspecialchars($row['OrderNumber']) ?></strong></td>
                                    <td><?= !empty($row['OrderDate']) ? date('d/m/Y', strtotime($row['OrderDate'])) : 'N/A' ?></td>
                                    <td><?= htmlspecialchars($row['ProductCode']) ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($row['ProductSaleType'] ?? 'N/A') ?></span></td>
                                    <td class="text-end"><?= number_format($row['Qty'], 0) ?></td>
                                    <td class="text-end"><?= number_format($row['TotalGrossAmount'], 0) ?></td>
                                    <td class="text-end text-danger"><?= number_format($row['TotalSchemeAmount'], 0) ?></td>
                                    <td class="text-end"><strong><?= number_format($row['TotalNetAmount'], 0) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>Không tìm thấy dữ liệu cho khách hàng này.
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        $(document).ready(function() {
            $('.detail-table').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/vi.json'
                },
                pageLength: 50,
                order: [[2, 'desc']]
            });

            <?php if (!empty($location)): ?>
                <?php
                    $coords = explode(',', $location);
                    if (count($coords) === 2) {
                        $lat = trim($coords[0]);
                        $lng = trim($coords[1]);
                ?>
                var map = L.map('map').setView([<?= $lat ?>, <?= $lng ?>], 16);
                
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors',
                    maxZoom: 19
                }).addTo(map);
                
                var marker = L.marker([<?= $lat ?>, <?= $lng ?>]).addTo(map);
                marker.bindPopup('<b><?= htmlspecialchars($data[0]['ten_khach_hang'] ?? 'Khách hàng') ?></b><br><?= htmlspecialchars($data[0]['dia_chi_khach_hang'] ?? '') ?>').openPopup();
                
                L.circle([<?= $lat ?>, <?= $lng ?>], {
                    color: '#667eea',
                    fillColor: '#667eea',
                    fillOpacity: 0.2,
                    radius: 100
                }).addTo(map);
                <?php } ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>