<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
error_reporting(0);
include('includes/config.php');
if (strlen($_SESSION['alogin']) == 0) {
    // If called via AJAX, return JSON instead of redirect so client can handle it gracefully
    $isAjaxAuth = (isset($_POST['ajax']) && $_POST['ajax'] == '1') ||
                  (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');
    if ($isAjaxAuth) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => false, 'error' => 'auth']);
        exit;
    } else {
        header('location:../adminlogin.php');
        exit;
    }
} else {
    // Handle bulk delete
    if (isset($_POST['bulk_delete'])) {
        $isAjax = (isset($_POST['ajax']) && $_POST['ajax'] == '1') ||
                  (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');
        $ids = [];
        if (isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])) {
            $ids = $_POST['selected_ids'];
        } elseif (isset($_POST['selected_ids']) && $_POST['selected_ids'] !== '') {
            $ids = [$_POST['selected_ids']];
        } elseif (isset($_POST['selected_ids']) === false && isset($_POST['selected_ids__'])) {
            $ids = (array)$_POST['selected_ids__'];
        } elseif (isset($_POST['selected_ids']) === false && isset($_POST['selected_ids_'])) {
            $ids = (array)$_POST['selected_ids_'];
        } elseif (isset($_POST['selected_ids']) === false && isset($_POST['selected_ids[]'])) {
            $ids = (array)$_POST['selected_ids[]'];
        }
        // Normalize IDs
        $ids = array_values(array_filter(array_map('trim', (array)$ids), function($v){ return $v !== '' && $v !== null; }));

        if (count($ids) === 0) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => false, 'error' => 'กรุณาเลือกอุปกรณ์อย่างน้อย 1 รายการ']);
                exit;
            } else {
                $_SESSION['admin_error'] = "กรุณาเลือกอุปกรณ์อย่างน้อย 1 รายการ";
                header('location:manage-equipment.php');
                exit;
            }
        }
        try {
            $dbh->beginTransaction();
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            // Delete dependent pricing rows first to satisfy FK
            $pricingStmt = $dbh->prepare("DELETE FROM tblequipment_pricing WHERE EquipmentId IN ($placeholders)");
            $pricingStmt->execute($ids);

            $stmt = $dbh->prepare("DELETE FROM tblequipment WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $deleted = $stmt->rowCount();
            $dbh->commit();
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => true, 'deleted' => $deleted]);
                exit;
            } else {
                $_SESSION['delmsg'] = "ลบอุปกรณ์ที่เลือกสำเร็จ";
                header('location:manage-equipment.php');
                exit;
            }
        } catch (Exception $ex) {
            if ($dbh->inTransaction()) { $dbh->rollBack(); }
            $friendly = 'เกิดข้อผิดพลาดในการลบ';
            // Foreign key constraint violation (e.g., still referenced by bookings/issued records)
            if ($ex->getCode() === '23000') {
                $friendly = 'ลบไม่ได้: มีการอ้างอิงอุปกรณ์นี้อยู่ (เช่น การจอง/ประวัติการยืม)';
            }
            error_log('[manage-equipment] delete failed: ' . $ex->getMessage());
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'ok' => false,
                    'error' => $friendly,
                    'detail' => $ex->getMessage(),
                ]);
                exit;
            } else {
                $_SESSION['admin_error'] = $friendly;
                header('location:manage-equipment.php');
                exit;
            }
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
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>E-Sports | Manage Equipment</title>
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
    <!-- MENU SECTION START-->
    <?php include('includes/header.php');?>
    <!-- MENU SECTION END-->

    <div class="content-wrapper">
        <div class="container">
            <div class="row pad-botm">
                <div class="col-md-12">
                    <h4 class="header-line">จัดการอุปกรณ์กีฬา</h4>
                </div>
            </div>
            <div class="row">
                <!-- Alerts suppressed; errors handled via modal -->
            </div>

            <div class="row">
                <div class="col-md-12">
                    <!-- Advanced Tables -->
                    <div class="panel panel-default">
                        <div class="panel-body">
                            <form method="post" id="bulkDeleteForm">
                                <div class="d-flex justify-content-end align-items-center mb-2">
                                    <div class="d-flex align-items-center gap-3">
                                        <span id="selectedCount" class="badge rounded-pill bg-light text-muted border">เลือกแล้ว 0 รายการ</span>
                                        <button type="submit" name="bulk_delete" id="bulkDeleteBtn" class="btn btn-danger rounded-pill btn-delete" disabled>
                                            <i class="fa fa-trash"></i> ลบที่เลือก
                                        </button>
                                    </div>
                                </div>
                                <table class="table table-striped table-bordered table-hover w-100" id="dataTables-example">
                                        <thead>
                                            <tr>
                                                <th style="width:40px; text-align:center;"><input type="checkbox" id="selectAll" title="เลือกทั้งหมด" /></th>
                                                <th>ชื่ออุปกรณ์กีฬา</th>
                                                <th>หมวดหมู่</th>
                                                <th>จำนวนคงเหลือ</th>
                                                <th class="status-col">สถานะ</th>
                                                <th>วันที่เพิ่ม</th>
                                                <th>อัปเดตล่าสุด</th>
                                                <th>จัดการ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                                    $sql = "SELECT 
                                                                e.EquipmentName, 
                                                                e.EquipmentCode, 
                                                                e.IsActive, 
                                                                c.CategoryName, 
                                                                s.SupplierName, 
                                                                e.Quantity, 
                                                                e.id as bookid,
                                                                COALESCE(e.CreatedAt, e.RegDate) AS CreatedDate,
                                                                COALESCE(e.UpdatedAt, e.UpdationDate) AS UpdatedDate
                                                            FROM tblequipment e
                                                            LEFT JOIN tblcategory c ON c.id = e.CatId
                                                            LEFT JOIN tblsuppliers s ON s.id = e.SupplierId";
                                                $query = $dbh->prepare($sql);
                                                $query->execute();
                                                $results = $query->fetchAll(PDO::FETCH_OBJ);
                                                if($query->rowCount() > 0) {
                                                    foreach($results as $result) {
                                            ?>                                      
                                            <tr class="odd gradeX">
                                                <td class="center" style="text-align:center;"><input type="checkbox" name="selected_ids[]" value="<?php echo htmlentities($result->bookid);?>" /></td>
                                                <td class="center">
                                                    <div style="font-weight:600;"><?php echo htmlentities($result->EquipmentName);?></div>
                                                    <small class="text-muted">รหัส: <?php echo htmlentities($result->EquipmentCode);?> • ผู้รับผิดชอบ: <?php echo htmlentities($result->SupplierName);?></small>
                                                </td>
                                                <td class="center"><?php echo htmlentities($result->CategoryName);?></td>
                                                <td class="center">
                                                    <?php echo htmlentities($result->Quantity);?>
                                                </td>
                                                <td class="center status-col">
                                                    <?php if ((int)$result->IsActive === 1) { ?>
                                                        <span class="badge status-badge bg-success">พร้อมให้ยืม</span>
                                                    <?php } else { ?>
                                                        <span class="badge status-badge bg-secondary">ปิดการยืม</span>
                                                    <?php } ?>
                                                </td>
                                                <td class="center">
                                                    <?php echo !empty($result->CreatedDate) ? htmlentities(date('d-m-Y H:i:s', strtotime($result->CreatedDate))) : '-'; ?>
                                                </td>
                                                <td class="center">
                                                    <?php echo !empty($result->UpdatedDate) ? htmlentities(date('d-m-Y H:i:s', strtotime($result->UpdatedDate))) : '-'; ?>
                                                </td>
                                                <td class="center">
                                                    <a href="edit-equipment.php?bookid=<?php echo htmlentities($result->bookid);?>" class="btn btn-primary btn-sm"><i class="fa fa-edit"></i> แก้ไข</a>
                                                </td>
                                            </tr>
                                            <?php }} ?>                                      
                                        </tbody>
                                </table>
                                
                            </form>
                        </div>                  
                    </div>
                    <!-- End Advanced Tables -->
                </div>
            </div>
        </div>
    </div>

    <!-- CONTENT-WRAPPER SECTION END-->
    <?php include('includes/footer.php');?>
    <!-- FOOTER SECTION END-->

    <!-- JAVASCRIPT FILES PLACED AT THE BOTTOM TO REDUCE THE LOADING TIME  -->
    <!-- CORE JQUERY  -->
    <script src="assets/js/jquery-1.10.2.js?v=2"></script>
    <!-- BOOTSTRAP SCRIPTS  -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DATATABLE SCRIPTS  -->
    <script src="assets/js/dataTables/jquery.dataTables.js?v=2"></script>
    <script src="assets/js/dataTables/dataTables.bootstrap.js?v=2"></script>
    <!-- CUSTOM SCRIPTS  -->
    <script src="assets/js/custom.js?v=4"></script>
    <!-- MODERN INTERACTIONS (shared at root assets) -->
    <script src="../assets/js/interactions.js?v=3"></script>

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
                        <button type="button" class="btn btn-success" data-bs-dismiss="modal">ตกลง</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Feedback Modal -->
        <div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="feedbackModalLabel">ข้อผิดพลาด</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="feedbackModalBody"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirm Delete Modal -->
        <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title" id="confirmDeleteLabel">ยืนยันการลบ</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="confirmDeleteBody">คุณแน่ใจหรือว่าต้องการลบรายการที่เลือก?</div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">ลบ</button>
                    </div>
                </div>
            </div>
        </div>

    <script>
    // Debug logging for F12 console
    // Commented out - remove debug logs
    // console.log('=== Manage Equipment Page Loaded ===');
    // console.log('jQuery version:', typeof jQuery !== 'undefined' ? jQuery.fn.jquery : 'Not loaded');
    // console.log('Bootstrap version:', typeof bootstrap !== 'undefined' ? '5.3.0+' : 'Not loaded');
    // console.log('DataTables loaded:', typeof $.fn.dataTable !== 'undefined');
    
    $(document).ready(function() {
        // Show success modal if there is a page-level success message (prevent duplicate)
        var pageSuccess = <?php echo json_encode($pageSuccess); ?>;
        
        if (pageSuccess && !sessionStorage.getItem('modalShown')) {
            sessionStorage.setItem('modalShown', 'true');
            
            var successModalEl = document.getElementById('successModal');
            if (successModalEl) {
                successModalEl.querySelector('#successModalBody').textContent = pageSuccess;
                var successModal = new bootstrap.Modal(successModalEl, {
                    backdrop: 'static',
                    keyboard: true
                });
                
                // Show modal once
                successModal.show();
                
                // Clear sessionStorage when modal closes
                successModalEl.addEventListener('hidden.bs.modal', function(e) {
                    sessionStorage.removeItem('modalShown');
                    if (document.activeElement) {
                        document.activeElement.blur();
                    }
                    successModal.dispose();
                }, { once: true });
            }
        }
        
        // Clear sessionStorage on page load if no success message (page refresh)
        if (!pageSuccess) {
            sessionStorage.removeItem('modalShown');
        }

        // Show modal if there is a page-level error
        var pageError = <?php echo json_encode($pageError); ?>;
        if (pageError) {
            var modalEl = document.getElementById('feedbackModal');
            if (modalEl) {
                modalEl.querySelector('#feedbackModalBody').textContent = pageError;
                var modal = new bootstrap.Modal(modalEl);
                modal.show();
            } else if (window.showToast) {
                window.showToast(pageError, 'danger');
            }
        }

        // Log clicks on edit buttons
        $('a[href*="edit-equipment.php"]').on('click', function(e) {
            // console.log('Edit button clicked:', $(this).attr('href'));
        });
        
        // Log clicks on delete buttons
        $('a[href*="del="]').on('click', function(e) {
            // console.log('Delete button clicked:', $(this).attr('href'));
        });

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
            // If any unchecked while selectAll is checked, uncheck header
            if (!$(this).is(':checked')) {
                $('#selectAll').prop('checked', false);
            }
            updateSelectionState();
        });

        // Debug: Check computed styles for alignment issues
        // Commented out - remove debug logs
        /*
        setTimeout(function() {
            var badge = document.getElementById('selectedCount');
            var btn = document.getElementById('bulkDeleteBtn');
            
            if (badge) {
                var badgeStyles = window.getComputedStyle(badge);
                console.log('=== Badge (#selectedCount) Computed Styles ===');
                console.log('display:', badgeStyles.display);
                console.log('align-items:', badgeStyles.alignItems);
                console.log('justify-content:', badgeStyles.justifyContent);
                console.log('text-align:', badgeStyles.textAlign);
                console.log('line-height:', badgeStyles.lineHeight);
                console.log('vertical-align:', badgeStyles.verticalAlign);
                console.log('padding:', badgeStyles.padding);
                console.log('height:', badgeStyles.height);
            }
            
            if (btn) {
                var btnStyles = window.getComputedStyle(btn);
                console.log('=== Button (#bulkDeleteBtn) Computed Styles ===');
                console.log('display:', btnStyles.display);
                console.log('align-items:', btnStyles.alignItems);
                console.log('justify-content:', btnStyles.justifyContent);
                console.log('text-align:', btnStyles.textAlign);
                console.log('line-height:', btnStyles.lineHeight);
                console.log('vertical-align:', btnStyles.verticalAlign);
                console.log('padding:', btnStyles.padding);
                console.log('height:', btnStyles.height);
            }
            
            // Check pagination buttons
            var paginateBtn = document.querySelector('.dataTables_paginate .paginate_button');
            if (paginateBtn) {
                var paginateStyles = window.getComputedStyle(paginateBtn);
                console.log('=== Pagination Button Computed Styles ===');
                console.log('display:', paginateStyles.display);
                console.log('text-align:', paginateStyles.textAlign);
                console.log('line-height:', paginateStyles.lineHeight);
                console.log('vertical-align:', paginateStyles.verticalAlign);
                console.log('padding:', paginateStyles.padding);
                console.log('align-items:', paginateStyles.alignItems);
                console.log('justify-content:', paginateStyles.justifyContent);
            }
            
            // Force pagination button alignment with JavaScript
            $('.dataTables_paginate .paginate_button').each(function() {
                $(this).css({
                    'display': 'inline-flex',
                    'align-items': 'center',
                    'justify-content': 'center',
                    'text-align': 'center',
                    'line-height': '1'
                });
            });
            
            console.log('=== After JavaScript Fix ===');
            if (paginateBtn) {
                var afterStyles = window.getComputedStyle(paginateBtn);
                console.log('align-items:', afterStyles.alignItems);
                console.log('justify-content:', afterStyles.justifyContent);
            }
        }, 1000);
        */
        
        // Force pagination button alignment (without console logs)
        setTimeout(function() {
            $('.dataTables_paginate .paginate_button').each(function() {
                $(this).css({
                    'display': 'inline-flex',
                    'align-items': 'center',
                    'justify-content': 'center',
                    'text-align': 'center',
                    'line-height': '1'
                });
            });
        }, 1000);
        var pendingDeleteIds = [];
        var confirmModalEl = document.getElementById('confirmDeleteModal');
        var confirmModal = confirmModalEl ? new bootstrap.Modal(confirmModalEl) : null;

        $('#bulkDeleteForm').on('submit', function(e) {
            e.preventDefault();
            console.log('Bulk delete submit intercepted');
            const checked = $('input[name="selected_ids[]"]:checked');
            const count = checked.length;
            if (count === 0) {
                if (window.showToast) window.showToast('กรุณาเลือกรายการที่จะลบ', 'danger');
                return false;
            }

            pendingDeleteIds = checked.map(function(){ return $(this).val(); }).get();
            $('#confirmDeleteBody').text('คุณแน่ใจหรือว่าต้องการลบ ' + count + ' รายการที่เลือก?');
            if (confirmModal) {
                console.log('Opening confirm modal');
                confirmModal.show();
            }
        });

        $('#confirmDeleteBtn').on('click', function() {
            var checked = $('input[name="selected_ids[]"]:checked');
            if (pendingDeleteIds.length === 0) {
                if (window.showToast) window.showToast('กรุณาเลือกรายการที่จะลบ', 'danger');
                return;
            }
            // Prevent focus from remaining on a soon-to-be-hidden element
            $('#confirmDeleteBtn').blur();
            if (confirmModal) confirmModal.hide();
            performBulkDelete(pendingDeleteIds, checked);
        });

        // After modal hides, return focus to the bulk delete button
        if (confirmModalEl) {
            confirmModalEl.addEventListener('hidden.bs.modal', function() {
                var bulkBtn = document.getElementById('bulkDeleteBtn');
                if (bulkBtn) bulkBtn.focus();
            });
        }

        function performBulkDelete(ids, checked) {
            const $btn = $('#bulkDeleteBtn');
            $btn.prop('disabled', true).addClass('disabled').html('<i class="fa fa-spinner fa-spin me-1"></i> กำลังลบ...');

            console.log('Bulk delete sending ids:', ids);
            $.ajax({
                url: 'manage-equipment.php',
                method: 'POST',
                dataType: 'json',
                traditional: true,
                timeout: 20000,
                data: { bulk_delete: 1, ajax: 1, 'selected_ids[]': ids },
                success: function(res) {
                    if (res && res.ok) {
                        var dt = $.fn.DataTable && $.fn.DataTable.isDataTable('#dataTables-example') ? $('#dataTables-example').DataTable() : null;
                        checked.each(function(){
                            var $row = $(this).closest('tr');
                            if (dt) dt.row($row).remove(); else $row.remove();
                        });
                        if (dt) dt.draw(false);
                        if (window.showToast) window.showToast('ลบสำเร็จ ' + res.deleted + ' รายการ', 'success');
                        // Reset selection state
                        $('#selectAll').prop('checked', false);
                        updateSelectionState();
                    } else if (res && res.error === 'auth') {
                        if (window.showToast) window.showToast('เซสชันหมดเวลา โปรดเข้าสู่ระบบใหม่', 'warning');
                        window.location.href = '../adminlogin.php';
                    } else {
                        console.error('Bulk delete failed response:', res);
                        var msg = (res && res.error) ? res.error : 'เกิดข้อผิดพลาดระหว่างการลบ';
                        if (res && res.detail) {
                            msg += ' (' + res.detail + ')';
                        }
                        if (window.showToast) window.showToast(msg, 'danger');
                        else alert(msg);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Bulk delete AJAX error', {
                        status: jqXHR.status,
                        textStatus: textStatus,
                        error: errorThrown,
                        response: jqXHR.responseText
                    });
                    if (window.showToast) window.showToast('เครือข่ายผิดพลาด ลบไม่สำเร็จ', 'danger');
                },
                complete: function() {
                    $btn.removeClass('disabled').html('<i class="fa fa-trash me-1"></i> ลบที่เลือก');
                    updateSelectionState();
                    pendingDeleteIds = [];
                }
            });
        }
    });
    </script>
    <style>
    /* Admin page overrides to avoid conflicts with modern-style.css */
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
    /* Prevent unexpected horizontal scrollbar on desktop */
    .dataTables_wrapper { overflow-x: hidden; }
    /* Align and tidy DataTables controls */
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
