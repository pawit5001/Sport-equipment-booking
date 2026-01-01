<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
error_reporting(0);
include('includes/config.php');

if (strlen($_SESSION['alogin']) == 0) {
    header('location:../adminlogin.php');
    exit;
}

$bookingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($bookingId <= 0) {
    header('location:manage-issued-equipment.php');
    exit;
}

// ดึงข้อมูล booking
$sql = "SELECT b.*, 
        m.Name, m.Surname, m.StudentID, m.Email,
        a.FullName as ReceiverName
        FROM tblbookings b 
        JOIN tblmembers m ON b.MemberId = m.id 
        LEFT JOIN admin a ON b.ReturnedBy = a.id
        WHERE b.id = :id";
$query = $dbh->prepare($sql);
$query->execute([':id' => $bookingId]);
$booking = $query->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    $_SESSION['admin_error'] = "ไม่พบรายการยืมนี้";
    header('location:manage-issued-equipment.php');
    exit;
}

// ดึงรายการอุปกรณ์
$sqlItems = "SELECT bd.*, e.EquipmentName, e.EquipmentCode, e.EquipmentImage,
                    bd.DamagedQty, bd.LostQty, bd.CompensationAmount, bd.DamageNote
             FROM tblbookingdetails bd 
             JOIN tblequipment e ON bd.EquipmentId = e.id 
             WHERE bd.BookingId = :bookingId";
$queryItems = $dbh->prepare($sqlItems);
$queryItems->execute([':bookingId' => $bookingId]);
$items = $queryItems->fetchAll(PDO::FETCH_ASSOC);

// คำนวณสรุป
$totalItems = 0;
$totalReturned = 0;
$totalFine = 0;
$totalCompensation = 0;
$totalDamaged = 0;
$totalLost = 0;
$today = new DateTime();

foreach ($items as $item) {
    $totalItems += $item['Quantity'];
    $totalReturned += ($item['QuantityReturned'] ?? 0);
    $totalFine += ($item['FineAmount'] ?? 0);
    $totalCompensation += ($item['CompensationAmount'] ?? 0);
    $totalDamaged += ($item['DamagedQty'] ?? 0);
    $totalLost += ($item['LostQty'] ?? 0);
}

// หาสถานะ
$statusText = 'รอดำเนินการ';
$statusClass = 'secondary';
switch ($booking['Status']) {
    case 'borrowed': $statusText = 'กำลังยืม'; $statusClass = 'primary'; break;
    case 'returned': $statusText = 'คืนแล้ว'; $statusClass = 'success'; break;
    case 'partial': $statusText = 'คืนบางส่วน'; $statusClass = 'warning'; break;
    case 'overdue': $statusText = 'เกินกำหนด'; $statusClass = 'danger'; break;
    case 'cancelled': $statusText = 'ยกเลิก'; $statusClass = 'secondary'; break;
}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>E-Sports | รายละเอียดใบยืม</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <link href="../assets/css/modern-style.css" rel="stylesheet" />
    <style>
        .detail-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .detail-header {
            background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
            color: white;
            padding: 20px;
        }
        .detail-body { padding: 20px; }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .info-item {
            padding: 10px;
            background: #f8fafc;
            border-radius: 8px;
        }
        .info-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .info-value {
            font-weight: 600;
            color: #1e293b;
        }
        
        .item-card {
            background: #f8fafc;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #e2e8f0;
        }
        .item-card.returned {
            background: #dcfce7;
            border-color: #86efac;
        }
        .item-card.partial {
            background: #fef3c7;
            border-color: #fcd34d;
        }
        
        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .summary-box {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 10px;
            padding: 20px;
        }
    </style>
