<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
error_reporting(0);
include('includes/config.php');

if (strlen($_SESSION['alogin']) == 0) {
    header('location:../adminlogin.php');
    exit;
}

$bookingId = intval($_GET['id']);
$isEditMode = isset($_GET['edit']) && $_GET['edit'] == '1';

// ดึงค่ากลางจาก booking_settings
$defaultDamageRate = 50;  // 50% default
$defaultLostRate = 100;   // 100% default
$defaultFinePerDay = 50;
try {
    $settingStmt = $dbh->query("SELECT LateFeesPerDay, DamageFeesRate, LostItemFeesRate FROM tblbooking_settings LIMIT 1");
    $settings = $settingStmt ? $settingStmt->fetch(PDO::FETCH_ASSOC) : null;
    if ($settings) {
        $defaultFinePerDay = isset($settings['LateFeesPerDay']) ? (float)$settings['LateFeesPerDay'] : $defaultFinePerDay;
        $defaultDamageRate = isset($settings['DamageFeesRate']) ? (float)$settings['DamageFeesRate'] * 100 : $defaultDamageRate; // แปลงเป็น %
        $defaultLostRate = isset($settings['LostItemFeesRate']) ? (float)$settings['LostItemFeesRate'] * 100 : $defaultLostRate;
    }
} catch (Exception $ex) {
    // Keep fallback defaults
}

// ดึงข้อมูล admin ที่ login อยู่
$currentAdminEmail = $_SESSION['alogin'];
$currentAdminId = $_SESSION['adminid'] ?? null;

// ดึงรายชื่อ admin ทั้งหมด
$sqlAdmins = "SELECT id, Name, Surname, Email FROM tblmembers WHERE role = 'admin' AND Status = 1 ORDER BY Name";
$queryAdmins = $dbh->prepare($sqlAdmins);
$queryAdmins->execute();
$admins = $queryAdmins->fetchAll(PDO::FETCH_ASSOC);

// ดึงข้อมูล booking
$sql = "SELECT b.*, m.Name, m.Surname, m.StudentID, m.Email 
        FROM tblbookings b 
        JOIN tblmembers m ON b.MemberId = m.id 
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
$sqlItems = "SELECT bd.*, e.EquipmentName, e.EquipmentCode, e.Price
             FROM tblbookingdetails bd 
             JOIN tblequipment e ON bd.EquipmentId = e.id 
             WHERE bd.BookingId = :bookingId";
$queryItems = $dbh->prepare($sqlItems);
$queryItems->execute([':bookingId' => $bookingId]);
$items = $queryItems->fetchAll(PDO::FETCH_ASSOC);

// คำนวณค่าปรับ
$today = new DateTime();
$totalFine = 0;
foreach ($items as &$item) {
    $item['calculatedFine'] = 0;
    $item['overdueDays'] = 0;
    
    if ($item['DueDate'] && $item['ReturnStatus'] != 1) {
        $dueDate = new DateTime($item['DueDate']);
        if ($today > $dueDate) {
            $item['overdueDays'] = $dueDate->diff($today)->days;
            $item['calculatedFine'] = $item['overdueDays'] * ($item['FinePerDay'] ?? 10) * $item['Quantity'];
            $totalFine += $item['calculatedFine'];
        }
    }
}
unset($item);

