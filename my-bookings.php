<?php
session_start();
error_reporting(0);
include('includes/config.php');

if (strlen($_SESSION['login']) == 0) {
    header('location:index.php');
    exit;
}

$memberId = $_SESSION['stdid'];

// ดึงประวัติการยืม (ใช้ tblbookings)
$sql = "SELECT b.*, 
        (SELECT COUNT(*) FROM tblbookingdetails WHERE BookingId = b.id) as ItemCount,
        (SELECT SUM(Quantity) FROM tblbookingdetails WHERE BookingId = b.id) as TotalQty,
        (SELECT MIN(DueDate) FROM tblbookingdetails WHERE BookingId = b.id) as EarliestDue,
        (SELECT MAX(DueDate) FROM tblbookingdetails WHERE BookingId = b.id) as LatestDue
        FROM tblbookings b
        WHERE b.MemberId = :memberId
        ORDER BY b.BookingDate DESC";

$query = $dbh->prepare($sql);
$query->bindParam(':memberId', $memberId, PDO::PARAM_INT);
$query->execute();
$bookings = $query->fetchAll(PDO::FETCH_ASSOC);

// คำนวณค่าปรับสำหรับแต่ละ booking
$today = new DateTime();
foreach ($bookings as &$booking) {
    $booking['totalFine'] = 0;
    $booking['totalCompensation'] = 0;
    $booking['totalReturned'] = 0;
    $booking['totalDamaged'] = 0;
    $booking['totalLost'] = 0;
    
    // ดึงรายละเอียดเพื่อคำนวณค่าปรับและค่าชดเชย
    $sqlDetails = "SELECT bd.*, e.EquipmentName, e.EquipmentCode FROM tblbookingdetails bd 
                   JOIN tblequipment e ON bd.EquipmentId = e.id 
                   WHERE bd.BookingId = :bookingId";
    $queryDetails = $dbh->prepare($sqlDetails);
    $queryDetails->execute([':bookingId' => $booking['id']]);
    $details = $queryDetails->fetchAll(PDO::FETCH_ASSOC);
    
    $booking['items'] = $details;
    
    foreach ($details as $detail) {
        // ค่าชดเชยจาก database
        $booking['totalCompensation'] += floatval($detail['CompensationAmount'] ?? 0);
        $booking['totalReturned'] += intval($detail['QuantityReturned'] ?? 0);
        $booking['totalDamaged'] += intval($detail['DamagedQty'] ?? 0);
        $booking['totalLost'] += intval($detail['LostQty'] ?? 0);
        
        // ค่าปรับจาก database หรือคำนวณ
        if (floatval($detail['FineAmount'] ?? 0) > 0) {
            $booking['totalFine'] += floatval($detail['FineAmount']);
        } elseif ($detail['DueDate'] && $detail['ReturnStatus'] != 1) {
            $dueDate = new DateTime($detail['DueDate']);
            if ($today > $dueDate) {
                $overdueDays = $dueDate->diff($today)->days;
                $booking['totalFine'] += $overdueDays * ($detail['FinePerDay'] ?? 0) * $detail['Quantity'];
            }
        }
    }
    
    // อัพเดทสถานะเป็น overdue ถ้าเกินกำหนด
    if ($booking['totalFine'] > 0 && $booking['Status'] == 'borrowed') {
        $booking['Status'] = 'overdue';
    }
}
unset($booking);
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>E-Sports | ประวัติการยืม</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <link href="assets/css/modern-style.css" rel="stylesheet" />
    <style>
        .status-badge {
            padding: 0.4rem 0.85rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-borrowed { background: #dbeafe; color: #1e40af; }
        .status-returned { background: #dcfce7; color: #166534; }
        .status-partial { background: #fef3c7; color: #92400e; }
        .status-overdue { background: #fee2e2; color: #991b1b; }
        .status-cancelled { background: #f1f5f9; color: #64748b; }
        
        .booking-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 16px;
            overflow: hidden;
            transition: all 0.3s;
            border: 1px solid #e2e8f0;
        }
        .booking-card:hover {
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
            transform: translateY(-2px);
            border-color: #3b82f6;
        }
        .booking-card.overdue {
            border-color: #ef4444;
        }
        .booking-header {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        .booking-code {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e40af;
        }
        .booking-body { padding: 20px; }
        .booking-info { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); 
            gap: 16px; 
            margin-bottom: 15px;
        }
        .info-item label { 
            font-size: 0.75rem; 
            color: #64748b; 
            display: block; 
            margin-bottom: 4px; 
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-item span { font-weight: 600; color: #1e293b; }
        .info-item span.text-danger { color: #ef4444 !important; }
        
        /* Item Table - แบบ Admin */
        .item-table {
            width: 100%;
            margin-top: 10px;
            font-size: 0.85rem;
            border-collapse: collapse;
        }
        .item-table th {
            background: #f1f5f9;
            padding: 10px 12px;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 600;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
        }
        .item-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .item-table tr:last-child td { border-bottom: none; }
        .item-table tr:hover { background: #f8fafc; }
        
        .item-code {
            font-family: 'Consolas', monospace;
            font-size: 0.8rem;
            color: #64748b;
        }
        .item-name {
            font-weight: 600;
            color: #1e293b;
        }
        .qty-badge {
            background: #dbeafe;
            color: #1e40af;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .due-date {
            font-size: 0.85rem;
        }
        .item-status-returned { color: #22c55e; font-weight: 500; }
        .item-status-pending { color: #f59e0b; font-weight: 500; }
        .item-status-overdue { color: #ef4444; font-weight: 500; }
        
        .booking-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #e2e8f0;
        }
        
        .fine-badge {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .due-soon {
            background: #fef3c7;
            color: #92400e;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { font-size: 64px; color: #cbd5e1; margin-bottom: 20px; }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .filter-tab {
            padding: 8px 16px;
            border-radius: 20px;
            border: 2px solid #e2e8f0;
            background: white;
            color: #64748b;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        .filter-tab:hover {
            border-color: #3b82f6;
            color: #3b82f6;
        }
        .filter-tab.active {
            background: #1e40af;
            border-color: #1e40af;
            color: white;
        }
        
        /* Sorting Styles */
        .sort-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .sort-label {
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .sort-btn {
            padding: 6px 14px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: white;
            color: #64748b;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .sort-btn:hover {
            border-color: #3b82f6;
            color: #3b82f6;
        }
        .sort-btn.active {
            background: #eff6ff;
            border-color: #3b82f6;
            color: #1e40af;
        }
        .sort-btn i.sort-icon {
            font-size: 0.7rem;
            transition: transform 0.2s;
        }
        .sort-btn.desc i.sort-icon {
            transform: rotate(180deg);
        }
        
        /* Pagination Styles */
        .pagination-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding: 15px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            flex-wrap: wrap;
            gap: 15px;
        }
        .pagination-info {
            color: #64748b;
            font-size: 0.9rem;
        }
        .pagination-info strong {
            color: #1e40af;
        }
        .pagination-pages {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        .page-btn {
            min-width: 36px;
            height: 36px;
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 8px;
            color: #64748b;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .page-btn:hover:not(.disabled) {
            border-color: #3b82f6;
            color: #3b82f6;
        }
        .page-btn.active {
            background: #1e40af;
            border-color: #1e40af;
            color: white;
        }
        .page-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .page-numbers {
            display: flex;
            flex-direction: row;
            gap: 5px;
        }
        .pagination-buttons {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 5px;
        }
        .per-page-select {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .per-page-select label {
            color: #64748b;
            font-size: 0.85rem;
        }
        .per-page-select select {
            padding: 6px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <?php include('includes/header.php'); ?>
    
    <div class="content-wrapper">
        <div class="container py-4">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="fa fa-history me-2"></i>ประวัติการยืม</h4>
                    <p class="text-muted mb-0">ดูรายการยืมทั้งหมดของคุณ</p>
                </div>
                <a href="dashboard.php" class="btn btn-primary">
                    <i class="fa fa-plus me-1"></i> ยืมอุปกรณ์ใหม่
                </a>
            </div>
            
            <?php if (empty($bookings)): ?>
            <!-- Empty State -->
            <div class="card">
                <div class="card-body empty-state">
                    <i class="fa fa-inbox"></i>
                    <h5>ยังไม่มีประวัติการยืม</h5>
                    <p class="text-muted">คุณยังไม่เคยยืมอุปกรณ์ใดๆ</p>
                    <a href="dashboard.php" class="btn btn-primary mt-3">
                        <i class="fa fa-search me-1"></i> เริ่มยืมอุปกรณ์
                    </a>
                </div>
            </div>
            <?php else: ?>
            
            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <div class="filter-tab active" data-filter="all">
                    <i class="fa fa-list me-1"></i> ทั้งหมด (<?php echo count($bookings); ?>)
                </div>
                <div class="filter-tab" data-filter="borrowed">
                    <i class="fa fa-clock-o me-1"></i> กำลังยืม
                </div>
                <div class="filter-tab" data-filter="overdue">
                    <i class="fa fa-exclamation-triangle me-1"></i> เกินกำหนด
                </div>
                <div class="filter-tab" data-filter="returned">
                    <i class="fa fa-check me-1"></i> คืนแล้ว
                </div>
            </div>
            
            <!-- Sorting Controls -->
            <div class="sort-controls">
                <span class="sort-label"><i class="fa fa-sort me-1"></i>เรียงตาม:</span>
                <button class="sort-btn active" data-sort="date" data-order="desc">
                    <i class="fa fa-calendar"></i> วันที่ยืม
                    <i class="fa fa-arrow-up sort-icon"></i>
                </button>
                <button class="sort-btn" data-sort="due" data-order="asc">
                    <i class="fa fa-clock-o"></i> กำหนดคืน
                    <i class="fa fa-arrow-up sort-icon"></i>
                </button>
                <button class="sort-btn" data-sort="code" data-order="asc">
                    <i class="fa fa-hashtag"></i> รหัส
                    <i class="fa fa-arrow-up sort-icon"></i>
                </button>
                <button class="sort-btn" data-sort="items" data-order="desc">
                    <i class="fa fa-cubes"></i> จำนวน
                    <i class="fa fa-arrow-up sort-icon"></i>
                </button>
            </div>
            
            <!-- Bookings List -->
            <div id="bookingsList">
                <?php foreach ($bookings as $booking): 
                    $statusClass = $booking['Status'];
                    $statusText = 'กำลังยืม';
                    switch ($booking['Status']) {
                        case 'borrowed': $statusText = 'กำลังยืม'; break;
                        case 'returned': $statusText = 'คืนแล้ว'; break;
                        case 'partial': $statusText = 'คืนบางส่วน'; break;
                        case 'overdue': $statusText = 'เกินกำหนด'; break;
                        case 'cancelled': $statusText = 'ยกเลิก'; break;
                    }
                    
                    $bookingDateObj = new DateTime($booking['BookingDate']);
                    $latestDue = $booking['LatestDue'] ? new DateTime($booking['LatestDue']) : null;
                    $totalBorrowDays = $latestDue ? $bookingDateObj->diff($latestDue)->days : 0;
                    
                    // คำนวณวันที่เหลือ
                    $daysLeft = null;
                    if ($latestDue && $booking['Status'] != 'returned') {
                        $daysLeft = $today->diff($latestDue);
                        $daysLeft = $daysLeft->invert ? -$daysLeft->days : $daysLeft->days;
                    }
                ?>
                <div class="booking-card <?php echo $statusClass == 'overdue' ? 'overdue' : ''; ?>" 
                     data-status="<?php echo $statusClass; ?>"
                     data-date="<?php echo $booking['BookingDate']; ?>"
                     data-due="<?php echo $booking['LatestDue'] ?? '9999-12-31'; ?>"
                     data-code="<?php echo $booking['BookingCode']; ?>"
                     data-items="<?php echo $booking['TotalQty']; ?>">
                    <div class="booking-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <span class="booking-code">
                                <i class="fa fa-ticket me-1"></i> <?php echo htmlspecialchars($booking['BookingCode']); ?>
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <?php if ($booking['totalFine'] > 0): ?>
                            <span class="fine-badge">
                                <i class="fa fa-clock-o me-1"></i> ค่าปรับ ฿<?php echo number_format($booking['totalFine'], 0); ?>
                            </span>
                            <?php endif; ?>
                            
                            <?php if ($booking['totalCompensation'] > 0): ?>
                            <span class="fine-badge" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                                <i class="fa fa-exclamation-circle me-1"></i> ค่าชดเชย ฿<?php echo number_format($booking['totalCompensation'], 0); ?>
                            </span>
                            <?php endif; ?>
                            
                            <?php if ($daysLeft !== null && $daysLeft > 0 && $daysLeft <= 3 && $booking['Status'] == 'borrowed'): ?>
                            <span class="due-soon">
                                <i class="fa fa-clock-o me-1"></i> เหลือ <?php echo $daysLeft; ?> วัน
                            </span>
                            <?php endif; ?>
                            
                            <span class="status-badge status-<?php echo $statusClass; ?>">
                                <?php echo $statusText; ?>
                            </span>
                        </div>
                    </div>
                    <div class="booking-body">
                        <div class="booking-info">
                            <div class="info-item">
                                <label><i class="fa fa-calendar me-1"></i> วันที่ยืม</label>
                                <span><?php echo date('d/m/Y H:i', strtotime($booking['BookingDate'])); ?></span>
                            </div>
                            <div class="info-item">
                                <label><i class="fa fa-calendar-check-o me-1"></i> กำหนดคืน</label>
                                <span class="<?php echo ($daysLeft !== null && $daysLeft < 0) ? 'text-danger' : ''; ?>">
                                    <?php echo $latestDue ? $latestDue->format('d/m/Y') : '-'; ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <label><i class="fa fa-clock-o me-1"></i> ระยะเวลา</label>
                                <span><?php echo $totalBorrowDays; ?> วัน</span>
                            </div>
                            <div class="info-item">
                                <label><i class="fa fa-cubes me-1"></i> จำนวน</label>
                                <span><?php echo $booking['ItemCount']; ?> รายการ (<?php echo $booking['TotalQty']; ?> ชิ้น)</span>
                            </div>
                            <?php if ($booking['totalReturned'] > 0): ?>
                            <div class="info-item">
                                <label><i class="fa fa-undo me-1"></i> คืนแล้ว</label>
                                <span>
                                    <?php echo $booking['totalReturned']; ?>/<?php echo $booking['TotalQty']; ?> ชิ้น
                                    <?php if ($booking['totalDamaged'] > 0 || $booking['totalLost'] > 0): ?>
                                    <br><small class="text-muted">
                                        <?php 
                                        $parts = [];
                                        if ($booking['totalDamaged'] > 0) $parts[] = "ชำรุด {$booking['totalDamaged']}";
                                        if ($booking['totalLost'] > 0) $parts[] = "หาย {$booking['totalLost']}";
                                        echo implode(', ', $parts);
                                        ?>
                                    </small>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Item Table -->
                        <div style="overflow-x: auto; margin-top: 12px;">
                            <table class="item-table">
                                <thead>
                                    <tr>
                                        <th style="width: 15%">รหัส</th>
                                        <th style="width: 30%">อุปกรณ์</th>
                                        <th class="text-center" style="width: 10%">จำนวน</th>
                                        <th class="text-center" style="width: 20%">กำหนดคืน</th>
                                        <th class="text-center" style="width: 25%">สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $todayForItem = new DateTime();
                                    foreach ($booking['items'] as $item): 
                                        $itemDue = $item['DueDate'] ? new DateTime($item['DueDate']) : null;
                                        $isOverdue = $itemDue && $todayForItem > $itemDue && $item['ReturnStatus'] != 1;
                                        $isReturned = $item['ReturnStatus'] == 1;
                                        $returned = intval($item['QuantityReturned'] ?? 0);
                                        $damaged = intval($item['DamagedQty'] ?? 0);
                                        $lost = intval($item['LostQty'] ?? 0);
                                    ?>
                                    <tr>
                                        <td><span class="item-code"><?php echo htmlspecialchars($item['EquipmentCode']); ?></span></td>
                                        <td><span class="item-name"><?php echo htmlspecialchars($item['EquipmentName']); ?></span></td>
                                        <td class="text-center"><span class="qty-badge"><?php echo $item['Quantity']; ?></span></td>
                                        <td class="text-center">
                                            <span class="due-date <?php echo $isOverdue ? 'text-danger' : ''; ?>">
                                                <?php echo $itemDue ? $itemDue->format('d/m/Y') : '-'; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($isReturned): ?>
                                                <span class="item-status-returned"><i class="fa fa-check-circle me-1"></i>คืนแล้ว</span>
                                                <?php if ($damaged > 0 || $lost > 0): ?>
                                                <br><small class="text-muted">
                                                    <?php 
                                                    $parts = [];
                                                    if ($damaged > 0) $parts[] = "ชำรุด {$damaged}";
                                                    if ($lost > 0) $parts[] = "หาย {$lost}";
                                                    echo implode(', ', $parts);
                                                    ?>
                                                </small>
                                                <?php endif; ?>
                                            <?php elseif ($returned > 0): ?>
                                                <span class="item-status-pending"><i class="fa fa-clock-o me-1"></i>คืนบางส่วน (<?php echo $returned; ?>/<?php echo $item['Quantity']; ?>)</span>
                                            <?php elseif ($isOverdue): ?>
                                                <span class="item-status-overdue"><i class="fa fa-exclamation-circle me-1"></i>เกินกำหนด</span>
                                            <?php else: ?>
                                                <span class="item-status-pending"><i class="fa fa-clock-o me-1"></i>กำลังยืม</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="booking-actions">
                            <a href="booking-receipt.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fa fa-file-text me-1"></i> ดูใบเสร็จ
                            </a>
                            <?php if ($booking['Status'] == 'borrowed' || $booking['Status'] == 'overdue'): ?>
                            <span class="btn btn-sm btn-outline-secondary disabled">
                                <i class="fa fa-info-circle me-1"></i> กรุณานำอุปกรณ์มาคืนที่เคาน์เตอร์
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination Controls -->
            <div class="pagination-controls">
                <div class="pagination-info">
                    แสดง <span id="showFrom">1</span>-<span id="showTo">10</span> จาก <span id="totalItems"><?php echo count($bookings); ?></span> รายการ
                </div>
                <div class="pagination-buttons">
                    <button type="button" class="page-btn" id="prevPage" disabled>
                        <i class="fa fa-chevron-left"></i>
                    </button>
                    <div class="page-numbers" id="pageNumbers">
                        <!-- Generated by JavaScript -->
                    </div>
                    <button type="button" class="page-btn" id="nextPage">
                        <i class="fa fa-chevron-right"></i>
                    </button>
                </div>
                <div class="per-page-select">
                    <label>แสดง:</label>
                    <select id="perPage">
                        <option value="5">5</option>
                        <option value="10" selected>10</option>
                        <option value="20">20</option>
                        <option value="all">ทั้งหมด</option>
                    </select>
                    <span>รายการ</span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include('includes/footer.php'); ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // Pagination variables
        var perPage = 10;
        var currentPage = 1;
        var totalItems = 0;
        var totalPages = 1;
        
        // Initialize pagination
        function initPagination() {
            var $visibleCards = getVisibleCards();
            totalItems = $visibleCards.length;
            
            if (perPage === 'all' || perPage >= totalItems) {
                totalPages = 1;
            } else {
                totalPages = Math.ceil(totalItems / perPage);
            }
            
            currentPage = 1;
            renderPagination();
            showPage(currentPage);
        }
        
        // Get visible cards (after filter)
        function getVisibleCards() {
            var activeFilter = $('.filter-tab.active').data('filter');
            if (activeFilter === 'all') {
                return $('#bookingsList .booking-card');
            } else {
                return $('#bookingsList .booking-card[data-status="' + activeFilter + '"]');
            }
        }
        
        // Show specific page
        function showPage(page) {
            var $allCards = $('#bookingsList .booking-card');
            var $visibleCards = getVisibleCards();
            
            // Hide all cards first
            $allCards.hide();
            
            if (perPage === 'all') {
                $visibleCards.show();
                $('#showFrom').text(1);
                $('#showTo').text($visibleCards.length);
            } else {
                var start = (page - 1) * perPage;
                var end = start + perPage;
                
                $visibleCards.each(function(index) {
                    if (index >= start && index < end) {
                        $(this).show();
                    }
                });
                
                $('#showFrom').text($visibleCards.length > 0 ? start + 1 : 0);
                $('#showTo').text(Math.min(end, $visibleCards.length));
            }
            
            $('#totalItems').text($visibleCards.length);
            
            // Update button states
            $('#prevPage').prop('disabled', currentPage <= 1);
            $('#nextPage').prop('disabled', currentPage >= totalPages || totalPages <= 1);
        }
        
        // Render pagination buttons
        function renderPagination() {
            var $pageNumbers = $('#pageNumbers');
            $pageNumbers.empty();
            
            if (totalPages <= 1) {
                $pageNumbers.append('<button class="page-btn active">1</button>');
                return;
            }
            
            // Show max 5 page buttons
            var startPage = Math.max(1, currentPage - 2);
            var endPage = Math.min(totalPages, startPage + 4);
            
            if (endPage - startPage < 4) {
                startPage = Math.max(1, endPage - 4);
            }
            
            for (var i = startPage; i <= endPage; i++) {
                var activeClass = i === currentPage ? 'active' : '';
                $pageNumbers.append('<button class="page-btn ' + activeClass + '" data-page="' + i + '">' + i + '</button>');
            }
        }
        
        // Page number click
        $(document).on('click', '#pageNumbers .page-btn', function() {
            currentPage = parseInt($(this).data('page'));
            renderPagination();
            showPage(currentPage);
        });
        
        // Previous page
        $('#prevPage').click(function() {
            if (currentPage > 1) {
                currentPage--;
                renderPagination();
                showPage(currentPage);
            }
        });
        
        // Next page
        $('#nextPage').click(function() {
            if (currentPage < totalPages) {
                currentPage++;
                renderPagination();
                showPage(currentPage);
            }
        });
        
        // Per page change
        $('#perPage').change(function() {
            var val = $(this).val();
            perPage = val === 'all' ? 'all' : parseInt(val);
            initPagination();
        });
        
        // Initialize on page load
        initPagination();
        
        // Filter tabs
        $('.filter-tab').click(function() {
            var filter = $(this).data('filter');
            
            $('.filter-tab').removeClass('active');
            $(this).addClass('active');
            
            // Re-initialize pagination after filter change
            initPagination();
        });
        
        // Sorting functionality
        $('.sort-btn').click(function() {
            var sortBy = $(this).data('sort');
            var currentOrder = $(this).data('order');
            
            // Toggle order if same button clicked
            if ($(this).hasClass('active')) {
                currentOrder = currentOrder === 'asc' ? 'desc' : 'asc';
                $(this).data('order', currentOrder);
            }
            
            // Update UI
            $('.sort-btn').removeClass('active desc');
            $(this).addClass('active');
            if (currentOrder === 'desc') {
                $(this).addClass('desc');
            }
            
            // Sort cards
            var $container = $('#bookingsList');
            var $cards = $container.children('.booking-card').get();
            
            $cards.sort(function(a, b) {
                var valA, valB;
                
                switch(sortBy) {
                    case 'date':
                        valA = $(a).data('date');
                        valB = $(b).data('date');
                        break;
                    case 'due':
                        valA = $(a).data('due');
                        valB = $(b).data('due');
                        break;
                    case 'code':
                        valA = $(a).data('code');
                        valB = $(b).data('code');
                        break;
                    case 'items':
                        valA = parseInt($(a).data('items')) || 0;
                        valB = parseInt($(b).data('items')) || 0;
                        break;
                }
                
                if (sortBy === 'items') {
                    return currentOrder === 'asc' ? valA - valB : valB - valA;
                } else {
                    if (currentOrder === 'asc') {
                        return valA > valB ? 1 : -1;
                    } else {
                        return valA < valB ? 1 : -1;
                    }
                }
            });
            
            $.each($cards, function(idx, card) {
                $container.append(card);
            });
            
            // Re-initialize pagination after sorting
            initPagination();
        });
    });
    </script>
</body>
</html>
