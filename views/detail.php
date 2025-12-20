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
                <!-- ✅ THAY THẾ PHẦN:  Thông tin Bất thường --> 

<?php if (!empty($anomalyInfo) && $anomalyInfo['total_score'] > 0): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="section-header" style="background: linear-gradient(135deg, #ff6b6b15 0%, #ee5a6f15 100%); border-left-color: #dc3545;">
            <h5 class="mb-0" style="color: #dc3545;">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Phát Hiện Hành Vi Bất Thường
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
                        trong hành vi mua hàng - Bấm vào từng mục để xem chi tiết
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

        <!-- Danh Sách Dấu Hiệu (Clickable) -->
        <div style="margin-bottom: 30px;">
            <h6 style="margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #667eea; color: #333;">
                <i class="fas fa-list-check me-2"></i>
                Danh Sách <?= count($anomalyInfo['details']) ?> Dấu Hiệu 
                <small class="text-muted">(Bấm vào mỗi dấu hiệu để xem chi tiết đầy đủ)</small>
            </h6>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 15px;">
                <?php foreach ($anomalyInfo['details'] as $index => $detail): ?>
                <div 
                    class="anomaly-list-item" 
                    data-anomaly-json="<?= htmlspecialchars(json_encode($detail), ENT_QUOTES, 'UTF-8') ?>"
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
                                Điểm gốc: <?= $detail['score'] ?>/100 | 
                                Trọng số: <?= $detail['weight'] ?>% | 
                                <strong>Bấm để xem chi tiết</strong>
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

        <!-- Khuyến Nghị -->
        <div class="alert alert-info" style="border-left: 4px solid #667eea;">
            <h6 class="mb-2">
                <i class="fas fa-lightbulb me-2"></i><strong>Khuyến nghị hành động:</strong>
            </h6>
            <ul class="mb-0">
                <?php if ($anomalyInfo['risk_level'] === 'critical'): ?>
                    <li><strong>🔴 ĐỘ ƯU TIÊN CỰC CAO:</strong> Kiểm tra NGAY LẬP TỨC - Liên hệ NVBH trong 4 giờ</li>
                    <li>Rà soát toàn bộ lịch sử giao dịch của khách hàng</li>
                    <li>Xác minh tính hợp lệ của chương trình GKHL (nếu có)</li>
                    <li>Tạm dừng các đơn hàng mới cho đến khi xác minh xong</li>
                    <li>Báo cáo lên cấp quản lý để xử lý</li>
                <?php elseif ($anomalyInfo['risk_level'] === 'high'): ?>
                    <li><strong>🟠 ĐỘ ƯU TIÊN CAO:</strong> Theo dõi sát và xác minh trong 24 giờ</li>
                    <li>Liên hệ NVBH để xác nhận thông tin</li>
                    <li>Lập kế hoạch kiểm tra chi tiết trong 2-3 ngày</li>
                    <li>Đưa vào danh sách theo dõi đặc biệt</li>
                <?php elseif ($anomalyInfo['risk_level'] === 'medium'): ?>
                    <li><strong>🟡 ĐỘ ƯU TIÊN TRUNG BÌNH:</strong> Ghi nhận và theo dõi</li>
                    <li>So sánh với các tháng trước để xác định xu hướng</li>
                    <li>Đưa vào danh sách giám sát định kỳ</li>
                <?php else: ?>
                    <li><strong>🟢 GHI NHẬN:</strong> Duy trì giám sát thường xuyên</li>
                    <li>Theo dõi trong 1-2 tháng tiếp theo</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<!-- Modal Chi Tiết (Đã có sẵn ở trên) -->

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

    <script>
/**
 * ✅ JAVASCRIPT HIỂN THỊ MODAL CHI TIẾT - SỬ DỤNG DỮ LIỆU THẬT TỪ PHP
 * Thêm vào cuối file detail.php trước </body>
 */

