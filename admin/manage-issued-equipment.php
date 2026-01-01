<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
error_reporting(0);
include('includes/config.php');
if (strlen($_SESSION['alogin']) == 0) {
    header('location:../adminlogin.php');
    exit;
} else {

// ดึงรายการยืมทั้งหมด (กรุ๊ปตาม Booking)
$sql = "SELECT b.*, 
        m.Name, m.Surname, m.StudentID, m.Email,
        (SELECT COUNT(*) FROM tblbookingdetails WHERE BookingId = b.id) as ItemCount,
        (SELECT SUM(Quantity) FROM tblbookingdetails WHERE BookingId = b.id) as TotalQty,
        (SELECT SUM(QuantityReturned) FROM tblbookingdetails WHERE BookingId = b.id) as TotalReturned,
        (SELECT MAX(DueDate) FROM tblbookingdetails WHERE BookingId = b.id) as LatestDue
        FROM tblbookings b
        JOIN tblmembers m ON b.MemberId = m.id
        ORDER BY b.BookingDate DESC";

$query = $dbh->prepare($sql);
$query->execute();
$bookings = $query->fetchAll(PDO::FETCH_ASSOC);

// คำนวณค่าปรับและสถานะ
$today = new DateTime();
foreach ($bookings as &$booking) {
    $booking['totalFine'] = 0;
    $booking['hasOverdue'] = false;
    
    // ดึงรายละเอียด
    $sqlDetails = "SELECT bd.*, e.EquipmentName, e.EquipmentCode 
                   FROM tblbookingdetails bd 
                   JOIN tblequipment e ON bd.EquipmentId = e.id 
                   WHERE bd.BookingId = :bookingId";
    $queryDetails = $dbh->prepare($sqlDetails);
    $queryDetails->execute([':bookingId' => $booking['id']]);
    $booking['items'] = $queryDetails->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($booking['items'] as $item) {
        if ($item['DueDate'] && $item['ReturnStatus'] != 1) {
            $dueDate = new DateTime($item['DueDate']);
            if ($today > $dueDate) {
                $overdueDays = $dueDate->diff($today)->days;
                $booking['totalFine'] += $overdueDays * ($item['FinePerDay'] ?? 10) * $item['Quantity'];
                $booking['hasOverdue'] = true;
            }
        }
    }
    
    // อัพเดทสถานะจริง
    if ($booking['hasOverdue'] && in_array($booking['Status'], ['borrowed', 'partial'])) {
        $booking['displayStatus'] = 'overdue';
    } else {
        $booking['displayStatus'] = $booking['Status'];
    }
}
unset($booking);

// นับสถิติ (partial นับรวมกับ borrowed เพราะยังมีของค้างอยู่)
$stats = [
    'all' => count($bookings),
    'borrowed' => 0,
    'overdue' => 0,
    'returned' => 0
];
foreach ($bookings as $b) {
    if ($b['displayStatus'] == 'borrowed' || $b['displayStatus'] == 'partial') $stats['borrowed']++;
    elseif ($b['displayStatus'] == 'overdue') $stats['overdue']++;
    elseif ($b['displayStatus'] == 'returned') $stats['returned']++;
}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>E-Sports | จัดการยืม/คืนอุปกรณ์</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/js/dataTables/dataTables.bootstrap.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <link href="../assets/css/modern-style.css" rel="stylesheet" />
    <style>
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border: 2px solid transparent;
            transition: all 0.3s;
            cursor: pointer;
        }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-card.active { border-color: #3b82f6; }
        .stat-card .number { font-size: 1.5rem; font-weight: 700; }
        .stat-card .label { color: #64748b; font-size: 0.8rem; }
        .stat-card.borrowed .number { color: #3b82f6; }
        .stat-card.overdue .number { color: #ef4444; }
        .stat-card.returned .number { color: #22c55e; }
        
        .booking-row {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            margin-bottom: 12px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .booking-row:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .booking-row.overdue { border-left: 3px solid #ef4444; }
        .booking-row.borrowed { border-left: 3px solid #3b82f6; }
        .booking-row.returned { border-left: 3px solid #22c55e; }
        .booking-row.partial { border-left: 3px solid #f59e0b; }
        
        .booking-header {
            background: #f8fafc;
            padding: 10px 15px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .booking-code {
            font-weight: 600;
            color: #1e40af;
            font-size: 0.95rem;
        }
        .booking-body { padding: 15px; }
        
        .student-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px dashed #e2e8f0;
        }
        .student-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3b82f6, #1e40af);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1rem;
        }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-borrowed { background: #dbeafe; color: #1e40af; }
        .status-returned { background: #dcfce7; color: #166534; }
        .status-overdue { background: #fee2e2; color: #991b1b; }
        .status-partial { background: #fef3c7; color: #92400e; }
        
        .fine-badge {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.75rem;
        }
        
        .item-table {
            width: 100%;
            margin-top: 10px;
            font-size: 0.85rem;
        }
        .item-table th {
            background: #f1f5f9;
            padding: 8px 10px;
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 600;
        }
        .item-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #f1f5f9;
        }
        .item-table tr:last-child td { border-bottom: none; }
        
        .item-status-returned { color: #22c55e; }
        .item-status-pending { color: #f59e0b; }
        .item-status-overdue { color: #ef4444; }
        
        .search-box {
            position: relative;
            margin-bottom: 15px;
        }
        .search-box input {
            padding-left: 40px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            font-size: 0.9rem;
        }
        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
        .student-name { font-size: 0.9rem; }
        .student-name .text-muted { font-size: 0.8rem; }
        .btn-sm { font-size: 0.8rem; padding: 5px 12px; }
        
        /* Sorting Styles */
        .sort-controls {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .sort-label {
            color: #64748b;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .sort-btn {
            padding: 5px 12px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            background: white;
            color: #64748b;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .sort-btn:hover {
            border-color: #3b82f6;
            color: #3b82f6;
        }
        .sort-btn.active {
            background: #eff6ff;
            border-color: #3b82f6;
            color: #1e40af;
        }
        .sort-btn i.sort-icon {
            font-size: 0.65rem;
            transition: transform 0.2s;
        }
        .sort-btn.desc i.sort-icon {
            transform: rotate(180deg);
        }
        .controls-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        /* Pagination Styles */
        .pagination-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        }
        .pagination-info {
            color: #64748b;
            font-size: 0.85rem;
        }
        .pagination-buttons {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .page-btn {
            padding: 6px 12px;
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            color: #64748b;
            transition: all 0.2s;
        }
        .page-btn:hover:not(:disabled) {
            border-color: #3b82f6;
            color: #3b82f6;
        }
        .page-btn.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .page-numbers {
            display: flex;
            gap: 5px;
        }
        .per-page-select {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #64748b;
        }
        .per-page-select select {
            padding: 5px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <?php include('includes/header.php');?>
    
    <div class="content-wrapper">
        <div class="container" style="max-width: 1200px;">
            <div class="row pad-botm">
                <div class="col-md-12">
                    <h4 class="header-line">📦 จัดการยืม/คืนอุปกรณ์</h4>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if($_SESSION['admin_msg']): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                ✅ <?php echo $_SESSION['admin_msg']; $_SESSION['admin_msg']=''; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            <?php if($_SESSION['admin_error']): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                ❌ <?php echo $_SESSION['admin_error']; $_SESSION['admin_error']=''; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Stats Cards -->
            <div class="row mb-3">
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-card active" data-filter="all">
                        <div class="number"><?php echo $stats['all']; ?></div>
                        <div class="label">📋 ทั้งหมด</div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-card borrowed" data-filter="borrowed">
                        <div class="number"><?php echo $stats['borrowed']; ?></div>
                        <div class="label">⏳ กำลังยืม</div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-card overdue" data-filter="overdue">
                        <div class="number"><?php echo $stats['overdue']; ?></div>
                        <div class="label">⚠️ เกินกำหนด</div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-card returned" data-filter="returned">
                        <div class="number"><?php echo $stats['returned']; ?></div>
                        <div class="label">✅ คืนแล้ว</div>
                    </div>
                </div>
            </div>
            
            <!-- Search & Sort Controls -->
            <div class="controls-row">
                <div class="search-box" style="margin-bottom: 0; flex: 1; min-width: 250px;">
                    <i class="fa fa-search"></i>
                    <input type="text" class="form-control" id="searchInput" placeholder="ค้นหา รหัสใบยืม, รหัสนักศึกษา, ชื่อ...">
                </div>
                <div class="sort-controls">
                    <span class="sort-label"><i class="fa fa-sort"></i> เรียง:</span>
                    <button class="sort-btn active" data-sort="date" data-order="desc">
                        <i class="fa fa-calendar"></i> วันที่
                        <i class="fa fa-arrow-up sort-icon"></i>
                    </button>
                    <button class="sort-btn" data-sort="due" data-order="asc">
                        <i class="fa fa-clock-o"></i> กำหนดคืน
                        <i class="fa fa-arrow-up sort-icon"></i>
                    </button>
                    <button class="sort-btn" data-sort="student" data-order="asc">
                        <i class="fa fa-user"></i> นักศึกษา
                        <i class="fa fa-arrow-up sort-icon"></i>
                    </button>
                    <button class="sort-btn" data-sort="fine" data-order="desc">
                        <i class="fa fa-money"></i> ค่าปรับ
                        <i class="fa fa-arrow-up sort-icon"></i>
                    </button>
                </div>
            </div>
            
            <!-- Booking List -->
            <div id="bookingList">
                <?php if (empty($bookings)): ?>
                <div class="text-center py-5">
                    <div style="font-size: 64px;">📭</div>
                    <h5 class="mt-3">ยังไม่มีรายการยืม</h5>
                </div>
                <?php else: ?>
                <?php foreach ($bookings as $booking): 
                    $statusClass = $booking['displayStatus'];
                    $statusText = 'กำลังยืม';
                    switch ($statusClass) {
                        case 'borrowed': $statusText = 'กำลังยืม'; break;
                        case 'returned': $statusText = 'คืนแล้ว'; break;
                        case 'overdue': $statusText = 'เกินกำหนด'; break;
                        case 'partial': $statusText = 'คืนบางส่วน'; break;
                    }
                    $initial = mb_substr($booking['Name'], 0, 1, 'UTF-8');
                    $latestDue = $booking['LatestDue'] ? new DateTime($booking['LatestDue']) : null;
                ?>
                <div class="booking-row <?php echo $statusClass; ?>" 
                     data-status="<?php echo $statusClass; ?>" 
                     data-search="<?php echo strtolower($booking['BookingCode'] . ' ' . $booking['StudentID'] . ' ' . $booking['Name'] . ' ' . $booking['Surname']); ?>"
                     data-date="<?php echo $booking['BookingDate']; ?>"
                     data-due="<?php echo $booking['LatestDue'] ?? '9999-12-31'; ?>"
                     data-student="<?php echo $booking['Name'] . ' ' . $booking['Surname']; ?>"
                     data-fine="<?php echo $booking['totalFine']; ?>">
                    <div class="booking-header">
                        <div>
                            <span class="booking-code">🎫 <?php echo htmlspecialchars($booking['BookingCode']); ?></span>
                            <span class="text-muted ms-2"><?php echo date('d/m/Y H:i', strtotime($booking['BookingDate'])); ?></span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($booking['totalFine'] > 0): ?>
                            <span class="fine-badge">💰 ค่าปรับ ฿<?php echo number_format($booking['totalFine'], 0); ?></span>
                            <?php endif; ?>
                            <span class="status-badge status-<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                        </div>
                    </div>
                    <div class="booking-body">
                        <!-- Student Info -->
                        <div class="student-info">
                            <div class="student-avatar"><?php echo $initial; ?></div>
                            <div class="student-name">
                                <strong><?php echo htmlspecialchars($booking['Name'] . ' ' . $booking['Surname']); ?></strong>
                                <div class="text-muted">
                                    🎓 <?php echo htmlspecialchars($booking['StudentID']); ?> | 
                                    📧 <?php echo htmlspecialchars($booking['Email']); ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Due Date Info -->
                        <div class="mb-2" style="font-size: 0.85rem;">
                            <span class="text-muted">📅 กำหนดคืน:</span>
                            <strong class="<?php echo $booking['hasOverdue'] ? 'text-danger' : ''; ?>">
                                <?php echo $latestDue ? $latestDue->format('d/m/Y') : '-'; ?>
                            </strong>
                            <?php if ($booking['hasOverdue']): ?>
                            <span class="text-danger small">(เกินกำหนด)</span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Items Table -->
                        <table class="item-table">
                            <thead>
                                <tr>
                                    <th>อุปกรณ์</th>
                                    <th class="text-center">ยืม</th>
                                    <th class="text-center">คืนแล้ว</th>
                                    <th class="text-center">กำหนดคืน</th>
                                    <th class="text-center">สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($booking['items'] as $item): 
                                    $itemDue = $item['DueDate'] ? new DateTime($item['DueDate']) : null;
                                    $itemOverdue = $itemDue && $today > $itemDue && $item['ReturnStatus'] != 1;
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['EquipmentName']); ?></strong>
                                        <div class="text-muted small"><?php echo htmlspecialchars($item['EquipmentCode']); ?></div>
                                    </td>
                                    <td class="text-center"><?php echo $item['Quantity']; ?></td>
                                    <td class="text-center"><?php echo $item['QuantityReturned'] ?? 0; ?></td>
                                    <td class="text-center <?php echo $itemOverdue ? 'text-danger' : ''; ?>">
                                        <?php echo $itemDue ? $itemDue->format('d/m/Y') : '-'; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($item['ReturnStatus'] == 1): ?>
                                        <span class="item-status-returned">✅ คืนแล้ว</span>
                                        <?php elseif ($itemOverdue): ?>
                                        <span class="item-status-overdue">⚠️ เกินกำหนด</span>
                                        <?php else: ?>
                                        <span class="item-status-pending">⏳ รอคืน</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- Actions -->
                        <?php if ($booking['displayStatus'] != 'returned'): ?>
                        <div class="mt-2 pt-2 border-top d-flex gap-2">
                            <a href="return-booking.php?id=<?php echo $booking['id']; ?>" class="btn btn-success btn-sm">
                                ✅ รับคืน
                            </a>
                            <a href="view-booking.php?id=<?php echo $booking['id']; ?>" class="btn btn-outline-primary btn-sm">
                                👁️ ดูรายละเอียด
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="mt-2 pt-2 border-top d-flex gap-2">
                            <a href="return-booking.php?id=<?php echo $booking['id']; ?>&edit=1" class="btn btn-warning btn-sm">
                                ✏️ แก้ไขการรับคืน
                            </a>
                            <a href="view-booking.php?id=<?php echo $booking['id']; ?>" class="btn btn-outline-primary btn-sm">
                                👁️ ดูรายละเอียด
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Pagination Controls -->
            <div class="pagination-controls">
                <div class="pagination-info">
                    แสดง <span id="showFrom">1</span>-<span id="showTo">10</span> จาก <span id="totalItems"><?php echo count($bookings); ?></span> รายการ
                </div>
                <div class="pagination-buttons">
                    <button type="button" class="page-btn" id="prevPage" disabled>
                        <i class="fa fa-chevron-left"></i>
                    </button>
                    <div class="page-numbers" id="pageNumbers">
                        <!-- Generated by JavaScript -->
                    </div>
                    <button type="button" class="page-btn" id="nextPage">
                        <i class="fa fa-chevron-right"></i>
                    </button>
                </div>
                <div class="per-page-select">
                    <label>แสดง:</label>
                    <select id="perPage">
                        <option value="5">5</option>
                        <option value="10" selected>10</option>
                        <option value="20">20</option>
                        <option value="all">ทั้งหมด</option>
                    </select>
                    <span>รายการ</span>
                </div>
            </div>
        </div>
    </div>

    <?php include('includes/footer.php');?>
    <script src="assets/js/jquery-1.10.2.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // Pagination variables
        var perPage = 10;
        var currentPage = 1;
        var totalItems = 0;
        var totalPages = 1;
        
        // Get visible rows based on filter and search
        function getVisibleRows() {
            var activeFilter = $('.stat-card.active').data('filter');
            var searchValue = $('#searchInput').val().toLowerCase();
            
            return $('#bookingList .booking-row').filter(function() {
                var $row = $(this);
                var status = $row.data('status');
                var searchData = $row.data('search');
                
                // Filter check
                var passFilter = false;
                if (activeFilter === 'all') {
                    passFilter = true;
                } else if (activeFilter === 'borrowed') {
                    passFilter = (status === 'borrowed' || status === 'partial');
                } else {
                    passFilter = (status === activeFilter);
                }
                
                // Search check
                var passSearch = !searchValue || searchData.indexOf(searchValue) > -1;
                
                return passFilter && passSearch;
            });
        }
        
        // Initialize pagination
        function initPagination() {
            var $visibleRows = getVisibleRows();
            totalItems = $visibleRows.length;
            
            if (perPage === 'all' || perPage >= totalItems) {
                totalPages = 1;
            } else {
                totalPages = Math.ceil(totalItems / perPage);
            }
            
            currentPage = 1;
            renderPagination();
            showPage(currentPage);
        }
        
        // Show specific page
        function showPage(page) {
            var $allRows = $('#bookingList .booking-row');
            var $visibleRows = getVisibleRows();
            
            // Hide all rows first
            $allRows.hide();
            
            if (perPage === 'all') {
                $visibleRows.show();
                $('#showFrom').text(1);
                $('#showTo').text($visibleRows.length);
            } else {
                var start = (page - 1) * perPage;
                var end = start + perPage;
                
                $visibleRows.each(function(index) {
                    if (index >= start && index < end) {
                        $(this).show();
                    }
                });
                
                $('#showFrom').text($visibleRows.length > 0 ? start + 1 : 0);
                $('#showTo').text(Math.min(end, $visibleRows.length));
            }
            
            $('#totalItems').text($visibleRows.length);
            
            // Update button states
            $('#prevPage').prop('disabled', currentPage <= 1);
            $('#nextPage').prop('disabled', currentPage >= totalPages || totalPages <= 1);
        }
        
        // Render pagination buttons
        function renderPagination() {
            var $pageNumbers = $('#pageNumbers');
            $pageNumbers.empty();
            
            if (totalPages <= 1) {
                $pageNumbers.append('<button class="page-btn active">1</button>');
                return;
            }
            
            // Show max 5 page buttons
            var startPage = Math.max(1, currentPage - 2);
            var endPage = Math.min(totalPages, startPage + 4);
            
            if (endPage - startPage < 4) {
                startPage = Math.max(1, endPage - 4);
            }
            
            for (var i = startPage; i <= endPage; i++) {
                var activeClass = i === currentPage ? 'active' : '';
                $pageNumbers.append('<button class="page-btn ' + activeClass + '" data-page="' + i + '">' + i + '</button>');
            }
        }
        
        // Page number click
        $(document).on('click', '#pageNumbers .page-btn', function() {
            currentPage = parseInt($(this).data('page'));
            renderPagination();
            showPage(currentPage);
        });
        
        // Previous page
        $('#prevPage').click(function() {
            if (currentPage > 1) {
                currentPage--;
                renderPagination();
                showPage(currentPage);
            }
        });
        
        // Next page
        $('#nextPage').click(function() {
            if (currentPage < totalPages) {
                currentPage++;
                renderPagination();
                showPage(currentPage);
            }
        });
        
        // Per page change
        $('#perPage').change(function() {
            var val = $(this).val();
            perPage = val === 'all' ? 'all' : parseInt(val);
            initPagination();
        });
        
        // Initialize on page load
        initPagination();
        
        // Filter by status
        $('.stat-card').click(function() {
            var filter = $(this).data('filter');
            $('.stat-card').removeClass('active');
            $(this).addClass('active');
            
            // Re-initialize pagination after filter
            initPagination();
        });
        
        // Search
        $('#searchInput').on('keyup', function() {
            // Re-initialize pagination after search
            initPagination();
        });
        
        // Sorting functionality
        $('.sort-btn').click(function() {
            var sortBy = $(this).data('sort');
            var currentOrder = $(this).data('order');
            
            // Toggle order if same button clicked
            if ($(this).hasClass('active')) {
                currentOrder = currentOrder === 'asc' ? 'desc' : 'asc';
                $(this).data('order', currentOrder);
            }
            
            // Update UI
            $('.sort-btn').removeClass('active desc');
            $(this).addClass('active');
            if (currentOrder === 'desc') {
                $(this).addClass('desc');
            }
            
            // Sort rows
            var $container = $('#bookingList');
            var $rows = $container.children('.booking-row').get();
            
            $rows.sort(function(a, b) {
                var valA, valB;
                
                switch(sortBy) {
                    case 'date':
                        valA = $(a).data('date');
                        valB = $(b).data('date');
                        break;
                    case 'due':
                        valA = $(a).data('due');
                        valB = $(b).data('due');
                        break;
                    case 'student':
                        valA = $(a).data('student').toLowerCase();
                        valB = $(b).data('student').toLowerCase();
                        break;
                    case 'fine':
                        valA = parseFloat($(a).data('fine')) || 0;
                        valB = parseFloat($(b).data('fine')) || 0;
                        break;
                }
                
                if (sortBy === 'fine') {
                    return currentOrder === 'asc' ? valA - valB : valB - valA;
                } else {
                    if (currentOrder === 'asc') {
                        return valA > valB ? 1 : -1;
                    } else {
                        return valA < valB ? 1 : -1;
                    }
                }
            });
            
            $.each($rows, function(idx, row) {
                $container.append(row);
            });
            
            // Re-initialize pagination after sorting
            initPagination();
        });
    });
    </script>
</body>
</html>
<?php } ?>
