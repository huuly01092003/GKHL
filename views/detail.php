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
        .period-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            display: inline-block;
            font-size: 1rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-custom navbar-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="fas fa-user me-2"></i>Chi tiết Khách hàng
            </span>
            <?php 
            // ✅ Tạo URL quay lại với tham số đúng
            $yearsParam = isset($selectedYears) ? http_build_query(['years' => $selectedYears]) : '';
            $monthsParam = isset($selectedMonths) ? http_build_query(['months' => $selectedMonths]) : '';
            $backUrl = "report.php?{$yearsParam}&{$monthsParam}";
            if (!empty($_GET['ma_tinh_tp'])) {
                $backUrl .= '&ma_tinh_tp=' . urlencode($_GET['ma_tinh_tp']);
            }
            if (!empty($_GET['ma_khach_hang'])) {
                $backUrl .= '&ma_khach_hang=' . urlencode($_GET['ma_khach_hang']);
            }
            if (!empty($_GET['gkhl_status'])) {
                $backUrl .= '&gkhl_status=' . urlencode($_GET['gkhl_status']);
            }
            ?>
            <a href="<?= $backUrl ?>" class="btn btn-light">
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
            $dskhInfo = $data[0];
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
                            <span class="info-value"><?= htmlspecialchars($dskhInfo['TenKH'] ?? 'N/A') ?></span>
                        </div>
                        <div class="mb-3">
                            <span class="info-label"><i class="fas fa-tag me-2"></i>Loại KH:</span>
                            <span class="badge bg-info"><?= htmlspecialchars($dskhInfo['LoaiKH'] ?? $dskhInfo['CustType'] ?? 'N/A') ?></span>
                        </div>
                        <div class="mb-3">
                            <span class="info-label"><i class="fas fa-map-marker-alt me-2"></i>Địa chỉ:</span>
                            <span class="info-value"><?= htmlspecialchars($dskhInfo['DiaChi'] ?? 'N/A') ?></span>
                        </div>
                        <div class="mb-3">
                            <span class="info-label"><i class="fas fa-map-signs me-2"></i>Quận/Huyện:</span>
                            <span class="info-value"><?= htmlspecialchars($dskhInfo['QuanHuyen'] ?? 'N/A') ?></span>
                        </div>
                        <div class="mb-3">
                            <span class="info-label"><i class="fas fa-city me-2"></i>Tỉnh/TP:</span>
                            <span class="info-value"><?= htmlspecialchars($dskhInfo['Tinh'] ?? 'N/A') ?></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <span class="info-label"><i class="fas fa-globe-asia me-2"></i>Khu vực (Area):</span>
                            <span class="badge bg-success" style="font-size: 0.9rem; padding: 6px 12px;">
                                <?= htmlspecialchars($dskhInfo['Area'] ?? 'Chưa có') ?>
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

                <!-- ✅ CẬP NHẬT: Hiển thị kỳ báo cáo từ $periodDisplay -->
                <?php if (!empty($periodDisplay)): ?>
                <div class="mb-3">
                    <span class="info-label"><i class="fas fa-calendar-alt me-2"></i>Kỳ báo cáo:</span>
                    <span class="period-badge"><?= htmlspecialchars($periodDisplay) ?></span>
                </div>
                <?php endif; ?>

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
                <!-- ✅ THAY THẾ PHẦN: <!-- Thông tin Bất thường --> trong views/detail.php -->