// ============================================
// CONFIG CHO TỪNG LOẠI BẤT THƯỜNG
// ============================================
const anomalyConfig = {
    'sudden_spike': {
        icon: '📈',
        title: 'Doanh Số Tăng Đột Biến',
        color: '#dc3545',
        getExplanation: (m) => {
            const increase = m.increase_percent || 0;
            const months = m.historical_months || 3;
            return `Khách hàng tăng doanh số ${increase}% so với trung bình ${months} tháng trước. ` +
                   `Đây là dấu hiệu điển hình của việc tích lũy hàng hóa trước khi chốt chương trình, hoặc hành vi gian lận.`;
        },
        renderEvidence: (evidence) => {
            if (!evidence || evidence.length === 0) {
                return '<tr><td colspan="3" class="text-center text-muted">Không có dữ liệu</td></tr>';
            }
            return evidence.map(row => `
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 10px;">${row.period}</td>
                    <td style="padding: 10px; font-weight: 600;">${row.value}</td>
                    <td style="padding: 10px;">${row.comparison}</td>
                </tr>
            `).join('');
        }
    },
    
    'return_after_long_break': {
        icon: '↩️',
        title: 'Quay Lại Sau Thời Gian Dài',
        color: '#fd7e14',
        getExplanation: (m) => {
            const gap = m.months_gap || 0;
            const increase = m.increase_percent || 0;
            return `Khách hàng nghỉ mua hàng ${gap} tháng sau đó đột ngột quay lại và mua với số lượng lớn (${increase}% so với lần trước). ` +
                   `Hành vi này rất bất thường và thường liên quan đến gian lận chương trình khuyến mãi.`;
        },
        renderEvidence: (evidence) => {
            if (!evidence || evidence.length === 0) {
                return '<tr><td colspan="3" class="text-center text-muted">Không có dữ liệu lịch sử</td></tr>';
            }
            return evidence.map(row => `
                <tr style="border-bottom: 1px solid #eee; ${row.orders == 0 ? 'background: #fff3cd;' : ''}">
                    <td style="padding: 10px;">${row.period}</td>
                    <td style="padding: 10px; font-weight: 600;">${row.value}</td>
                    <td style="padding: 10px;">${row.orders} đơn ${row.orders == 0 ? '⚠️ NGHỈ' : ''}</td>
                </tr>
            `).join('');
        }
    },
    
    'checkpoint_rush': {
        icon: '🎯',
        title: 'Mua Tập Trung Thời Điểm Chốt Số',
        color: '#ffc107',
        getExplanation: (m) => {
            const ratio = m.checkpoint_ratio || 0;
            const mid = m.mid_checkpoint || 0;
            const end = m.end_checkpoint || 0;
            return `Khách hàng tập trung ${ratio}% đơn hàng vào 2 thời điểm: giữa tháng (12-14): ${mid} đơn và cuối tháng (26-28): ${end} đơn. ` +
                   `Đây là thời điểm chốt số KPI của nhiều chương trình, rất dễ gian lận.`;
        },
        renderEvidence: (evidence) => {
            if (!evidence || evidence.length === 0) {
                return '<tr><td colspan="3" class="text-center text-muted">Không có dữ liệu</td></tr>';
            }
            return evidence.map(row => {
                const isCheckpoint = row.comparison.includes('Giữa tháng') || row.comparison.includes('Cuối tháng');
                return `
                <tr style="border-bottom: 1px solid #eee; ${isCheckpoint ? 'background: #fff3cd;' : ''}">
                    <td style="padding: 10px;">${row.period}</td>
                    <td style="padding: 10px; font-weight: 600;">${row.value}</td>
                    <td style="padding: 10px;">${row.comparison} ${isCheckpoint ? '⚠️' : ''}</td>
                </tr>
            `}).join('');
        }
    },
    
    'product_concentration': {
        icon: '📦',
        title: 'Chỉ Mua 1 Loại Sản Phẩm',
        color: '#e83e8c',
        getExplanation: (m) => {
            const types = m.distinct_types || 0;
            const concentration = m.concentration_percent || 0;
            const topType = m.top_product_type || 'N/A';
            return `Khách hàng chỉ mua ${types} loại sản phẩm (${topType}) với tỷ lệ tập trung ${concentration}%. ` +
                   `Khách hàng thực thường mua đa dạng, việc tập trung vào 1 sản phẩm có thể là tích lũy để đạt KPI.`;
        },
        renderEvidence: (evidence) => {
            if (!evidence || evidence.length === 0) {
                return '<tr><td colspan="3" class="text-center text-muted">Không có dữ liệu</td></tr>';
            }
            return evidence.map((row, idx) => `
                <tr style="border-bottom: 1px solid #eee; ${idx === 0 ? 'background: #fff3cd;' : ''}">
                    <td style="padding: 10px;">${row.period}</td>
                    <td style="padding: 10px; font-weight: 600;">${row.value}</td>
                    <td style="padding: 10px;">${row.comparison} ${idx === 0 ? '⚠️ CHỦ LỰC' : ''}</td>
                </tr>
            `).join('');
        }
    },
    
    'unusual_product_pattern': {
        icon: '🔀',
        title: 'Mua Sản Phẩm Khác Lạ',
        color: '#6f42c1',
        getExplanation: (m) => {
            const newProducts = m.new_products || 0;
            const newRatio = m.new_ratio || 0;
            const types = m.new_product_types || 'N/A';
            return `Khách hàng đột ngột mua ${newProducts} loại sản phẩm mới (${newRatio}%) khác lạ so với thói quen: ${types}. ` +
                   `Hành vi thay đổi đột ngột thường liên quan đến gian lận hoặc thay đổi người mua.`;
        },
        renderEvidence: (evidence) => {
            if (!evidence || !evidence.usual_products || !evidence.new_products) {
                return '<tr><td colspan="3" class="text-center text-muted">Không có dữ liệu so sánh</td></tr>';
            }
            
            let html = '<tr><td colspan="3" style="background: #e7f3ff; padding: 10px; font-weight: 600;">📌 Sản phẩm thường mua (12 tháng trước)</td></tr>';
            evidence.usual_products.forEach(row => {
                html += `
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px;">Loại SP: ${row.product_type}</td>
                        <td style="padding: 10px;">${row.frequency} lần</td>
                        <td style="padding: 10px;">${formatMoney(row.total_amount)}</td>
                    </tr>
                `;
            });
            
            html += '<tr><td colspan="3" style="background: #fff3cd; padding: 10px; font-weight: 600;">⚠️ Sản phẩm mới (kỳ này)</td></tr>';
            evidence.new_products.forEach(row => {
                html += `
                    <tr style="border-bottom: 1px solid #eee; background: #fffbf0;">
                        <td style="padding: 10px;">${row.period}</td>
                        <td style="padding: 10px; font-weight: 600;">${row.value}</td>
                        <td style="padding: 10px;">${row.comparison}</td>
                    </tr>
                `;
            });
            
            return html;
        }
    },
    
    'burst_orders': {
        icon: '⚡',
        title: 'Mua Dồn Dập Trong Ngắn Hạn',
        color: '#dc3545',
        getExplanation: (m) => {
            const maxOrders = m.max_orders_in_day || 0;
            const maxDate = m.max_order_date || 'N/A';
            const consecutive = m.max_consecutive_days || 0;
            return `Khách hàng đặt ${maxOrders} đơn trong 1 ngày (${maxDate}) và mua liên tục ${consecutive} ngày. ` +
                   `Hành vi bình thường là rải đều trong tháng, việc dồn dập là dấu hiệu gian lận rõ ràng.`;
        },
        renderEvidence: (evidence) => {
            if (!evidence || evidence.length === 0) {
                return '<tr><td colspan="3" class="text-center text-muted">Không có dữ liệu</td></tr>';
            }
            return evidence.map((row, idx) => {
                const isHighVolume = parseInt(row.comparison) >= 5;
                return `
                <tr style="border-bottom: 1px solid #eee; ${isHighVolume ? 'background: #fff3cd;' : ''}">
                    <td style="padding: 10px;">${row.period}</td>
                    <td style="padding: 10px; font-weight: 600;">${row.value}</td>
                    <td style="padding: 10px;">${row.comparison} ${isHighVolume ? '⚠️ DỒN DẬP' : ''}</td>
                </tr>
            `}).join('');
        }
    },
    
    'high_value_outlier': {
        icon: '💰',
        title: 'Giá Trị Đơn Cao Bất Thường (>3σ)',
        color: '#28a745',
        getExplanation: (m) => {
            const sigma = m.sigma_count || 0;
            const max = formatMoney(m.max_order_value);
            const threshold = formatMoney(m.threshold_3sigma);
            return `Có đơn hàng với giá trị ${max} cao hơn ${sigma.toFixed(2)} lần độ lệch chuẩn so với trung bình (ngưỡng 3σ: ${threshold}). ` +
                   `Đây là outlier thống kê, cần kiểm tra kỹ.`;
        },
        renderEvidence: (evidence) => {
            if (!evidence || evidence.length === 0) {
                return '<tr><td colspan="3" class="text-center text-muted">Không có dữ liệu</td></tr>';
            }
            return evidence.map((row, idx) => `
                <tr style="border-bottom: 1px solid #eee; ${idx === 0 ? 'background: #fff3cd;' : ''}">
                    <td style="padding: 10px;">${row.period}</td>
                    <td style="padding: 10px; font-weight: 600;">${row.value}</td>
                    <td style="padding: 10px;">${row.comparison} ${idx === 0 ? '⚠️ CAO NHẤT' : ''}</td>
                </tr>
            `).join('');
        }
    },
    
    'no_purchase_after_spike': {
        icon: '🛑',
        title: 'Không Mua Sau Khi Tăng Đột Biến',
        color: '#6c757d',
        getExplanation: (m) => {
            const drop = m.drop_percent || 0;
            const hasActivity = m.has_activity || false;
            return `Sau khi mua nhiều đột biến, khách hàng ${hasActivity ? 'giảm ' + drop + '%' : 'ngừng mua hoàn toàn'} trong 1-2 tháng tiếp theo. ` +
                   `Đây là dấu hiệu rõ ràng của việc "đẩy" doanh số để đạt KPI.`;
        },
        renderEvidence: (evidence) => {
            if (!evidence || evidence.length === 0) {
                return '<tr><td colspan="3" class="text-center text-muted">Không có dữ liệu</td></tr>';
            }
            return evidence.map(row => {
                const isSpike = row.period.includes('Spike');
                const isAfter = row.period.includes('Sau spike');
                const noActivity = row.comparison.includes('Không có');
                return `
                <tr style="border-bottom: 1px solid #eee; ${isSpike ? 'background: #fff3cd;' : ''} ${noActivity ? 'background: #f8d7da;' : ''}">
                    <td style="padding: 10px;">${row.period}</td>
                    <td style="padding: 10px; font-weight: 600;">${row.value}</td>
                    <td style="padding: 10px;">${row.comparison} ${noActivity ? '⚠️ NGỪNG' : ''}</td>
                </tr>
            `}).join('');
        }
    }
};

