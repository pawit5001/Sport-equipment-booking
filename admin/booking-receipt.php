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
    CONCAT(a.Name, ' ', a.Surname) as ReceiverName
    FROM tblbookings b 
    JOIN tblmembers m ON b.MemberId = m.id 
    LEFT JOIN tblmembers a ON b.ReturnedBy = a.id
    WHERE b.id = :id";
$query = $dbh->prepare($sql);
$query->execute([':id' => $bookingId]);
$booking = $query->fetch(PDO::FETCH_ASSOC);

if (!$booking) { 
    header('location:manage-issued-equipment.php'); 
    exit; 
}

// ดึงรายการอุปกรณ์
$sqlItems = "SELECT bd.*, e.EquipmentName, e.EquipmentCode,
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
switch ($booking['Status']) {
    case 'borrowed': $statusText = 'กำลังยืม'; break;
    case 'returned': $statusText = 'คืนแล้ว'; break;
    case 'partial': $statusText = 'คืนบางส่วน'; break;
    case 'overdue': $statusText = 'เกินกำหนด'; break;
    case 'cancelled': $statusText = 'ยกเลิก'; break;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>ใบเสร็จ #<?php echo htmlspecialchars($booking['BookingCode']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; margin: 0; }
            .receipt { border: none !important; box-shadow: none !important; }
        }
        body {
            background: #f3f4f6;
            font-family: 'Sarabun', sans-serif;
        }
        .receipt {
            max-width: 800px;
            margin: 30px auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .receipt-header {
            background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
            color: white;
            padding: 25px;
            text-align: center;
        }
        .receipt-header h2 {
            margin: 0;
            font-size: 1.5rem;
        }
        .receipt-header .booking-code {
            font-size: 1.8rem;
            font-weight: 700;
            margin-top: 5px;
        }
        .receipt-body {
            padding: 25px;
        }
        .info-section {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px dashed #e5e7eb;
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
            margin-bottom: 3px;
        }
        .info-value {
            font-weight: 600;
            color: #1e293b;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            background: #f1f5f9;
            padding: 12px;
            text-align: left;
            font-size: 0.85rem;
            color: #475569;
        }
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .items-table tr:last-child td {
            border-bottom: none;
        }
        .summary-box {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }
        .summary-row.total {
            border-top: 2px solid #cbd5e1;
            margin-top: 10px;
            padding-top: 15px;
            font-weight: 700;
            font-size: 1.1rem;
        }
        .fine-amount {
            color: #dc2626;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .status-borrowed { background: #dbeafe; color: #1e40af; }
        .status-returned { background: #dcfce7; color: #166534; }
        .status-partial { background: #fef3c7; color: #92400e; }
        .status-overdue { background: #fee2e2; color: #991b1b; }
        .print-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 15px 25px;
            font-size: 1rem;
            border-radius: 50px;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
        }
        .footer-note {
            text-align: center;
            color: #94a3b8;
            font-size: 0.85rem;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px dashed #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="receipt-header">
            <h2>🏆 E-Sports Equipment Center</h2>
            <div class="booking-code"><?php echo htmlspecialchars($booking['BookingCode']); ?></div>
            <div style="opacity: 0.8; margin-top: 5px;">ใบเสร็จการยืมอุปกรณ์</div>
        </div>
        
        <div class="receipt-body">
            <!-- Info Section -->
            <div class="info-section">
                <div class="info-item">
                    <div class="info-label">ผู้ยืม</div>
                    <div class="info-value"><?php echo htmlspecialchars($booking['Name'] . ' ' . $booking['Surname']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">รหัสนักศึกษา</div>
                    <div class="info-value"><?php echo htmlspecialchars($booking['StudentID']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">วันที่ยืม</div>
                    <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($booking['BookingDate'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">สถานะ</div>
                    <div class="info-value">
                        <span class="status-badge status-<?php echo $booking['Status']; ?>"><?php echo $statusText; ?></span>
                    </div>
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
            
            <!-- Items Table -->
            <h5 style="margin-bottom: 15px;">📦 รายการอุปกรณ์</h5>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>อุปกรณ์</th>
                        <th style="text-align: center;">จำนวน</th>
                        <th style="text-align: center;">กำหนดคืน</th>
                        <th style="text-align: center;">สถานะคืน</th>
                        <th style="text-align: right;">ค่าใช้จ่าย</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($item['EquipmentName']); ?></strong>
                            <div style="font-size: 0.8rem; color: #64748b;"><?php echo htmlspecialchars($item['EquipmentCode']); ?></div>
                        </td>
                        <td style="text-align: center;"><?php echo $item['Quantity']; ?></td>
                        <td style="text-align: center;">
                            <?php echo $item['DueDate'] ? date('d/m/Y', strtotime($item['DueDate'])) : '-'; ?>
                        </td>
                        <td style="text-align: center;">
                            <?php 
                            $normalReturn = ($item['QuantityReturned'] ?? 0) - ($item['DamagedQty'] ?? 0) - ($item['LostQty'] ?? 0);
                            $normalReturn = max(0, $normalReturn);
                            $hasReturn = false;
                            ?>
                            <?php if ($normalReturn > 0): $hasReturn = true; ?>
                            <div style="color: #22c55e;">ปกติ <?php echo $normalReturn; ?> ชิ้น</div>
                            <?php endif; ?>
                            <?php if (($item['DamagedQty'] ?? 0) > 0): $hasReturn = true; ?>
                            <div style="color: #f59e0b;">ชำรุด <?php echo $item['DamagedQty']; ?> ชิ้น</div>
                            <?php endif; ?>
                            <?php if (($item['LostQty'] ?? 0) > 0): $hasReturn = true; ?>
                            <div style="color: #ef4444;">หาย <?php echo $item['LostQty']; ?> ชิ้น</div>
                            <?php endif; ?>
                            <?php if (!$hasReturn): ?>-<?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <?php 
                            $itemFine = ($item['FineAmount'] ?? 0);
                            $itemComp = ($item['CompensationAmount'] ?? 0);
                            $itemTotal = $itemFine + $itemComp;
                            ?>
                            <?php if ($itemTotal > 0): ?>
                            <?php if ($itemFine > 0): ?>
                            <div style="font-size: 0.8rem; color: #f59e0b;">⏰ ฿<?php echo number_format($itemFine, 0); ?></div>
                            <?php endif; ?>
                            <?php if ($itemComp > 0): ?>
                            <div style="font-size: 0.8rem; color: #ef4444;">💸 ฿<?php echo number_format($itemComp, 0); ?></div>
                            <?php endif; ?>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Summary -->
            <div class="summary-box">
                <div class="summary-row">
                    <span>จำนวนยืมทั้งหมด</span>
                    <span><?php echo $totalItems; ?> ชิ้น</span>
                </div>
                <div class="summary-row">
                    <span>คืนแล้ว</span>
                    <span><?php echo $totalReturned; ?> ชิ้น<?php 
                    $returnDetails = [];
                    $normalCount = $totalReturned - $totalDamaged - $totalLost;
                    if ($normalCount > 0) $returnDetails[] = "ปกติ $normalCount";
                    if ($totalDamaged > 0) $returnDetails[] = "ชำรุด $totalDamaged";
                    if ($totalLost > 0) $returnDetails[] = "หาย $totalLost";
                    if (count($returnDetails) > 1 || ($totalDamaged > 0 || $totalLost > 0)): 
                    ?> (<?php echo implode(', ', $returnDetails); ?>)<?php endif; ?></span>
                </div>
                <div class="summary-row">
                    <span>คงเหลือ</span>
                    <span><?php echo $totalItems - $totalReturned; ?> ชิ้น</span>
                </div>
                <?php if ($totalFine > 0): ?>
                <div class="summary-row">
                    <span>⏰ ค่าปรับล่าช้า</span>
                    <span style="color: #f59e0b;">฿<?php echo number_format($totalFine, 0); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($totalCompensation > 0): ?>
                <div class="summary-row">
                    <span>💸 ค่าชดเชย (ชำรุด/สูญหาย)</span>
                    <span style="color: #ef4444;">฿<?php echo number_format($totalCompensation, 0); ?></span>
                </div>
                <?php endif; ?>
                <?php 
                $grandTotal = $totalFine + $totalCompensation;
                if ($grandTotal > 0): ?>
                <div class="summary-row total" style="font-size: 1.2rem;">
                    <span>💰 รวมทั้งหมด</span>
                    <span class="fine-amount">฿<?php echo number_format($grandTotal, 0); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($booking['Notes']): ?>
            <div style="margin-top: 20px; padding: 15px; background: #fef3c7; border-radius: 8px;">
                <strong>📝 หมายเหตุ:</strong> <?php echo nl2br(htmlspecialchars($booking['Notes'])); ?>
            </div>
            <?php endif; ?>
            
            <div class="footer-note">
                <p>🏆 E-Sports Equipment Center</p>
                <p>พิมพ์เมื่อ: <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Print Button -->
    <button class="btn btn-primary print-btn no-print" onclick="window.print()">
        🖨️ พิมพ์ใบเสร็จ
    </button>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