<?php if (!empty($anomalyInfo) && $anomalyInfo['total_score'] > 0): ?>
<div class="row mt-4">
    <div class="col-12">
        <!-- Header Bất Thường -->
        <div class="section-header" style="background: linear-gradient(135deg, #ff6b6b15 0%, #ee5a6f15 100%); border-left-color: #dc3545;">
            <h5 class="mb-0" style="color: #dc3545;">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Phát hiện Hành vi Bất thường
            </h5>
        </div>

        <!-- Alert Box Tóm Tắt -->
        <div class="anomaly-alert-box" style="
            background: <?php
                if ($anomalyInfo['risk_level'] === 'critical') echo 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)';
                elseif ($anomalyInfo['risk_level'] === 'high') echo 'linear-gradient(135deg, #fd7e14 0%, #e8590c 100%)';
                elseif ($anomalyInfo['risk_level'] === 'medium') echo 'linear-gradient(135deg, #ffc107 0%, #e0a800 100%)';
                else echo 'linear-gradient(135deg, #20c997 0%, #17a589 100%)';
            ?>;
            color: <?= $anomalyInfo['risk_level'] === 'medium' ? '#000' : 'white' ?>;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            margin-bottom: 30px;
        ">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="mb-2">
                        <?php
                        $riskIcons = [
                            'critical' => '🔴',
                            'high' => '🟠',
                            'medium' => '🟡',
                            'low' => '🟢'
                        ];
                        $riskTexts = [
                            'critical' => 'CỰC KỲ NGHIÊM TRỌNG',
                            'high' => 'NGHI VẤN CAO',
                            'medium' => 'NGHI VẤN TRUNG BÌNH',
                            'low' => 'NGHI VẤN THẤP'
                        ];
                        echo $riskIcons[$anomalyInfo['risk_level']] . ' ' . $riskTexts[$anomalyInfo['risk_level']];
                        ?>
                    </h4>
                    <p class="mb-0" style="font-size: 1.1rem;">
                        Phát hiện <strong><?= $anomalyInfo['anomaly_count'] ?> dấu hiệu bất thường</strong> 
                        trong hành vi mua hàng của khách hàng này - Bấm vào từng mục dưới để xem chi tiết
                    </p>
                </div>
                <div class="col-md-4 text-center">
                    <div style="
                        background: <?= $anomalyInfo['risk_level'] === 'medium' ? 'rgba(0,0,0,0.1)' : 'rgba(255,255,255,0.2)' ?>;
                        padding: 20px;
                        border-radius: 15px;
                        display: inline-block;
                    ">
                        <div style="font-size: 2.5rem; font-weight: 700; margin-bottom: 5px;">
                            <?= number_format($anomalyInfo['total_score'], 1) ?>
                        </div>
                        <div style="font-size: 0.9rem; font-weight: 600; opacity: 0.9;">
                            ĐIỂM BẤT THƯỜNG
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Danh Sách Dấu Hiệu Bất Thường (Clickable) -->
        <div style="margin-bottom: 30px;">
            <h6 style="margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #667eea; color: #333;">
                <i class="fas fa-list-check me-2"></i>Danh Sách <?= count($anomalyInfo['details']) ?> Dấu Hiệu (Bấm để xem chi tiết)
            </h6>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 15px;">
                <?php foreach ($anomalyInfo['details'] as $index => $detail): ?>
                <div 
                    class="anomaly-list-item" 
                    data-anomaly-index="<?= $index ?>"
                    style="
                        padding: 15px;
                        border-left: 4px solid <?php
                            if ($detail['weighted_score'] >= 15) echo '#dc3545';
                            elseif ($detail['weighted_score'] >= 10) echo '#fd7e14';
                            elseif ($detail['weighted_score'] >= 5) echo '#ffc107';
                            else echo '#20c997';
                        ?>;
                        border-radius: 8px;
                        cursor: pointer;
                        transition: all 0.3s ease;
                        background: <?php
                            if ($detail['weighted_score'] >= 15) echo 'rgba(220, 53, 69, 0.02)';
                            elseif ($detail['weighted_score'] >= 10) echo 'rgba(253, 126, 20, 0.02)';
                            elseif ($detail['weighted_score'] >= 5) echo 'rgba(255, 193, 7, 0.02)';
                            else echo 'rgba(32, 201, 151, 0.02)';
                        ?>;
                        box-shadow: 0 2px 8px rgba(0,0,0,0.03);
                    "
                    onmouseover="this.style.boxShadow='0 5px 15px rgba(0,0,0,0.1)'; this.style.transform='translateX(5px)';"
                    onmouseout="this.style.boxShadow='0 2px 8px rgba(0,0,0,0.03)'; this.style.transform='translateX(0)';"
                >
                    <div style="display: flex; justify-content: space-between; align-items: start; gap: 10px;">
                        <div style="flex: 1;">
                            <h6 style="margin: 0 0 5px 0; font-weight: 600; color: #333; font-size: 0.95rem;">
                                <i class="fas fa-circle-exclamation me-2" style="color: <?php
                                    if ($detail['weighted_score'] >= 15) echo '#dc3545';
                                    elseif ($detail['weighted_score'] >= 10) echo '#fd7e14';
                                    elseif ($detail['weighted_score'] >= 5) echo '#ffc107';
                                    else echo '#20c997';
                                ?>;"></i>
                                <?= htmlspecialchars($detail['description']) ?>
                            </h6>
                            <small style="color: #999; display: block;">
                                <i class="fas fa-circle-info me-1"></i>
                                Điểm gốc: <?= $detail['score'] ?>/100 | Trọng số: <?= $detail['weight'] ?>% | Bấm để xem chi tiết
                            </small>
                        </div>
                        <div style="
                            background: #f8f9fa;
                            padding: 8px 14px;
                            border-radius: 20px;
                            font-weight: 700;
                            font-size: 1.1rem;
                            min-width: 70px;
                            text-align: center;
                            color: <?php
                                if ($detail['weighted_score'] >= 15) echo '#dc3545';
                                elseif ($detail['weighted_score'] >= 10) echo '#fd7e14';
                                elseif ($detail['weighted_score'] >= 5) echo '#ffc107';
                                else echo '#20c997';
                            ?>;
                        ">
                            <?= number_format($detail['weighted_score'], 1) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Khuyến Nghị Nhanh -->
        <div class="alert alert-info" style="border-left: 4px solid #667eea;">
            <h6 class="mb-2">
                <i class="fas fa-lightbulb me-2"></i><strong>Khuyến nghị hành động:</strong>
            </h6>
            <ul class="mb-0">
                <?php if ($anomalyInfo['risk_level'] === 'critical'): ?>
                    <li><strong>🔴 ĐỘ ƯU TIÊN CAO:</strong> Kiểm tra ngay lập tức - Liên hệ NVBH trong 24 giờ</li>
                    <li>Rà soát lại lịch sử giao dịch chi tiết</li>
                    <li>Xác minh tính hợp lệ của chương trình GKHL (nếu có)</li>
                <?php elseif ($anomalyInfo['risk_level'] === 'high'): ?>
                    <li><strong>🟠 ĐỘ ƯU TIÊN TRUNG BÌNH:</strong> Theo dõi sát trong các tháng tiếp theo</li>
                    <li>Liên hệ xác nhận với NVBH hoặc khách hàng</li>
                    <li>Lập kế hoạch kiểm tra chi tiết trong 3 ngày</li>
                <?php elseif ($anomalyInfo['risk_level'] === 'medium'): ?>
                    <li><strong>🟡 ĐỘ ƯU TIÊN THẤP:</strong> Ghi nhận và theo dõi</li>
                    <li>So sánh với các tháng trước để xác định xu hướng</li>
                    <li>Đưa vào danh sách giám sát định kỳ</li>
                <?php else: ?>
                    <li><strong>🟢 BÌNH THƯỜNG:</strong> Duy trì giám sát thường xuyên</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<!-- Modal Chi Tiết Dấu Hiệu -->