// ============================================
// HÀM HELPER
// ============================================
function formatMoney(value) {
    if (!value || value === 0) return '0';
    return parseFloat(value).toLocaleString('vi-VN');
}

function getMetricCards(type, metrics) {
    switch(type) {
        case 'sudden_spike':
            return [
                {label: 'Doanh số kỳ này', value: formatMoney(metrics.current_sales), unit: 'VNĐ'},
                {label: 'TB 3-6 tháng trước', value: formatMoney(metrics.historical_avg), unit: 'VNĐ'},
                {label: 'Mức tăng', value: '+' + (metrics.increase_percent || 0) + '%', unit: '', highlight: true},
                {label: 'Chênh lệch', value: formatMoney(metrics.difference || 0), unit: 'VNĐ'}
            ];
        
        case 'return_after_long_break':
            return [
                {label: 'Thời gian nghỉ', value: metrics.months_gap || 0, unit: 'tháng'},
                {label: 'Doanh số quay lại', value: formatMoney(metrics.current_sales), unit: 'VNĐ'},
                {label: 'Doanh số trước đó', value: formatMoney(metrics.last_sales), unit: 'VNĐ'},
                {label: 'Mức tăng', value: '+' + (metrics.increase_percent || 0) + '%', unit: '', highlight: true}
            ];
        
        case 'checkpoint_rush':
            return [
                {label: 'Đơn tại checkpoint', value: metrics.checkpoint_orders || 0, unit: 'đơn'},
                {label: 'Tổng đơn', value: metrics.total_orders || 0, unit: 'đơn'},
                {label: 'Tỷ lệ đơn', value: (metrics.checkpoint_ratio || 0) + '%', unit: '', highlight: true},
                {label: 'DS tại checkpoint', value: formatMoney(metrics.checkpoint_amount), unit: 'VNĐ'},
                {label: 'Tổng doanh số', value: formatMoney(metrics.total_amount), unit: 'VNĐ'},
                {label: 'Tỷ lệ DS', value: (metrics.amount_ratio || 0) + '%', unit: '', highlight: true}
            ];
        
        case 'product_concentration':
            return [
                {label: 'Số loại SP', value: metrics.distinct_types || 0, unit: 'loại'},
                {label: 'Loại chính', value: metrics.top_product_type || 'N/A', unit: ''},
                {label: 'SL loại chính', value: (metrics.top_product_qty || 0).toLocaleString(), unit: 'đơn vị'},
                {label: 'Tổng SL', value: (metrics.total_qty || 0).toLocaleString(), unit: 'đơn vị'},
                {label: 'Tỷ lệ tập trung', value: (metrics.concentration_percent || 0) + '%', unit: '', highlight: true}
            ];
        
        case 'unusual_product_pattern':
            return [
                {label: 'Sản phẩm mới', value: metrics.new_products || 0, unit: 'loại'},
                {label: 'Tổng loại SP', value: metrics.total_products || 0, unit: 'loại'},
                {label: 'Tỷ lệ SP mới', value: (metrics.new_ratio || 0) + '%', unit: '', highlight: true},
                {label: 'DS từ SP mới', value: formatMoney(metrics.new_sales), unit: 'VNĐ'},
                {label: 'Tỷ lệ DS mới', value: (metrics.new_sales_ratio || 0) + '%', unit: '', highlight: true}
            ];
        
        case 'burst_orders':
            return [
                {label: 'Đơn/ngày cao nhất', value: metrics.max_orders_in_day || 0, unit: 'đơn'},
                {label: 'Ngày', value: metrics.max_order_date || 'N/A', unit: ''},
                {label: 'Liên tục', value: metrics.max_consecutive_days || 0, unit: 'ngày'},
                {label: 'Tổng ngày mua', value: metrics.total_days || 0, unit: 'ngày'},
                {label: 'TB đơn/ngày', value: (metrics.avg_orders_per_day || 0).toFixed(1), unit: 'đơn'}
            ];
        
        case 'high_value_outlier':
            return [
                {label: 'Giá trị đơn cao nhất', value: formatMoney(metrics.max_order_value), unit: 'VNĐ'},
                {label: 'Giá trị TB', value: formatMoney(metrics.avg_order_value), unit: 'VNĐ'},
                {label: 'Độ lệch chuẩn (σ)', value: formatMoney(metrics.stddev), unit: 'VNĐ'},
                {label: 'Số sigma', value: (metrics.sigma_count || 0).toFixed(2) + 'σ', unit: '', highlight: true},
                {label: 'Ngưỡng 3σ', value: formatMoney(metrics.threshold_3sigma), unit: 'VNĐ'}
            ];
        
        case 'no_purchase_after_spike':
            return [
                {label: 'DS kỳ spike', value: formatMoney(metrics.spike_sales), unit: 'VNĐ'},
                {label: 'DS sau đó', value: formatMoney(metrics.after_sales), unit: 'VNĐ'},
                {label: 'Đơn sau đó', value: metrics.after_orders || 0, unit: 'đơn'},
                {label: 'Mức giảm', value: '-' + (metrics.drop_percent || 0) + '%', unit: '', highlight: true}
            ];
        
        default:
            return [];
    }
}

