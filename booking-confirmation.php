<?php
session_start();
error_reporting(0);
include('includes/config.php');

if (strlen($_SESSION['login']) == 0) {
    header('location:index.php');
    exit;
}

// ตรวจสอบว่ามี booking ID หรือไม่ (รองรับทั้งแบบเก่าและใหม่)
$mainBookingId = $_SESSION['last_booking_id'] ?? null;
$bookingCode = $_SESSION['last_booking_code'] ?? null;

// Fallback สำหรับระบบเก่า
if (!$mainBookingId && isset($_SESSION['last_booking_ids'])) {
    // ใช้ระบบเก่า
    $bookingIds = $_SESSION['last_booking_ids'];
    $memberId = $_SESSION['stdid'];
    
    $placeholders = implode(',', array_fill(0, count($bookingIds), '?'));
    $sql = "SELECT bd.id as DetailId, bd.BookingDate, bd.Quantity, bd.DueDate, bd.FinePerDay,
            e.EquipmentName, e.EquipmentCode, e.MaxBorrowDays, c.CategoryName
            FROM tblbookingdetails bd
            JOIN tblequipment e ON bd.EquipmentId = e.id
            LEFT JOIN tblcategory c ON e.CatId = c.id
            WHERE bd.id IN ($placeholders) AND bd.MemberId = ?
            ORDER BY bd.id ASC";
    
    $query = $dbh->prepare($sql);
    $params = array_merge($bookingIds, [$memberId]);
    $query->execute($params);
    $items = $query->fetchAll(PDO::FETCH_ASSOC);
    
    $bookingCode = '#' . implode(', #', array_map(function($id) { return str_pad($id, 6, '0', STR_PAD_LEFT); }, $bookingIds));
    $totalItems = array_sum(array_column($items, 'Quantity'));
    $bookingDate = $items[0]['BookingDate'] ?? date('Y-m-d H:i:s');
    
} elseif ($mainBookingId) {
    // ใช้ระบบใหม่
    $memberId = $_SESSION['stdid'];
    
    // ดึงข้อมูล booking หลัก
    $sqlBooking = "SELECT * FROM tblbookings WHERE id = :id AND MemberId = :memberId";
    $queryBooking = $dbh->prepare($sqlBooking);
    $queryBooking->execute([':id' => $mainBookingId, ':memberId' => $memberId]);
    $booking = $queryBooking->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        header('location:dashboard.php');
        exit;
    }
    
    // ดึงรายละเอียด
    $sql = "SELECT bd.id as DetailId, bd.BookingDate, bd.Quantity, bd.DueDate, bd.FinePerDay,
            e.EquipmentName, e.EquipmentCode, e.MaxBorrowDays, c.CategoryName
            FROM tblbookingdetails bd
            JOIN tblequipment e ON bd.EquipmentId = e.id
            LEFT JOIN tblcategory c ON e.CatId = c.id
            WHERE bd.BookingId = :bookingId
            ORDER BY bd.id ASC";
    
    $query = $dbh->prepare($sql);
    $query->execute([':bookingId' => $mainBookingId]);
    $items = $query->fetchAll(PDO::FETCH_ASSOC);
    
    $bookingCode = $booking['BookingCode'];
    $totalItems = $booking['TotalItems'];
    $bookingDate = $booking['BookingDate'];
    
} else {
    header('location:dashboard.php');
    exit;
}

// ดึงข้อมูลนักศึกษา
$sqlStudent = "SELECT * FROM tblmembers WHERE id = :memberId";
$queryStudent = $dbh->prepare($sqlStudent);
$queryStudent->bindParam(':memberId', $memberId, PDO::PARAM_INT);
$queryStudent->execute();
$studentInfo = $queryStudent->fetch(PDO::FETCH_ASSOC);

if (count($items) == 0) {
    header('location:dashboard.php');
    exit;
}

// คำนวณวันคืนล่าสุด
$latestDueDate = null;
foreach ($items as $item) {
    if ($item['DueDate'] && (!$latestDueDate || strtotime($item['DueDate']) > strtotime($latestDueDate))) {
        $latestDueDate = $item['DueDate'];
    }
}