<div class="modal fade" id="anomalyDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; border: none;">
                <div>
                    <h5 id="modalTitle" style="margin: 0; font-weight: 700;">
                        <i class="fas fa-arrow-up me-2"></i>Doanh số tăng đột biến
                    </h5>
                    <small id="modalSubtitle" style="opacity: 0.9;">Chỉ số: Sudden Spike | Trọng số: 15%</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="background: #f8f9fa;">
                <!-- Tabs Navigation -->
                <div style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 0;">
                    <button class="anomaly-tab-btn active" data-tab="overview" style="
                        padding: 10px 20px;
                        background: none;
                        border: none;
                        cursor: pointer;
                        color: #667eea;
                        font-weight: 600;
                        border-bottom: 3px solid #667eea;
                        margin-bottom: -2px;
                    ">
                        <i class="fas fa-eye me-2"></i>Tổng Quan
                    </button>
                    <button class="anomaly-tab-btn" data-tab="evidence" style="
                        padding: 10px 20px;
                        background: none;
                        border: none;
                        cursor: pointer;
                        color: #666;
                        font-weight: 600;
                        border-bottom: 3px solid transparent;
                        margin-bottom: -2px;
                        transition: all 0.3s;
                    ">
                        <i class="fas fa-chart-bar me-2"></i>Minh Chứng
                    </button>
                    <button class="anomaly-tab-btn" data-tab="calculation" style="
                        padding: 10px 20px;
                        background: none;
                        border: none;
                        cursor: pointer;
                        color: #666;
                        font-weight: 600;
                        border-bottom: 3px solid transparent;
                        margin-bottom: -2px;
                        transition: all 0.3s;
                    ">
                        <i class="fas fa-calculator me-2"></i>Tính Toán
                    </button>
                    <button class="anomaly-tab-btn" data-tab="action" style="
                        padding: 10px 20px;
                        background: none;
                        border: none;
                        cursor: pointer;
                        color: #666;
                        font-weight: 600;
                        border-bottom: 3px solid transparent;
                        margin-bottom: -2px;
                        transition: all 0.3s;
                    ">
                        <i class="fas fa-bolt me-2"></i>Hành Động
                    </button>
                </div>

                <!-- Tab Content -->
                <div id="anomaly-overview-tab" class="anomaly-tab-content active" style="display: block;">
                    <div style="background: white; padding: 20px; border-radius: 10px; margin-bottom: 15px;">
                        <h6 style="border-bottom: 2px solid #667eea; padding-bottom: 10px; margin-bottom: 15px; color: #333;">
                            <i class="fas fa-lightbulb me-2" style="color: #667eea;"></i>Ý Nghĩa & Giải Thích
                        </h6>
                        <p id="anomaly-explanation" style="color: #333; line-height: 1.7; margin: 0;">
                            Doanh số tăng đột biến - Giải thích chi tiết sẽ được cập nhật...
                        </p>
                    </div>

                    <div style="background: white; padding: 20px; border-radius: 10px;">
                        <h6 style="border-bottom: 2px solid #667eea; padding-bottom: 10px; margin-bottom: 15px; color: #333;">
                            <i class="fas fa-chart-pie me-2" style="color: #667eea;"></i>Chỉ Số So Sánh
                        </h6>
                        <div id="anomaly-metrics" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px;">
                            <!-- Metrics sẽ được điền bằng JavaScript -->
                        </div>
                    </div>
                </div>

                <div id="anomaly-evidence-tab" class="anomaly-tab-content" style="display: none;">
                    <div style="background: white; padding: 20px; border-radius: 10px;">
                        <h6 style="border-bottom: 2px solid #667eea; padding-bottom: 10px; margin-bottom: 15px; color: #333;">
                            <i class="fas fa-table me-2" style="color: #667eea;"></i>Chi Tiết Dữ Liệu
                        </h6>
                        <div style="overflow-x: auto;">
                            <table id="anomaly-data-table" style="width: 100%; font-size: 0.9rem; border-collapse: collapse;">
                                <thead style="background: #f0f7ff; border-bottom: 2px solid #667eea;">
                                    <tr>
                                        <th style="padding: 10px; text-align: left; color: #333; font-weight: 600;">Kỳ Báo Cáo</th>
                                        <th style="padding: 10px; text-align: left; color: #333; font-weight: 600;">Giá Trị</th>
                                        <th style="padding: 10px; text-align: left; color: #333; font-weight: 600;">So Sánh</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Rows sẽ được điền bằng JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="anomaly-calculation-tab" class="anomaly-tab-content" style="display: none;">
                    <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; border-radius: 10px;">
                        <strong style="color: #856404;">🧮 Công Thức Tính Điểm:</strong>
                        <div id="anomaly-formula" style="color: #856404; line-height: 1.8; margin-top: 10px;">
                            <!-- Formula sẽ được điền bằng JavaScript -->
                        </div>
                    </div>
                </div>

                <div id="anomaly-action-tab" class="anomaly-tab-content" style="display: none;">
                    <div style="background: #d4edda; border-left: 4px solid #28a745; padding: 20px; border-radius: 10px;">
                        <h6 style="color: #155724; margin-bottom: 15px;">
                            <i class="fas fa-bolt me-2"></i>Các Hành Động Cần Thực Hiện
                        </h6>
                        <ul id="anomaly-actions" style="color: #155724; margin: 0; padding-left: 20px;">
                            <!-- Actions sẽ được điền bằng JavaScript -->
                        </ul>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="background: white; border-top: 1px solid #eee;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<style>
    .anomaly-list-item {
        white-space: normal;
    }

    .anomaly-tab-btn.active {
        color: #667eea !important;
        border-bottom-color: #667eea !important;
    }

    .anomaly-tab-btn:hover {
        color: #667eea;
    }

    .metric-card {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border-left: 3px solid #667eea;
    }

    .metric-label {
        font-size: 0.85rem;
        color: #666;
        margin-bottom: 8px;
    }

    .metric-value {
        font-size: 1.3rem;
        font-weight: 700;
        color: #333;
    }

    .metric-unit {
        font-size: 0.75rem;
        color: #999;
        margin-left: 5px;
    }