function getFormula(type, metrics) {
    switch(type) {
        case 'sudden_spike':
            return `
                <strong>Công thức tính điểm gốc:</strong><br>
                - Tăng ≥500%: 100 điểm<br>
                - Tăng ≥400%: 90 điểm<br>
                - Tăng ≥300%: 80 điểm<br>
                - Tăng ≥200%: 65 điểm<br>
                - Tăng ≥150%: 50 điểm<br><br>
                <strong>Trường hợp này:</strong> Tăng ${metrics.increase_percent}% → Điểm gốc: ${metrics.score || 0}/100<br>
                <strong>Trọng số:</strong> 20%<br>
                <strong>Điểm cuối:</strong> ${metrics.score || 0} × 20% = ${((metrics.score || 0) * 0.2).toFixed(1)} điểm
            `;
        
        case 'return_after_long_break':
            return `
                <strong>Công thức:</strong><br>
                - Nghỉ ≥6 tháng + Tăng ≥200%: 100 điểm<br>
                - Nghỉ ≥4 tháng + Tăng ≥150%: 80 điểm<br>
                - Nghỉ ≥3 tháng + Tăng ≥100%: 60 điểm<br><br>
                <strong>Trường hợp này:</strong> Nghỉ ${metrics.months_gap} tháng, Tăng ${metrics.increase_percent}%<br>
                <strong>Trọng số:</strong> 18%
            `;
        
        case 'checkpoint_rush':
            return `
                <strong>Công thức:</strong><br>
                - Checkpoint ≥80% đơn và DS: 100 điểm<br>
                - Checkpoint ≥70%: 85 điểm<br>
                - Checkpoint ≥60%: 70 điểm<br>
                - Checkpoint ≥50%: 55 điểm<br><br>
                <strong>Trường hợp này:</strong> ${metrics.checkpoint_ratio}% đơn, ${metrics.amount_ratio}% DS<br>
                <strong>Trọng số:</strong> 16%
            `;
        
        case 'product_concentration':
            return `
                <strong>Công thức:</strong><br>
                - 1 loại + Tập trung ≥95%: 100 điểm<br>
                - 1 loại + Tập trung ≥90%: 85 điểm<br>
                - 1 loại + Tập trung ≥80%: 70 điểm<br>
                - 2 loại + Tập trung ≥85%: 60 điểm<br><br>
                <strong>Trường hợp này:</strong> ${metrics.distinct_types} loại, Tập trung ${metrics.concentration_percent}%<br>
                <strong>Trọng số:</strong> 14%
            `;
        
        case 'unusual_product_pattern':
            return `
                <strong>Công thức:</strong><br>
                - SP mới ≥80% + DS mới ≥70%: 100 điểm<br>
                - SP mới ≥60% + DS mới ≥50%: 80 điểm<br>
                - SP mới ≥40% hoặc DS mới ≥40%: 60 điểm<br><br>
                <strong>Trường hợp này:</strong> ${metrics.new_ratio}% SP mới, ${metrics.new_sales_ratio}% DS mới<br>
                <strong>Trọng số:</strong> 12%
            `;
        
        case 'burst_orders':
            return `
                <strong>Công thức:</strong><br>
                - ≥10 đơn/ngày + Liên tục 3 ngày: 100 điểm<br>
                - ≥8 đơn/ngày + Liên tục 2 ngày: 85 điểm<br>
                - ≥6 đơn/ngày: 70 điểm<br>
                - ≥5 đơn/ngày và >3x TB: 60 điểm<br><br>
                <strong>Trường hợp này:</strong> ${metrics.max_orders_in_day} đơn/ngày, Liên tục ${metrics.max_consecutive_days} ngày<br>
                <strong>Trọng số:</strong> 15%
            `;
        
        case 'high_value_outlier':
            return `
                <strong>Công thức:</strong><br>
                Số σ = (Giá trị max - Trung bình) / Độ lệch chuẩn<br><br>
                - ≥5σ: 100 điểm<br>
                - ≥4σ: 85 điểm<br>
                - ≥3σ: 70 điểm<br>
                - ≥2.5σ: 50 điểm<br><br>
                <strong>Trường hợp này:</strong> ${(metrics.sigma_count || 0).toFixed(2)}σ<br>
                <strong>Trọng số:</strong> 13%
            `;
        
        case 'no_purchase_after_spike':
            return `
                <strong>Công thức:</strong><br>
                - Không mua gì sau spike: 100 điểm<br>
                - Giảm ≥90%: 85 điểm<br>
                - Giảm ≥80%: 70 điểm<br>
                - Giảm ≥70%: 55 điểm<br><br>
                <strong>Lưu ý:</strong> Chỉ áp dụng nếu có spike (≥50 điểm)<br>
                <strong>Trọng số:</strong> 10%
            `;
        
        default:
            return 'Công thức chưa được định nghĩa';
    }
}

