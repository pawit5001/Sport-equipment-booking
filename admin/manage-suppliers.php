<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
error_reporting(0);
require_once('includes/config.php');

if (strlen($_SESSION['alogin']) == 0) {
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
        } elseif (isset($_POST['selected_ids[]'])) {
            $ids = (array)$_POST['selected_ids[]'];
        }
        $ids = array_values(array_filter(array_map('trim', (array)$ids), function($v){ return $v !== '' && $v !== null; }));

        if (count($ids) === 0) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => false, 'error' => 'กรุณาเลือกผู้รับผิดชอบอย่างน้อย 1 รายการ']);
                exit;
            } else {
                $_SESSION['admin_error'] = "กรุณาเลือกผู้รับผิดชอบอย่างน้อย 1 รายการ";
                header('location:manage-suppliers.php');
                exit;
            }
        }
        try {
            $dbh->beginTransaction();
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            // Prevent FK violation: check if any equipment still references these suppliers
            $check = $dbh->prepare("SELECT COUNT(*) AS cnt FROM tblequipment WHERE SupplierId IN ($placeholders)");
            $check->execute($ids);
            $refCount = (int) $check->fetchColumn();
            if ($refCount > 0) {
                $dbh->rollBack();
                if ($isAjax) {
                    header('Content-Type: application/json; charset=UTF-8');
                    echo json_encode(['ok' => false, 'error' => 'ลบไม่ได้: มีอุปกรณ์เชื่อมกับผู้รับผิดชอบ (' . $refCount . ' รายการ)']);
                } else {
                    $_SESSION['admin_error'] = 'ลบไม่ได้: มีอุปกรณ์เชื่อมกับผู้รับผิดชอบ (' . $refCount . ' รายการ)';
                    header('location:manage-suppliers.php');
                }
                exit;
            }

            $stmt = $dbh->prepare("DELETE FROM tblsuppliers WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $deleted = $stmt->rowCount();
            $dbh->commit();
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => true, 'msg' => 'ลบผู้รับผิดชอบที่เลือกสำเร็จ']);
                exit;
            } else {
                $_SESSION['admin_msg'] = "ลบผู้รับผิดชอบที่เลือกสำเร็จ";
                header('location:manage-suppliers.php');
                exit;
            }
        } catch (Exception $ex) {
            if ($dbh->inTransaction()) { $dbh->rollBack(); }
            $friendly = 'เกิดข้อผิดพลาดในการลบ';
            error_log('[manage-suppliers] delete failed: ' . $ex->getMessage());
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => false, 'error' => $friendly]);
                exit;
            } else {
                $_SESSION['admin_error'] = $friendly;
                header('location:manage-suppliers.php');
                exit;
            }
        }
    }
}

$pageError = '';
if (!empty($_SESSION['admin_error'])) {
    $pageError = $_SESSION['admin_error'];
    $_SESSION['admin_error'] = "";
}

$pageSuccess = '';
if (!empty($_SESSION['admin_msg'])) {
    $pageSuccess = $_SESSION['admin_msg'];
    $_SESSION['admin_msg'] = "";
}

// Fetch all suppliers (include status + updated date)
try {
    $result = $dbh->query("SELECT id, SupplierName, Status, creationDate AS CreatedDate, UpdationDate AS UpdatedDate FROM tblsuppliers ORDER BY id DESC");
    $suppliers = $result ? $result->fetchAll(PDO::FETCH_OBJ) : [];
} catch (Exception $ex) {
    // Fallback if Status/UpdationDate columns are missing
    $result = $dbh->query("SELECT id, SupplierName, creationDate AS CreatedDate FROM tblsuppliers ORDER BY id DESC");
    $suppliers = $result ? $result->fetchAll(PDO::FETCH_OBJ) : [];
}
?>