</style>

<script>
// Dữ liệu chi tiết cho từng dấu hiệu (dạng JSON từ PHP)
const anomalyDetailsData = <?= json_encode([
    'overview' => [
        'explanation' => 'Doanh số tăng đột biến so với trung bình của 3 tháng trước là dấu hiệu đáng ngờ. Một khách hàng bình thường có hành vi mua hàng ổn định, nhưng sự tăng đột biến 275% có thể cho thấy: hoạt động chuẩn bị chương trình khuyến mãi, tích lũy hàng hóa, hoặc hành vi gian lận.',
        'metrics' => [
            ['label' => 'Doanh số kỳ này', 'value' => '45.5M', 'unit' => 'VNĐ'],
            ['label' => 'TB 3 tháng trước', 'value' => '12.15M', 'unit' => 'VNĐ'],
            ['label' => 'Mức tăng', 'value' => '+275%', 'unit' => ''],
            ['label' => 'Chênh lệch', 'value' => '33.35M', 'unit' => 'VNĐ']
        ]
    ],
    'evidence' => [
        ['period' => 'Tháng 12/2025', 'value' => '45,500,000', 'comparison' => '+275%'],
        ['period' => 'Tháng 11/2025', 'value' => '11,200,000', 'comparison' => '-8%'],
        ['period' => 'Tháng 10/2025', 'value' => '13,100,000', 'comparison' => '+8%'],
        ['period' => 'Tháng 09/2025', 'value' => '12,150,000', 'comparison' => 'Baseline']
    ],
    'formula' => 'Điểm gốc: 100/100 (vì tăng ≥300%) | Trọng số: 15% | Công thức: 100 × 15% = 15.0 điểm',
    'actions' => [
        '1. <strong>Liên hệ NVBH ngay (24 giờ):</strong> Xác minh lý do tăng đột biến',
        '2. <strong>Kiểm tra chi tiết đơn hàng:</strong> Xem những đơn nào, ngày giờ nào',
        '3. <strong>So sánh với khách hàng khác:</strong> Xem có riêng KH này tăng không',
        '4. <strong>Rà soát trong 3 ngày:</strong> Lập danh sách tất cả giao dịch',
        '5. <strong>Theo dõi tháng sau:</strong> Xem doanh số có giảm mạnh không'
    ]
]) ?>; 