function getActions(type) {
    switch(type) {
        case 'sudden_spike':
            return [
                '<strong>1. Liên hệ NVBH ngay (trong 24 giờ):</strong> Xác minh lý do tăng đột biến',
                '<strong>2. Kiểm tra chi tiết đơn hàng:</strong> Xem những đơn nào, ngày nào, sản phẩm gì',
                '<strong>3. So sánh với khách hàng khác:</strong> Xem có chỉ KH này tăng hay nhiều KH cùng tăng',
                '<strong>4. Rà soát trong 3 ngày:</strong> Lập danh sách tất cả giao dịch bất thường',
                '<strong>5. Theo dõi 2-3 tháng tiếp theo:</strong> Xem doanh số có giảm mạnh/ngừng mua không'
            ];
        
        case 'return_after_long_break':
            return [
                '<strong>1. Xác minh ngay lập tức:</strong> Tại sao khách hàng quay lại sau thời gian dài?',
                '<strong>2. Kiểm tra lịch sử:</strong> Có mua hàng từ nguồn khác không?',
                '<strong>3. Thẩm định đơn hàng:</strong> Kiểm tra tính hợp lệ của các đơn',
                '<strong>4. Theo dõi liên tục:</strong> Xem có tiếp tục mua hay dừng sau 1-2 tháng'
            ];
        
        case 'checkpoint_rush':
            return [
                '<strong>1. Rà soát ngay:</strong> Tại sao lại tập trung vào 2 thời điểm này?',
                '<strong>2. Kiểm tra đối chiếu:</strong> So sánh với các KH khác trong khu vực',
                '<strong>3. Xác minh giao hàng:</strong> Đơn có thực sự được giao không?',
                '<strong>4. Cảnh báo NVBH:</strong> Nhắc nhở về quy trình kiểm soát'
            ];
        
        case 'product_concentration':
            return [
                '<strong>1. Xác minh nhu cầu:</strong> Tại sao chỉ mua 1 loại?',
                '<strong>2. Kiểm tra kho:</strong> Sản phẩm có tồn kho lâu không?',
                '<strong>3. So sánh lịch sử:</strong> Trước đây KH có mua đa dạng không?',
                '<strong>4. Rà soát chương trình KM:</strong> Có đang chạy KM cho SP này không?'
            ];
        
        case 'unusual_product_pattern':
            return [
                '<strong>1. Xác minh:</strong> Có thay đổi người quản lý/chủ cửa hàng không?',
                '<strong>2. Kiểm tra:</strong> Sản phẩm mới có phù hợp với ngành hàng không?',
                '<strong>3. So sánh:</strong> Các KH khác có mua SP này không?',
                '<strong>4. Theo dõi:</strong> Tháng sau có tiếp tục mua SP mới không?'
            ];
        
        case 'burst_orders':
            return [
                '<strong>1. Kiểm tra GẤP:</strong> Tại sao lại đặt nhiều đơn cùng lúc?',
                '<strong>2. Xác minh giao hàng:</strong> Tất cả đơn có được giao thực tế không?',
                '<strong>3. Rà soát hệ thống:</strong> Có phải lỗi hệ thống tạo đơn trùng không?',
                '<strong>4. Cảnh báo nghiêm trọng:</strong> Đưa vào danh sách theo dõi đặc biệt'
            ];
        
        case 'high_value_outlier':
            return [
                '<strong>1. Xác minh đơn hàng:</strong> Kiểm tra chi tiết sản phẩm và số lượng',
                '<strong>2. Đối chiếu:</strong> So với các đơn khác của KH',
                '<strong>3. Xác nhận thanh toán:</strong> Đã thanh toán đầy đủ chưa?',
                '<strong>4. Kiểm tra giao hàng:</strong> Đơn có thực sự được giao không?'
            ];
        
        case 'no_purchase_after_spike':
            return [
                '<strong>1. Kết luận gian lận:</strong> Khả năng cao là đẩy DS giả',
                '<strong>2. Rà soát toàn bộ:</strong> Kiểm tra lại tất cả đơn hàng trong kỳ spike',
                '<strong>3. Liên hệ ngay:</strong> Yêu cầu NVBH giải trình',
                '<strong>4. Xử lý:</strong> Cân nhắc các biện pháp xử lý theo quy định'
            ];
        
        default:
            return ['Chưa có khuyến nghị cụ thể'];
    }
}

