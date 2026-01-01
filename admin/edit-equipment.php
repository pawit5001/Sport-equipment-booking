<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include('includes/config.php');

$targetDir = dirname(__DIR__) . "/uploads/";
$defaultImage = "no_image_available1.png";

if (strlen($_SESSION['alogin']) == 0) {
    header('location:../adminlogin.php');
    exit;
} else {
    if(isset($_POST['update'])) {
        $isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';
        $bookname = $_POST['bookname'];
        $category = $_POST['category'];
        $author = $_POST['author'];
        $quantity = $_POST['quantity'];
        $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
        $price = isset($_POST['price']) && $_POST['price'] !== '' ? (float)$_POST['price'] : 0;
        $bookid = intval($_GET['bookid']);

        // Check if EquipmentName already exists in the database
        $checkSql = "SELECT * FROM tblequipment WHERE EquipmentName = :bookname AND id != :bookid";
        $checkQuery = $dbh->prepare($checkSql);
        $checkQuery->bindParam(':bookname', $bookname, PDO::PARAM_STR);
        $checkQuery->bindParam(':bookid', $bookid, PDO::PARAM_INT);
        $checkQuery->execute();
        $count = $checkQuery->rowCount();

        if ($count > 0) {
            $_SESSION['admin_error'] = "ในระบบมีข้อมูลนี้อยู่แล้ว";
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => $_SESSION['admin_error']]);
                exit;
            }
            header('location:manage-equipment.php');
            exit;
        }

        try {
            $dbh->beginTransaction();

            // Upload new image if selected
            if(isset($_FILES['bookimage']['name']) && $_FILES['bookimage']['error'] === UPLOAD_ERR_OK) {
                $file_size = $_FILES['bookimage']['size'];
                $file_tmp = $_FILES['bookimage']['tmp_name'];
                $file_parts = explode('.', $_FILES['bookimage']['name']);
                $file_ext = strtolower(end($file_parts));
                $extensions = array("jpeg", "jpg", "png");

                if(!in_array($file_ext, $extensions)) {
                    throw new Exception('รูปแบบไฟล์ไม่ถูกต้อง, กรุณาเลือกไฟล์ JPEG หรือ PNG');
                }
                if($file_size > 2097152) {
                    throw new Exception('ไฟล์รูปภาพขนาดใหญ่เกินไป (ขนาดไม่เกิน 2 MB)');
                }

                $file_name = 'RMUTTO-IMG' . uniqid() . rand(100000, 999999) . '.' . $file_ext;
                $image_path = $targetDir . $file_name;

                // Delete old image if exists and not default
                $oldImage = $_POST['old_image'] ?? '';
                if ($oldImage && $oldImage !== $defaultImage) {
                    $oldPath = $targetDir . $oldImage;
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                }

                move_uploaded_file($file_tmp, $image_path);

                // Update the database with the new image
                $sql_update_image = "UPDATE tblequipment SET EquipmentImage=:bookimage WHERE id=:bookid";
                $query_update_image = $dbh->prepare($sql_update_image);
                $query_update_image->bindParam(':bookimage', $file_name, PDO::PARAM_STR);
                $query_update_image->bindParam(':bookid', $bookid, PDO::PARAM_INT);
                $query_update_image->execute();
            }

            // Update equipment details
            $sql = "UPDATE tblequipment SET EquipmentName=:bookname, CatId=:category, SupplierId=:author, Quantity=:quantity, IsActive=:isactive, Price=:price WHERE id=:bookid";
            $query = $dbh->prepare($sql);
            $query->bindParam(':bookname', $bookname, PDO::PARAM_STR);
            $query->bindParam(':category', $category, PDO::PARAM_STR);
            $query->bindParam(':author', $author, PDO::PARAM_STR);
            $query->bindParam(':quantity', $quantity, PDO::PARAM_INT);
            $query->bindParam(':isactive', $isActive, PDO::PARAM_INT);
            $query->bindParam(':price', $price);
            $query->bindParam(':bookid', $bookid, PDO::PARAM_INT);
            $query->execute();

            $dbh->commit();

            if ($isAjax) {
                // Don't set session message for AJAX - modal shown in client-side
                header('Content-Type: application/json');
                echo json_encode(['ok' => true, 'msg' => 'แก้ไขรายละเอียดสำเร็จ']);
                exit;
            }
            $_SESSION['admin_msg'] = "แก้ไขรายละเอียดสำเร็จ";
            header('location:manage-equipment.php');
            exit;
        } catch (Exception $ex) {
            if ($dbh->inTransaction()) {
                $dbh->rollBack();
            }
            $_SESSION['admin_error'] = 'ปรับปรุงล้มเหลว: ' . $ex->getMessage();
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => $_SESSION['admin_error']]);
                exit;
            }
            header('location:manage-equipment.php');
            exit;
        }
    }

    $bookid=intval($_GET['bookid']);
    $sql = "SELECT tblequipment.EquipmentName, tblcategory.CategoryName, tblcategory.id as cid, tblsuppliers.SupplierName, tblsuppliers.id as athrid,
                   tblequipment.EquipmentCode, tblequipment.Quantity, tblequipment.EquipmentImage, tblequipment.id as bookid,
                   tblequipment.IsActive, tblequipment.Price
            FROM tblequipment
            JOIN tblcategory ON tblcategory.id=tblequipment.CatId
            JOIN tblsuppliers ON tblsuppliers.id=tblequipment.SupplierId
            WHERE tblequipment.id=:bookid";

    $query = $dbh->prepare($sql);
    $query->bindParam(':bookid', $bookid, PDO::PARAM_INT);
    $query->execute();
    $result = $query->fetch(PDO::FETCH_OBJ);
}
?>