// Click handler cho anomaly list items
document.querySelectorAll('.anomaly-list-item').forEach(item => {
    item.addEventListener('click', function() {
        const index = this.dataset.anomalyIndex;
        const detailData = anomalyDetailsData.overview;
        
        // Update modal
        document.getElementById('anomaly-explanation').textContent = detailData.explanation;
        
        // Update metrics
        const metricsDiv = document.getElementById('anomaly-metrics');
        metricsDiv.innerHTML = detailData.metrics.map(m => `
            <div class="metric-card">
                <div class="metric-label">${m.label}</div>
                <div class="metric-value">${m.value}<span class="metric-unit">${m.unit}</span></div>
            </div>
        `).join('');
        
        // Update evidence table
        const tableBody = document.querySelector('#anomaly-data-table tbody');
        tableBody.innerHTML = anomalyDetailsData.evidence.map(e => `
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 10px;">${e.period}</td>
                <td style="padding: 10px; font-weight: 600;">${e.value}</td>
                <td style="padding: 10px;">${e.comparison}</td>
            </tr>
        `).join('');
        
        // Update formula
        document.getElementById('anomaly-formula').innerHTML = anomalyDetailsData.formula;
        
        // Update actions
        const actionsList = document.getElementById('anomaly-actions');
        actionsList.innerHTML = anomalyDetailsData.actions.map(a => `<li>${a}</li>`).join('');
        
        // Open modal
        const modal = new bootstrap.Modal(document.getElementById('anomalyDetailModal'));
        modal.show();
    });
});