// ============================================
// HÀM HIỂN THỊ MODAL
// ============================================
function showAnomalyDetailModal(data) {
    const config = anomalyConfig[data.type];
    if (!config) {
        console.error('Không tìm thấy config cho type:', data.type);
        return;
    }
    
    const metrics = data.metrics || {};
    const modal = document.getElementById('anomalyDetailModal');
    if (!modal) {
        console.error('Không tìm thấy modal');
        return;
    }
    
    // Update header
    document.getElementById('modalTitle').innerHTML = `${config.icon} ${config.title}`;
    document.getElementById('modalSubtitle').textContent = 
        `Chỉ số: ${data.type} | Trọng số: ${data.weight}% | Điểm: ${data.weighted_score.toFixed(1)}`;
    modal.querySelector('.modal-header').style.background = 
        `linear-gradient(135deg, ${config.color} 0%, ${adjustColor(config.color, -20)} 100%)`;
    
    // Tab 1: TỔNG QUAN
    document.getElementById('anomaly-explanation').textContent = config.getExplanation(metrics);
    
    const metricCards = getMetricCards(data.type, metrics);
    document.getElementById('anomaly-metrics').innerHTML = metricCards.map(m => `
        <div class="metric-card" style="${m.highlight ? 'border-left-color: ' + config.color + ';' : ''}">
            <div class="metric-label">${m.label}</div>
            <div class="metric-value" style="${m.highlight ? 'color: ' + config.color + ';' : ''}">
                ${m.value}<span class="metric-unit">${m.unit}</span>
            </div>
        </div>
    `).join('');
    
    // Tab 2: MINH CHỨNG - DỮ LIỆU THẬT
    const tableBody = document.querySelector('#anomaly-data-table tbody');
    tableBody.innerHTML = config.renderEvidence(metrics.evidence);
    
    // Tab 3: TÍNH TOÁN
    document.getElementById('anomaly-formula').innerHTML = getFormula(data.type, metrics);
    
    // Tab 4: HÀNH ĐỘNG
    const actionsList = document.getElementById('anomaly-actions');
    actionsList.innerHTML = getActions(data.type).map(a => `<li>${a}</li>`).join('');
    
    // Mở modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

// ============================================
// KHỞI TẠO KHI TRANG LOAD
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // Gắn sự kiện click cho tất cả anomaly list items
    document.querySelectorAll('.anomaly-list-item').forEach(item => {
        item.addEventListener('click', function() {
            const jsonData = this.dataset.anomalyJson;
            if (!jsonData) {
                console.error('Không có dữ liệu JSON');
                return;
            }
            
            try {
                const data = JSON.parse(jsonData);
                showAnomalyDetailModal(data);
            } catch(e) {
                console.error('Lỗi parse JSON:', e);
            }
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
});

// ============================================
// HELPER: ĐIỀU CHỈNH MÀU
// ============================================
function adjustColor(color, percent) {
    const num = parseInt(color.replace("#",""), 16);
    const amt = Math.round(2.55 * percent);
    const R = (num >> 16) + amt;
    const G = (num >> 8 & 0x00FF) + amt;
    const B = (num & 0x0000FF) + amt;
    return "#" + (0x1000000 + (R<255?R<1?0:R:255)*0x10000 +
        (G<255?G<1?0:G:255)*0x100 + (B<255?B<1?0:B:255))
        .toString(16).slice(1);
}
</script>

<!-- ✅ THÊM VÀO CUỐI FILE views/detail.php TRƯỚC </body> -->

<script>
/**
 * ========================================
 * FIX MODAL FREEZE BUG
 * ========================================
 * Vấn đề: Sau khi đóng modal, trang bị freeze (không thao tác được)
 * Nguyên nhân: 
 * 1. Backdrop không bị remove
 * 2. Body class 'modal-open' không bị xóa
 * 3. Body style 'overflow: hidden' vẫn còn
 * 4. Event listener bị treo
 * 
 * Giải pháp: Force cleanup mọi thứ khi modal đóng
 */

(function() {
    'use strict';
    
    // ============================================
    // 1. GLOBAL CLEANUP FUNCTION
    // ============================================
    function forceModalCleanup() {
        // Remove all backdrops
        document.querySelectorAll('.modal-backdrop').forEach(el => {
            el.remove();
        });
        
        // Remove modal-open class from body
        document.body.classList.remove('modal-open');
        
        // Reset body styles
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
        
        // Remove any stuck modals
        document.querySelectorAll('.modal.show').forEach(modal => {
            modal.classList.remove('show');
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        });
        
        console.log('✅ Modal cleanup completed');
    }
    
    // ============================================
    // 2. ATTACH CLEANUP TO ALL MODALS
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        const modals = document.querySelectorAll('.modal');
        
        modals.forEach(function(modalElement) {
            // On modal hidden event
            modalElement.addEventListener('hidden.bs.modal', function() {
                console.log('Modal hidden event fired for:', modalElement.id);
                
                // Delay cleanup slightly to ensure Bootstrap finishes
                setTimeout(forceModalCleanup, 100);
            });
            
            // On modal hide event (before hidden)
            modalElement.addEventListener('hide.bs.modal', function() {
                console.log('Modal hide event fired for:', modalElement.id);
            });
        });
        
        // ============================================
        // 3. CLOSE BUTTON OVERRIDE
        // ============================================
        document.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                console.log('Close button clicked');
                
                // Force cleanup after 200ms
                setTimeout(forceModalCleanup, 200);
            });
        });
        
        // ============================================
        // 4. ESC KEY HANDLER
        // ============================================
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const openModals = document.querySelectorAll('.modal.show');
                if (openModals.length > 0) {
                    console.log('ESC pressed, closing modals');
                    setTimeout(forceModalCleanup, 200);
                }
            }
        });
        
        // ============================================
        // 5. BACKDROP CLICK HANDLER
        // ============================================
        document.querySelectorAll('.modal').forEach(function(modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    console.log('Backdrop clicked');
                    setTimeout(forceModalCleanup, 200);
                }
            });
        });
        
        console.log('✅ Modal freeze fix initialized');
    });
    
    // ============================================
    // 6. EMERGENCY CLEANUP ON WINDOW FOCUS
    // ============================================
    window.addEventListener('focus', function() {
        const hasBackdrop = document.querySelector('.modal-backdrop');
        const hasModalOpen = document.body.classList.contains('modal-open');
        const hasVisibleModal = document.querySelector('.modal.show');
        
        if ((hasBackdrop || hasModalOpen) && !hasVisibleModal) {
            console.warn('⚠️ Detected stuck modal state, cleaning up...');
            forceModalCleanup();
        }
    });
    
    // ============================================
    // 7. EXPOSE CLEANUP FUNCTION GLOBALLY
    // ============================================
    window.forceModalCleanup = forceModalCleanup;
    
})();

