<?php
session_start();
require_once('includes/config.php');

// Authentication check
if (!isset($_SESSION['alogin']) || strlen($_SESSION['alogin']) == 0) {
    header('location:../adminlogin.php');
    exit;
}

// Handle form submission (AJAX)
if (!empty($_POST['add']) && !empty($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    try {
        $categoryName = trim($_POST['CategoryName'] ?? '');
        $status = isset($_POST['Status']) && $_POST['Status'] === '0' ? 0 : 1;
        
        if (empty($categoryName)) {
            echo json_encode(['ok' => false, 'error' => 'ชื่อหมวดหมู่ไม่ได้ระบุ']);
            exit;
        }
        
        // Check for duplicates
        $stmt = $dbh->prepare("SELECT id FROM tblcategory WHERE CategoryName = ? LIMIT 1");
        $stmt->execute([$categoryName]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['ok' => false, 'error' => 'หมวดหมู่นี้มีอยู่แล้ว']);
            exit;
        }
        
        // Insert new category
        $stmt = $dbh->prepare("INSERT INTO tblcategory (CategoryName, Status, CreationDate) VALUES (?, ?, NOW())");
        
        if ($stmt->execute([$categoryName, $status])) {
            $newId = $dbh->lastInsertId();
            echo json_encode([
                'ok' => true,
                'id' => $newId,
                'msg' => 'เพิ่มหมวดหมู่สำเร็จ'
            ]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'ไม่สามารถเพิ่มหมวดหมู่ได้']);
        }
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => 'ข้อผิดพลาด: ' . $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>E-Sports | Add Category</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <link href="../assets/css/modern-style.css" rel="stylesheet" />
    <link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css' />
</head>
<body>
    <?php include('includes/header.php');?>
    <div class="content-wrapper" style="margin-top: 60px; padding-bottom: 40px;">
        <div class="container">
            <div class="row pad-botm">
                <div class="col-md-12">
                    <h4 class="header-line">เพิ่มหมวดหมู่</h4>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">รายละเอียดหมวดหมู่</div>
                        <div class="card-body">
                            <form class="row g-3" role="form" method="post" novalidate>
                                <div class="col-md-6">
                                    <label class="form-label">ชื่อหมวดหมู่<span style="color:red;">*</span></label>
                                    <input class="form-control" type="text" name="CategoryName" placeholder="ระบุชื่อหมวดหมู่" required />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">สถานะ<span style="color:red;">*</span></label>
                                    <select class="form-select" name="Status" required>
                                        <option value="1" selected>พร้อมใช้งาน</option>
                                        <option value="0">ไม่พร้อมใช้งาน</option>
                                    </select>
                                </div>
                                <div class="col-12 d-flex gap-2 mt-3" style="gap: 0.5rem !important;">
                                    <a href="manage-categories.php" class="btn btn-outline-secondary"><i class="fa fa-arrow-left"></i> ย้อนกลับ</a>
                                    <button type="submit" name="add" class="btn btn-success"><i class="fa fa-save"></i> เพิ่มหมวดหมู่</button>
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
    <script src="../assets/js/interactions.js?v=2"></script>
    <script>
    $(function(){
        var $form = $('form[method="post"]');
        $form.on('submit', function(e){
            e.preventDefault();
            
            var categoryName = $('input[name="CategoryName"]').val().trim();
            var status = $('select[name="Status"]').val();
            
            if (!categoryName) {
                $('#errorModalBody').text('กรุณากรอกชื่อหมวดหมู่');
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
                url: 'add-category.php',
                method: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json',
                timeout: 20000,
                success: function(res){
                    if (res && res.ok) {
                        $('#successModalBody').text('เพิ่มหมวดหมู่สำเร็จ');
                        var successModal = new bootstrap.Modal(document.getElementById('successModal'));
                        successModal.show();
                        $form[0].reset();
                        $('#successModal').on('hidden.bs.modal', function () {
                            window.location.href = 'manage-categories.php';
                        });
                    } else {
                        var msg = (res && res.error) ? res.error : 'เพิ่มหมวดหมู่ไม่สำเร็จ';
                        $('#errorModalBody').text(msg);
                        var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                        errorModal.show();
                    }
                },
                error: function(jqXHR, textStatus, errorThrown){
                    $('#errorModalBody').text('เครือข่ายมีปัญหา ลองใหม่อีกครั้ง');
                    var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                    errorModal.show();
                },
                complete: function(){
                    $submit.removeClass('disabled').prop('disabled', false).html('<i class="fa fa-save"></i> เพิ่มหมวดหมู่');
                }
            });
        });
    });
    </script>
    <style>
    body { min-height: 100vh; display: flex; flex-direction: column; }
    .content-wrapper { flex: 1; }
    .footer-section { margin-top: auto; }
    </style>
</body>
</html>
