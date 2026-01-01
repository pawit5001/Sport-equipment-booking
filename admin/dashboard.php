<?php
session_start();
error_reporting(0);
include('includes/config.php');
if (strlen($_SESSION['alogin']) == 0) {
    header('location:../adminlogin.php');
    exit;
}

// ===== สถิติหลัก =====

// อุปกรณ์ทั้งหมด (รายการ)
$totalEquipment = $dbh->query("SELECT COUNT(*) FROM tblequipment")->fetchColumn();

// อุปกรณ์ที่ใช้งานได้ (รายการ)
$activeEquipment = $dbh->query("SELECT COUNT(*) FROM tblequipment WHERE IsActive = 1")->fetchColumn();

// จำนวนชิ้นทั้งหมด
$totalQuantity = $dbh->query("SELECT COALESCE(SUM(Quantity), 0) FROM tblequipment WHERE IsActive = 1")->fetchColumn();

// จำนวนที่ถูกยืมอยู่
$borrowedQuantity = $dbh->query("SELECT COALESCE(SUM(bd.Quantity - COALESCE(bd.QuantityReturned, 0)), 0) FROM tblbookingdetails bd JOIN tblbookings b ON bd.BookingId = b.id WHERE b.Status IN ('borrowed', 'partial')")->fetchColumn();

// พร้อมให้ยืม
$availableQuantity = $totalQuantity - $borrowedQuantity;

// หมวดหมู่
$totalCategories = $dbh->query("SELECT COUNT(*) FROM tblcategory")->fetchColumn();

// สมาชิก
$totalMembers = $dbh->query("SELECT COUNT(*) FROM tblmembers")->fetchColumn();

// ผู้รับผิดชอบ
$totalSuppliers = $dbh->query("SELECT COUNT(*) FROM tblsuppliers")->fetchColumn();

// ===== สถิติการยืม =====

// การยืมทั้งหมด (bookings)
$totalBookings = $dbh->query("SELECT COUNT(*) FROM tblbookings")->fetchColumn();

// กำลังยืม (borrowed + partial)
$borrowedCount = $dbh->query("SELECT COUNT(*) FROM tblbookings WHERE Status IN ('borrowed', 'partial')")->fetchColumn();