</head>
<body>
    <?php include('includes/header.php');?>
    
    <div class="content-wrapper">
        <div class="container" style="max-width: 1000px;">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="manage-issued-equipment.php">📦 จัดการยืม/คืน</a></li>
                    <li class="breadcrumb-item active">รายละเอียดใบยืม</li>
                </ol>
            </nav>
            
            <!-- Main Card -->
            <div class="detail-card">
                <div class="detail-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h4 class="mb-1" style="color: white;">🎫 <?php echo htmlspecialchars($booking['BookingCode']); ?></h4>
                            <p class="mb-0" style="color: rgba(255,255,255,0.9);">ใบยืมอุปกรณ์</p>
                        </div>
                        <span class="badge bg-<?php echo $statusClass; ?> fs-6"><?php echo $statusText; ?></span>
                    </div>
                </div>
                
                <div class="detail-body">
                    <!-- Student Info -->
                    <h5 class="mb-3">👤 ข้อมูลผู้ยืม</h5>
                    <div class="info-grid mb-4">
                        <div class="info-item">
                            <div class="info-label">ชื่อ-นามสกุล</div>
                            <div class="info-value"><?php echo htmlspecialchars($booking['Name'] . ' ' . $booking['Surname']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">รหัสนักศึกษา</div>
                            <div class="info-value"><?php echo htmlspecialchars($booking['StudentID']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">อีเมล</div>
                            <div class="info-value"><?php echo htmlspecialchars($booking['Email']); ?></div>
                        </div>
                    </div>
                    
                    <!-- Booking Info -->
                    <h5 class="mb-3">📋 ข้อมูลการยืม</h5>
                    <div class="info-grid mb-4">
                        <div class="info-item">
                            <div class="info-label">วันที่ยืม</div>
                            <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($booking['BookingDate'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">จำนวนรายการ</div>
                            <div class="info-value"><?php echo $booking['TotalItems']; ?> รายการ (<?php echo $totalItems; ?> ชิ้น)</div>
                        </div>
                        <?php if ($booking['ReturnedAt']): ?>
                        <div class="info-item">
                            <div class="info-label">วันที่คืน</div>
                            <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($booking['ReturnedAt'])); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($booking['ReceiverName']): ?>
                        <div class="info-item">
                            <div class="info-label">ผู้รับคืน</div>
                            <div class="info-value"><?php echo htmlspecialchars($booking['ReceiverName']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($booking['Notes']): ?>
                    <div class="alert alert-info">
                        <strong>📝 หมายเหตุ:</strong> <?php echo nl2br(htmlspecialchars($booking['Notes'])); ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Items List -->
                    <h5 class="mb-3">📦 รายการอุปกรณ์</h5>
                    <?php foreach ($items as $item): 
                        $isFullyReturned = ($item['QuantityReturned'] ?? 0) >= $item['Quantity'];
                        $isPartialReturned = ($item['QuantityReturned'] ?? 0) > 0 && !$isFullyReturned;
                        $itemClass = $isFullyReturned ? 'returned' : ($isPartialReturned ? 'partial' : '');
                        
                        // คำนวณสถานะเกินกำหนด
                        $isOverdue = false;
                        $overdueDays = 0;
                        if ($item['DueDate'] && $item['ReturnStatus'] != 1) {
                            $dueDate = new DateTime($item['DueDate']);
                            if ($today > $dueDate) {
                                $isOverdue = true;
                                $overdueDays = $dueDate->diff($today)->days;
                            }
                        }
                    ?>
                    <div class="item-card <?php echo $itemClass; ?>">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <strong><?php echo htmlspecialchars($item['EquipmentName']); ?></strong>
                                <div class="text-muted small"><?php echo htmlspecialchars($item['EquipmentCode']); ?></div>
                            </div>
                            <div class="col-md-2 text-center">
                                <div class="info-label">ยืม</div>
                                <div class="info-value"><?php echo $item['Quantity']; ?></div>
                            </div>
                            <div class="col-md-2 text-center">
                                <div class="info-label">คืนแล้ว</div>
                                <div class="info-value text-success"><?php echo $item['QuantityReturned'] ?? 0; ?></div>
                            </div>
                            <div class="col-md-2 text-center">
                                <div class="info-label">กำหนดคืน</div>
                                <div class="info-value <?php echo $isOverdue ? 'text-danger' : ''; ?>">
                                    <?php echo $item['DueDate'] ? date('d/m/Y', strtotime($item['DueDate'])) : '-'; ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($item['FineAmount'] > 0 || $item['CompensationAmount'] > 0 || $item['DamagedQty'] > 0 || $item['LostQty'] > 0): ?>
                        <div class="mt-2 pt-2 border-top">
                            <?php if ($item['FineAmount'] > 0): ?>
                            <span class="text-warning me-3">⏰ ค่าปรับล่าช้า: ฿<?php echo number_format($item['FineAmount'], 0); ?></span>
                            <?php endif; ?>
                            <?php if ($item['DamagedQty'] > 0): ?>
                            <span class="badge bg-warning me-2">🟡 ชำรุด <?php echo $item['DamagedQty']; ?> ชิ้น</span>
                            <?php endif; ?>
                            <?php if ($item['LostQty'] > 0): ?>
                            <span class="badge bg-danger me-2">🔴 หาย <?php echo $item['LostQty']; ?> ชิ้น</span>
                            <?php endif; ?>
                            <?php if ($item['CompensationAmount'] > 0): ?>
                            <span class="text-danger">💸 ค่าชดเชย: ฿<?php echo number_format($item['CompensationAmount'], 0); ?></span>
                            <?php endif; ?>
                            <?php if ($item['DamageNote']): ?>
                            <div class="text-muted small mt-1">📝 <?php echo nl2br(htmlspecialchars($item['DamageNote'])); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($isOverdue): ?>
                        <div class="mt-2">
                            <span class="badge bg-danger">⚠️ เกินกำหนด <?php echo $overdueDays; ?> วัน</span>
                        </div>
                        <?php elseif ($isFullyReturned): ?>
                        <div class="mt-2">
                            <span class="badge bg-success">✅ คืนครบแล้ว</span>
                            <?php if ($item['ReturnDate']): ?>
                            <span class="text-muted small ms-2">เมื่อ <?php echo date('d/m/Y H:i', strtotime($item['ReturnDate'])); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php elseif ($isPartialReturned): ?>
                        <div class="mt-2">
                            <span class="badge bg-warning">⏳ คืนบางส่วน</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- Summary -->
                    <div class="summary-box mt-4">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="fs-4 fw-bold text-primary"><?php echo $totalItems; ?></div>
                                <div class="text-muted small">ยืมทั้งหมด</div>
                            </div>
                            <div class="col-md-3">
                                <div class="fs-4 fw-bold text-success"><?php echo $totalReturned; ?></div>
                                <div class="text-muted small">คืนแล้ว</div>
                            </div>
                            <div class="col-md-3">
                                <div class="fs-4 fw-bold <?php echo $totalFine > 0 ? 'text-warning' : 'text-secondary'; ?>">
                                    ฿<?php echo number_format($totalFine, 0); ?>
                                </div>
                                <div class="text-muted small">ค่าปรับล่าช้า</div>
                            </div>
                            <div class="col-md-3">
                                <div class="fs-4 fw-bold <?php echo $totalCompensation > 0 ? 'text-danger' : 'text-secondary'; ?>">
                                    ฿<?php echo number_format($totalCompensation, 0); ?>
                                </div>
                                <div class="text-muted small">ค่าชดเชย</div>
                            </div>
                        </div>
                        <?php if ($totalDamaged > 0 || $totalLost > 0): ?>
                        <div class="text-center mt-3 pt-3 border-top">
                            <?php if ($totalDamaged > 0): ?>
                            <span class="badge bg-warning me-2">🟡 ชำรุด <?php echo $totalDamaged; ?> ชิ้น</span>
                            <?php endif; ?>
                            <?php if ($totalLost > 0): ?>
                            <span class="badge bg-danger">🔴 สูญหาย <?php echo $totalLost; ?> ชิ้น</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php 
                        $grandTotal = $totalFine + $totalCompensation;
                        if ($grandTotal > 0): ?>
                        <div class="text-center mt-3 pt-3 border-top">
                            <div class="fs-3 fw-bold text-danger">รวมทั้งหมด: ฿<?php echo number_format($grandTotal, 0); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Actions -->
                    <div class="d-flex gap-2 mt-4">
                        <a href="manage-issued-equipment.php" class="btn btn-outline-secondary">
                            ← ย้อนกลับ
                        </a>
                        <?php if ($booking['Status'] != 'returned'): ?>
                        <a href="return-booking.php?id=<?php echo $bookingId; ?>" class="btn btn-success">
                            ✅ รับคืนอุปกรณ์
                        </a>
                        <?php else: ?>
                        <a href="return-booking.php?id=<?php echo $bookingId; ?>&edit=1" class="btn btn-warning">
                            ✏️ แก้ไขการรับคืน
                        </a>
                        <?php endif; ?>
                        <a href="booking-receipt.php?id=<?php echo $bookingId; ?>" class="btn btn-primary" target="_blank">
                            🖨️ พิมพ์ใบเสร็จ
                        </a>
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
