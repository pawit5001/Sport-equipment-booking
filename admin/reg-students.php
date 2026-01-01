<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
error_reporting(0);
include('includes/config.php');
if(strlen($_SESSION['alogin'])==0)
    {   
header('location:../adminlogin.php');
}
else {
    // Handle bulk delete
    if (isset($_POST['bulk_delete'])) {
        $isAjax = (isset($_POST['ajax']) && $_POST['ajax'] == '1') ||
                  (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');
        $ids = [];
        if (isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])) {
            $ids = $_POST['selected_ids'];
        } elseif (isset($_POST['selected_ids']) && $_POST['selected_ids'] !== '') {
            $ids = [$_POST['selected_ids']];
        }
        // Normalize IDs
        $ids = array_values(array_filter(array_map('intval', (array)$ids), function($v){ return $v > 0; }));

        if (count($ids) === 0) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => false, 'error' => 'กรุณาเลือกสมาชิกอย่างน้อย 1 รายการ']);
                exit;
            } else {
                $_SESSION['admin_error'] = "กรุณาเลือกสมาชิกอย่างน้อย 1 รายการ";
                header('location:reg-students.php');
                exit;
            }
        }
        try {
            $dbh->beginTransaction();
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            // Check how many are admins
            $checkStmt = $dbh->prepare("SELECT COUNT(*) as admin_count FROM tblmembers WHERE id IN ($placeholders) AND Role = 'admin'");
            $checkStmt->execute($ids);
            $adminCount = $checkStmt->fetch(PDO::FETCH_OBJ)->admin_count;
            
            // Delete only non-admin accounts
            $stmt = $dbh->prepare("DELETE FROM tblmembers WHERE id IN ($placeholders) AND Role != 'admin'");
            $stmt->execute($ids);
            $deleted = $stmt->rowCount();
            $dbh->commit();
            
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                if ($deleted === 0 && $adminCount > 0) {
                    echo json_encode(['ok' => false, 'error' => 'ไม่สามารถลบผู้ดูแลระบบได้ โปรดเลือกสมาชิก (นักศึกษา) เท่านั้น']);
                } elseif ($deleted > 0 && $adminCount > 0) {
                    echo json_encode(['ok' => true, 'deleted' => $deleted, 'message' => "ลบสมาชิก $deleted รายการแล้ว (ไม่สามารถลบผู้ดูแลระบบ $adminCount รายการ)"]);
                } else {
                    echo json_encode(['ok' => true, 'deleted' => $deleted]);
                }
                exit;
            } else {
                if ($deleted === 0 && $adminCount > 0) {
                    $_SESSION['admin_error'] = "ไม่สามารถลบผู้ดูแลระบบได้ โปรดเลือกสมาชิก (นักศึกษา) เท่านั้น";
                } elseif ($deleted > 0 && $adminCount > 0) {
                    $_SESSION['admin_msg'] = "ลบสมาชิก $deleted รายการแล้ว (ไม่สามารถลบผู้ดูแลระบบ $adminCount รายการ)";
                } else {
                    $_SESSION['admin_msg'] = "ลบสมาชิกที่เลือก $deleted รายการแล้ว";
                }
                header('location:reg-students.php');
                exit;
            }
        } catch (Exception $ex) {
            if ($dbh->inTransaction()) { $dbh->rollBack(); }
            $friendly = 'เกิดข้อผิดพลาดในการลบ';
            error_log('[reg-students] delete failed: ' . $ex->getMessage());
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => false, 'error' => $friendly]);
                exit;
            } else {
                $_SESSION['admin_error'] = $friendly;
                header('location:reg-students.php');
                exit;
            }
        }
}

// Capture page-level error to display in modal (cleared after read)
$pageError = '';
if (!empty($_SESSION['admin_error'])) {
    $pageError = $_SESSION['admin_error'];
    $_SESSION['admin_error'] = "";
}