<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>E-Sports | Edit Equipment</title>
    <!-- BOOTSTRAP CORE STYLE  -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <!-- FONT AWESOME STYLE  -->
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <!-- CUSTOM STYLE  -->
    <link href="assets/css/style.css" rel="stylesheet" />
    <!-- MODERN STYLE (shared at root assets) -->
    <link href="../assets/css/modern-style.css" rel="stylesheet" />
    <!-- GOOGLE FONT -->
    <link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css' />
</head>
<body>
    <!------MENU SECTION START-->
    <?php include('includes/header.php');?>
    <!-- MENU SECTION END-->
    
    <div class="content-wrapper" style="margin-top: 60px; padding-bottom: 40px;">
        <div class="container">
            <div class="row pad-botm">
                <div class="col-md-12">
                    <h4 class="header-line">แก้ไขรายละเอียดอุปกรณ์กีฬา</h4>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">รายละเอียดอุปกรณ์</div>
                        <div class="card-body">
                            <form class="row g-3" role="form" method="post" enctype="multipart/form-data" data-no-auto-loading="true">
                                <?php if ($result): ?>
                                    <div class="col-md-6">
                                        <label class="form-label">ชื่ออุปกรณ์กีฬา <span style="color:red;">*</span></label>
                                        <input class="form-control" type="text" name="bookname" value="<?php echo htmlentities($result->EquipmentName);?>" required />
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">หมวดหมู่<span style="color:red;">*</span></label>
                                        <select class="form-select" name="category" required>
                                            <option value="<?php echo htmlentities($result->cid);?>"><?php echo htmlentities($result->CategoryName);?></option>
                                            <?php 
                                            $resultss = [];
                                            try {
                                                $status=1;
                                                $sql1 = "SELECT * FROM tblcategory WHERE Status=:status";
                                                $query1 = $dbh->prepare($sql1);
                                                $query1->bindParam(':status',$status, PDO::PARAM_INT);
                                                $query1->execute();
                                                $resultss = $query1->fetchAll(PDO::FETCH_OBJ);
                                            } catch (Exception $ex) {
                                                // Fallback if Status column doesn't exist
                                                $sql1 = "SELECT * FROM tblcategory";
                                                $query1 = $dbh->prepare($sql1);
                                                $query1->execute();
                                                $resultss = $query1->fetchAll(PDO::FETCH_OBJ);
                                            }

                                            if(!empty($resultss)) {
                                                foreach($resultss as $row) {           
                                                    if($result->cid == $row->id) {
                                                        continue;
                                                    } else { ?>
                                                        <option value="<?php echo htmlentities($row->id);?>"><?php echo htmlentities($row->CategoryName);?></option>
                                                    <?php }
                                                }
                                            } ?> 
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">ผู้รับผิดชอบ<span style="color:red;">*</span></label>
                                        <select class="form-select" name="author" required>
                                            <option value="<?php echo htmlentities($result->athrid);?>"><?php echo htmlentities($result->SupplierName);?></option>
                                            <?php 
                                            $result2 = [];
                                            try {
                                                $supStatus = 1;
                                                $sql2 = "SELECT * FROM tblsuppliers WHERE Status=:status";
                                                $query2 = $dbh->prepare($sql2);
                                                $query2->bindParam(':status', $supStatus, PDO::PARAM_INT);
                                                $query2->execute();
                                                $result2=$query2->fetchAll(PDO::FETCH_OBJ);
                                            } catch (Exception $ex) {
                                                // Fallback if Status column doesn't exist
                                                $sql2 = "SELECT * FROM tblsuppliers";
                                                $query2 = $dbh->prepare($sql2);
                                                $query2->execute();
                                                $result2=$query2->fetchAll(PDO::FETCH_OBJ);
                                            }

                                            if(!empty($result2)) {
                                                foreach($result2 as $ret) {           
                                                    if($result->athrid == $ret->id) {
                                                        continue;
                                                    } else { ?>  
                                                        <option value="<?php echo htmlentities($ret->id);?>"><?php echo htmlentities($ret->SupplierName);?></option>
                                                    <?php }
                                                }
                                            } ?> 
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">รหัสอุปกรณ์กีฬา</label>
                                        <input class="form-control" type="text" value="<?php echo isset($result->EquipmentCode) ? htmlentities($result->EquipmentCode) : ''; ?>" readonly style="background-color: #f0f0f0; font-family: monospace; font-weight: bold;" />
                                        <small class="text-muted">รหัสอุปกรณ์ถูกสร้างอัตโนมัติและไม่สามารถเปลี่ยนแปลงได้</small>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">จำนวน<span style="color:red;">*</span></label>
                                        <input class="form-control" type="number" name="quantity" value="<?php echo htmlentities($result->Quantity);?>" required max="999" />
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">สถานะความพร้อม</label>
                                        <select class="form-select" name="is_active">
                                            <option value="1" <?php echo $result->IsActive ? 'selected' : ''; ?>>พร้อมให้ยืม</option>
                                            <option value="0" <?php echo !$result->IsActive ? 'selected' : ''; ?>>ปิดการยืมชั่วคราว</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">ราคาอุปกรณ์ (บาท) <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text">฿</span>
                                            <input class="form-control" type="number" name="price" step="0.01" min="0" value="<?php echo htmlentities(number_format(($result->Price ?? 0), 2, '.', '')); ?>" required />
                                        </div>
                                        <small class="text-muted">ใช้สำหรับคำนวณค่าชดเชยกรณีชำรุด/สูญหาย</small>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label">รูปภาพปัจจุบัน</label>
                                        <div class="mt-2">
                                            <img src="/E-Sports/uploads/<?php echo htmlentities($result->EquipmentImage ?: $defaultImage);?>" alt="equipment" style="max-width: 200px; height: auto;" class="rounded border">
                                        </div>
                                        <input type="hidden" name="old_image" value="<?php echo htmlentities($result->EquipmentImage ?: $defaultImage);?>">
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label">อัปโหลดรูปภาพใหม่</label>
                                        <input class="form-control" type="file" name="bookimage" accept="image/*" />
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-danger">ไม่พบข้อมูลอุปกรณ์</div>
                                <?php endif; ?>
                                <div class="col-12 d-flex gap-2 mt-3" style="gap: 0.5rem !important;">
                                    <a href="manage-equipment.php" class="btn btn-outline-secondary"><i class="fa fa-arrow-left"></i> ย้อนกลับ</a>
                                    <button type="submit" name="update" class="btn btn-success"><i class="fa fa-save"></i> แก้ไขรายละเอียดอุปกรณ์</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include('includes/footer.php');?>
    
    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">สำเร็จ</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="successModalBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">ตรวจสอบรายการ</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="errorModalLabel">ข้อผิดพลาด</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="errorModalBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Confirm Update Modal -->
    <div class="modal fade" id="confirmUpdateModal" tabindex="-1" aria-labelledby="confirmUpdateLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="confirmUpdateLabel">ยืนยันการแก้ไข</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">คุณแน่ใจหรือว่าต้องการบันทึกการแก้ไข?</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-success" id="confirmUpdateBtn">ยืนยันการแก้ไข</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- FOOTER SECTION END-->
    <!-- JAVASCRIPT FILES PLACED AT THE BOTTOM TO REDUCE THE LOADING TIME  -->
    <!-- CORE JQUERY  -->
    <script src="assets/js/jquery-1.10.2.js?v=2"></script>
    <!-- BOOTSTRAP SCRIPTS  -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- CUSTOM SCRIPTS  -->
    <script src="assets/js/custom.js?v=2"></script>
    <!-- MODERN INTERACTIONS (shared at root assets) -->
    <script src="../assets/js/interactions.js?v=2"></script>
    <script>
    $(document).ready(function() {
        var $form = $('form[method="post"]');
        var $btn = $form.find('button[name="update"]');
        var confirmModalEl = document.getElementById('confirmUpdateModal');
        var confirmModal = confirmModalEl ? new bootstrap.Modal(confirmModalEl) : null;
        var pendingFormData = null;

        $form.on('submit', function(e) {
            e.preventDefault();
            console.log('Form submit intercepted - showing confirm modal');
            
            // Store form data for later submission
            pendingFormData = new FormData(this);
            pendingFormData.append('ajax', '1');
            pendingFormData.append('update', '1');
            
            // Show confirmation modal
            if (confirmModal) {
                confirmModal.show();
            }
        });

        $('#confirmUpdateBtn').on('click', function() {
            if (!pendingFormData) return;
            
            console.log('AJAX form submit started');
            
            // Close confirmation modal
            if (confirmModal) confirmModal.hide();

            if ($btn.length) {
                $btn.prop('disabled', true).addClass('disabled');
            }

            $.ajax({
                url: window.location.href,
                type: 'POST',
                dataType: 'json',
                data: pendingFormData,
                processData: false,
                contentType: false,
                success: function(resp) {
                    console.log('AJAX response:', resp);

                    if (resp && resp.ok) {
                        $('#successModalBody').text(resp.msg || 'แก้ไขสำเร็จ');
                        var successModal = new bootstrap.Modal(document.getElementById('successModal'));
                        successModal.show();
                        // Redirect to manage-equipment.php after modal is hidden
                        $('#successModal').on('hidden.bs.modal', function () {
                            window.location.href = 'manage-equipment.php';
                        });
                    } else {
                        var msg = (resp && resp.error) ? resp.error : 'เกิดข้อผิดพลาด';
                        $('#errorModalBody').text(msg);
                        var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                        errorModal.show();
                        if ($btn.length) {
                            $btn.prop('disabled', false).removeClass('disabled');
                        }
                    }
                },
                error: function(xhr) {
                    console.error('AJAX error', xhr.status, xhr.responseText);
                    $('#errorModalBody').text('เกิดข้อผิดพลาดในการส่งข้อมูล');
                    var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                    errorModal.show();
                    if ($btn.length) {
                        $btn.prop('disabled', false).removeClass('disabled');
                    }
                },
                complete: function() {
                    pendingFormData = null;
                }
            });
        });
    });
    </script>
</body>
</html>