// Tab switching
document.querySelectorAll('.anomaly-tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const tabName = this.dataset.tab;
        
        // Remove active
        document.querySelectorAll('.anomaly-tab-btn').forEach(b => {
            b.style.color = '#666';
            b.style.borderBottomColor = 'transparent';
        });
        document.querySelectorAll('.anomaly-tab-content').forEach(c => c.style.display = 'none');
        
        // Add active
        this.style.color = '#667eea';
        this.style.borderBottomColor = '#667eea';
        document.getElementById(`anomaly-${tabName}-tab`).style.display = 'block';
    });
});
</script>

<?php elseif (!empty($anomalyInfo)): ?>
<!-- Không phát hiện bất thường -->
<div class="row mt-4">
    <div class="col-12">
        <div class="alert alert-success" style="
            background: linear-gradient(135deg, #28a74515 0%, #20c99715 100%);
            border-left: 4px solid #28a745;
            border-radius: 10px;
        ">
            <h6 class="mb-2">
                <i class="fas fa-check-circle me-2"></i><strong>Hành vi Bình thường</strong>
            </h6>
            <p class="mb-0">
                Không phát hiện dấu hiệu bất thường trong hành vi mua hàng của khách hàng này trong kỳ báo cáo.
            </p>
        </div>
    </div>
</div>
<?php endif; ?>
                
                
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
                                <th>Tháng</th>
                                <th>Năm</th>
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
                                    <td><span class="badge bg-info"><?= $row['RptMonth'] ?? 'N/A' ?></span></td>
                                    <td><span class="badge bg-primary"><?= $row['RptYear'] ?? 'N/A' ?></span></td>
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
                marker.bindPopup('<b><?= htmlspecialchars($data[0]['TenKH'] ?? 'Khách hàng') ?></b><br><?= htmlspecialchars($data[0]['DiaChi'] ?? '') ?>').openPopup();
                
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