// Capture page-level success to display in modal (cleared after read)
$pageSuccess = '';
if (!empty($_SESSION['admin_msg'])) {
    $pageSuccess = $_SESSION['admin_msg'];
    $_SESSION['admin_msg'] = "";
}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>E-Sports | Members</title>
    <!-- BOOTSTRAP CORE STYLE  -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <!-- FONT AWESOME STYLE  -->
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <!-- DATATABLE STYLE  -->
    <link href="assets/js/dataTables/dataTables.bootstrap.css" rel="stylesheet" />
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
    <div class="content-wrapper">
        <div class="container">
            <div class="row pad-botm">
                <div class="col-md-12">
                    <h4 class="header-line">จัดการนักศึกษา</h4>
                </div>
            </div>

            <!-- Bulk Delete Toolbar -->
            <div class="row" style="margin-bottom: 15px;">
                <div class="col-md-12">
                    <form id="bulkDeleteForm" method="POST" action="reg-students.php">
                        <input type="hidden" name="bulk_delete" value="1">
                        <div class="d-flex justify-content-end align-items-center mb-2">
                            <div class="d-flex align-items-center gap-3">
                                <span id="selectedCount" class="badge rounded-pill bg-light text-muted border">เลือกแล้ว 0 รายการ</span>
                                <button type="submit" id="bulkDeleteBtn" class="btn btn-danger rounded-pill btn-delete" disabled>
                                    <i class="fa fa-trash"></i> ลบที่เลือก
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <!-- Advanced Tables -->
                    <div class="panel panel-default">
                        <div class="panel-body">
                            <table class="table table-striped table-bordered table-hover w-100" id="dataTables-example">
                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" id="selectAll" style="width: 18px; height: 18px; accent-color: #1e40af;"></th>
                                            <th>ชื่อ-นามสกุล</th>
                                            <th>รหัสนักศึกษา</th>
                                            <th>อีเมล</th>
                                            <th>บทบาท</th>
                                            <th class="status-col">สถานะ</th>
                                            <th>จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
