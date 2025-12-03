<?php
// views/components/navbar.php - Component navigation dùng chung
function renderNavbar($currentPage = '', $thangNam = '') {
?>
<nav class="navbar navbar-expand-lg navbar-custom navbar-dark sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-chart-line me-2"></i>
            <strong>HỆ THỐNG BÁO CÁO</strong>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'import' ? 'active' : '' ?>" href="index.php">
                        <i class="fas fa-file-import me-1"></i>Import Báo cáo
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'report' ? 'active' : '' ?>" href="report.php">
                        <i class="fas fa-chart-bar me-1"></i>Báo cáo KH
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'dskh' ? 'active' : '' ?>" href="dskh.php">
                        <i class="fas fa-users me-1"></i>DSKH
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'gkhl' ? 'active' : '' ?>" href="gkhl.php">
                        <i class="fas fa-handshake me-1"></i>GKHL
                    </a>
                </li>
            </ul>
            
            <?php if ($currentPage === 'report' && !empty($thangNam)): ?>
            <span class="navbar-text me-3">
                <i class="fas fa-calendar-alt me-2"></i>
                <strong>Tháng: <?= htmlspecialchars($thangNam) ?></strong>
            </span>
            <?php endif; ?>
            
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-database me-1"></i>Quản lý dữ liệu
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="index.php"><i class="fas fa-upload me-2"></i>Import BC</a></li>
                        <li><a class="dropdown-item" href="dskh.php"><i class="fas fa-upload me-2"></i>Import DSKH</a></li>
                        <li><a class="dropdown-item" href="gkhl.php"><i class="fas fa-upload me-2"></i>Import GKHL</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="dskh.php?action=list"><i class="fas fa-list me-2"></i>Xem DSKH</a></li>
                        <li><a class="dropdown-item" href="gkhl.php?action=list"><i class="fas fa-list me-2"></i>Xem GKHL</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<style>
.navbar-custom {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    box-shadow: 0 2px 10px rgba(0,0,0,0.15);
}
.navbar-custom .nav-link {
    color: rgba(255,255,255,0.85);
    font-weight: 500;
    transition: all 0.3s;
    border-radius: 8px;
    margin: 0 2px;
    padding: 8px 15px;
}
.navbar-custom .nav-link:hover {
    background: rgba(255,255,255,0.15);
    color: white;
}
.navbar-custom .nav-link.active {
    background: rgba(255,255,255,0.25);
    color: white;
    font-weight: 600;
}
.navbar-brand strong {
    font-size: 1.1rem;
    letter-spacing: 0.5px;
}
.dropdown-menu {
    border-radius: 10px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}
.dropdown-item {
    padding: 10px 20px;
    transition: all 0.2s;
}
.dropdown-item:hover {
    background: #f8f9fa;
    padding-left: 25px;
}
</style>
<?php
}
?>