/**
 * ========================================
 * ENHANCED showAnomalyDetailModal
 * ========================================
 * Thêm cleanup vào hàm mở modal
 */
function showAnomalyDetailModal(data) {
    // Cleanup trước khi mở modal mới
    if (typeof window.forceModalCleanup === 'function') {
        window.forceModalCleanup();
    }
    
    const config = anomalyConfig[data.type];
    if (!config) {
        console.error('Không tìm thấy config cho type:', data.type);
        return;
    }
    
    const metrics = data.metrics || {};
    const modal = document.getElementById('anomalyDetailModal');
    if (!modal) {
        console.error('Không tìm thấy modal');
        return;
    }
    
    // Update header
    document.getElementById('modalTitle').innerHTML = `${config.icon} ${config.title}`;
    document.getElementById('modalSubtitle').textContent = 
        `Chỉ số: ${data.type} | Trọng số: ${data.weight}% | Điểm: ${data.weighted_score.toFixed(1)}`;
    modal.querySelector('.modal-header').style.background = 
        `linear-gradient(135deg, ${config.color} 0%, ${adjustColor(config.color, -20)} 100%)`;
    
    // ... (giữ nguyên phần update content)
    document.getElementById('anomaly-explanation').textContent = config.getExplanation(metrics);
    
    const metricCards = getMetricCards(data.type, metrics);
    document.getElementById('anomaly-metrics').innerHTML = metricCards.map(m => `
        <div class="metric-card" style="${m.highlight ? 'border-left-color: ' + config.color + ';' : ''}">
            <div class="metric-label">${m.label}</div>
            <div class="metric-value" style="${m.highlight ? 'color: ' + config.color + ';' : ''}">
                ${m.value}<span class="metric-unit">${m.unit}</span>
            </div>
        </div>
    `).join('');
    
    const tableBody = document.querySelector('#anomaly-data-table tbody');
    tableBody.innerHTML = config.renderEvidence(metrics.evidence);
    
    document.getElementById('anomaly-formula').innerHTML = getFormula(data.type, metrics);
    
    const actionsList = document.getElementById('anomaly-actions');
    actionsList.innerHTML = getActions(data.type).map(a => `<li>${a}</li>`).join('');
    
    // ✅ IMPROVED: Mở modal với error handling
    try {
        const bsModal = new bootstrap.Modal(modal, {
            backdrop: 'static', // Prevent close on backdrop click initially
            keyboard: true
        });
        
        // Add cleanup on close
        modal.addEventListener('hidden.bs.modal', function handler() {
            console.log('✅ Anomaly modal closed, cleaning up...');
            window.forceModalCleanup();
            modal.removeEventListener('hidden.bs.modal', handler);
        }, { once: true });
        
        bsModal.show();
        console.log('✅ Modal opened successfully');
        
    } catch (error) {
        console.error('❌ Error opening modal:', error);
        window.forceModalCleanup();
    }
}

/**
 * ========================================
 * CONSOLE HELPER
 * ========================================
 * Cho phép user test cleanup từ console
 */
console.log('%c🔧 Modal Fix Loaded', 'color: #28a745; font-size: 14px; font-weight: bold;');
console.log('%cĐể force cleanup modal, gõ: window.forceModalCleanup()', 'color: #666;');
</script>

<style>
/**
 * ========================================
 * CSS FIX: Prevent scroll issues
 * ========================================
 */
.modal.show {
    overflow-x: hidden;
    overflow-y: auto;
}

.modal-backdrop {
    /* Ensure backdrop is always below modals */
    z-index: 1040;
}

.modal {
    /* Ensure modals are above backdrop */
    z-index: 1050;
}

/* Fix for body when modal is open */
body.modal-open {
    overflow: hidden !important;
    padding-right: 0 !important;
}

/* Ensure backdrop removal animation */
.modal-backdrop.fade {
    transition: opacity 0.15s linear;
}

.modal-backdrop.show {
    opacity: 0.5;
}
</style>
</body>
</html>