// ประมวลผลการแก้ไข (สำหรับโหมดแก้ไข)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_edit'])) {
    $isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';
    try {
        $dbh->beginTransaction();
        
        $totalQtyAll = 0;
        $totalReturnedAll = 0;
        
        foreach ($items as $item) {
            $detailId = $item['id'];
            $newReturnedQty = intval($_POST['edit_returned_' . $detailId] ?? 0);
            $newDamagedQty = intval($_POST['edit_damaged_' . $detailId] ?? 0);
            $newLostQty = intval($_POST['edit_lost_' . $detailId] ?? 0);
            $newFine = floatval($_POST['edit_fine_' . $detailId] ?? 0);
            $newCompensation = floatval($_POST['edit_compensation_' . $detailId] ?? 0);
            
            $currentReturned = intval($item['QuantityReturned'] ?? 0);
            $currentDamaged = intval($item['DamagedQty'] ?? 0);
            $currentLost = intval($item['LostQty'] ?? 0);
            
            // ตรวจสอบไม่เกินจำนวนที่ยืม
            if ($newReturnedQty > $item['Quantity']) {
                throw new Exception("จำนวนคืนเกินจำนวนที่ยืม: " . $item['EquipmentName']);
            }
            
            // ตรวจสอบชำรุด+หาย ไม่เกินจำนวนคืน
            if (($newDamagedQty + $newLostQty) > $newReturnedQty) {
                throw new Exception("จำนวนชำรุด+หายเกินจำนวนคืน: " . $item['EquipmentName']);
            }
            
            // คำนวณส่วนต่างสำหรับปรับ stock
            // คืนเข้าคลัง = ปกติ + ชำรุด (ไม่รวมหาย)
            $oldReturnToStock = $currentReturned - $currentLost;
            $newNormalQty = $newReturnedQty - $newDamagedQty - $newLostQty;
            $newReturnToStock = $newNormalQty + $newDamagedQty; // ปกติ + ชำรุด คืนเข้าคลัง
            $stockDiff = $newReturnToStock - $oldReturnToStock;
            
            $returnStatus = ($newReturnedQty >= $item['Quantity']) ? 1 : 0;
            
            // กำหนด condition
            $condition = 'normal';
            if ($newLostQty > 0) {
                $condition = 'lost';
            } elseif ($newDamagedQty > 0) {
                $condition = 'damaged';
            }
            
            // อัพเดท booking detail
            $sqlUpdate = "UPDATE tblbookingdetails SET 
                          QuantityReturned = :returned,
                          ReturnStatus = :status,
                          DamagedQty = :damaged,
                          LostQty = :lost,
                          FineAmount = :fine,
                          CompensationAmount = :compensation,
                          ReturnCondition = :condition,
                          ReturnDate = NOW()
                          WHERE id = :id";
            $queryUpdate = $dbh->prepare($sqlUpdate);
            $queryUpdate->execute([
                ':returned' => $newReturnedQty,
                ':status' => $returnStatus,
                ':damaged' => $newDamagedQty,
                ':lost' => $newLostQty,
                ':fine' => $newFine,
                ':compensation' => $newCompensation,
                ':condition' => $condition,
                ':id' => $detailId
            ]);
            
            // ปรับจำนวนอุปกรณ์ในคลัง
            if ($stockDiff != 0) {
                $sqlStock = "UPDATE tblequipment SET Quantity = Quantity + :qty WHERE id = :equipId";
                $queryStock = $dbh->prepare($sqlStock);
                $queryStock->execute([
                    ':qty' => $stockDiff,
                    ':equipId' => $item['EquipmentId']
                ]);
            }
            
            $totalQtyAll += $item['Quantity'];
            $totalReturnedAll += $newReturnedQty;
        }
        
        // กำหนดสถานะที่ถูกต้อง
        $newStatus = 'borrowed'; // default
        if ($totalReturnedAll >= $totalQtyAll) {
            $newStatus = 'returned';
        } elseif ($totalReturnedAll > 0) {
            $newStatus = 'partial';
        }
        
        $editedBy = intval($_POST['received_by'] ?? $currentAdminId);
        $sqlBooking = "UPDATE tblbookings SET Status = :status, ReturnedBy = :editedBy, UpdatedAt = NOW() WHERE id = :id";
        $queryBooking = $dbh->prepare($sqlBooking);
        $queryBooking->execute([':status' => $newStatus, ':editedBy' => $editedBy, ':id' => $bookingId]);
        
        $dbh->commit();
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'msg' => 'แก้ไขการรับคืนสำเร็จ']);
            exit;
        }
        $_SESSION['admin_msg'] = "แก้ไขการรับคืนสำเร็จ";
        header('location:manage-issued-equipment.php');
        exit;
        
    } catch (Exception $e) {
        $dbh->rollBack();
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            exit;
        }
        $_SESSION['admin_error'] = $e->getMessage();
    }
}