// Flag ว่าแสดงผลแล้ว
$_SESSION['booking_shown'] = true;
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>E-Sports | ยืนยันการยืม</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <link href="assets/css/modern-style.css" rel="stylesheet" />
    <style>
        .confirmation-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }
        .success-header {
            text-align: center;
            padding-bottom: 2rem;
            border-bottom: 3px solid #10b981;
        }
        .success-icon {
            font-size: 4rem;
            color: #10b981;
            margin-bottom: 1rem;
        }
        .success-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }
        .success-subtitle {
            font-size: 1.1rem;
            color: #64748b;
        }
        .booking-number {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            padding: 1.5rem;
            border-radius: 0.75rem;
            margin: 1.5rem 0;
            text-align: center;
            border: 2px solid #10b981;
        }
        .booking-number-label {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 0.5rem;
        }
        .booking-number-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #10b981;
        }
        .info-section {
            background: #f8fafc;
            border-radius: 0.75rem;
            padding: 1.25rem;
            margin-bottom: 1rem;
        }
        .info-section h6 {
            color: #1e40af;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e2e8f0;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px dashed #e2e8f0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #64748b;
        }
        .info-value {
            font-weight: 600;
            color: #1e293b;
        }
        .item-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 0.75rem;
            transition: all 0.2s;
        }
        .item-card:hover {
            border-color: #3b82f6;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
        }
        .item-name {
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 0.25rem;
        }
        .item-code {
            font-size: 0.85rem;
            color: #64748b;
        }
        .item-qty {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
        }
        .due-date-badge {
            background: #fef3c7;
            color: #92400e;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .days-badge {
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .fine-badge {
            background: #fee2e2;
            color: #991b1b;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        .action-buttons .btn {
            flex: 1;
            padding: 0.75rem 1.5rem;
        }
        @media print {
            .no-print { display: none !important; }
            .confirmation-card { box-shadow: none; }
        }
    </style>
</head>
<body>
    <?php include('includes/header.php');?>
    <div class="content-wrapper">
        <div class="container py-4">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="confirmation-card">
                        <!-- Success Header -->
                        <div class="success-header">
                            <div class="success-icon">
                                <i class="fa fa-check-circle"></i>
                            </div>
                            <h2 class="success-title">ยืมสำเร็จ!</h2>
                            <p class="success-subtitle">ขอบคุณที่ใช้บริการ</p>
                        </div>
                        
                        <!-- Booking Number -->
                        <div class="booking-number">
                            <div class="booking-number-label">รหัสการยืม</div>
                            <div class="booking-number-value"><?php echo htmlspecialchars($bookingCode); ?></div>
                        </div>
                        
                        <!-- Booking Info -->
                        <div class="info-section">
                            <h6><i class="fa fa-info-circle me-2"></i>ข้อมูลการยืม</h6>
                            <div class="info-row">
                                <span class="info-label">รหัสนักศึกษา:</span>
                                <span class="info-value"><?php echo htmlspecialchars($studentInfo['StudentID']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">ชื่อ-สกุล:</span>
                                <span class="info-value"><?php echo htmlspecialchars($studentInfo['Name'] . ' ' . $studentInfo['Surname']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">วันที่ยืม:</span>
                                <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($bookingDate)); ?> น.</span>
                            </div>
                            <?php if ($latestDueDate): ?>
                            <div class="info-row">
                                <span class="info-label">กำหนดคืนล่าสุด:</span>
                                <span class="info-value text-warning">
                                    <i class="fa fa-calendar"></i> 
                                    <?php echo date('d/m/Y', strtotime($latestDueDate)); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            <div class="info-row">
                                <span class="info-label">จำนวนรวม:</span>
                                <span class="info-value"><?php echo $totalItems; ?> ชิ้น</span>
                            </div>
                        </div>
                        
                        <!-- Items List -->
                        <div class="info-section">
                            <h6><i class="fa fa-list me-2"></i>รายการอุปกรณ์</h6>
                            <?php 
                            $today = new DateTime();
                            foreach ($items as $item): 
                                $dueDate = $item['DueDate'] ? new DateTime($item['DueDate']) : null;
                                $borrowDays = $dueDate ? $today->diff($dueDate)->days : 0;
                                if ($dueDate && $dueDate < $today) {
                                    $borrowDays = -$borrowDays;
                                }
                            ?>
                            <div class="item-card">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="item-name"><?php echo htmlspecialchars($item['EquipmentName']); ?></div>
                                        <div class="item-code">
                                            รหัส: <?php echo htmlspecialchars($item['EquipmentCode']); ?> | <?php echo htmlspecialchars($item['CategoryName']); ?>
                                        </div>
                                        <div class="mt-2">
                                            <?php if ($item['DueDate']): ?>
                                            <span class="due-date-badge">
                                                <i class="fa fa-calendar"></i> กำหนดคืน: <?php echo date('d/m/Y', strtotime($item['DueDate'])); ?>
                                            </span>
                                            <span class="days-badge ms-1">
                                                <i class="fa fa-clock-o"></i> <?php echo $borrowDays; ?> วัน
                                            </span>
                                            <?php endif; ?>
                                            <?php if ($item['FinePerDay'] > 0): ?>
                                            <span class="fine-badge ms-1">
                                                <i class="fa fa-money"></i> ค่าปรับ ฿<?php echo number_format($item['FinePerDay'], 0); ?>/วัน
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="item-qty">
                                        <i class="fa fa-cube"></i> <?php echo $item['Quantity']; ?> ชิ้น
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Warning -->
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle me-2"></i>
                            <strong>โปรดทราบ:</strong> กรุณาคืนอุปกรณ์ตามวันกำหนด หากคืนล่าช้าจะมีค่าปรับตามอัตราที่ระบุของแต่ละรายการ
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="action-buttons no-print">
                            <a href="booking-receipt.php<?php echo $mainBookingId ? '?id='.$mainBookingId : ''; ?>" class="btn btn-primary">
                                <i class="fa fa-file-text me-1"></i> ดูใบเสร็จ
                            </a>
                            <a href="my-bookings.php" class="btn btn-outline-secondary">
                                <i class="fa fa-history me-1"></i> ประวัติการยืม
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-primary">
                                <i class="fa fa-home me-1"></i> กลับหน้าหลัก
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('includes/footer.php');?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
