<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('includes/config.php');

if(strlen($_SESSION['login'])==0) {   
    header('location:index.php');
    exit;
}

// ดึงข้อมูลนักศึกษาที่ login
$memberId = $_SESSION['stdid'];
$sqlStudent = "SELECT * FROM tblmembers WHERE id = :memberId";
$queryStudent = $dbh->prepare($sqlStudent);
$queryStudent->bindParam(':memberId', $memberId, PDO::PARAM_INT);
$queryStudent->execute();
$studentInfo = $queryStudent->fetch(PDO::FETCH_ASSOC);

// ดึงข้อมูลตะกร้า
$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
$cartItems = [];
$totalItems = 0;

// Clean up cart - ensure all values are integers
foreach ($cart as $id => $value) {
    if (is_array($value)) {
        $cart[$id] = isset($value['quantity']) ? (int)$value['quantity'] : 1;
    } else {
        $cart[$id] = (int)$value;
    }
}
$_SESSION['cart'] = $cart;

if (!empty($cart)) {
    $ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT e.*, c.CategoryName FROM tblequipment e 
            LEFT JOIN tblcategory c ON e.CatId = c.id 
            WHERE e.id IN ($placeholders)";
    $query = $dbh->prepare($sql);
    $query->execute($ids);
    $equipments = $query->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($equipments as $eq) {
        $eq['cart_qty'] = $cart[$eq['id']];
        $cartItems[] = $eq;
        $totalItems += $cart[$eq['id']];
    }
}

