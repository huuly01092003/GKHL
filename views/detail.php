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
                 <!-- Thông tin Bất thường -->
                <?php if (!empty($anomalyInfo) && $anomalyInfo['total_score'] > 0): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="section-header" style="background: linear-gradient(135deg, #ff6b6b15 0%, #ee5a6f15 100%); border-left-color: #dc3545;">
                            <h5 class="mb-0" style="color: #dc3545;">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Phát hiện Hành vi Bất thường
                            </h5>
                        </div>
                        
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
                            margin-bottom: 20px;
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
                                        trong hành vi mua hàng của khách hàng này
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

                        <!-- Chi tiết các dấu hiệu bất thường -->
                        <div class="row">
                            <?php foreach ($anomalyInfo['details'] as $index => $detail): ?>
                            <div class="col-md-6 mb-3">
                                <div class="anomaly-detail-card" style="
                                    background: white;
                                    padding: 15px;
                                    border-radius: 10px;
                                    border-left: 4px solid <?php
                                        if ($detail['weighted_score'] >= 15) echo '#dc3545';
                                        elseif ($detail['weighted_score'] >= 10) echo '#fd7e14';
                                        elseif ($detail['weighted_score'] >= 5) echo '#ffc107';
                                        else echo '#20c997';
                                    ?>;
                                    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
                                ">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-0" style="flex: 1;">
                                            <i class="fas fa-exclamation-circle me-2" style="color: <?php
                                                if ($detail['weighted_score'] >= 15) echo '#dc3545';
                                                elseif ($detail['weighted_score'] >= 10) echo '#fd7e14';
                                                elseif ($detail['weighted_score'] >= 5) echo '#ffc107';
                                                else echo '#20c997';
                                            ?>;"></i>
                                            <?= htmlspecialchars($detail['description']) ?>
                                        </h6>
                                        <span class="badge" style="
                                            background: <?php
                                                if ($detail['weighted_score'] >= 15) echo '#dc3545';
                                                elseif ($detail['weighted_score'] >= 10) echo '#fd7e14';
                                                elseif ($detail['weighted_score'] >= 5) echo '#ffc107';
                                                else echo '#20c997';
                                            ?>;
                                            color: <?= $detail['weighted_score'] >= 5 && $detail['weighted_score'] < 15 ? '#000' : 'white' ?>;
                                            font-size: 0.85rem;
                                            padding: 5px 10px;
                                        ">
                                            <?= round($detail['weighted_score'], 1) ?> điểm
                                        </span>
                                    </div>
                                    <div class="text-muted" style="font-size: 0.85rem;">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Điểm gốc: <?= $detail['score'] ?>/100 
                                        | Trọng số: <?= $detail['weight'] ?>%
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Khuyến nghị -->
                        <div class="alert alert-info mt-3">
                            <h6 class="mb-2">
                                <i class="fas fa-lightbulb me-2"></i><strong>Khuyến nghị hành động:</strong>
                            </h6>
                            <ul class="mb-0">
                                <?php if ($anomalyInfo['risk_level'] === 'critical'): ?>
                                    <li><strong>Kiểm tra ngay lập tức:</strong> Liên hệ NVBH phụ trách để xác minh các đơn hàng</li>
                                    <li><strong>Xem xét giao dịch:</strong> Rà soát lại lịch sử giao dịch chi tiết</li>
                                    <li><strong>Đối chiếu GKHL:</strong> Kiểm tra tính hợp lệ của chương trình tham gia</li>
                                <?php elseif ($anomalyInfo['risk_level'] === 'high'): ?>
                                    <li><strong>Theo dõi sát:</strong> Giám sát hành vi mua hàng trong các tháng tiếp theo</li>
                                    <li><strong>Xác minh thông tin:</strong> Liên hệ xác nhận với NVBH hoặc khách hàng</li>
                                <?php elseif ($anomalyInfo['risk_level'] === 'medium'): ?>
                                    <li><strong>Ghi nhận:</strong> Lưu ý theo dõi trong kỳ báo cáo tiếp theo</li>
                                    <li><strong>Phân tích xu hướng:</strong> So sánh với các tháng trước để đánh giá</li>
                                <?php else: ?>
                                    <li><strong>Theo dõi thường xuyên:</strong> Duy trì giám sát định kỳ</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
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