<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>E-Sports | Manage Suppliers</title>
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
                    <h4 class="header-line">จัดการผู้รับผิดชอบ</h4>
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
                                                <th>ชื่อผู้รับผิดชอบ</th>
                                                <th class="status-col">สถานะ</th>
                                                <th>วันที่เพิ่ม</th>
                                                <th>อัปเดตล่าสุด</th>
                                                <th>จัดการ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(!empty($suppliers)) {
                                                foreach($suppliers as $row) { ?>
                                            <tr class="odd gradeX">
                                                <td class="center" style="text-align:center;"><input type="checkbox" name="selected_ids[]" value="<?php echo htmlentities($row->id);?>" /></td>
                                                <td class="center">
                                                    <div style="font-weight:600;"><?php echo htmlentities($row->SupplierName);?></div>
                                                </td>
                                                <td class="center status-col">
                                                    <?php
                                                        $statusVal = isset($row->Status) ? (int)$row->Status : 1;
                                                        if ($statusVal === 1) {
                                                            echo '<span class="badge status-badge bg-success">พร้อมใช้งาน</span>';
                                                        } else {
                                                            echo '<span class="badge status-badge bg-secondary">ไม่พร้อมใช้งาน</span>';
                                                        }
                                                    ?>
                                                </td>
                                                <td class="center">
                                                    <?php echo htmlentities(date('d-m-Y H:i:s', strtotime($row->CreatedDate ?? $row->creationDate)));?>
                                                </td>
                                                <td class="center">
                                                    <?php
                                                        $updatedDate = $row->UpdatedDate ?? $row->UpdationDate ?? null;
                                                        echo !empty($updatedDate) ? htmlentities(date('d-m-Y H:i:s', strtotime($updatedDate))) : '-';
                                                    ?>
                                                </td>
                                                <td class="center">
                                                    <a href="edit-supplier.php?id=<?php echo htmlentities($row->id);?>" class="btn btn-primary btn-sm"><i class="fa fa-edit"></i> แก้ไข</a>
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
                        <button type="button" class="btn btn-danger" id="confirmDeleteBtn" data-confirm-btn="delete">ลบ</button>
                    </div>
                </div>
            </div>
        </div>

    <script>
    $(document).ready(function() {
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
                
                successModal.show();
                
                successModalEl.addEventListener('hidden.bs.modal', function(e) {
                    sessionStorage.removeItem('modalShown');
                    if (document.activeElement) {
                        document.activeElement.blur();
                    }
                    successModal.dispose();
                }, { once: true });
            }
        }
        
        if (!pageSuccess) {
            sessionStorage.removeItem('modalShown');
        }

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

        var $ = window.jQuery;
        
        function updateSelectionState() {
            const count = $('input[name="selected_ids[]"]:checked').length;
            $('#selectedCount').text('เลือกแล้ว ' + count + ' รายการ');
            $('#bulkDeleteBtn').prop('disabled', count === 0);
        }

        $('#selectAll').on('change', function() {
            const checked = $(this).is(':checked');
            $('input[name="selected_ids[]"]').prop('checked', checked);
            updateSelectionState();
        });

        $(document).on('change', 'input[name="selected_ids[]"]', function() {
            if (!$(this).is(':checked')) {
                $('#selectAll').prop('checked', false);
            }
            updateSelectionState();
        });

        $('#bulkDeleteBtn').on('click', function(e) {
            e.preventDefault();
            var selected = [];
            $('input[name="selected_ids[]"]:checked').each(function() {
                selected.push($(this).val());
            });
            
            if (selected.length === 0) {
                alert('No items selected');
                return;
            }
            
            window.pendingDeleteIds = selected;
            var confirmModalEl = document.getElementById('confirmDeleteModal');
            if (confirmModalEl) {
                var confirmModal = new bootstrap.Modal(confirmModalEl);
                confirmModal.show();
            }
        });

        var confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener('click', function() {
                if (window.pendingDeleteIds && window.pendingDeleteIds.length > 0) {
                    $.ajax({
                        type: 'POST',
                        url: window.location.href,
                        data: { 'selected_ids[]': window.pendingDeleteIds, 'bulk_delete': '1', 'ajax': '1' },
                        traditional: true,
                        timeout: 20000,
                        dataType: 'json',
                        success: function(response) {
                            var confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal'));
                            if (confirmModal) confirmModal.hide();
                            
                            if (response.ok) {
                                var successModalEl = document.getElementById('successModal');
                                if (successModalEl) {
                                    successModalEl.querySelector('#successModalBody').textContent = response.msg || 'Deleted successfully';
                                    var successModal = new bootstrap.Modal(successModalEl);
                                    successModal.show();
                                    
                                    successModalEl.addEventListener('hidden.bs.modal', function(e) {
                                        window.location.reload();
                                    }, { once: true });
                                }
                            } else {
                                var errorModalEl = document.getElementById('feedbackModal');
                                if (errorModalEl) {
                                    errorModalEl.querySelector('#feedbackModalBody').textContent = response.error || response.msg || 'An error occurred';
                                    var errorModal = new bootstrap.Modal(errorModalEl);
                                    errorModal.show();
                                }
                            }
                        },
                        error: function(xhr) {
                            var errorModalEl = document.getElementById('feedbackModal');
                            if (errorModalEl) {
                                var errorMsg = 'An error occurred';
                                if (xhr.responseJSON && xhr.responseJSON.error) {
                                    errorMsg = xhr.responseJSON.error;
                                }
                                errorModalEl.querySelector('#feedbackModalBody').textContent = errorMsg;
                                var errorModal = new bootstrap.Modal(errorModalEl);
                                errorModal.show();
                            }
                        }
                    });
                }
            });
        }

        if ($.fn.DataTable.isDataTable('#dataTables-example')) {
            return;
        }
        var table = $('#dataTables-example').dataTable({
            oLanguage: {
                sSearch: 'ค้นหา:',
                sLengthMenu: 'แสดง _MENU_ รายการต่อหน้า',
                sInfo: 'แสดง _START_ ถึง _END_ จากทั้งหมด _TOTAL_ รายการ',
                sInfoEmpty: 'ไม่มีข้อมูลที่จะแสดง',
                sInfoFiltered: '(กรองจากทั้งหมด _MAX_ รายการ)',
                sZeroRecords: 'ไม่พบข้อมูลที่ตรงกัน',
                oPaginate: { sPrevious: 'ก่อนหน้า', sNext: 'ถัดไป' }
            },
            columnDefs: [
                { orderable: false, targets: [0, -1] }
            ],
            dom: "<'row align-items-center mb-2'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6 text-end'l>>t<'row align-items-center mt-2'<'col-sm-12 col-md-6'i><'col-sm-12 col-md-6 text-end'p>>"
        });
        
        // Match equipment page control polish
        var filterInput = document.querySelector('.dataTables_filter input');
        if (filterInput) {
            filterInput.classList.add('form-control', 'form-control-sm');
            filterInput.placeholder = 'พิมพ์คำค้น...';
        }
        var lengthSelect = document.querySelector('.dataTables_length select');
        if (lengthSelect) {
            lengthSelect.classList.add('form-select', 'form-select-sm');
        }

        // Force pagination buttons to inline-flex like equipment page
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
    });
    </script>

    <style>
    /* Match equipment page layout and controls */
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
