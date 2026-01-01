<?php
session_start();
require_once('includes/config.php');

// Authentication check
if (!isset($_SESSION['alogin']) || strlen($_SESSION['alogin']) == 0) {
    header('location:../adminlogin.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);

// Handle form submission (AJAX)
if (!empty($_POST['update']) && !empty($_POST['ajax'])) {
    header('Content-Type: application/json');
    try {
        $supplierName = trim($_POST['SupplierName'] ?? '');
        $status = isset($_POST['Status']) && $_POST['Status'] === '0' ? 0 : 1;
        if (empty($supplierName)) {
            echo json_encode(['ok' => false, 'error' => 'ชื่อผู้รับผิดชอบไม่ได้ระบุ']);
            exit;
        }
        $stmt = $dbh->prepare("SELECT id FROM tblsuppliers WHERE SupplierName = ? AND id != ? LIMIT 1");
        $stmt->execute([$supplierName, $id]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['ok' => false, 'error' => 'ชื่อผู้รับผิดชอบนี้มีอยู่แล้ว']);
            exit;
        }
        $stmt = $dbh->prepare("UPDATE tblsuppliers SET SupplierName = ?, Status = ?, UpdationDate = NOW() WHERE id = ?");
        if ($stmt->execute([$supplierName, $status, $id])) {
            echo json_encode(['ok' => true, 'msg' => 'แก้ไขผู้รับผิดชอบสำเร็จ']);
        } else {
            echo json_encode(['ok' => false, 'error' => 'ไม่สามารถแก้ไขผู้รับผิดชอบได้']);
        }
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => 'ข้อผิดพลาด: ' . $e->getMessage()]);
    }
    exit;
}

// Fetch supplier data
$stmt = $dbh->prepare("SELECT * FROM tblsuppliers WHERE id = ?");
$stmt->execute([$id]);
$result = $stmt->fetch(PDO::FETCH_OBJ);

if (!$result) {
    $_SESSION['admin_error'] = 'ไม่พบผู้รับผิดชอบ';
    header('location:manage-suppliers.php');
    exit;
}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>E-Sports | Edit Supplier</title>
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
                    <h4 class="header-line">แก้ไขผู้รับผิดชอบ</h4>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">รายละเอียดผู้รับผิดชอบ</div>
                        <div class="card-body">
                            <form class="row g-3" role="form" method="post" novalidate>
                                <div class="col-md-6">
                                    <label class="form-label">ชื่อผู้รับผิดชอบ<span style="color:red;">*</span></label>
                                    <input class="form-control" type="text" name="SupplierName" value="<?php echo htmlentities($result->SupplierName);?>" required />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">สถานะ<span style="color:red;">*</span></label>
                                    <select class="form-select" name="Status" required>
                                        <option value="1" <?php echo (isset($result->Status) && $result->Status == 1) ? 'selected' : ''; ?>>พร้อมใช้งาน</option>
                                        <option value="0" <?php echo (isset($result->Status) && $result->Status == 0) ? 'selected' : ''; ?>>ไม่พร้อมใช้งาน</option>
                                    </select>
                                </div>
                                <div class="col-12 d-flex gap-2 mt-3" style="gap: 0.5rem !important;">
                                    <a href="manage-suppliers.php" class="btn btn-outline-secondary"><i class="fa fa-arrow-left"></i> ย้อนกลับ</a>
                                    <button type="submit" name="update" class="btn btn-success"><i class="fa fa-save"></i> บันทึกการแก้ไข</button>
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
    
    <script src="assets/js/jquery-1.10.2.js?v=2"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/custom.js?v=2"></script>
    <script src="../assets/js/interactions.js?v=2"></script>
    <script>
    $(document).ready(function() {
        var $form = $('form[method="post"]');
        var confirmModalEl = document.getElementById('confirmUpdateModal');
        var confirmModal = confirmModalEl ? new bootstrap.Modal(confirmModalEl) : null;
        var pendingFormData = null;

        $form.on('submit', function(e) {
            e.preventDefault();
            pendingFormData = new FormData(this);
            pendingFormData.append('ajax', '1');
            pendingFormData.append('update', '1');
            if (confirmModal) confirmModal.show();
        });

        $('#confirmUpdateBtn').on('click', function() {
            if (!pendingFormData) return;
            if (confirmModal) confirmModal.hide();
            var $btn = $form.find('button[name="update"]');
            if ($btn.length) $btn.prop('disabled', true).addClass('disabled');

            $.ajax({
                url: window.location.href,
                type: 'POST',
                dataType: 'json',
                data: pendingFormData,
                processData: false,
                contentType: false,
                success: function(resp) {
                    if (resp && resp.ok) {
                        $('#successModalBody').text(resp.msg || 'แก้ไขสำเร็จ');
                        var successModal = new bootstrap.Modal(document.getElementById('successModal'));
                        successModal.show();
                        $('#successModal').on('hidden.bs.modal', function () {
                            window.location.href = 'manage-suppliers.php';
                        });
                    } else {
                        var msg = (resp && resp.error) ? resp.error : 'เกิดข้อผิดพลาด';
                        $('#errorModalBody').text(msg);
                        var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                        errorModal.show();
                        if ($btn.length) $btn.prop('disabled', false).removeClass('disabled');
                    }
                },
                error: function(xhr) {
                    $('#errorModalBody').text('เกิดข้อผิดพลาดในการส่งข้อมูล');
                    var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                    errorModal.show();
                    if ($btn.length) $btn.prop('disabled', false).removeClass('disabled');
                },
                complete: function() {
                    pendingFormData = null;
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
