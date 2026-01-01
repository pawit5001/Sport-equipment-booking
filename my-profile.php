<?php
session_start();
include('includes/config.php');
error_reporting(0);

if (strlen($_SESSION['login']) == 0) {
    header('location:index.php');
    exit;
}

$sid = $_SESSION['stdid'];
$sql = "SELECT * FROM tblmembers WHERE id=:sid";
$query = $dbh->prepare($sql);
$query->bindParam(':sid', $sid, PDO::PARAM_STR);
$query->execute();
$user = $query->fetch(PDO::FETCH_OBJ);

// Get booking stats
$sqlStats = "SELECT 
    COUNT(*) as totalBookings,
    SUM(CASE WHEN Status = 'returned' THEN 1 ELSE 0 END) as returnedBookings,
    SUM(CASE WHEN Status IN ('borrowed', 'partial') THEN 1 ELSE 0 END) as activeBookings
    FROM tblbookings WHERE MemberId = :sid";
$queryStats = $dbh->prepare($sqlStats);
$queryStats->bindParam(':sid', $sid, PDO::PARAM_STR);
$queryStats->execute();
$stats = $queryStats->fetch(PDO::FETCH_OBJ);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>E-Sports | โปรไฟล์ของฉัน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <link href="assets/css/modern-style.css" rel="stylesheet" />
    <style>
        .profile-hero {
            background: linear-gradient(135deg, #1e40af 0%, #0891b2 100%);
            padding: 4rem 0 6rem;
            color: white;
            margin-bottom: -60px;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #38bdf8 0%, #818cf8 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            margin: 0 auto;
            border: 4px solid rgba(255,255,255,0.3);
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            position: relative;
            z-index: 10;
        }
        .profile-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            position: relative;
            z-index: 5;
            margin-top: -60px;
        }
        .profile-card-header {
            padding: 80px 2rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid #f1f5f9;
        }
        .profile-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }
        .profile-id {
            color: #64748b;
            font-size: 0.95rem;
        }
        .profile-id span {
            background: linear-gradient(135deg, #dbeafe, #e0e7ff);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-weight: 600;
            color: #1e40af;
        }
        .info-section {
            padding: 1.5rem 2rem;
        }
        .info-section-title {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        .info-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-icon {
            width: 40px;
            height: 40px;
            background: #f1f5f9;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            margin-right: 1rem;
        }
        .info-content label {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-bottom: 0.15rem;
            display: block;
        }
        .info-content p {
            margin: 0;
            font-weight: 600;
            color: #1e293b;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            padding: 1.5rem 2rem;
            background: #f8fafc;
        }
        .stat-item {
            text-align: center;
            padding: 1rem;
            background: white;
            border-radius: 12px;
        }
        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1e40af;
        }
        .stat-label {
            font-size: 0.8rem;
            color: #64748b;
        }
        .stat-item.active .stat-value {
            color: #f59e0b;
        }
        .stat-item.returned .stat-value {
            color: #10b981;
        }
        .action-buttons {
            padding: 1.5rem 2rem;
            display: flex;
            gap: 1rem;
        }
        .action-buttons .btn {
            flex: 1;
            padding: 0.75rem;
            border-radius: 12px;
            font-weight: 600;
        }
        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include('includes/header.php'); ?>
    
    <!-- Hero Section -->
    <div class="profile-hero">
        <div class="container">
            <div class="profile-avatar">
                <i class="fa fa-user"></i>
            </div>
        </div>
    </div>
    
    <div class="content-wrapper" style="padding-top: 0;">
        <div class="container" style="max-width: 600px;">
            <div class="profile-card">
                <!-- Header with Name -->
                <div class="profile-card-header">
                    <h2 class="profile-name"><?php echo htmlspecialchars($user->Name . ' ' . $user->Surname); ?></h2>
                    <p class="profile-id">
                        <span><i class="fa fa-id-card me-1"></i><?php echo htmlspecialchars($user->StudentID); ?></span>
                    </p>
                </div>
                
                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats->totalBookings ?? 0; ?></div>
                        <div class="stat-label">ยืมทั้งหมด</div>
                    </div>
                    <div class="stat-item active">
                        <div class="stat-value"><?php echo $stats->activeBookings ?? 0; ?></div>
                        <div class="stat-label">กำลังยืม</div>
                    </div>
                    <div class="stat-item returned">
                        <div class="stat-value"><?php echo $stats->returnedBookings ?? 0; ?></div>
                        <div class="stat-label">คืนแล้ว</div>
                    </div>
                </div>
                
                <!-- Info Section -->
                <div class="info-section">
                    <div class="info-section-title">
                        <i class="fa fa-info-circle me-1"></i> ข้อมูลส่วนตัว
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fa fa-envelope"></i>
                        </div>
                        <div class="info-content">
                            <label>อีเมล</label>
                            <p><?php echo htmlspecialchars($user->Email); ?></p>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fa fa-clock-o"></i>
                        </div>
                        <div class="info-content">
                            <label>วันที่ลงทะเบียน</label>
                            <p><?php echo date('d/m/Y', strtotime($user->RegDate)); ?></p>
                        </div>
                    </div>
                    
                    <?php if($user->UpdationDate && $user->UpdationDate != '0000-00-00 00:00:00'): ?>
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fa fa-clock-o"></i>
                        </div>
                        <div class="info-content">
                            <label>อัปเดตล่าสุด</label>
                            <p><?php echo date('d/m/Y H:i', strtotime($user->UpdationDate)); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="change-password.php" class="btn btn-warning">
                        <i class="fa fa-key me-2"></i>เปลี่ยนรหัสผ่าน
                    </a>
                    <a href="my-bookings.php" class="btn btn-outline-primary">
                        <i class="fa fa-history me-2"></i>ประวัติการยืม
                    </a>
                </div>
                
                <!-- Note -->
                <div class="px-4 pb-4">
                    <div class="alert alert-light border mb-0" style="border-radius: 12px;">
                        <small class="text-muted">
                            <i class="fa fa-info-circle me-1 text-primary"></i>
                            หากต้องการแก้ไขข้อมูลส่วนตัว กรุณาติดต่อเจ้าหน้าที่
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('includes/footer.php'); ?>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
