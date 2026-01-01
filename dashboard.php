<?php
session_start();
error_reporting(0);
include('includes/config.php');

if (strlen($_SESSION['login']) == 0) {
    header('location:index.php');
} else {
    $mobileno = $_SESSION['stdid'];
    
    // Initialize cart if not exists
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>E-Sports | Dashboard</title>
    <!-- BOOTSTRAP 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <!-- FONT AWESOME STYLE  -->
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <!-- CUSTOM STYLE  -->
    <link href="assets/css/style.css" rel="stylesheet" />
    <!-- MODERN STYLE -->
    <link href="assets/css/modern-style.css" rel="stylesheet" />
    <!-- GOOGLE FONT -->
    <link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css' />
</head>
<body>
    <!------MENU SECTION START-->
    <?php include('includes/header.php'); ?>
    <!-- MENU SECTION END-->
    
    <div class="content-wrapper">
        <div class="container">
            <!-- Search & Filter -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fa fa-search"></i></span>
                        <input type="text" id="searchEquipment" class="form-control" placeholder="ค้นหาอุปกรณ์...">
                    </div>
                </div>
                <div class="col-md-4">
                    <select id="filterCategory" class="form-select">
                        <option value="">ทุกหมวดหมู่</option>
                        <?php
                        $catSql = "SELECT id, CategoryName FROM tblcategory ORDER BY CategoryName";
                        $catQuery = $dbh->prepare($catSql);
                        $catQuery->execute();
                        while ($cat = $catQuery->fetch(PDO::FETCH_OBJ)) {
                            echo '<option value="' . $cat->id . '">' . htmlentities($cat->CategoryName) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2 text-end">
                    <span class="badge bg-primary fs-6" id="equipmentCount">
                        <?php
                        $countSql = "SELECT COUNT(*) as total FROM tblequipment";
                        $countQuery = $dbh->prepare($countSql);
                        $countQuery->execute();
                        $countResult = $countQuery->fetch(PDO::FETCH_OBJ);
                        echo $countResult->total;
                        ?> รายการ
                    </span>
                </div>
            </div>

            <div class="row g-4" id="equipmentGrid">
                <?php
                // ดึงอุปกรณ์ทั้งหมด พร้อมคำนวณจำนวนที่พร้อมให้ยืม (หักจำนวนที่ถูกยืมไปแล้ว)
                $sql = "SELECT e.id, e.EquipmentImage, e.EquipmentName, e.EquipmentCode, e.Quantity, e.IsActive, e.CatId, c.CategoryName,
                        (e.Quantity - COALESCE(
                            (SELECT SUM(bd.Quantity - COALESCE(bd.QuantityReturned, 0)) 
                             FROM tblbookingdetails bd 
                             JOIN tblbookings b ON bd.BookingId = b.id 
                             WHERE bd.EquipmentId = e.id 
                             AND b.Status IN ('borrowed', 'partial')
                            ), 0)
                        ) as AvailableQty
                        FROM tblequipment e 
                        LEFT JOIN tblcategory c ON e.CatId = c.id 
                        ORDER BY e.IsActive DESC, e.EquipmentName";
                $query = $dbh->prepare($sql);
                $query->execute();
                $results = $query->fetchAll(PDO::FETCH_OBJ);
                
                if (count($results) > 0) {
                    foreach ($results as $result) {
                        $isInactive = $result->IsActive != 1;
                        $availableQty = max(0, $result->AvailableQty); // ป้องกันติดลบ
                ?>
                    <div class="col-lg-3 col-md-4 col-sm-6 equipment-item <?php echo $isInactive ? 'inactive-item' : ''; ?>" data-category="<?php echo $result->CatId; ?>" data-name="<?php echo htmlentities(strtolower($result->EquipmentName)); ?>" data-active="<?php echo $result->IsActive; ?>">
                        <div class="equipment-card <?php echo $isInactive ? 'inactive-card' : ''; ?>">
                            <div class="equipment-image">
                                <?php if($result->EquipmentImage) { ?>
                                    <img src="uploads/<?php echo htmlentities($result->EquipmentImage); ?>" alt="<?php echo htmlentities($result->EquipmentName); ?>" <?php echo $isInactive ? 'style="filter: grayscale(50%); opacity: 0.7;"' : ''; ?>>
                                <?php } else { ?>
                                    <div class="no-image">
                                        <i class="fa fa-image"></i>
                                    </div>
                                <?php } ?>
                                <?php if ($isInactive) { ?>
                                    <span class="badge bg-secondary position-absolute top-0 end-0 m-2">
                                        <i class="fa fa-ban"></i> ปิดให้ยืม
                                    </span>
                                <?php } elseif ($availableQty <= 0) { ?>
                                    <span class="badge bg-danger position-absolute top-0 end-0 m-2">หมด</span>
                                <?php } elseif ($availableQty <= 3) { ?>
                                    <span class="badge bg-warning text-dark position-absolute top-0 end-0 m-2">เหลือน้อย</span>
                                <?php } ?>
                                <?php if ($isInactive) { ?>
                                <div class="inactive-overlay">
                                    <i class="fa fa-lock"></i>
                                </div>
                                <?php } ?>
                            </div>
                            <div class="equipment-info">
                                <span class="equipment-category"><?php echo htmlentities($result->CategoryName ?? 'ไม่ระบุหมวดหมู่'); ?></span>
                                <h5 class="equipment-name"><?php echo htmlentities($result->EquipmentName); ?></h5>
                                <div class="equipment-code">
                                    <small>รหัส: <?php echo htmlentities($result->EquipmentCode); ?></small>
                                </div>
                                <div class="equipment-footer">
                                    <span class="equipment-quantity <?php echo $availableQty <= 3 && !$isInactive ? 'text-warning' : ''; ?>">
                                        <i class="fa fa-cubes"></i> <?php echo htmlentities($availableQty); ?> ชิ้น
                                    </span>
                                    <?php if ($isInactive) { ?>
                                    <span class="btn btn-sm btn-outline-secondary disabled" style="font-size: 0.75rem;">
                                        <i class="fa fa-ban"></i> ปิดชั่วคราว
                                    </span>
                                    <?php } elseif ($availableQty > 0) { ?>
                                    <button type="button" class="btn btn-sm btn-primary btn-add-cart" 
                                            data-id="<?php echo $result->id; ?>"
                                            data-name="<?php echo htmlentities($result->EquipmentName); ?>"
                                            data-code="<?php echo htmlentities($result->EquipmentCode); ?>"
                                            data-max="<?php echo $availableQty; ?>"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#addToCartModal">
                                        <i class="fa fa-cart-plus"></i> ยืม
                                    </button>
                                    <?php } else { ?>
                                    <button type="button" class="btn btn-sm btn-secondary" disabled>
                                        <i class="fa fa-times"></i> หมด
                                    </button>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php
                    }
                } else {
                ?>
                <div class="col-12 text-center py-5">
                    <i class="fa fa-inbox fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">ยังไม่มีอุปกรณ์ในระบบ</h5>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- Add to Cart Modal -->
    <div class="modal fade" id="addToCartModal" tabindex="-1" aria-labelledby="addToCartModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addToCartModalLabel">
                        <i class="fa fa-cart-plus me-2"></i>เพิ่มลงตะกร้า
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modal_equipment_id">
                    <div class="text-center mb-3">
                        <h5 id="modal_equipment_name" class="fw-bold"></h5>
                        <small class="text-muted">รหัส: <span id="modal_equipment_code"></span></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">จำนวนที่ต้องการยืม</label>
                        <div class="input-group">
                            <button type="button" class="btn btn-outline-secondary" id="btnMinus">
                                <i class="fa fa-minus"></i>
                            </button>
                            <input type="number" class="form-control text-center" id="modal_quantity" value="1" min="1">
                            <button type="button" class="btn btn-outline-secondary" id="btnPlus">
                                <i class="fa fa-plus"></i>
                            </button>
                        </div>
                        <small class="text-muted">คงเหลือ: <span id="modal_available"></span> ชิ้น</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-primary" id="btnAddToCart">
                        <i class="fa fa-cart-plus me-1"></i> เพิ่มลงตะกร้า
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
        <div id="cartToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="cartToastBody">
                    เพิ่มลงตะกร้าแล้ว
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <!-- CONTENT-WRAPPER SECTION END-->
    <?php include('includes/footer.php'); ?>
    <!-- FOOTER SECTION END-->

    <!-- JAVASCRIPT FILES -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/custom.js"></script>

    <script>
    $(document).ready(function() {
        var currentEquipment = {};
        var maxQuantity = 1;

        // Open modal - populate data
        $('.btn-add-cart').on('click', function() {
            currentEquipment = {
                id: $(this).data('id'),
                name: $(this).data('name'),
                code: $(this).data('code'),
                max: $(this).data('max')
            };
            maxQuantity = currentEquipment.max;
            
            $('#modal_equipment_id').val(currentEquipment.id);
            $('#modal_equipment_name').text(currentEquipment.name);
            $('#modal_equipment_code').text(currentEquipment.code);
            $('#modal_available').text(currentEquipment.max);
            $('#modal_quantity').val(1).attr('max', maxQuantity);
        });

        // Quantity buttons
        $('#btnMinus').on('click', function() {
            var qty = parseInt($('#modal_quantity').val()) || 1;
            if (qty > 1) {
                $('#modal_quantity').val(qty - 1);
            }
        });

        $('#btnPlus').on('click', function() {
            var qty = parseInt($('#modal_quantity').val()) || 1;
            if (qty < maxQuantity) {
                $('#modal_quantity').val(qty + 1);
            }
        });

        // Validate quantity input
        $('#modal_quantity').on('change', function() {
            var qty = parseInt($(this).val()) || 1;
            if (qty < 1) qty = 1;
            if (qty > maxQuantity) qty = maxQuantity;
            $(this).val(qty);
        });

        // Add to cart
        $('#btnAddToCart').on('click', function() {
            var equipmentId = $('#modal_equipment_id').val();
            var quantity = parseInt($('#modal_quantity').val()) || 1;
            
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> กำลังเพิ่ม...');
            
            $.ajax({
                url: 'cart-actions.php',
                type: 'POST',
                data: {
                    action: 'add',
                    equipment_id: equipmentId,
                    quantity: quantity
                },
                dataType: 'json',
                success: function(resp) {
                    console.log('Cart response:', resp);
                    if (resp.ok || resp.success) {
                        // Update cart badge
                        updateCartBadge(resp.cartCount || resp.count);
                        
                        // Show toast
                        showToast(resp.msg || resp.message, 'success');
                        
                        // Close modal
                        bootstrap.Modal.getInstance(document.getElementById('addToCartModal')).hide();
                    } else {
                        showToast(resp.error || resp.message || 'เกิดข้อผิดพลาด', 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error, xhr.responseText);
                    showToast('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'danger');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="fa fa-cart-plus me-1"></i> เพิ่มลงตะกร้า');
                }
            });
        });

        // Search equipment
        $('#searchEquipment').on('keyup', function() {
            var searchTerm = $(this).val().toLowerCase();
            filterEquipment();
        });

        // Filter by category
        $('#filterCategory').on('change', function() {
            filterEquipment();
        });

        function filterEquipment() {
            var searchTerm = $('#searchEquipment').val().toLowerCase();
            var categoryId = $('#filterCategory').val();
            var visibleCount = 0;

            $('.equipment-item').each(function() {
                var name = $(this).data('name');
                var cat = $(this).data('category').toString();
                
                var matchSearch = name.indexOf(searchTerm) > -1;
                var matchCategory = categoryId === '' || cat === categoryId;
                
                if (matchSearch && matchCategory) {
                    $(this).show();
                    visibleCount++;
                } else {
                    $(this).hide();
                }
            });

            $('#equipmentCount').text(visibleCount + ' รายการ');
        }

        function updateCartBadge(count) {
            var $badge = $('#cartBadge');
            if ($badge.length > 0) {
                $badge.text(count);
                if (count > 0) {
                    $badge.show();
                } else {
                    $badge.hide();
                }
            } else if (count > 0) {
                // Create badge if not exists
                $('a[href="book-equipment.php"]').append('<span id="cartBadge" class="cart-badge badge bg-danger rounded-pill" style="font-size: 0.7rem; margin-left: 5px;">' + count + '</span>');
            }
        }

        function showToast(message, type) {
            var $toast = $('#cartToast');
            $toast.removeClass('bg-success bg-danger bg-warning').addClass('bg-' + type);
            $('#cartToastBody').text(message);
            var toast = new bootstrap.Toast($toast[0]);
            toast.show();
        }

        // Load initial cart count
        $.ajax({
            url: 'cart-actions.php',
            type: 'POST',
            data: { action: 'count' },
            dataType: 'json',
            success: function(resp) {
                if (resp.ok && resp.cartCount > 0) {
                    updateCartBadge(resp.cartCount);
                }
            }
        });
    });
    </script>

    <style>
    /* Equipment Grid Styles */
    .equipment-card {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .equipment-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    }

    .equipment-image {
        height: 180px;
        background: #f8f9fa;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        position: relative;
    }

    .equipment-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .equipment-image .no-image {
        color: #dee2e6;
        font-size: 3rem;
    }

    .equipment-info {
        padding: 1rem;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .equipment-category {
        font-size: 0.75rem;
        color: #0891b2;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .equipment-name {
        font-size: 1rem;
        font-weight: 600;
        color: #1e293b;
        margin: 0.5rem 0;
        line-height: 1.3;
    }

    .equipment-code {
        color: #64748b;
        margin-bottom: 0.75rem;
    }

    .equipment-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: auto;
        padding-top: 0.75rem;
        border-top: 1px solid #f1f5f9;
    }

    .equipment-quantity {
        font-size: 0.9rem;
        color: #64748b;
    }

    .equipment-quantity i {
        margin-right: 4px;
    }

    .btn-add-cart {
        border-radius: 20px;
        padding: 0.35rem 1rem;
        font-weight: 500;
    }

    /* Inactive Equipment Styles */
    .inactive-card {
        opacity: 0.85;
        position: relative;
    }
    .inactive-card:hover {
        transform: none;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    .inactive-card .equipment-info {
        background: #f8f9fa;
    }
    .inactive-card .equipment-name {
        color: #64748b;
    }
    .inactive-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.3);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .inactive-overlay i {
        font-size: 3rem;
        color: rgba(255,255,255,0.8);
    }

    /* Search & Filter */
    .input-group-text {
        border-right: none;
    }
    
    .input-group .form-control {
        border-left: none;
    }
    
    .input-group .form-control:focus {
        box-shadow: none;
        border-color: #dee2e6;
    }

    /* Toast */
    .toast {
        min-width: 280px;
    }
    </div>
</body>
</html>
<?php } ?>