// ประมวลผลการคืน
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_return'])) {
    $isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';
    try {
        $dbh->beginTransaction();
        
        $allReturned = true;
        $totalFineCollected = 0;
        $totalCompensation = 0;
        
        foreach ($items as $item) {
            $detailId = $item['id'];
            $returnQty = intval($_POST['return_qty_' . $detailId] ?? 0);
            $damagedQty = intval($_POST['damaged_qty_' . $detailId] ?? 0);
            $lostQty = intval($_POST['lost_qty_' . $detailId] ?? 0);
            $fineAmount = floatval($_POST['fine_' . $detailId] ?? 0);
            $compensationAmount = floatval($_POST['compensation_' . $detailId] ?? 0);
            $damageNote = trim($_POST['damage_note_' . $detailId] ?? '');
            
            $totalReturnQty = $returnQty + $damagedQty + $lostQty;
            
            if ($totalReturnQty > 0) {
                // ตรวจสอบไม่เกินจำนวนที่ยืม
                $remaining = $item['Quantity'] - ($item['QuantityReturned'] ?? 0);
                if ($totalReturnQty > $remaining) {
                    throw new Exception("จำนวนคืนเกินจำนวนที่ยืม: " . $item['EquipmentName']);
                }
                
                $newReturned = ($item['QuantityReturned'] ?? 0) + $totalReturnQty;
                $returnStatus = ($newReturned >= $item['Quantity']) ? 1 : 0;
                
                // กำหนด condition
                $condition = 'normal';
                if ($lostQty > 0) {
                    $condition = 'lost';
                } elseif ($damagedQty > 0) {
                    $condition = 'damaged';
                }
                
                // อัพเดท booking detail
                $sqlUpdate = "UPDATE tblbookingdetails SET 
                              QuantityReturned = :returned,
                              ReturnStatus = :status,
                              ReturnDate = NOW(),
                              FineAmount = FineAmount + :fine,
                              ReturnCondition = :condition,
                              DamagedQty = DamagedQty + :damagedQty,
                              LostQty = LostQty + :lostQty,
                              CompensationAmount = CompensationAmount + :compensation,
                              DamageNote = CONCAT(IFNULL(DamageNote,''), :note)
                              WHERE id = :id";
                $queryUpdate = $dbh->prepare($sqlUpdate);
                $noteText = $damageNote ? "\n[" . date('d/m/Y H:i') . "] " . $damageNote : '';
                $queryUpdate->execute([
                    ':returned' => $newReturned,
                    ':status' => $returnStatus,
                    ':fine' => $fineAmount,
                    ':condition' => $condition,
                    ':damagedQty' => $damagedQty,
                    ':lostQty' => $lostQty,
                    ':compensation' => $compensationAmount,
                    ':note' => $noteText,
                    ':id' => $detailId
                ]);
                
                // คืนจำนวนอุปกรณ์กลับคลัง (เฉพาะปกติและชำรุด, ไม่รวมหาย)
                $returnToStock = $returnQty + $damagedQty; // หายไม่คืนคลัง
                if ($returnToStock > 0) {
                    $sqlStock = "UPDATE tblequipment SET Quantity = Quantity + :qty WHERE id = :equipId";
                    $queryStock = $dbh->prepare($sqlStock);
                    $queryStock->execute([
                        ':qty' => $returnToStock,
                        ':equipId' => $item['EquipmentId']
                    ]);
                }
                
                $totalFineCollected += $fineAmount;
                $totalCompensation += $compensationAmount;
                
                if ($returnStatus != 1) {
                    $allReturned = false;
                }
            } else {
                // ไม่ได้คืนรายการนี้
                if ($item['ReturnStatus'] != 1) {
                    $allReturned = false;
                }
            }
        }
        
        // อัพเดทสถานะ booking และบันทึกผู้รับคืน
        $newStatus = $allReturned ? 'returned' : 'partial';
        $receivedBy = intval($_POST['received_by'] ?? $currentAdminId);
        $sqlBooking = "UPDATE tblbookings SET Status = :status, ReturnedBy = :receivedBy, ReturnedAt = NOW(), UpdatedAt = NOW() WHERE id = :id";
        $queryBooking = $dbh->prepare($sqlBooking);
        $queryBooking->execute([':status' => $newStatus, ':receivedBy' => $receivedBy, ':id' => $bookingId]);
        
        $dbh->commit();
        
        $totalCollected = $totalFineCollected + $totalCompensation;
        $successMsg = "รับคืนอุปกรณ์สำเร็จ";
        if ($totalCollected > 0) {
            $details = [];
            if ($totalFineCollected > 0) $details[] = "ค่าปรับ ฿" . number_format($totalFineCollected, 0);
            if ($totalCompensation > 0) $details[] = "ค่าชดเชย ฿" . number_format($totalCompensation, 0);
            $successMsg .= " (เก็บ: " . implode(' + ', $details) . ")";
        }
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'msg' => $successMsg]);
            exit;
        }
        $_SESSION['admin_msg'] = $successMsg;
        header('location:manage-issued-equipment.php');
        exit;
        
    } catch (Exception $e) {
        $dbh->rollBack();
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            exit;
        }
        $_SESSION['admin_error'] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>E-Sports | รับคืนอุปกรณ์</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <link href="../assets/css/modern-style.css" rel="stylesheet" />
    <style>
        .return-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .return-header {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            padding: 20px;
        }
        .return-body { padding: 25px; }
        
        .student-box {
            background: #f8fafc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #3b82f6;
        }
        
        .item-card {
            background: #f8fafc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border: 2px solid #e2e8f0;
            transition: all 0.3s;
        }
        .item-card.returned {
            background: #dcfce7;
            border-color: #86efac;
        }
        .item-card.overdue {
            border-color: #fca5a5;
            background: #fef2f2;
        }
        
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .item-name {
            font-weight: 700;
            color: #1e293b;
            font-size: 1.1rem;
        }
        .item-code {
            color: #64748b;
            font-size: 0.85rem;
        }
        
        .qty-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .qty-box {
            text-align: center;
            padding: 10px;
            background: white;
            border-radius: 8px;
        }
        .qty-box .value {
            font-size: 1.5rem;
            font-weight: 700;
        }
        .qty-box .label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
        }
        
        .fine-warning {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 15px;
            border-left: 4px solid #f59e0b;
        }
        
        .return-input-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .summary-box {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            color: white;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .status-returned {
            background: #dcfce7;
            color: #166534;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-pending {
            background: #fef3c7;
            color: #92400e;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-overdue {
            background: #fee2e2;
            color: #991b1b;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php include('includes/header.php');?>
    
    <div class="content-wrapper">
        <div class="container py-4">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="manage-issued-equipment.php">📦 จัดการยืม/คืน</a></li>
                    <li class="breadcrumb-item active">รับคืนอุปกรณ์</li>
                </ol>
            </nav>
            
            <?php if($_SESSION['admin_error']): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                ❌ <?php echo $_SESSION['admin_error']; $_SESSION['admin_error']=''; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <div class="return-card">
                <div class="return-header" style="<?php echo $isEditMode ? 'background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);' : ''; ?>">
                    <h4 class="mb-1"><?php echo $isEditMode ? '✏️ แก้ไขการรับคืน' : '✅ รับคืนอุปกรณ์'; ?></h4>
                    <p class="mb-0 opacity-75">รหัสใบยืม: <?php echo htmlspecialchars($booking['BookingCode']); ?></p>
                </div>
                
                <div class="return-body">
                    <!-- Student Info -->
                    <div class="student-box">
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>👤 ผู้ยืม:</strong> <?php echo htmlspecialchars($booking['Name'] . ' ' . $booking['Surname']); ?></p>
                                <p class="mb-0"><strong>🎓 รหัสนักศึกษา:</strong> <?php echo htmlspecialchars($booking['StudentID']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>📅 วันที่ยืม:</strong> <?php echo date('d/m/Y H:i', strtotime($booking['BookingDate'])); ?></p>
                                <p class="mb-0"><strong>📧 อีเมล:</strong> <?php echo htmlspecialchars($booking['Email']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($totalFine > 0): ?>
                    <div class="alert alert-danger">
                        <strong>⚠️ มีค่าปรับค้างชำระ:</strong> ฿<?php echo number_format($totalFine, 0); ?>
                        <br><small>กรุณาเก็บค่าปรับก่อนรับคืนอุปกรณ์</small>
                    </div>
                    <?php endif; ?>
                    
                    <form method="post" id="returnForm">
                        <input type="hidden" name="<?php echo $isEditMode ? 'process_edit' : 'process_return'; ?>" value="1">
                        
                        <!-- Receiver Selection -->
                        <div class="student-box mb-3" style="border-left-color: <?php echo $isEditMode ? '#f59e0b' : '#22c55e'; ?>;">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <label class="form-label mb-2"><strong><?php echo $isEditMode ? '👨‍💼 ผู้แก้ไข' : '👨‍💼 ผู้รับคืนอุปกรณ์'; ?></strong></label>
                                    <select name="received_by" id="received_by" class="form-select" required>
                                        <?php foreach ($admins as $admin): ?>
                                        <option value="<?php echo $admin['id']; ?>" <?php echo ($admin['id'] == $currentAdminId) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($admin['Name'] . ' ' . $admin['Surname']); ?> (<?php echo htmlspecialchars($admin['Email']); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted"><?php echo $isEditMode ? 'ผู้ที่ทำการแก้ไข' : 'เลือกเจ้าหน้าที่ที่รับคืนอุปกรณ์'; ?></small>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>📅 <?php echo $isEditMode ? 'วันที่แก้ไข' : 'วันที่รับคืน'; ?>:</strong> <?php echo date('d/m/Y'); ?></p>
                                    <p class="mb-0"><strong>⏰ เวลา:</strong> <?php echo date('H:i'); ?> น.</p>
                                </div>
                            </div>
                        </div>
                        
                        <h5 class="mb-3">📋 รายการอุปกรณ์</h5>
                        
                        <?php foreach ($items as $item): 
                            $remaining = $item['Quantity'] - ($item['QuantityReturned'] ?? 0);
                            $isReturned = $item['ReturnStatus'] == 1;
                            $isOverdue = $item['overdueDays'] > 0;
                        ?>
                        <div class="item-card <?php echo $isReturned ? 'returned' : ($isOverdue ? 'overdue' : ''); ?>">
                            <div class="item-header">
                                <div>
                                    <div class="item-name"><?php echo htmlspecialchars($item['EquipmentName']); ?></div>
                                    <div class="item-code">📦 <?php echo htmlspecialchars($item['EquipmentCode']); ?></div>
                                </div>
                                <div>
                                    <?php if ($isReturned): ?>
                                    <span class="status-returned">✅ คืนแล้ว</span>
                                    <?php elseif ($isOverdue): ?>
                                    <span class="status-overdue">⚠️ เกินกำหนด <?php echo $item['overdueDays']; ?> วัน</span>
                                    <?php else: ?>
                                    <span class="status-pending">⏳ รอคืน</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="qty-info">
                                <div class="qty-box">
                                    <div class="value text-primary"><?php echo $item['Quantity']; ?></div>
                                    <div class="label">จำนวนยืม</div>
                                </div>
                                <div class="qty-box">
                                    <div class="value text-success"><?php echo $item['QuantityReturned'] ?? 0; ?></div>
                                    <div class="label">คืนแล้ว</div>
                                </div>
                                <div class="qty-box">
                                    <div class="value <?php echo $remaining > 0 ? 'text-warning' : 'text-success'; ?>"><?php echo $remaining; ?></div>
                                    <div class="label">คงค้าง</div>
                                </div>
                                <div class="qty-box">
                                    <div class="value"><?php echo $item['DueDate'] ? date('d/m', strtotime($item['DueDate'])) : '-'; ?></div>
                                    <div class="label">กำหนดคืน</div>
                                </div>
                            </div>
                            
                            <?php if ($isEditMode): ?>
                            <!-- Edit Mode: แก้ไขจำนวนที่คืนแล้ว -->
                            <?php 
                            $equipPrice = floatval($item['Price'] ?? 0);
                            $currentDamaged = intval($item['DamagedQty'] ?? 0);
                            $currentLost = intval($item['LostQty'] ?? 0);
                            $currentNormal = intval($item['QuantityReturned'] ?? 0) - $currentDamaged - $currentLost;
                            $currentFine = floatval($item['FineAmount'] ?? 0);
                            $currentCompensation = floatval($item['CompensationAmount'] ?? 0);
                            ?>
                            <div class="mt-3 pt-3 border-top">
                                <div class="alert alert-warning py-2 mb-3">
                                    <small>⚠️ <strong>โหมดแก้ไข:</strong> การเปลี่ยนแปลงจะมีผลต่อจำนวนอุปกรณ์ในคลัง</small>
                                </div>
                                
                                <div class="row g-3">
                                    <!-- จำนวนคืนรวม -->
                                    <div class="col-md-3">
                                        <label class="form-label">✅ จำนวนคืนรวม</label>
                                        <input type="number" name="edit_returned_<?php echo $item['id']; ?>" 
                                               class="form-control edit-returned" 
                                               min="0" max="<?php echo $item['Quantity']; ?>" 
                                               value="<?php echo $item['QuantityReturned'] ?? 0; ?>"
                                               data-original="<?php echo $item['QuantityReturned'] ?? 0; ?>"
                                               data-max="<?php echo $item['Quantity']; ?>"
                                               data-item-id="<?php echo $item['id']; ?>"
                                               data-equip-id="<?php echo $item['EquipmentId']; ?>">
                                        <small class="text-muted">จากทั้งหมด <?php echo $item['Quantity']; ?> ชิ้น</small>
                                    </div>
                                    
                                    <!-- จำนวนชำรุด -->
                                    <div class="col-md-3">
                                        <label class="form-label text-warning">🟡 จำนวนชำรุด</label>
                                        <input type="number" name="edit_damaged_<?php echo $item['id']; ?>" 
                                               class="form-control edit-damaged" 
                                               min="0" max="<?php echo $item['Quantity']; ?>" 
                                               value="<?php echo $currentDamaged; ?>"
                                               data-item-id="<?php echo $item['id']; ?>">
                                    </div>
                                    
                                    <!-- จำนวนหาย -->
                                    <div class="col-md-3">
                                        <label class="form-label text-danger">🔴 จำนวนหาย</label>
                                        <input type="number" name="edit_lost_<?php echo $item['id']; ?>" 
                                               class="form-control edit-lost" 
                                               min="0" max="<?php echo $item['Quantity']; ?>" 
                                               value="<?php echo $currentLost; ?>"
                                               data-item-id="<?php echo $item['id']; ?>">
                                    </div>
                                    
                                    <!-- จำนวนปกติ (display only) -->
                                    <div class="col-md-3">
                                        <label class="form-label text-success">🟢 ปกติ</label>
                                        <input type="text" class="form-control edit-normal" readonly
                                               value="<?php echo max(0, $currentNormal); ?>" 
                                               style="background: #f0f0f0;">
                                        <small class="text-muted">คำนวณอัตโนมัติ</small>
                                    </div>
                                    
                                    <!-- ค่าปรับล่าช้า -->
                                    <div class="col-md-6">
                                        <label class="form-label">⏰ ค่าปรับล่าช้า</label>
                                        <div class="input-group">
                                            <span class="input-group-text">฿</span>
                                            <input type="number" name="edit_fine_<?php echo $item['id']; ?>" 
                                                   class="form-control edit-fine" 
                                                   min="0" step="1" 
                                                   value="<?php echo $currentFine; ?>">
                                        </div>
                                    </div>
                                    
                                    <!-- ค่าชดเชย -->
                                    <div class="col-md-6">
                                        <label class="form-label">💸 ค่าชดเชย (ชำรุด/หาย)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">฿</span>
                                            <input type="number" name="edit_compensation_<?php echo $item['id']; ?>" 
                                                   class="form-control edit-compensation" 
                                                   min="0" step="1" 
                                                   value="<?php echo $currentCompensation; ?>">
                                        </div>
                                        <?php if ($equipPrice > 0): ?>
                                        <small class="text-muted">ราคา: ฿<?php echo number_format($equipPrice, 0); ?>/ชิ้น</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php elseif (!$isReturned && $remaining > 0): ?>
                            <!-- Normal Mode: รับคืนใหม่ -->
                            <?php 
                            $equipPrice = floatval($item['Price'] ?? 0);
                            $damageRate = $defaultDamageRate; // ใช้ค่ากลางจาก booking_settings
                            ?>
                            
                            <?php if ($isOverdue): ?>
                            <div class="fine-warning">
                                <strong>💰 ค่าปรับล่าช้า:</strong> <?php echo $item['overdueDays']; ?> วัน × ฿<?php echo number_format($item['FinePerDay'] ?? $defaultFinePerDay, 0); ?> × <?php echo $remaining; ?> ชิ้น = <strong>฿<?php echo number_format($item['calculatedFine'], 0); ?></strong>
                            </div>
                            <?php endif; ?>
                            
                            <!-- ราคาอุปกรณ์ -->
                            <?php if ($equipPrice > 0): ?>
                            <div class="alert alert-secondary py-2 mb-3">
                                <small>💵 ราคาอุปกรณ์: <strong>฿<?php echo number_format($equipPrice, 0); ?></strong>/ชิ้น | อัตราค่าซ่อม: <strong><?php echo $damageRate; ?>%</strong> | อัตราสูญหาย: <strong><?php echo $defaultLostRate; ?>%</strong></small>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-warning py-2 mb-3">
                                <small>⚠️ <strong>ยังไม่ได้ตั้งราคาอุปกรณ์</strong> - กรุณา<a href="edit-equipment.php?bookid=<?php echo $item['EquipmentId']; ?>" target="_blank" class="alert-link">กรอกราคา</a>เพื่อคำนวณค่าชดเชยอัตโนมัติ หรือกรอกค่าชดเชยด้วยตนเอง</small>
                            </div>
                            <?php endif; ?>
                            
                            <div class="row g-3">
                                <!-- จำนวนคืนปกติ -->
                                <div class="col-md-3">
                                    <label class="form-label text-success">🟢 คืนปกติ</label>
                                    <input type="number" name="return_qty_<?php echo $item['id']; ?>" 
                                           class="form-control return-qty" 
                                           min="0" max="<?php echo $remaining; ?>" 
                                           value="<?php echo $remaining; ?>"
                                           data-max="<?php echo $remaining; ?>"
                                           data-fine-per-day="<?php echo $item['FinePerDay'] ?? 10; ?>"
                                           data-overdue-days="<?php echo $item['overdueDays']; ?>"
                                           data-item-id="<?php echo $item['id']; ?>"
                                           data-price="<?php echo $equipPrice; ?>"
                                           data-damage-rate="<?php echo $damageRate; ?>">
                                </div>
                                
                                <!-- จำนวนชำรุด -->
                                <div class="col-md-3">
                                    <label class="form-label text-warning">🟡 ชำรุด</label>
                                    <input type="number" name="damaged_qty_<?php echo $item['id']; ?>" 
                                           class="form-control damaged-qty" 
                                           min="0" max="<?php echo $remaining; ?>" 
                                           value="0"
                                           data-item-id="<?php echo $item['id']; ?>"
                                           data-price="<?php echo $equipPrice; ?>"
                                           data-damage-rate="<?php echo $damageRate; ?>">
                                    <small class="text-muted">ค่าซ่อม <?php echo $damageRate; ?>%</small>
                                </div>
                                
                                <!-- จำนวนหาย -->
                                <div class="col-md-3">
                                    <label class="form-label text-danger">🔴 สูญหาย</label>
                                    <input type="number" name="lost_qty_<?php echo $item['id']; ?>" 
                                           class="form-control lost-qty" 
                                           min="0" max="<?php echo $remaining; ?>" 
                                           value="0"
                                           data-item-id="<?php echo $item['id']; ?>"
                                           data-price="<?php echo $equipPrice; ?>"
                                           data-lost-rate="<?php echo $defaultLostRate; ?>">
                                    <small class="text-muted">ชดเชย <?php echo $defaultLostRate; ?>%</small>
                                </div>
                                
                                <!-- ค่าปรับล่าช้า -->
                                <div class="col-md-3">
                                    <label class="form-label">⏰ ค่าปรับล่าช้า</label>
                                    <div class="input-group">
                                        <span class="input-group-text">฿</span>
                                        <input type="number" name="fine_<?php echo $item['id']; ?>" 
                                               class="form-control fine-input" 
                                               id="fine_<?php echo $item['id']; ?>"
                                               min="0" step="1" 
                                               value="<?php echo $item['calculatedFine']; ?>">
                                    </div>
                                </div>
                                
                                <!-- ค่าชดเชย -->
                                <div class="col-md-6">
                                    <label class="form-label">💸 ค่าชดเชย (ชำรุด+สูญหาย)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">฿</span>
                                        <input type="number" name="compensation_<?php echo $item['id']; ?>" 
                                               class="form-control compensation-input" 
                                               id="compensation_<?php echo $item['id']; ?>"
                                               min="0" step="1" 
                                               value="0">
                                    </div>
                                    <small class="text-muted" id="compensation_hint_<?php echo $item['id']; ?>">คำนวณอัตโนมัติตามจำนวนชำรุด/สูญหาย</small>
                                </div>
                                
                                <!-- หมายเหตุ -->
                                <div class="col-md-6">
                                    <label class="form-label">📝 หมายเหตุ (ถ้ามี)</label>
                                    <input type="text" name="damage_note_<?php echo $item['id']; ?>" 
                                           class="form-control" 
                                           placeholder="ระบุรายละเอียดความเสียหาย...">
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if ($isEditMode): ?>
                        <!-- Edit Mode Summary -->
                        <div class="alert alert-warning mt-4">
                            <strong>⚠️ โหมดแก้ไข:</strong> คุณกำลังแก้ไขจำนวนอุปกรณ์ที่รับคืนไปแล้ว<br>
                            <small>หากลดจำนวน ระบบจะหักคืนจากคลังอุปกรณ์ / หากเพิ่มจำนวน ระบบจะเพิ่มเข้าคลัง</small>
                        </div>
                        
                        <!-- Actions -->
                        <div class="d-flex gap-3 mt-4">
                            <button type="submit" class="btn btn-warning btn-lg">
                                ✏️ บันทึกการแก้ไข
                            </button>
                            <a href="manage-issued-equipment.php" class="btn btn-outline-secondary btn-lg">
                                ← ย้อนกลับ
                            </a>
                        </div>
                        <?php else: ?>
                        <!-- Normal Mode Summary -->
                        <div class="summary-box">
                            <div class="row align-items-center">
                                <div class="col-md-4 text-center border-end">
                                    <div class="fs-5 opacity-75">⏰ ค่าปรับล่าช้า</div>
                                    <span class="fs-3 fw-bold" id="totalFineDisplay">฿<?php echo number_format($totalFine, 0); ?></span>
                                </div>
                                <div class="col-md-4 text-center border-end">
                                    <div class="fs-5 opacity-75">💸 ค่าชดเชย</div>
                                    <span class="fs-3 fw-bold" id="totalCompensationDisplay">฿0</span>
                                </div>
                                <div class="col-md-4 text-center">
                                    <div class="fs-5 opacity-75">💰 รวมทั้งหมด</div>
                                    <span class="fs-2 fw-bold text-warning" id="grandTotalDisplay">฿<?php echo number_format($totalFine, 0); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="d-flex gap-3 mt-4">
                            <button type="submit" class="btn btn-success btn-lg">
                                ✅ ยืนยันการรับคืน
                            </button>
                            <a href="manage-issued-equipment.php" class="btn btn-outline-secondary btn-lg">
                                ← ย้อนกลับ
                            </a>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include('includes/footer.php');?>
    
    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">❌ ข้อผิดพลาด</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <div style="font-size: 64px;">❌</div>
                    <p class="mt-3 mb-0 fs-5" id="errorMessage"></p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ปิด</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Confirm Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">✅ ยืนยันการรับคืน</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <div style="font-size: 64px;">📦</div>
                    <p class="mt-3 mb-0 fs-5" id="confirmMessage">ยืนยันการรับคืนอุปกรณ์?</p>
                    <p class="text-muted" id="confirmDetails"></p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-success px-4" id="confirmOk">✅ ยืนยัน</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Alert Modal -->
    <div class="modal fade" id="alertModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">⚠️ แจ้งเตือน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <div style="font-size: 64px;">⚠️</div>
                    <p class="mt-3 mb-0 fs-5" id="alertMessage"></p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">ตกลง</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">✅ สำเร็จ</h5>
                </div>
                <div class="modal-body text-center py-4">
                    <div style="font-size: 64px;">🎉</div>
                    <p class="mt-3 mb-0 fs-5" id="successMessage"></p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-success px-4" id="successOkBtn">ตกลง</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/jquery-1.10.2.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        var confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
        var alertModal = new bootstrap.Modal(document.getElementById('alertModal'));
        var successModal = new bootstrap.Modal(document.getElementById('successModal'));
        var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
        var pendingFormData = null;
        
        function showAlert(message) {
            $('#alertMessage').text(message);
            alertModal.show();
        }
        
        function showError(message) {
            $('#errorMessage').text(message);
            errorModal.show();
        }
        
        function showSuccess(message) {
            $('#successMessage').text(message);
            successModal.show();
        }
        
        function showConfirm(message, details, callback) {
            $('#confirmMessage').text(message);
            $('#confirmDetails').html(details);
            pendingFormData = callback;
            confirmModal.show();
        }
        
        // Success modal -> redirect
        $('#successOkBtn').click(function() {
            window.location.href = 'manage-issued-equipment.php';
        });
        
        $('#confirmOk').click(function() {
            confirmModal.hide();
            if (pendingFormData) {
                submitFormAjax(pendingFormData);
            }
        });
        
        function submitFormAjax(formData) {
            var $btn = $('#returnForm button[type="submit"]');
            $btn.prop('disabled', true).addClass('disabled');
            
            $.ajax({
                url: window.location.href,
                type: 'POST',
                dataType: 'json',
                data: formData,
                processData: false,
                contentType: false,
                success: function(resp) {
                    if (resp && resp.ok) {
                        showSuccess(resp.msg || 'ดำเนินการสำเร็จ');
                    } else {
                        var msg = (resp && resp.error) ? resp.error : 'เกิดข้อผิดพลาด';
                        showError(msg);
                        $btn.prop('disabled', false).removeClass('disabled');
                    }
                },
                error: function(xhr) {
                    console.error('AJAX error', xhr.status, xhr.responseText);
                    showError('เกิดข้อผิดพลาดในการส่งข้อมูล');
                    $btn.prop('disabled', false).removeClass('disabled');
                }
            });
        }
        
        function updateTotalFine() {
            var totalFine = 0;
            var totalCompensation = 0;
            
            $('.fine-input').each(function() {
                totalFine += parseFloat($(this).val()) || 0;
            });
            $('.compensation-input').each(function() {
                totalCompensation += parseFloat($(this).val()) || 0;
            });
            
            var grandTotal = totalFine + totalCompensation;
            
            $('#totalFineDisplay').text('฿' + totalFine.toLocaleString());
            $('#totalCompensationDisplay').text('฿' + totalCompensation.toLocaleString());
            $('#grandTotalDisplay').text('฿' + grandTotal.toLocaleString());
        }
        
        // ฟังก์ชันคำนวณค่าชดเชย
        function calculateCompensation(itemId) {
            var $card = $('[data-item-id="' + itemId + '"]').closest('.item-card');
            var price = parseFloat($card.find('.return-qty').data('price')) || 0;
            var damageRate = parseFloat($card.find('.return-qty').data('damage-rate')) || 50;
            var lostRate = parseFloat($card.find('.lost-qty').data('lost-rate')) || 100;
            var damagedQty = parseInt($card.find('.damaged-qty').val()) || 0;
            var lostQty = parseInt($card.find('.lost-qty').val()) || 0;
            
            // ค่าซ่อม = จำนวนชำรุด × ราคา × อัตราซ่อม%
            var damageCompensation = damagedQty * price * (damageRate / 100);
            // ค่าสูญหาย = จำนวนหาย × ราคา × อัตราสูญหาย%
            var lostCompensation = lostQty * price * (lostRate / 100);
            
            var totalCompensation = damageCompensation + lostCompensation;
            $('#compensation_' + itemId).val(Math.round(totalCompensation));
            
            // แสดง hint
            var hint = '';
            if (damagedQty > 0 && price > 0) {
                hint += 'ชำรุด: ' + damagedQty + '×฿' + price.toLocaleString() + '×' + damageRate + '%= ฿' + Math.round(damageCompensation).toLocaleString();
            }
            if (lostQty > 0 && price > 0) {
                if (hint) hint += ' | ';
                hint += 'หาย: ' + lostQty + '×฿' + price.toLocaleString() + '×' + lostRate + '%= ฿' + Math.round(lostCompensation).toLocaleString();
            }
            if (!hint) hint = 'คำนวณอัตโนมัติตามจำนวนชำรุด/สูญหาย';
            $('#compensation_hint_' + itemId).text(hint);
            
            updateTotalFine();
        }
        
        // ตรวจสอบจำนวนรวมไม่เกิน remaining
        function validateQuantities(itemId) {
            var $card = $('[data-item-id="' + itemId + '"]').closest('.item-card');
            var max = parseInt($card.find('.return-qty').data('max')) || 0;
            var normalQty = parseInt($card.find('.return-qty').val()) || 0;
            var damagedQty = parseInt($card.find('.damaged-qty').val()) || 0;
            var lostQty = parseInt($card.find('.lost-qty').val()) || 0;
            
            var total = normalQty + damagedQty + lostQty;
            if (total > max) {
                return false;
            }
            return true;
        }
        
        // Event handlers
        $('.return-qty, .damaged-qty, .lost-qty').on('change', function() {
            var $this = $(this);
            var itemId = $this.data('item-id');
            var $card = $this.closest('.item-card');
            var max = parseInt($card.find('.return-qty').data('max')) || 0;
            
            // ตรวจสอบค่าไม่ติดลบ
            if (parseInt($this.val()) < 0) $this.val(0);
            
            var normalQty = parseInt($card.find('.return-qty').val()) || 0;
            var damagedQty = parseInt($card.find('.damaged-qty').val()) || 0;
            var lostQty = parseInt($card.find('.lost-qty').val()) || 0;
            var total = normalQty + damagedQty + lostQty;
            
            // ถ้าเกิน max ให้ปรับค่าปัจจุบันลง
            if (total > max) {
                var excess = total - max;
                var currentVal = parseInt($this.val()) || 0;
                $this.val(Math.max(0, currentVal - excess));
            }
            
            // คำนวณค่าปรับล่าช้า (เฉพาะจำนวนปกติ)
            var finePerDay = parseFloat($card.find('.return-qty').data('fine-per-day')) || 10;
            var overdueDays = parseInt($card.find('.return-qty').data('overdue-days')) || 0;
            normalQty = parseInt($card.find('.return-qty').val()) || 0;
            var fine = overdueDays * finePerDay * normalQty;
            $('#fine_' + itemId).val(Math.round(fine));
            
            // คำนวณค่าชดเชย
            calculateCompensation(itemId);
        });
        
        $('.fine-input, .compensation-input').on('change', updateTotalFine);
        
        // Edit Mode: คำนวณจำนวนปกติอัตโนมัติ
        $('.edit-returned, .edit-damaged, .edit-lost').on('change input', function() {
            var $this = $(this);
            var itemId = $this.data('item-id');
            var $card = $this.closest('.item-card');
            var max = parseInt($card.find('.edit-returned').data('max')) || 0;
            
            // ตรวจสอบค่าไม่ติดลบ
            if (parseInt($this.val()) < 0) $this.val(0);
            
            var totalReturned = parseInt($card.find('.edit-returned').val()) || 0;
            var damagedQty = parseInt($card.find('.edit-damaged').val()) || 0;
            var lostQty = parseInt($card.find('.edit-lost').val()) || 0;
            
            // ตรวจสอบ total returned ไม่เกิน max
            if (totalReturned > max) {
                $card.find('.edit-returned').val(max);
                totalReturned = max;
            }
            
            // ตรวจสอบ damaged + lost ไม่เกิน totalReturned
            if ((damagedQty + lostQty) > totalReturned) {
                if ($this.hasClass('edit-damaged')) {
                    damagedQty = Math.max(0, totalReturned - lostQty);
                    $this.val(damagedQty);
                } else if ($this.hasClass('edit-lost')) {
                    lostQty = Math.max(0, totalReturned - damagedQty);
                    $this.val(lostQty);
                }
            }
            
            // คำนวณจำนวนปกติ
            var normalQty = totalReturned - damagedQty - lostQty;
            $card.find('.edit-normal').val(Math.max(0, normalQty));
        });
        
        $('#returnForm').on('submit', function(e) {
            e.preventDefault();
            
            var hasReturn = false;
            var hasEdit = false;
            var totalQty = 0;
            var totalFine = 0;
            var totalCompensation = 0;
            var hasDamaged = false;
            var hasLost = false;
            
            // Check for return mode
            $('.return-qty').each(function() {
                var qty = parseInt($(this).val()) || 0;
                if (qty > 0) {
                    hasReturn = true;
                    totalQty += qty;
                }
            });
            $('.damaged-qty').each(function() {
                var qty = parseInt($(this).val()) || 0;
                if (qty > 0) {
                    hasReturn = true;
                    totalQty += qty;
                    hasDamaged = true;
                }
            });
            $('.lost-qty').each(function() {
                var qty = parseInt($(this).val()) || 0;
                if (qty > 0) {
                    hasReturn = true;
                    totalQty += qty;
                    hasLost = true;
                }
            });
            
            // Check for edit mode
            $('.edit-returned').each(function() {
                hasEdit = true;
                totalQty += parseInt($(this).val()) || 0;
            });
            
            $('.fine-input').each(function() {
                totalFine += parseFloat($(this).val()) || 0;
            });
            
            $('.compensation-input').each(function() {
                totalCompensation += parseFloat($(this).val()) || 0;
            });
            
            if (!hasReturn && !hasEdit) {
                showAlert('กรุณาระบุจำนวนอุปกรณ์ที่ต้องการรับคืน');
                return false;
            }
            
            var formData = new FormData(this);
            formData.append('ajax', '1');
            
            var details = '';
            if (hasReturn) {
                details = '<strong>รับคืนอุปกรณ์ ' + totalQty + ' ชิ้น</strong>';
                
                // แสดงรายละเอียดสถานะ
                if (hasDamaged || hasLost) {
                    details += '<br><small class="text-muted">';
                    if (hasDamaged) details += '🟡 มีอุปกรณ์ชำรุด ';
                    if (hasLost) details += '🔴 มีอุปกรณ์สูญหาย';
                    details += '</small>';
                }
                
                // แสดงค่าปรับและค่าชดเชย
                if (totalFine > 0 || totalCompensation > 0) {
                    details += '<br><div class="mt-2 p-2 bg-light rounded">';
                    if (totalFine > 0) {
                        details += '<div class="text-warning">⏰ ค่าปรับล่าช้า: ฿' + totalFine.toLocaleString() + '</div>';
                    }
                    if (totalCompensation > 0) {
                        details += '<div class="text-danger">💸 ค่าชดเชย: ฿' + totalCompensation.toLocaleString() + '</div>';
                    }
                    var grandTotal = totalFine + totalCompensation;
                    details += '<hr class="my-1"><div class="fw-bold">รวมทั้งหมด: ฿' + grandTotal.toLocaleString() + '</div>';
                    details += '</div>';
                }
                
                showConfirm('ยืนยันการรับคืนอุปกรณ์?', details, formData);
            } else if (hasEdit) {
                details = '<strong>บันทึกการแก้ไข</strong>';
                showConfirm('ยืนยันการแก้ไข?', details, formData);
            }
            
            return false;
        });
    });
    </script>
</body>
</html>