// เกินกำหนด (ตรวจสอบจาก DueDate จริง)
$realOverdueCount = $dbh->query("SELECT COUNT(DISTINCT b.id) 
                   FROM tblbookings b 
                   JOIN tblbookingdetails bd ON b.id = bd.BookingId 
                   WHERE b.Status IN ('borrowed', 'partial') 
                   AND bd.DueDate < CURDATE() 
                   AND bd.ReturnStatus != 1")->fetchColumn();

// คืนแล้ว
$returnedCount = $dbh->query("SELECT COUNT(*) FROM tblbookings WHERE Status = 'returned'")->fetchColumn();

// ===== สถิติการเงิน =====

// ค่าปรับรวม
$totalFine = $dbh->query("SELECT COALESCE(SUM(FineAmount), 0) FROM tblbookingdetails WHERE FineAmount > 0")->fetchColumn();

// ค่าชดเชยรวม
$totalCompensation = $dbh->query("SELECT COALESCE(SUM(CompensationAmount), 0) FROM tblbookingdetails WHERE CompensationAmount > 0")->fetchColumn();

// ===== สถิติอุปกรณ์ชำรุด/หาย =====
$totalDamaged = $dbh->query("SELECT COALESCE(SUM(DamagedQty), 0) FROM tblbookingdetails WHERE DamagedQty > 0")->fetchColumn();
$totalLost = $dbh->query("SELECT COALESCE(SUM(LostQty), 0) FROM tblbookingdetails WHERE LostQty > 0")->fetchColumn();

// ===== การยืมล่าสุด =====
$sqlRecent = "SELECT b.id, b.BookingCode, b.BookingDate, b.Status,
                     m.Name, m.Surname, m.StudentID,
                     (SELECT COUNT(*) FROM tblbookingdetails WHERE BookingId = b.id) as ItemCount,
                     (SELECT SUM(Quantity) FROM tblbookingdetails WHERE BookingId = b.id) as TotalQty,
                     (SELECT MAX(DueDate) FROM tblbookingdetails WHERE BookingId = b.id) as DueDate
              FROM tblbookings b
              JOIN tblmembers m ON b.MemberId = m.id
              ORDER BY b.BookingDate DESC
              LIMIT 5";
$recentBookings = $dbh->query($sqlRecent)->fetchAll(PDO::FETCH_OBJ);

// ===== อุปกรณ์ยอดนิยม =====
$sqlPopular = "SELECT e.EquipmentName, e.EquipmentCode, 
                      COUNT(bd.id) as BorrowCount,
                      SUM(bd.Quantity) as TotalQty
               FROM tblbookingdetails bd
               JOIN tblequipment e ON bd.EquipmentId = e.id
               GROUP BY bd.EquipmentId
               ORDER BY BorrowCount DESC
               LIMIT 5";
$popularEquipment = $dbh->query($sqlPopular)->fetchAll(PDO::FETCH_OBJ);

// ===== สมาชิกที่ยืมบ่อย =====
$sqlTopMembers = "SELECT m.Name, m.Surname, m.StudentID,
                         COUNT(b.id) as BookingCount
                  FROM tblbookings b
                  JOIN tblmembers m ON b.MemberId = m.id
                  GROUP BY b.MemberId
                  ORDER BY BookingCount DESC
                  LIMIT 5";
$topMembers = $dbh->query($sqlTopMembers)->fetchAll(PDO::FETCH_OBJ);

// ===== การยืมที่เกินกำหนด =====
$sqlOverdueList = "SELECT b.id, b.BookingCode, b.BookingDate, b.Status,
                          m.Name, m.Surname, m.StudentID,
                          (SELECT MAX(DueDate) FROM tblbookingdetails WHERE BookingId = b.id) as DueDate,
                          DATEDIFF(CURDATE(), (SELECT MAX(DueDate) FROM tblbookingdetails WHERE BookingId = b.id)) as OverdueDays
                   FROM tblbookings b
                   JOIN tblmembers m ON b.MemberId = m.id
                   WHERE b.Status IN ('borrowed', 'partial', 'overdue')
                   HAVING DueDate < CURDATE()
                   ORDER BY OverdueDays DESC
                   LIMIT 5";
$overdueList = $dbh->query($sqlOverdueList)->fetchAll(PDO::FETCH_OBJ);
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>E-Sports | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <link href="../assets/css/modern-style.css" rel="stylesheet" />
    <style>
    .content-wrapper { 
        margin-top: 30px !important; 
        padding-bottom: 30px;
    }
    
    .section-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .section-title i { color: #3b82f6; }
    
    /* Stat Cards */
    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 1.25rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        border: 1px solid #e2e8f0;
        transition: all 0.3s ease;
        height: 100%;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        border-color: #3b82f6;
    }
    .stat-card .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        margin-bottom: 0.75rem;
    }
    .stat-card .stat-number {
        font-size: 1.75rem;
        font-weight: 700;
        color: #1e293b;
        line-height: 1;
    }
    .stat-card .stat-label {
        font-size: 0.8rem;
        color: #64748b;
        margin-top: 0.25rem;
    }
    .stat-card .stat-link {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.75rem;
        color: #3b82f6;
        text-decoration: none;
        margin-top: 0.75rem;
    }
    .stat-card .stat-link:hover { color: #1e40af; }
    
    /* Color variants */
    .stat-card.primary .stat-icon { background: #dbeafe; color: #1e40af; }
    .stat-card.success .stat-icon { background: #dcfce7; color: #166534; }
    .stat-card.warning .stat-icon { background: #fef3c7; color: #92400e; }
    .stat-card.danger .stat-icon { background: #fee2e2; color: #991b1b; }
    .stat-card.info .stat-icon { background: #e0f2fe; color: #0369a1; }
    .stat-card.purple .stat-icon { background: #f3e8ff; color: #7c3aed; }
    .stat-card.orange .stat-icon { background: #ffedd5; color: #c2410c; }
    
    /* Quick Actions */
    .quick-action {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 0.875rem 1rem;
        background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
        color: white;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.3s;
    }
    .quick-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(30, 64, 175, 0.3);
        color: white;
    }
    .quick-action i { font-size: 1.1rem; }
    .quick-action.secondary { background: linear-gradient(135deg, #0891b2 0%, #06b6d4 100%); }
    .quick-action.success { background: linear-gradient(135deg, #059669 0%, #10b981 100%); }
    .quick-action.warning { background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%); }
    
    /* Info Cards */
    .info-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }
    .info-card .card-header {
        background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
        padding: 0.875rem 1rem;
        border-bottom: none;
        font-weight: 600;
        font-size: 0.9rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: white;
    }
    .info-card .card-header i { color: white !important; }
    .info-card .card-header .btn-outline-primary {
        color: white;
        border-color: rgba(255,255,255,0.5);
        font-size: 0.75rem;
    }
    .info-card .card-header .btn-outline-primary:hover {
        background: rgba(255,255,255,0.2);
        border-color: white;
    }
    .info-card .card-body { padding: 0; }
    
    /* Table Styles */
    .table-mini {
        margin: 0;
        font-size: 0.85rem;
    }
    .table-mini th {
        background: #f8fafc !important;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        color: #475569 !important;
        padding: 0.625rem 0.75rem;
        border-bottom: 2px solid #e2e8f0 !important;
    }
    .table-mini td {
        padding: 0.625rem 0.75rem;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .table-mini tr:last-child td { border-bottom: none; }
    .table-mini tr:hover { background: #f8fafc; }
    
    /* Status Badges */
    .status-badge {
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .status-borrowed { background: #dbeafe; color: #1e40af; }
    .status-returned { background: #dcfce7; color: #166534; }
    .status-partial { background: #fef3c7; color: #92400e; }
    .status-overdue { background: #fee2e2; color: #991b1b; }
    
    /* Progress bars */
    .mini-progress {
        height: 6px;
        border-radius: 3px;
        background: #e2e8f0;
        overflow: hidden;
    }
    .mini-progress .bar {
        height: 100%;
        border-radius: 3px;
    }
    
    /* Ranking */
    .rank-badge {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: 700;
        background: #f1f5f9;
        color: #64748b;
    }
    .rank-badge.gold { background: #fef3c7; color: #92400e; }
    .rank-badge.silver { background: #f1f5f9; color: #475569; }
    .rank-badge.bronze { background: #ffedd5; color: #c2410c; }
    
    /* Empty state */
    .empty-state {
        text-align: center;
        padding: 2rem;
        color: #94a3b8;
    }
    .empty-state i { font-size: 2rem; margin-bottom: 0.5rem; }
    
    /* Finance summary */
    .finance-summary {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
    .finance-item {
        padding: 1rem;
        border-radius: 10px;
        text-align: center;
    }
    .finance-item.fine { background: linear-gradient(135deg, #fef3c7, #fde68a); }
    .finance-item.comp { background: linear-gradient(135deg, #fee2e2, #fecaca); }
    .finance-item .amount {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
    }
    .finance-item .label {
        font-size: 0.75rem;
        color: #64748b;
    }
    
    @media (max-width: 768px) {
        .stat-card .stat-number { font-size: 1.5rem; }
        .finance-summary { grid-template-columns: 1fr; }
    }
    </style>
</head>
<body>
    <?php include('includes/header.php');?>

    <div class="content-wrapper">
        <div class="container" style="max-width: 1200px;">
            
            <!-- Section: สถิติหลัก -->
            <div class="section-title">
                <i class="fa fa-dashboard"></i> ภาพรวมระบบ
            </div>
            
            <div class="row g-3 mb-4">
                <!-- อุปกรณ์ -->
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="stat-card primary">
                        <div class="stat-icon"><i class="fa fa-cubes"></i></div>
                        <div class="stat-number"><?php echo $totalEquipment; ?></div>
                        <div class="stat-label">อุปกรณ์ทั้งหมด</div>
                        <div style="font-size: 0.7rem; color: #059669; margin-top: 4px;"><i class="fa fa-check-circle"></i> พร้อมยืม <?php echo $availableQuantity; ?>/<?php echo $totalQuantity; ?> ชิ้น</div>
                        <a href="manage-equipment.php" class="stat-link">ดูทั้งหมด <i class="fa fa-chevron-right"></i></a>
                    </div>
                </div>
                <!-- หมวดหมู่ -->
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="stat-card purple">
                        <div class="stat-icon"><i class="fa fa-folder"></i></div>
                        <div class="stat-number"><?php echo $totalCategories; ?></div>
                        <div class="stat-label">หมวดหมู่</div>
                        <a href="manage-categories.php" class="stat-link">ดูทั้งหมด <i class="fa fa-chevron-right"></i></a>
                    </div>
                </div>
                <!-- สมาชิก -->
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="stat-card info">
                        <div class="stat-icon"><i class="fa fa-users"></i></div>
                        <div class="stat-number"><?php echo $totalMembers; ?></div>
                        <div class="stat-label">นักศึกษาทั้งหมด</div>
                        <a href="reg-students.php" class="stat-link">ดูทั้งหมด <i class="fa fa-chevron-right"></i></a>
                    </div>
                </div>
                <!-- การยืมทั้งหมด -->
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="stat-card success">
                        <div class="stat-icon"><i class="fa fa-exchange"></i></div>
                        <div class="stat-number"><?php echo $totalBookings; ?></div>
                        <div class="stat-label">รายการยืมทั้งหมด</div>
                        <a href="manage-issued-equipment.php" class="stat-link">ดูทั้งหมด <i class="fa fa-chevron-right"></i></a>
                    </div>
                </div>
                <!-- กำลังยืม -->
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="stat-card warning">
                        <div class="stat-icon"><i class="fa fa-clock-o"></i></div>
                        <div class="stat-number"><?php echo $borrowedCount; ?></div>
                        <div class="stat-label">กำลังยืม</div>
                        <a href="manage-issued-equipment.php?filter=borrowed" class="stat-link">ดูรายการ <i class="fa fa-chevron-right"></i></a>
                    </div>
                </div>
                <!-- เกินกำหนด -->
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="stat-card danger">
                        <div class="stat-icon"><i class="fa fa-exclamation-triangle"></i></div>
                        <div class="stat-number"><?php echo $realOverdueCount; ?></div>
                        <div class="stat-label">เกินกำหนด</div>
                        <a href="manage-issued-equipment.php?filter=overdue" class="stat-link">ดูรายการ <i class="fa fa-chevron-right"></i></a>
                    </div>
                </div>
            </div>
            
            <!-- Section: ทางลัด -->
            <div class="section-title">
                <i class="fa fa-bolt"></i> ทางลัด
            </div>
            
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <a href="add-equipment.php" class="quick-action">
                        <i class="fa fa-plus-circle"></i> เพิ่มอุปกรณ์
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="manage-issued-equipment.php" class="quick-action secondary">
                        <i class="fa fa-exchange"></i> จัดการยืม/คืน
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="manage-equipment.php" class="quick-action success">
                        <i class="fa fa-cogs"></i> จัดการอุปกรณ์
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="reg-students.php" class="quick-action warning">
                        <i class="fa fa-user-plus"></i> จัดการสมาชิก
                    </a>
                </div>
            </div>
            
            <!-- Row: Finance + Damage Stats + Overdue -->
            <div class="row g-3 mb-4">
                <!-- Finance Summary -->
                <div class="col-md-6 col-lg-4">
                    <div class="info-card h-100">
                        <div class="card-header">
                            <span><i class="fa fa-money me-2"></i>สรุปรายรับ</span>
                        </div>
                        <div class="card-body p-3">
                            <div class="finance-summary">
                                <div class="finance-item fine">
                                    <div class="amount">฿<?php echo number_format($totalFine, 0); ?></div>
                                    <div class="label">⏰ ค่าปรับล่าช้า</div>
                                </div>
                                <div class="finance-item comp">
                                    <div class="amount">฿<?php echo number_format($totalCompensation, 0); ?></div>
                                    <div class="label">💸 ค่าชดเชย</div>
                                </div>
                            </div>
                            <div class="text-center mt-3 pt-3" style="border-top: 1px dashed #e2e8f0;">
                                <div style="font-size: 0.8rem; color: #64748b;">รวมทั้งหมด</div>
                                <div style="font-size: 1.5rem; font-weight: 700; color: #059669;">฿<?php echo number_format($totalFine + $totalCompensation, 0); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Equipment Issues -->
                <div class="col-md-6 col-lg-4">
                    <div class="info-card h-100">
                        <div class="card-header">
                            <span><i class="fa fa-warning me-2"></i>อุปกรณ์มีปัญหา</span>
                        </div>
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-around text-center">
                                <div>
                                    <div style="font-size: 2rem; font-weight: 700; color: #f59e0b;"><?php echo $totalDamaged; ?></div>
                                    <div style="font-size: 0.8rem; color: #64748b;"><i class="fa fa-wrench me-1"></i>ชำรุด</div>
                                </div>
                                <div style="border-left: 1px solid #e2e8f0;"></div>
                                <div>
                                    <div style="font-size: 2rem; font-weight: 700; color: #ef4444;"><?php echo $totalLost; ?></div>
                                    <div style="font-size: 0.8rem; color: #64748b;"><i class="fa fa-question-circle me-1"></i>สูญหาย</div>
                                </div>
                            </div>
                            <div class="mt-3 pt-3" style="border-top: 1px dashed #e2e8f0;">
                                <div class="d-flex justify-content-between mb-2">
                                    <span style="font-size: 0.8rem; color: #64748b;">คืนแล้ว/ยืมทั้งหมด</span>
                                    <span style="font-size: 0.8rem; font-weight: 600;"><?php echo $returnedCount; ?>/<?php echo $totalBookings; ?></span>
                                </div>
                                <div class="mini-progress">
                                    <div class="bar bg-success" style="width: <?php echo $totalBookings > 0 ? ($returnedCount / $totalBookings * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Overdue List -->
                <div class="col-md-12 col-lg-4">
                    <div class="info-card h-100">
                        <div class="card-header">
                            <span><i class="fa fa-exclamation-circle me-2 text-danger"></i>เกินกำหนด</span>
                            <?php if (count($overdueList) > 0): ?>
                            <span class="badge bg-danger"><?php echo count($overdueList); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if (count($overdueList) > 0): ?>
                            <table class="table table-mini">
                                <tbody>
                                    <?php foreach ($overdueList as $item): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; font-size: 0.8rem;"><?php echo htmlentities($item->Name . ' ' . $item->Surname); ?></div>
                                            <div style="font-size: 0.7rem; color: #64748b;"><?php echo htmlentities($item->BookingCode); ?></div>
                                        </td>
                                        <td class="text-end">
                                            <span class="badge bg-danger">เกิน <?php echo $item->OverdueDays; ?> วัน</span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <div class="empty-state">
                                <i class="fa fa-check-circle text-success"></i>
                                <div>ไม่มีการยืมเกินกำหนด</div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Row: Recent Bookings + Popular + Top Members -->
            <div class="row g-3">
                <!-- Recent Bookings -->
                <div class="col-lg-6">
                    <div class="info-card">
                        <div class="card-header">
                            <span><i class="fa fa-history me-2"></i>การยืมล่าสุด</span>
                            <a href="manage-issued-equipment.php" class="btn btn-sm btn-outline-primary">ดูทั้งหมด</a>
                        </div>
                        <div class="card-body">
                            <?php if (count($recentBookings) > 0): ?>
                            <table class="table table-mini">
                                <thead>
                                    <tr>
                                        <th>รหัส</th>
                                        <th>ผู้ยืม</th>
                                        <th>จำนวน</th>
                                        <th>สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentBookings as $booking): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; font-size: 0.8rem;"><?php echo htmlentities($booking->BookingCode); ?></div>
                                            <div style="font-size: 0.7rem; color: #64748b;"><?php echo date('d/m/Y', strtotime($booking->BookingDate)); ?></div>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.85rem;"><?php echo htmlentities($booking->Name . ' ' . $booking->Surname); ?></div>
                                            <div style="font-size: 0.7rem; color: #64748b;"><?php echo htmlentities($booking->StudentID); ?></div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark"><?php echo $booking->TotalQty ?? 0; ?> ชิ้น</span>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = 'borrowed';
                                            $statusText = 'กำลังยืม';
                                            switch ($booking->Status) {
                                                case 'returned': $statusClass = 'returned'; $statusText = 'คืนแล้ว'; break;
                                                case 'partial': $statusClass = 'partial'; $statusText = 'คืนบางส่วน'; break;
                                                case 'overdue': $statusClass = 'overdue'; $statusText = 'เกินกำหนด'; break;
                                            }
                                            ?>
                                            <span class="status-badge status-<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <div class="empty-state">
                                <i class="fa fa-inbox"></i>
                                <div>ยังไม่มีการยืม</div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Popular Equipment + Top Members -->
                <div class="col-lg-6">
                    <div class="row g-3">
                        <!-- Popular Equipment -->
                        <div class="col-12">
                            <div class="info-card">
                                <div class="card-header">
                                    <span><i class="fa fa-star me-2 text-warning"></i>อุปกรณ์ยอดนิยม</span>
                                </div>
                                <div class="card-body">
                                    <?php if (count($popularEquipment) > 0): ?>
                                    <table class="table table-mini">
                                        <tbody>
                                            <?php foreach ($popularEquipment as $index => $equip): ?>
                                            <tr>
                                                <td style="width: 40px;">
                                                    <span class="rank-badge <?php echo $index == 0 ? 'gold' : ($index == 1 ? 'silver' : ($index == 2 ? 'bronze' : '')); ?>"><?php echo $index + 1; ?></span>
                                                </td>
                                                <td>
                                                    <div style="font-weight: 600; font-size: 0.85rem;"><?php echo htmlentities($equip->EquipmentName); ?></div>
                                                    <div style="font-size: 0.7rem; color: #64748b;"><?php echo htmlentities($equip->EquipmentCode); ?></div>
                                                </td>
                                                <td class="text-end">
                                                    <span class="badge bg-primary"><?php echo $equip->BorrowCount; ?> ครั้ง</span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fa fa-bar-chart"></i>
                                        <div>ยังไม่มีข้อมูล</div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Top Members -->
                        <div class="col-12">
                            <div class="info-card">
                                <div class="card-header">
                                    <span><i class="fa fa-trophy me-2 text-warning"></i>นักศึกษาที่ยืมบ่อย</span>
                                </div>
                                <div class="card-body">
                                    <?php if (count($topMembers) > 0): ?>
                                    <table class="table table-mini">
                                        <tbody>
                                            <?php foreach ($topMembers as $index => $member): ?>
                                            <tr>
                                                <td style="width: 40px;">
                                                    <span class="rank-badge <?php echo $index == 0 ? 'gold' : ($index == 1 ? 'silver' : ($index == 2 ? 'bronze' : '')); ?>"><?php echo $index + 1; ?></span>
                                                </td>
                                                <td>
                                                    <div style="font-weight: 600; font-size: 0.85rem;"><?php echo htmlentities($member->Name . ' ' . $member->Surname); ?></div>
                                                    <div style="font-size: 0.7rem; color: #64748b;"><?php echo htmlentities($member->StudentID); ?></div>
                                                </td>
                                                <td class="text-end">
                                                    <span class="badge bg-success"><?php echo $member->BookingCount; ?> ครั้ง</span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fa fa-users"></i>
                                        <div>ยังไม่มีข้อมูล</div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>

    <?php include('includes/footer.php');?>
    
    <script src="assets/js/jquery-1.10.2.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>