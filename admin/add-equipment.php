<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include('includes/config.php');

$targetDir = dirname(__DIR__) . "/uploads/";
$defaultImage = "no_image_available1.png"; // ชื่อรูปภาพที่ใช้เป็นค่าเริ่มต้น

if (strlen($_SESSION['alogin']) == 0) {
    header('location:../adminlogin.php');
    exit;
} else { 
    if(isset($_POST['add'])) {
        $isAjax = (isset($_POST['ajax']) && $_POST['ajax'] == '1') ||
                  (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');
        $bookname = $_POST['bookname'];
        $category = $_POST['category'];
        $author = $_POST['author'];
        $quantity = $_POST['quantity'];
        
        // Auto-generate 13-digit equipment code: CategoryID(4) + SupplierID(4) + Sequential(5)
        $catCode = str_pad($category, 4, '0', STR_PAD_LEFT);
        $suppCode = str_pad($author, 4, '0', STR_PAD_LEFT);
        
        // Find next sequential number for this category+supplier combination
        $seqSql = "SELECT MAX(CAST(SUBSTRING(EquipmentCode, 9, 5) AS UNSIGNED)) as maxseq 
                   FROM tblequipment 
                   WHERE SUBSTRING(EquipmentCode, 1, 4) = :cat 
                   AND SUBSTRING(EquipmentCode, 5, 4) = :supp";
        $seqQuery = $dbh->prepare($seqSql);
        $seqQuery->bindParam(':cat', $catCode, PDO::PARAM_STR);
        $seqQuery->bindParam(':supp', $suppCode, PDO::PARAM_STR);
        $seqQuery->execute();
        $seqResult = $seqQuery->fetch(PDO::FETCH_ASSOC);
        $nextSeq = ($seqResult && $seqResult['maxseq']) ? (int)$seqResult['maxseq'] + 1 : 1;
        $seqCode = str_pad($nextSeq, 5, '0', STR_PAD_LEFT);
        
        $isbn = $catCode . $suppCode . $seqCode;
        $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
        $price = isset($_POST['price']) && $_POST['price'] !== '' ? (float)$_POST['price'] : 0;

        // Check if EquipmentName already exists in the database
        $checkSql = "SELECT * FROM tblequipment WHERE EquipmentName = :bookname";
        $checkQuery = $dbh->prepare($checkSql);
        $checkQuery->bindParam(':bookname', $bookname, PDO::PARAM_STR);
        $checkQuery->execute();
        $count = $checkQuery->rowCount();

        if ($count > 0) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => false, 'error' => 'ในระบบมีข้อมูลนี้อยู่แล้ว']);
                exit;
            }
            $_SESSION['admin_error'] = "ในระบบมีข้อมูลนี้อยู่แล้ว";
            header('location:manage-equipment.php');
            exit();
        }

        // Set the default image if no image is uploaded
        $file_name_with_ext = $defaultImage;

        // Check if file is uploaded successfully
        if(isset($_FILES['bookimage']) && $_FILES['bookimage']['error'] === UPLOAD_ERR_OK) {
            // Generate a unique ID starting with "RMUTTO-IMG"
            $file_name = 'RMUTTO-IMG' . uniqid() . rand(100000, 999999);

            $file_size = $_FILES['bookimage']['size'];
            $file_tmp = $_FILES['bookimage']['tmp_name'];
            $file_parts = explode('.', $_FILES['bookimage']['name']);
            $file_ext = strtolower(end($file_parts));

            $extensions = array("jpeg", "jpg", "png");

            if(!in_array($file_ext, $extensions)) {
                $_SESSION['admin_error'] = "รูปแบบไฟล์ไม่ถูกต้อง, กรุณาเลือกไฟล์ JPEG หรือ PNG.";
                header('location:manage-equipment.php');
                exit();
            }

            if($file_size > 2097152) {
                $_SESSION['admin_error'] = 'ไฟล์รูปภาพขนาดใหญ่เกินไป (ขนาดไม่เกิน 2 MB)';
                header('location:manage-equipment.php');
                exit();
            }

            // Combine the generated filename with the file extension
            $file_name_with_ext = $file_name . '.' . $file_ext;
            // Combine the target directory with the complete filename
            $image_path = $targetDir . $file_name_with_ext;

            // Move the uploaded file to the target directory with the generated filename
            move_uploaded_file($file_tmp, $image_path);
        }

        try {
            $dbh->beginTransaction();

            $sql = "INSERT INTO tblequipment(EquipmentName, CatId, SupplierId, EquipmentCode, Quantity, EquipmentImage, IsActive, Price)
                    VALUES(:bookname, :category, :author, :isbn, :quantity, :image, :isactive, :price)";
            $query = $dbh->prepare($sql);
            $query->bindParam(':bookname', $bookname, PDO::PARAM_STR);
            $query->bindParam(':category', $category, PDO::PARAM_STR);
            $query->bindParam(':author', $author, PDO::PARAM_STR);
            $query->bindParam(':isbn', $isbn, PDO::PARAM_STR);
            $query->bindParam(':quantity', $quantity, PDO::PARAM_INT);
            $query->bindParam(':image', $file_name_with_ext, PDO::PARAM_STR);
            $query->bindParam(':isactive', $isActive, PDO::PARAM_INT);
            $query->bindParam(':price', $price);
            $query->execute();

            $lastInsertId = $dbh->lastInsertId();
            if(!$lastInsertId) {
                throw new Exception('ไม่สามารถเพิ่มอุปกรณ์ได้');
            }

            $dbh->commit();

            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => true, 'id' => $lastInsertId, 'msg' => 'อุปกรณ์เพิ่มสำเร็จ']);
                exit;
            }

            $_SESSION['admin_msg'] = "อุปกรณ์กีฬาได้ถูกเพิ่มลงในรายการแล้ว";
            header('location:manage-equipment.php');
            exit();
        } catch (Exception $ex) {
            if ($dbh->inTransaction()) {
                $dbh->rollBack();
            }
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => false, 'error' => 'เกิดข้อผิดพลาดบางอย่าง', 'detail' => $ex->getMessage()]);
                exit;
            }
            $_SESSION['admin_error'] = "เกิดข้อผิดพลาดบางอย่าง กรุณาลองใหม่อีกครั้ง: " . $ex->getMessage();
            header('location:manage-equipment.php');
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>E-Sports | Add Equipment</title>
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
    <?php include('includes/header.php');?>
    <div class="content-wrapper" style="margin-top: 60px; padding-bottom: 40px;">
        <div class="container">
            <div class="row pad-botm">
                <div class="col-md-12">
                    <h4 class="header-line">เพิ่มอุปกรณ์กีฬา</h4>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">รายละเอียดอุปกรณ์</div>
                        <div class="card-body">
                            <form class="row g-3" role="form" method="post" enctype="multipart/form-data" novalidate>
                                <div class="col-md-6">
                                    <label class="form-label">ชื่ออุปกรณ์กีฬา<span style="color:red;">*</span></label>
                                    <input class="form-control" type="text" name="bookname" placeholder="เช่น ลูกฟุตบอล, ไม้เทนนิส" required />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">หมวดหมู่<span style="color:red;">*</span></label>
                                    <select class="form-select" name="category" id="categorySelect" required>
                                        <option value=""> เลือกหมวดหมู่ </option>
                                        <?php
                                        $status=1;
                                        $sql = "SELECT * from  tblcategory where Status=:status";
                                        $query = $dbh -> prepare($sql);
                                        $query -> bindParam(':status',$status, PDO::PARAM_STR);
                                        $query->execute();
                                        $results=$query->fetchAll(PDO::FETCH_OBJ);
                                        if($query->rowCount() > 0) {
                                            foreach($results as $result) {
                                        ?>
                                        <option value="<?php echo htmlentities($result->id);?>"><?php echo htmlentities($result->CategoryName);?></option>
                                        <?php }} ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ผู้รับผิดชอบ<span style="color:red;">*</span></label>
                                    <select class="form-select" name="author" id="supplierSelect" required>
                                        <option value="">เลือกผู้รับผิดชอบ</option>
                                        <?php
                                        $sql = "SELECT * from  tblsuppliers ";
                                        $query = $dbh -> prepare($sql);
                                        $query->execute();
                                        $results=$query->fetchAll(PDO::FETCH_OBJ);
                                        if($query->rowCount() > 0) {
                                            foreach($results as $result) {
                                        ?>
                                        <option value="<?php echo htmlentities($result->id);?>"><?php echo htmlentities($result->SupplierName);?></option>
                                        <?php }} ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">จำนวน<span style="color:red;">*</span></label>
                                    <input class="form-control" type="number" name="quantity" placeholder="0" value="0" required min="0" max="999" />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">สถานะความพร้อม</label>
                                    <select class="form-select" name="is_active">
                                        <option value="1" selected>พร้อมให้ยืม</option>
                                        <option value="0">ปิดการยืมชั่วคราว</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ราคาอุปกรณ์ (บาท) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">฿</span>
                                        <input class="form-control" type="number" name="price" step="0.01" min="0" value="0" required />
                                    </div>
                                    <small class="text-muted">ใช้สำหรับคำนวณค่าชดเชยกรณีชำรุด/สูญหาย</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">อัปโหลดรูปภาพ</label>
                                    <input class="form-control" type="file" name="bookimage" accept="image/*" />
                                </div>
                                <div class="col-12 d-flex gap-2 mt-3" style="gap: 0.5rem !important;">
                                    <a href="manage-equipment.php" class="btn btn-outline-secondary"><i class="fa fa-arrow-left"></i> ย้อนกลับ</a>
                                    <button type="submit" name="add" class="btn btn-success"><i class="fa fa-save"></i> เพิ่มอุปกรณ์กีฬา</button>
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
    
    <script src="assets/js/jquery-1.10.2.js?v=2"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/custom.js?v=2"></script>
    <!-- MODERN INTERACTIONS (shared at root assets) -->
    <script src="../assets/js/interactions.js?v=2"></script>
    <script>
    console.log('=== Add Equipment Page Loaded ===');
    console.log('jQuery version:', typeof jQuery !== 'undefined' ? jQuery.fn.jquery : 'Not loaded');

    // AJAX submit to avoid full page refresh
    $(function(){
        var $form = $('form[method="post"]');
        $form.on('submit', function(e){
            e.preventDefault();
            
            // Custom validation
            var bookname = $('input[name="bookname"]').val().trim();
            var category = $('select[name="category"]').val();
            var author = $('select[name="author"]').val();
            var quantity = $('input[name="quantity"]').val();
            
            if (!bookname) {
                $('#errorModalBody').text('กรุณากรอกชื่ออุปกรณ์กีฬา');
                var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                errorModal.show();
                return;
            }
            
            if (!category) {
                $('#errorModalBody').text('กรุณาเลือกหมวดหมู่');
                var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                errorModal.show();
                return;
            }
            
            if (!author) {
                $('#errorModalBody').text('กรุณาเลือกผู้รับผิดชอบ');
                var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                errorModal.show();
                return;
            }
            
            if (!quantity || quantity < 0) {
                $('#errorModalBody').text('กรุณากรอกจำนวนที่ถูกต้อง');
                var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                errorModal.show();
                return;
            }
            
            var formData = new FormData(this);
            formData.append('ajax', '1');
            formData.append('add', '1');
            var $submit = $(this).find('button[type="submit"]');
            $submit.prop('disabled', true).addClass('disabled').html('<i class="fa fa-spinner fa-spin me-1"></i> กำลังบันทึก...');
            $.ajax({
                url: 'add-equipment.php',
                method: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json',
                timeout: 20000,
                success: function(res){
                    console.log('Add equipment response:', res);
                    if (res && res.ok) {
                        $('#successModalBody').text('เพิ่มอุปกรณ์สำเร็จ');
                        var successModal = new bootstrap.Modal(document.getElementById('successModal'));
                        successModal.show();
                        $form[0].reset();
                        // Redirect to manage-equipment.php after modal is hidden
                        $('#successModal').on('hidden.bs.modal', function () {
                            window.location.href = 'manage-equipment.php';
                        });
                    } else {
                        var msg = (res && res.error) ? res.error : 'เพิ่มอุปกรณ์ไม่สำเร็จ';
                        if (res && res.detail) msg += ' (' + res.detail + ')';
                        $('#errorModalBody').text(msg);
                        var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                        errorModal.show();
                    }
                },
                error: function(jqXHR, textStatus, errorThrown){
                    console.error('Add equipment AJAX error', {status: jqXHR.status, textStatus, error: errorThrown, response: jqXHR.responseText});
                    $('#errorModalBody').text('เครือข่ายมีปัญหา ลองใหม่อีกครั้ง');
                    var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                    errorModal.show();
                },
                complete: function(){
                    $submit.removeClass('disabled').prop('disabled', false).html('<i class="fa fa-save"></i> เพิ่มอุปกรณ์กีฬา');
                }
            });
        });
    });
    </script>
</body>
</html>