<?php 
$sql = "SELECT id, Name, Surname, Email, StudentID, Status, Role FROM tblmembers ORDER BY id DESC";
$query = $dbh->prepare($sql);
$query->execute();
$results=$query->fetchAll(PDO::FETCH_OBJ);
if($query->rowCount() > 0)
{
foreach($results as $result)
{               ?>                                      
                                        <tr class="odd gradeX">
                                            <td class="center"><input type="checkbox" name="selected_ids[]" value="<?php echo htmlentities($result->id); ?>" style="width: 18px; height: 18px; accent-color: #1e40af;"></td>
                                            <td class="center">
                                                <div style="font-weight:600;"><?php echo htmlentities($result->Name . ' ' . $result->Surname);?></div>
                                            </td>
                                            <td class="center"><?php echo htmlentities($result->StudentID);?></td>
                                            <td class="center"><?php echo htmlentities($result->Email);?></td>
                                            <td class="center">
                                                <?php echo ($result->Role == 'admin') ? '<span class="badge bg-secondary">ผู้ดูแลระบบ</span>' : '<span class="badge bg-primary">นักศึกษา</span>'; ?>
                                            </td>
                                            <td class="center status-col">
                                                <?php if ((int)$result->Status === 1) { ?>
                                                    <span class="badge status-badge bg-success">ปกติ</span>
                                                <?php } else { ?>
                                                    <span class="badge status-badge bg-danger">ถูกแบน</span>
                                                <?php } ?>
                                            </td>
                                            <td class="center">
                                                <a href="edit-student.php?id=<?php echo htmlentities($result->id);?>" class="btn btn-primary btn-sm"><i class="fa fa-edit"></i> แก้ไข</a>
                                            </td>
                                        </tr>
 <?php }} ?>                                      
                                    </tbody>
                                </table>
                        </div>
                    </div>
                    <!--End Advanced Tables -->
                </div>
            </div>
    </div>
    </div>

     <!-- CONTENT-WRAPPER SECTION END-->
  <?php include('includes/footer.php');?>
      <!-- FOOTER SECTION END-->
    <!-- JAVASCRIPT FILES PLACED AT THE BOTTOM TO REDUCE THE LOADING TIME  -->
    <!-- CORE JQUERY  -->
    <script src="assets/js/jquery-1.10.2.js"></script>
    <!-- BOOTSTRAP SCRIPTS  -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DATATABLE SCRIPTS  -->
    <script src="assets/js/dataTables/jquery.dataTables.js"></script>
    <script src="assets/js/dataTables/dataTables.bootstrap.js"></script>
      <!-- CUSTOM SCRIPTS  -->
    <script src="assets/js/custom.js"></script>
    <!-- MODERN INTERACTIONS (shared at root assets) -->
    <script src="../assets/js/interactions.js"></script>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-success text-white border-0">
                    <h5 class="modal-title" id="successModalLabel"><i class="fa fa-check-circle me-2"></i>สำเร็จ</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="successModalBody">
                    ดำเนินการสำเร็จ
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal" onclick="location.reload();">ตกลง</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Feedback Modal -->
    <div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-danger text-white border-0">
                    <h5 class="modal-title" id="feedbackModalLabel"><i class="fa fa-exclamation-circle me-2"></i>ข้อผิดพลาด</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="feedbackModalBody">
                    เกิดข้อผิดพลาด
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">ปิด</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirm Delete Modal -->
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-warning text-dark border-0">
                    <h5 class="modal-title" id="confirmDeleteLabel"><i class="fa fa-trash me-2"></i>ยืนยันการลบ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="confirmDeleteBody">
                    คุณแน่ใจหรือว่าต้องการลบ?
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">ลบ</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        // Show success modal if there is a page-level success message
        var pageSuccess = <?php echo json_encode($pageSuccess ?? ''); ?>;
        if (pageSuccess && !sessionStorage.getItem('modalShown')) {
            sessionStorage.setItem('modalShown', 'true');
            var successModalEl = document.getElementById('successModal');
            if (successModalEl) {
                successModalEl.querySelector('#successModalBody').textContent = pageSuccess;
                var successModal = new bootstrap.Modal(successModalEl, { keyboard: true });
                successModal.show();
                successModalEl.addEventListener('hidden.bs.modal', function(e) {
                    sessionStorage.removeItem('modalShown');
                    successModal.dispose();
                }, { once: true });
            }
        }

        // Clear sessionStorage on page load if no success message
        if (!pageSuccess) {
            sessionStorage.removeItem('modalShown');
        }

        // Show modal if there is a page-level error
        var pageError = <?php echo json_encode($pageError ?? ''); ?>;
        if (pageError) {
            var modalEl = document.getElementById('feedbackModal');
            if (modalEl) {
                modalEl.querySelector('#feedbackModalBody').textContent = pageError;
                var modal = new bootstrap.Modal(modalEl);
                modal.show();
            }
        }

        function updateSelectionState() {
            const count = $('input[name="selected_ids[]"]:checked').length;
            $('#selectedCount').text('เลือกแล้ว ' + count + ' รายการ');
            $('#bulkDeleteBtn').prop('disabled', count === 0);
        }

        // Select/Deselect all
        $('#selectAll').on('change', function() {
            const checked = $(this).is(':checked');
            $('input[name="selected_ids[]"]').prop('checked', checked);
            updateSelectionState();
        });

        // Per-row checkbox change updates state
        $(document).on('change', 'input[name="selected_ids[]"]', function() {
            if (!$(this).is(':checked')) {
                $('#selectAll').prop('checked', false);
            }
            updateSelectionState();
        });

        var pendingDeleteIds = [];
        var confirmModalEl = document.getElementById('confirmDeleteModal');
        var confirmModal = confirmModalEl ? new bootstrap.Modal(confirmModalEl) : null;

        $('#bulkDeleteForm').on('submit', function(e) {
            e.preventDefault();
            const checked = $('input[name="selected_ids[]"]:checked');
            const count = checked.length;
            if (count === 0) {
                alert('กรุณาเลือกสมาชิกอย่างน้อย 1 รายการ');
                return;
            }

            pendingDeleteIds = checked.map(function(){ return $(this).val(); }).get();
            $('#confirmDeleteBody').text('คุณแน่ใจหรือว่าต้องการลบ ' + count + ' รายการที่เลือก?');
            if (confirmModal) {
                confirmModal.show();
            }
        });

        $('#confirmDeleteBtn').on('click', function() {
            var checked = $('input[name="selected_ids[]"]:checked');
            if (pendingDeleteIds.length === 0) {
                alert('ไม่มีรายการที่เลือก');
                return;
            }
            $('#confirmDeleteBtn').blur();
            if (confirmModal) confirmModal.hide();
            performBulkDelete(pendingDeleteIds, checked);
        });

        if (confirmModalEl) {
            confirmModalEl.addEventListener('hidden.bs.modal', function() {
                var bulkBtn = document.getElementById('bulkDeleteBtn');
                if (bulkBtn) bulkBtn.focus();
            });
        }

        function performBulkDelete(ids, checked) {
            const $btn = $('#bulkDeleteBtn');
            $btn.prop('disabled', true).addClass('disabled').html('<i class="fa fa-spinner fa-spin me-1"></i> กำลังลบ...');

            $.ajax({
                url: 'reg-students.php',
                type: 'POST',
                data: {
                    selected_ids: ids,
                    bulk_delete: 1,
                    ajax: 1
                },
                dataType: 'json',
                success: function(response) {
                    $btn.prop('disabled', false).removeClass('disabled').html('<i class="fa fa-trash"></i> ลบที่เลือก');
                    
                    if (response.ok) {
                        var successModalEl = document.getElementById('successModal');
                        if (successModalEl) {
                            var message = response.message || ('ลบสมาชิกที่เลือก ' + response.deleted + ' รายการแล้ว');
                            successModalEl.querySelector('#successModalBody').textContent = message;
                            var successModal = new bootstrap.Modal(successModalEl);
                            successModal.show();
                        }
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        var errorMsg = response.error || 'เกิดข้อผิดพลาดในการลบ';
                        var feedbackModalEl = document.getElementById('feedbackModal');
                        if (feedbackModalEl) {
                            feedbackModalEl.querySelector('#feedbackModalBody').textContent = errorMsg;
                            var feedbackModal = new bootstrap.Modal(feedbackModalEl);
                            feedbackModal.show();
                        }
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).removeClass('disabled').html('<i class="fa fa-trash"></i> ลบที่เลือก');
                    var feedbackModalEl = document.getElementById('feedbackModal');
                    if (feedbackModalEl) {
                        feedbackModalEl.querySelector('#feedbackModalBody').textContent = 'เกิดข้อผิดพลาดในการเชื่อมต่อ';
                        var feedbackModal = new bootstrap.Modal(feedbackModalEl);
                        feedbackModal.show();
                    }
                }
            });
        }
    });
    </script>

    <style>
    /* Admin page overrides */
    .content-wrapper { margin-top: 40px !important; min-height: auto !important; display: block !important; padding: 0 !important; }
    .container { max-width: 1140px; }
    
    /* Table UX tweaks */
    #dataTables-example th, #dataTables-example td { vertical-align: middle; }
    #dataTables-example th:first-child, #dataTables-example td:first-child { width: 48px; text-align: center; }
    #dataTables-example th:last-child, #dataTables-example td:last-child { white-space: nowrap; width: 120px; }
    #dataTables-example th.status-col, #dataTables-example td.status-col { width: 130px; text-align: center; }
    #dataTables-example input[type="checkbox"] { width: 18px; height: 18px; accent-color: #1e40af; }
    #dataTables-example .btn.btn-sm { padding: .35rem .7rem; font-size: .9rem; }
    #dataTables-example .status-badge { padding: .35rem .7rem; font-size: .9rem; border-radius: 999px; font-weight: 600; }
    
    /* Prevent unexpected horizontal scrollbar */
    .dataTables_wrapper { overflow-x: hidden; }
    
    /* Align DataTables controls */
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_length { float: none !important; }
    .dataTables_wrapper .dataTables_filter label,
    .dataTables_wrapper .dataTables_length label {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        margin-bottom: 0;
        white-space: nowrap;
    }
    .dataTables_wrapper .dataTables_filter input { width: 280px; }
    
    /* Table spacing */
    #dataTables-example { margin-top: 1.5rem !important; }
    
    /* Bulk delete toolbar */
    #selectedCount {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        min-height: 42px !important;
        padding: .5rem 1rem !important;
        line-height: 1.5 !important;
        vertical-align: middle !important;
        text-align: center !important;
    }
    .btn-delete {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 6px !important;
        min-height: 42px !important;
        padding: .5rem 1rem !important;
        line-height: 1.5 !important;
        vertical-align: middle !important;
        text-align: center !important;
    }
    .btn-delete .fa { 
        line-height: 1.5; 
        vertical-align: middle; 
        margin: 0 !important; 
        padding: 0 !important;
    }
    .btn-delete:disabled { opacity: .6; cursor: not-allowed; }
    
    /* Footer positioning */
    body { min-height: 100vh; display: flex; flex-direction: column; }
    .content-wrapper { flex: 1; }
    .footer-section { margin-top: auto; }
    
    /* Pagination buttons */
    .dataTables_paginate .paginate_button,
    .dataTables_paginate .paginate_button.previous,
    .dataTables_paginate .paginate_button.next,
    .dataTables_paginate .paginate_button.first,
    .dataTables_paginate .paginate_button.last {
        padding: .5rem .75rem !important;
        margin: 0 .25rem !important;
        border-radius: .5rem !important;
        border: 1px solid #dee2e6 !important;
        background: white !important;
        color: #495057 !important;
        transition: all 0.2s ease !important;
        text-align: center !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        vertical-align: middle !important;
        line-height: 1 !important;
        min-width: 40px !important;
        height: auto !important;
    }
    .dataTables_paginate .paginate_button:hover,
    .dataTables_paginate .paginate_button.previous:hover,
    .dataTables_paginate .paginate_button.next:hover {
        background: #f8f9fa !important;
        border-color: #1e40af !important;
        color: #1e40af !important;
    }
    .dataTables_paginate .paginate_button.current {
        background: linear-gradient(135deg, #1e40af 0%, #0891b2 100%) !important;
        border-color: #1e40af !important;
        color: white !important;
        font-weight: 600 !important;
        text-align: center !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
    }
    .dataTables_paginate .paginate_button.disabled {
        opacity: 0.5 !important;
        cursor: not-allowed !important;
    }
    .dataTables_paginate { 
        margin-top: 1rem !important; 
        text-align: center !important;
    }
    </style>
</body>
</html>
<?php } ?>
