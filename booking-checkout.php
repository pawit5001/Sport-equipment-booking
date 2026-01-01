<?php
session_start();
error_reporting(0);
include('includes/config.php');

if (strlen($_SESSION['login']) == 0) {
    header('location:index.php');
} else {
    $memberId = $_SESSION['stdid'];
    
    // Get cart items
    $sql = "SELECT bc.CartId, bc.EquipmentId, bc.Quantity, 
            e.EquipmentName, e.EquipmentCode, e.MaxBorrowDays
            FROM tblbooking_cart bc
            JOIN tblequipment e ON bc.EquipmentId = e.id
            WHERE bc.MemberId = :member
            ORDER BY bc.AddedAt DESC";
    
    $query = $dbh->prepare($sql);
    $query->bindParam(':member', $memberId, PDO::PARAM_INT);
    $query->execute();
    $cartItems = $query->fetchAll(PDO::FETCH_OBJ);
    
    if(count($cartItems) == 0) {
        header('location:book-equipment-v2.php');
        exit;
    }
    
    // Handle booking submission
    if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_booking') {
        try {
            $returnDate = $_POST['return_date'] ?? date('Y-m-d', strtotime('+7 days'));
            $notes = $_POST['notes'] ?? '';
            
            // Validate return date
            $returnDateTime = strtotime($returnDate);
            if($returnDateTime === false || $returnDateTime <= time()) {
                throw new Exception('วันที่คืนไม่ถูกต้อง');
            }
            
            // Start transaction
            $dbh->beginTransaction();
            
            // Create booking order
            $sql = "INSERT INTO tblbooking_order (MemberId, PlannedReturnDate, TotalItems, Notes, Status) 
                    VALUES (:member, :return_date, :total_items, :notes, 'pending')";
            $query = $dbh->prepare($sql);
            $query->bindParam(':member', $memberId, PDO::PARAM_INT);
            $query->bindParam(':return_date', $returnDate, PDO::PARAM_STR);
            $query->bindParam(':total_items', count($cartItems), PDO::PARAM_INT);
            $query->bindParam(':notes', $notes, PDO::PARAM_STR);
            $query->execute();
            
            $bookingId = $dbh->lastInsertId();
            
            // Add booking items
            foreach($cartItems as $item) {
                $plannedReturnDateTime = date('Y-m-d H:i:s', strtotime($returnDate));
                
                $sql = "INSERT INTO tblbooking_items (BookingId, EquipmentId, Quantity, PlannedReturnDate) 
                        VALUES (:booking_id, :equipment_id, :quantity, :planned_return)";
                $query = $dbh->prepare($sql);
                $query->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
                $query->bindParam(':equipment_id', $item->EquipmentId, PDO::PARAM_INT);
                $query->bindParam(':quantity', $item->Quantity, PDO::PARAM_INT);
                $query->bindParam(':planned_return', $plannedReturnDateTime, PDO::PARAM_STR);
                $query->execute();
            }
            
            // Clear cart
            $sql = "DELETE FROM tblbooking_cart WHERE MemberId = :member";
            $query = $dbh->prepare($sql);
            $query->bindParam(':member', $memberId, PDO::PARAM_INT);
            $query->execute();
            
            // Commit transaction
            $dbh->commit();
            
            // Redirect to booking confirmation
            header('location:booking-confirmation.php?booking_id=' . $bookingId);
            exit;
            
        } catch(Exception $e) {
            $dbh->rollBack();
            $error = $e->getMessage();
        }
    }
    
    // Get member info
    $sql = "SELECT Email, Name, Surname FROM tblmembers WHERE id = :id";
    $query = $dbh->prepare($sql);
    $query->bindParam(':id', $memberId, PDO::PARAM_INT);
    $query->execute();
    $member = $query->fetch(PDO::FETCH_OBJ);
    
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>E-Sports | สรุปการยืม</title>
    <!-- BOOTSTRAP 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <!-- FONT AWESOME STYLE  -->
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <!-- CUSTOM STYLE  -->
    <link href="assets/css/style.css" rel="stylesheet" />
    <!-- MODERN STYLE -->
    <link href="assets/css/modern-style.css" rel="stylesheet" />
    <style>
        :root {
            --primary: #1e40af;
            --secondary: #0891b2;
            --success: #10b981;
        }

        body {
            background: linear-gradient(135deg, #f5f3ff 0%, #faf8f3 100%);
            min-height: 100vh;
        }

        .hero-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 2.5rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(30, 64, 175, 0.2);
            border-radius: 0 0 1rem 1rem;
        }

        .checkout-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .checkout-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 3px solid var(--primary);
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1rem;
        }

        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .item-row:last-child {
            border-bottom: none;
        }

        .item-info {
            flex: 1;
        }

        .item-name {
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .item-code {
            font-size: 0.85rem;
            color: #94a3b8;
        }

        .item-qty {
            background: #f1f5f9;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            color: var(--primary);
            min-width: 100px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 0.75rem;
            font-size: 1rem;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(30, 64, 175, 0.1);
        }

        .member-info {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary);
        }

        .member-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            color: #64748b;
        }

        .member-row strong {
            color: #1e293b;
        }

        .summary-box {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            padding: 1.5rem;
            border-radius: 0.75rem;
            border-left: 4px solid var(--primary);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            color: #64748b;
        }

        .summary-row strong {
            color: #1e293b;
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 0.75rem;
            font-weight: 700;
            font-size: 1.05rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-back {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #e2e8f0;
            color: #1e293b;
            text-decoration: none;
            border-radius: 0.75rem;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-back:hover {
            background: #cbd5e1;
        }

        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1rem;
            border-left: 4px solid #dc2626;
        }

        @media (max-width: 768px) {
            .checkout-card {
                padding: 1.5rem;
            }

            .item-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .item-qty {
                width: 100%;
                text-align: left;
            }
        }
    </style>

</head>
<body>
    <!------MENU SECTION START-->
    <?php include('includes/header.php'); ?>
    <!-- MENU SECTION END-->

    <!-- HERO SECTION -->
    <div class="hero-section">
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <h1 style="font-size: 2rem; font-weight: 700; margin: 0;">
                        <i class="fa fa-check-circle"></i> สรุปการยืม
                    </h1>
                    <p style="margin: 0.5rem 0 0; opacity: 0.9;">ตรวจสอบข้อมูลและยืนยันการยืม</p>
                </div>
            </div>
        </div>
    </div>

    <div class="content-wrapper">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 offset-lg-2">
                    <?php if(isset($error)) { ?>
                    <div class="error-message">
                        <i class="fa fa-exclamation-circle"></i> <?php echo htmlentities($error); ?>
                    </div>
                    <?php } ?>

                    <!-- Member Info -->
                    <div class="checkout-card">
                        <div class="checkout-title">
                            <i class="fa fa-user-circle"></i> ข้อมูลของคุณ
                        </div>
                        <div class="member-info">
                            <div class="member-row">
                                <span>ชื่อ:</span>
                                <strong><?php echo htmlentities($member->Name . ' ' . $member->Surname); ?></strong>
                            </div>
                            <div class="member-row">
                                <span>อีเมล:</span>
                                <strong><?php echo htmlentities($member->Email); ?></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Items Summary -->
                    <div class="checkout-card">
                        <div class="checkout-title">
                            <i class="fa fa-list"></i> รายการอุปกรณ์ที่ยืม
                        </div>

                        <?php foreach($cartItems as $item) { ?>
                        <div class="item-row">
                            <div class="item-info">
                                <div class="item-name"><?php echo htmlentities($item->EquipmentName); ?></div>
                                <div class="item-code">รหัส: <?php echo htmlentities($item->EquipmentCode); ?></div>
                            </div>
                            <div class="item-qty">
                                <i class="fa fa-cube"></i> <?php echo htmlentities($item->Quantity); ?> ชิ้น
                            </div>
                        </div>
                        <?php } ?>

                        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 2px solid #e2e8f0;">
                            <strong style="color: #1e293b;">รวมทั้งสิ้น <?php echo count($cartItems); ?> รายการ</strong>
                        </div>
                    </div>

                    <!-- Booking Details -->
                    <div class="checkout-card">
                        <div class="checkout-title">
                            <i class="fa fa-calendar"></i> รายละเอียดการยืม
                        </div>

                        <form method="POST" onsubmit="return confirm('ยืนยันการยืมอุปกรณ์?');">
                            <input type="hidden" name="action" value="confirm_booking">

                            <div class="form-group">
                                <label class="form-label">วันที่ต้องการคืน *</label>
                                <input type="date" name="return_date" class="form-control" required
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                       value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                                <small style="color: #94a3b8;">
                                    กรุณาเลือกวันที่ต้องการคืนอุปกรณ์
                                </small>
                            </div>

                            <div class="form-group">
                                <label class="form-label">หมายเหตุเพิ่มเติม</label>
                                <textarea name="notes" class="form-control" rows="3" 
                                          placeholder="เช่น เหตุผลการยืม หรือข้อมูลพิเศษ..."></textarea>
                            </div>

                            <div class="summary-box">
                                <div class="summary-row">
                                    <span>วันที่ยืม:</span>
                                    <strong><?php echo date('d/m/Y'); ?></strong>
                                </div>
                                <div class="summary-row">
                                    <span>วันที่คืน (ตามที่เลือก):</span>
                                    <strong id="returnDateDisplay"><?php echo date('d/m/Y', strtotime('+7 days')); ?></strong>
                                </div>
                                <div class="summary-row">
                                    <span>ระยะเวลาการยืม:</span>
                                    <strong id="borrowDays">7 วัน</strong>
                                </div>
                                <div class="summary-row" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(30, 64, 175, 0.1);">
                                    <span style="color: var(--primary);">⚠️ ข้อสำคัญ:</span>
                                </div>
                                <div style="font-size: 0.85rem; color: #64748b; margin-top: 0.5rem;">
                                    <p style="margin: 0.25rem 0;">• เก็บรักษาอุปกรณ์ให้ดี ห้ามทำให้เสียหาย</p>
                                    <p style="margin: 0.25rem 0;">• คืนอุปกรณ์ได้ตามวันที่กำหนด หรือก่อนวันนั้น</p>
                                    <p style="margin: 0.25rem 0;">• หากคืนล่าช้า จะมีค่าปรับ 50 บาท/วัน</p>
                                </div>
                            </div>

                            <button type="submit" class="btn-submit">
                                <i class="fa fa-check-circle"></i> ยืนยันการยืม
                            </button>
                        </form>
                    </div>

                    <div style="text-align: center; margin-top: 1rem;">
                        <a href="book-equipment-v2.php" class="btn-back">
                            <i class="fa fa-arrow-left"></i> กลับไปเลือกอุปกรณ์
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include('includes/footer.php'); ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Update return date display
        document.querySelector('input[name="return_date"]').addEventListener('change', function() {
            const returnDate = new Date(this.value);
            const todayDate = new Date();
            
            const diffTime = returnDate - todayDate;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            document.getElementById('returnDateDisplay').textContent = 
                returnDate.toLocaleDateString('th-TH', { year: 'numeric', month: '2-digit', day: '2-digit' });
            document.getElementById('borrowDays').textContent = diffDays + ' วัน';
        });
    </script>
</body>
</html>
<?php } ?>