// ประมวลผลการยืม
if(isset($_POST['checkout']) && !empty($cartItems)) {
    $success = true;
    $bookingIds = [];
    
    try {
        $dbh->beginTransaction();
        
        // Generate Booking Code
        $dateCode = date('Ymd');
        $seqQuery = $dbh->query("SELECT COUNT(*) + 1 as seq FROM tblbookings WHERE DATE(BookingDate) = CURDATE()");
        $seq = $seqQuery->fetch(PDO::FETCH_ASSOC)['seq'];
        $bookingCode = 'BK-' . $dateCode . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
        
        // Insert main booking
        $sqlBooking = "INSERT INTO tblbookings (BookingCode, MemberId, BookingDate, Status, TotalItems, Notes) 
                       VALUES (:code, :memberId, NOW(), 'borrowed', :total, :notes)";
        $queryBooking = $dbh->prepare($sqlBooking);
        $queryBooking->execute([
            ':code' => $bookingCode,
            ':memberId' => $memberId,
            ':total' => $totalItems,
            ':notes' => $_POST['notes'] ?? ''
        ]);
        $mainBookingId = $dbh->lastInsertId();
        
        foreach($cartItems as $item) {
            $quantity = $item['cart_qty'];
            $dueDate = $_POST['due_date_' . $item['id']] ?? date('Y-m-d', strtotime('+' . ($item['MaxBorrowDays'] ?? 7) . ' days'));
            $finePerDay = $item['FinePerDay'] ?? 10;
            
            // ตรวจสอบจำนวนคงเหลือ
            $sqlCheck = "SELECT Quantity FROM tblequipment WHERE id = :id";
            $queryCheck = $dbh->prepare($sqlCheck);
            $queryCheck->bindParam(':id', $item['id'], PDO::PARAM_INT);
            $queryCheck->execute();
            $currentQty = $queryCheck->fetchColumn();
            
            if($currentQty < $quantity) {
                throw new Exception("อุปกรณ์ {$item['EquipmentName']} มีไม่เพียงพอ (เหลือ {$currentQty} ชิ้น)");
            }
            
            // ลดจำนวนอุปกรณ์
            $newQty = $currentQty - $quantity;
            $sqlUpdate = "UPDATE tblequipment SET Quantity = :qty WHERE id = :id";
            $queryUpdate = $dbh->prepare($sqlUpdate);
            $queryUpdate->bindParam(':qty', $newQty, PDO::PARAM_INT);
            $queryUpdate->bindParam(':id', $item['id'], PDO::PARAM_INT);
            $queryUpdate->execute();
            
            // เพิ่มรายการยืม
            $sqlInsert = "INSERT INTO tblbookingdetails(BookingId, MemberId, EquipmentId, Quantity, BookingDate, DueDate, FinePerDay) 
                          VALUES(:bookingId, :memberId, :equipId, :qty, NOW(), :dueDate, :finePerDay)";
            $queryInsert = $dbh->prepare($sqlInsert);
            $queryInsert->execute([
                ':bookingId' => $mainBookingId,
                ':memberId' => $memberId,
                ':equipId' => $item['id'],
                ':qty' => $quantity,
                ':dueDate' => $dueDate,
                ':finePerDay' => $finePerDay
            ]);
            
            $bookingIds[] = $dbh->lastInsertId();
        }
        
        $dbh->commit();
        
        // ล้างตะกร้า
        unset($_SESSION['cart']);
        
        // เก็บ booking ID สำหรับหน้าใบเสร็จ
        $_SESSION['last_booking_id'] = $mainBookingId;
        $_SESSION['last_booking_code'] = $bookingCode;
        $_SESSION['msg'] = "ยืมอุปกรณ์สำเร็จ!";
        
        header('location:booking-confirmation.php');
        exit;
        
    } catch(Exception $e) {
        $dbh->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>E-Sports | ตะกร้ายืมอุปกรณ์</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <link href="assets/css/modern-style.css" rel="stylesheet" />
    <link href='https://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css' />
    <style>
        .empty-cart { text-align: center; padding: 60px 20px; }
        .empty-cart i { font-size: 64px; color: #dee2e6; margin-bottom: 20px; }
        
        .student-info { 
            background: #ffffff; 
            border-radius: 12px; 
            padding: 20px; 
            margin-bottom: 24px; 
            border: 2px solid #1e40af;
            box-shadow: 0 4px 15px rgba(30, 64, 175, 0.1);
        }
        .student-info h5 { 
            margin-bottom: 15px; 
            color: #1e40af;
            font-weight: 700;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }
        .student-info p { margin-bottom: 10px; font-size: 15px; color: #374151; }
        .student-info p strong { color: #1e293b; }
        .summary-card { background: #f8f9fa; border-radius: 12px; padding: 20px; }
        
        /* Cart Item Card Style */
        .cart-item {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            transition: all 0.3s;
            position: relative;
        }
        .cart-item:hover {
            border-color: #3b82f6;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.15);
        }
        .cart-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #e5e7eb;
        }
        .cart-item-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e40af;
            margin-bottom: 4px;
        }
        .cart-item-code {
            font-size: 0.85rem;
            color: #64748b;
        }
        .cart-item-category {
            display: inline-block;
            background: #dbeafe;
            color: #1e40af;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            margin-top: 5px;
        }
        
        .btn-delete-item {
            background: #fee2e2;
            color: #dc2626;
            border: none;
            width: 42px;
            height: 42px;
            border-radius: 10px;
            font-size: 18px;
            transition: all 0.2s;
            flex-shrink: 0;
        }
        .btn-delete-item:hover {
            background: #dc2626;
            color: white;
            transform: scale(1.05);
        }
        
        .cart-item-body {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 15px;
        }
        .cart-field {
            background: #f8fafc;
            border-radius: 10px;
            padding: 12px;
        }
        .cart-field-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .qty-control { 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            justify-content: center;
        }
        .qty-control button { 
            width: 36px; 
            height: 36px; 
            padding: 0; 
            border-radius: 8px;
            font-weight: bold;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .qty-control .qty-input { 
            width: 55px !important; 
            height: 36px;
            text-align: center; 
            border-radius: 8px;
            font-weight: 700;
            font-size: 1.1rem;
            border: 2px solid #e5e7eb;
            background: white;
            color: #1e293b;
            padding: 0;
        }
        
        .due-date-input {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 14px;
            transition: all 0.2s;
            width: 100%;
        }
        .due-date-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .due-date-input.overdue {
            border-color: #ef4444;
            background-color: #fef2f2;
        }
        
        .borrow-days {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 20px;
            display: inline-block;
            margin-top: 8px;
            font-weight: 600;
        }
        .borrow-days.normal { background: #dcfce7; color: #166534; }
        .borrow-days.warning { background: #fef3c7; color: #92400e; }
        .borrow-days.danger { background: #fee2e2; color: #991b1b; }
        
        .fine-badge {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: #78350f;
            padding: 8px 14px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1rem;
            display: inline-block;
        }
        
        .fine-info {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
            text-align: center;
        }
        
        .max-days-hint {
            font-size: 0.7rem;
            color: #6b7280;
            margin-top: 6px;
        }
        
        .total-fine-box {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
        }
        
        @media (max-width: 576px) {
            .cart-item-body {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include('includes/header.php');?>
    <div class="content-wrapper">
        <div class="container py-4">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1"><i class="fa fa-shopping-cart me-2"></i>ตะกร้ายืมอุปกรณ์</h4>
                    <p class="text-muted mb-0">ตรวจสอบรายการและกำหนดวันคืน</p>
                </div>
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="fa fa-plus me-1"></i> เพิ่มอุปกรณ์
                </a>
            </div>

            <?php if(isset($_SESSION['error'])): ?>
            <div class="modal fade" id="errorModal" tabindex="-1" data-bs-backdrop="static">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title"><i class="fa fa-exclamation-circle me-2"></i>เกิดข้อผิดพลาด</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center py-4">
                            <i class="fa fa-times-circle text-danger" style="font-size: 64px;"></i>
                            <p class="mt-3 mb-0 fs-5"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></p>
                        </div>
                        <div class="modal-footer justify-content-center">
                            <button type="button" class="btn btn-danger px-4" data-bs-dismiss="modal">ตกลง</button>
                        </div>
                    </div>
                </div>
            </div>
            <script>document.addEventListener('DOMContentLoaded', function() { new bootstrap.Modal(document.getElementById('errorModal')).show(); });</script>
            <?php endif; ?>

            <?php if(empty($cartItems)): ?>
            <div class="card">
                <div class="card-body empty-cart">
                    <i class="fa fa-shopping-cart"></i>
                    <h5>ตะกร้าว่างเปล่า</h5>
                    <p class="text-muted">คุณยังไม่ได้เลือกอุปกรณ์ที่ต้องการยืม</p>
                    <a href="dashboard.php" class="btn btn-primary mt-3">
                        <i class="fa fa-search me-1"></i> เลือกอุปกรณ์
                    </a>
                </div>
            </div>
            <?php else: ?>
            
            <form method="post" id="checkoutForm">
                <input type="hidden" name="checkout" value="1">
                
                <div class="row">
                    <div class="col-lg-8 mb-4">
                        <div class="card">
                            <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="fa fa-list me-2"></i>รายการอุปกรณ์ (<?php echo $totalItems; ?> ชิ้น)</h6>
                            </div>
                            <div class="card-body" id="cartItemsContainer">
                                <?php foreach($cartItems as $item): 
                                    $maxDays = $item['MaxBorrowDays'] ?? 7;
                                    $finePerDay = $item['FinePerDay'] ?? 10;
                                    $defaultDueDate = date('Y-m-d', strtotime('+' . $maxDays . ' days'));
                                    $minDate = date('Y-m-d', strtotime('+1 day'));
                                ?>
                                <div class="cart-item" 
                                     data-id="<?php echo $item['id']; ?>" 
                                     data-max-days="<?php echo $maxDays; ?>" 
                                     data-fine="<?php echo $finePerDay; ?>">
                                    
                                    <!-- Header: ชื่อ + ปุ่มลบ -->
                                    <div class="cart-item-header">
                                        <div>
                                            <div class="cart-item-name"><?php echo htmlspecialchars($item['EquipmentName']); ?></div>
                                            <div class="cart-item-code">📦 รหัส: <?php echo htmlspecialchars($item['EquipmentCode']); ?></div>
                                            <span class="cart-item-category"><?php echo htmlspecialchars($item['CategoryName']); ?></span>
                                        </div>
                                        <button type="button" class="btn-delete-item" title="ลบรายการนี้">
                                            🗑️
                                        </button>
                                    </div>
                                    
                                    <!-- Body: จำนวน, กำหนดคืน, ค่าปรับ -->
                                    <div class="cart-item-body">
                                        <!-- จำนวน -->
                                        <div class="cart-field">
                                            <div class="cart-field-label">📦 จำนวน</div>
                                            <div class="qty-control">
                                                <button type="button" class="btn btn-outline-secondary btn-qty-minus">−</button>
                                                <input type="number" class="form-control qty-input" 
                                                       value="<?php echo $item['cart_qty']; ?>" 
                                                       min="1" max="<?php echo $item['Quantity'] + $item['cart_qty']; ?>"
                                                       data-max="<?php echo $item['Quantity'] + $item['cart_qty']; ?>" readonly>
                                                <button type="button" class="btn btn-outline-secondary btn-qty-plus">+</button>
                                            </div>
                                        </div>
                                        
                                        <!-- กำหนดคืน -->
                                        <div class="cart-field">
                                            <div class="cart-field-label">📅 กำหนดคืน</div>
                                            <input type="date" 
                                                   name="due_date_<?php echo $item['id']; ?>" 
                                                   class="form-control due-date-input" 
                                                   value="<?php echo $defaultDueDate; ?>"
                                                   min="<?php echo $minDate; ?>"
                                                   required>
                                            <div class="text-center">
                                                <div class="borrow-days normal" data-item-id="<?php echo $item['id']; ?>">
                                                    ⏰ <span class="days-text"><?php echo $maxDays; ?> วัน</span>
                                                </div>
                                                <div class="max-days-hint">
                                                    ℹ️ แนะนำ: ไม่เกิน <?php echo $maxDays; ?> วัน
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- ค่าปรับ/วัน -->
                                        <div class="cart-field">
                                            <div class="cart-field-label">💰 ค่าปรับ/วัน</div>
                                            <div class="text-center">
                                                <span class="fine-badge">
                                                    ฿<?php echo number_format($finePerDay, 0); ?>
                                                </span>
                                            </div>
                                            <div class="fine-info d-none" data-fine-info="<?php echo $item['id']; ?>">
                                                <small class="text-danger">
                                                    ⚠️ <span class="fine-text">เกินกำหนด: ฿0</span>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="card-footer bg-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <button type="button" class="btn btn-outline-danger btn-sm" id="btnClearCart">
                                        🗑️ ล้างตะกร้า
                                    </button>
                                    <small class="text-muted">
                                        ℹ️ 
                                        หากยืมเกินกำหนด จะมีค่าปรับตามอัตราที่ระบุ
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mt-3">
                            <div class="card-body">
                                <label class="form-label"><i class="fa fa-sticky-note me-1"></i> หมายเหตุ (ถ้ามี)</label>
                                <textarea name="notes" class="form-control" rows="2" placeholder="ระบุหมายเหตุเพิ่มเติม เช่น วัตถุประสงค์การยืม"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="student-info">
                            <h5><i class="fa fa-user me-2"></i>ข้อมูลผู้ยืม</h5>
                            <p><strong>รหัสนักศึกษา:</strong> <?php echo htmlspecialchars($studentInfo['StudentID']); ?></p>
                            <p><strong>ชื่อ-สกุล:</strong> <?php echo htmlspecialchars($studentInfo['Name'] . ' ' . $studentInfo['Surname']); ?></p>
                            <p class="mb-0"><strong>อีเมล:</strong> <?php echo htmlspecialchars($studentInfo['Email']); ?></p>
                        </div>
                        
                        <div class="card">
                            <div class="card-body summary-card">
                                <h6 class="mb-3"><i class="fa fa-file-text me-2"></i>สรุปรายการ</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>จำนวนรายการ:</span>
                                    <strong id="summaryItemCount"><?php echo count($cartItems); ?> รายการ</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>จำนวนชิ้นรวม:</span>
                                    <strong id="summaryTotalPcs"><?php echo $totalItems; ?> ชิ้น</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>วันที่ยืม:</span>
                                    <strong><?php echo date('d/m/Y'); ?></strong>
                                </div>
                                
                                <div id="fineWarning" class="total-fine-box d-none">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><i class="fa fa-exclamation-triangle me-1"></i> ค่าปรับโดยประมาณ:</span>
                                        <strong id="totalFineAmount">฿0</strong>
                                    </div>
                                    <small class="d-block mt-1 opacity-75">* หากคืนตามวันกำหนดที่เลือกไว้</small>
                                </div>
                                
                                <hr>
                                <button type="submit" class="btn btn-success w-100 btn-lg">
                                    <i class="fa fa-check me-2"></i> ยืนยันการยืม
                                </button>
                            </div>
                        </div>
                        
                        <div class="card mt-3">
                            <div class="card-body">
                                <h6 class="text-danger mb-2"><i class="fa fa-gavel me-1"></i> นโยบายค่าปรับ</h6>
                                <ul class="small text-muted mb-0" style="padding-left: 1.2rem;">
                                    <li>คืนเกินกำหนด คิดค่าปรับตามอัตราแต่ละอุปกรณ์</li>
                                    <li>ค่าปรับคำนวณเป็นรายวัน</li>
                                    <li>กรุณาคืนอุปกรณ์ตามกำหนดเพื่อหลีกเลี่ยงค่าปรับ</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fa fa-question-circle me-2"></i>ยืนยัน</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="fa fa-question-circle text-primary" style="font-size: 48px;"></i>
                    <p class="mt-3 mb-0 fs-5" id="confirmMessage"></p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-primary px-4" id="confirmOk">ตกลง</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="alertModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fa fa-exclamation-triangle me-2"></i>แจ้งเตือน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="fa fa-exclamation-triangle text-warning" style="font-size: 48px;"></i>
                    <p class="mt-3 mb-0 fs-5" id="alertMessage"></p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">ตกลง</button>
                </div>
            </div>
        </div>
    </div>

    <?php include('includes/footer.php');?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        var confirmCallback = null;
        var confirmModalEl = document.getElementById('confirmModal');
        var alertModalEl = document.getElementById('alertModal');
        var confirmModal = confirmModalEl ? new bootstrap.Modal(confirmModalEl) : null;
        var alertModal = alertModalEl ? new bootstrap.Modal(alertModalEl) : null;
        
        function showConfirm(message, callback) {
            if (!confirmModal) { if (confirm(message)) callback(); return; }
            $('#confirmMessage').html(message);
            confirmCallback = callback;
            confirmModal.show();
        }
        
        function showAlert(message) {
            if (!alertModal) { alert(message); return; }
            $('#alertMessage').text(message);
            alertModal.show();
        }
        
        $('#confirmOk').click(function() {
            confirmModal.hide();
            if(confirmCallback) confirmCallback();
        });

        function calculateDaysAndFine() {
            var totalFine = 0;
            var today = new Date();
            today.setHours(0,0,0,0);
            
            $('.cart-item').each(function() {
                var $item = $(this);
                var itemId = $item.data('id');
                var maxDays = parseInt($item.data('max-days')) || 7;
                var finePerDay = parseFloat($item.data('fine')) || 10;
                var qty = parseInt($item.find('.qty-input').val()) || 1;
                
                var dueDateStr = $item.find('.due-date-input').val();
                var dueDate = new Date(dueDateStr);
                dueDate.setHours(0,0,0,0);
                
                var diffTime = dueDate - today;
                var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                
                var $daysBadge = $item.find('.borrow-days');
                var $daysText = $daysBadge.find('.days-text');
                $daysText.text(diffDays + ' วัน');
                
                $daysBadge.removeClass('normal warning danger');
                $item.find('.due-date-input').removeClass('overdue');
                
                if (diffDays <= maxDays) {
                    $daysBadge.addClass('normal');
                } else if (diffDays <= maxDays + 3) {
                    $daysBadge.addClass('warning');
                } else {
                    $daysBadge.addClass('danger');
                    $item.find('.due-date-input').addClass('overdue');
                }
                
                var $fineInfo = $('[data-fine-info="' + itemId + '"]');
                if (diffDays > maxDays) {
                    var overDays = diffDays - maxDays;
                    var itemFine = overDays * finePerDay * qty;
                    totalFine += itemFine;
                    $fineInfo.removeClass('d-none');
                    $fineInfo.find('.fine-text').text('เกินกำหนด ' + overDays + ' วัน: ฿' + itemFine.toLocaleString());
                } else {
                    $fineInfo.addClass('d-none');
                }
            });
            
            if (totalFine > 0) {
                $('#fineWarning').removeClass('d-none');
                $('#totalFineAmount').text('฿' + totalFine.toLocaleString());
            } else {
                $('#fineWarning').addClass('d-none');
            }
        }
        
        calculateDaysAndFine();
        $(document).on('change', '.due-date-input', function() { calculateDaysAndFine(); });

        function updateQty(id, qty) {
            $.post('cart-actions.php', { action: 'update', id: id, quantity: qty }, function(response) {
                if(response.success) {
                    updateSummary();
                    updateCartBadge();
                    calculateDaysAndFine();
                } else {
                    showAlert(response.message || 'เกิดข้อผิดพลาด');
                    setTimeout(function() { location.reload(); }, 1500);
                }
            }, 'json');
        }
        
        function removeItem(id) {
            $.post('cart-actions.php', { action: 'remove', id: id }, function(response) {
                if(response.success) {
                    $('.cart-item[data-id="'+id+'"]').fadeOut(300, function() {
                        $(this).remove();
                        updateSummary();
                        updateCartBadge();
                        calculateDaysAndFine();
                        if($('.cart-item').length === 0) location.reload();
                    });
                }
            }, 'json');
        }
        
        function updateSummary() {
            let items = 0, total = 0;
            $('.cart-item').each(function() {
                items++;
                total += parseInt($(this).find('.qty-input').val()) || 0;
            });
            $('#summaryItemCount').text(items + ' รายการ');
            $('#summaryTotalPcs').text(total + ' ชิ้น');
        }
        
        function updateCartBadge() {
            $.post('cart-actions.php', { action: 'count' }, function(response) {
                var $badge = $('#cartBadge');
                if(response.count > 0) {
                    if($badge.length) $badge.text(response.count).show();
                } else {
                    $badge.hide();
                }
            }, 'json');
        }
        
        $(document).on('click', '.btn-qty-minus', function() {
            let input = $(this).siblings('.qty-input');
            let val = parseInt(input.val()) - 1;
            if(val >= 1) {
                input.val(val);
                let id = $(this).closest('.cart-item').data('id');
                updateQty(id, val);
            }
        });
        
        $(document).on('click', '.btn-qty-plus', function() {
            let input = $(this).siblings('.qty-input');
            let val = parseInt(input.val()) + 1;
            let max = parseInt(input.data('max'));
            if(val <= max) {
                input.val(val);
                let id = $(this).closest('.cart-item').data('id');
                updateQty(id, val);
            }
        });
        
        $(document).on('click', '.btn-delete-item', function() {
            let id = $(this).closest('.cart-item').data('id');
            showConfirm('ต้องการลบรายการนี้?', function() { removeItem(id); });
        });
        
        $('#btnClearCart').click(function() {
            showConfirm('ต้องการล้างตะกร้าทั้งหมด?', function() {
                $.post('cart-actions.php', { action: 'clear' }, function(response) {
                    if(response.success) location.reload();
                }, 'json');
            });
        });
        
        var checkoutPending = false;
        $('#checkoutForm').submit(function(e) {
            if(!checkoutPending) {
                e.preventDefault();
                var itemCount = $('.cart-item').length;
                var totalPcs = 0;
                $('.cart-item').each(function() {
                    totalPcs += parseInt($(this).find('.qty-input').val()) || 0;
                });
                
                var message = 'ยืนยันการยืมอุปกรณ์?<br><br>';
                message += '<strong>' + itemCount + ' รายการ (' + totalPcs + ' ชิ้น)</strong>';
                
                if (!$('#fineWarning').hasClass('d-none')) {
                    var totalFine = $('#totalFineAmount').text();
                    message += '<br><br><span class="text-danger"><i class="fa fa-exclamation-triangle"></i> หากไม่คืนตามกำหนดจะมีค่าปรับ ' + totalFine + '</span>';
                }
                
                showConfirm(message, function() {
                    checkoutPending = true;
                    $('#checkoutForm').submit();
                });
            }
        });
    });
    </script>
</body>
</html>
