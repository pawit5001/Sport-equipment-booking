<?php
session_start();
error_reporting(0);
include('includes/config.php');
if (strlen($_SESSION['alogin']) == 0) {
  header('location:../adminlogin.php');
  exit;
}

$msg = '';
$error = '';

// Handle status transitions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['booking_id'])) {
    $bookingId = (int)$_POST['booking_id'];
    $action = $_POST['action'];
    $allowed = ['approve','borrow','return','cancel'];
    if (in_array($action, $allowed, true)) {
        try {
            if ($action === 'approve') {
                $q = $dbh->prepare("UPDATE tblbooking_order SET `Status` = 'approved' WHERE BookingId = :bid");
            } elseif ($action === 'borrow') {
                $q = $dbh->prepare("UPDATE tblbooking_order SET `Status` = 'borrowed' WHERE BookingId = :bid");
            } elseif ($action === 'return') {
                $today = date('Y-m-d');
                $q = $dbh->prepare("UPDATE tblbooking_order SET `Status` = 'returned', ActualReturnDate = COALESCE(ActualReturnDate, :today) WHERE BookingId = :bid");
                $q->bindParam(':today', $today);
            } else { // cancel
                $q = $dbh->prepare("UPDATE tblbooking_order SET `Status` = 'cancelled' WHERE BookingId = :bid");
            }
            $q->bindParam(':bid', $bookingId, PDO::PARAM_INT);
            $q->execute();
            $msg = 'อัปเดตสถานะคำสั่งเรียบร้อย';
        } catch (Exception $ex) {
            $error = 'อัปเดตสถานะล้มเหลว: ' . $ex->getMessage();
        }
    }
}

$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$validStatuses = ['all','pending','approved','borrowed','returned','cancelled'];
if (!in_array($status, $validStatuses, true)) { $status = 'all'; }

$sql = "SELECT bo.BookingId, bo.MemberId, bo.BookingDate, bo.PlannedReturnDate, bo.ActualReturnDate, bo.Status, bo.TotalItems,
                m.MemberName
         FROM tblbooking_order bo
         JOIN tblmembers m ON m.id = bo.MemberId";
if ($status !== 'all') {
    $sql .= " WHERE bo.Status = :st";
}
$sql .= " ORDER BY bo.BookingDate DESC";
$q = $dbh->prepare($sql);
if ($status !== 'all') {
    $q->bindParam(':st', $status);
}
$q->execute();
$orders = $q->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>Admin | จัดการคำสั่งยืม</title>
    <link href="assets/css/bootstrap.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <link href="../assets/css/modern-style.css" rel="stylesheet" />
</head>
<body>
<?php include('includes/header.php'); ?>
<div class="content-wrapper">
  <div class="container">
    <div class="row">
      <div class="col-md-12">
        <h3 class="header-line">จัดการคำสั่งยืม</h3>
      </div>
    </div>
    <?php if (!empty($error)) { ?><div class="alert alert-danger"><strong>ผิดพลาด:</strong> <?php echo htmlentities($error); ?></div><?php } ?>
    <?php if (!empty($msg)) { ?><div class="alert alert-success"><strong>สำเร็จ:</strong> <?php echo htmlentities($msg); ?></div><?php } ?>

    <ul class="nav nav-pills mb-3">
      <?php foreach ($validStatuses as $st) { ?>
        <li class="nav-item"><a class="nav-link <?php echo $status===$st?'active':''; ?>" href="manage-bookings.php?status=<?php echo $st; ?>"><?php echo strtoupper($st); ?></a></li>
      <?php } ?>
    </ul>

    <div class="panel panel-default">
      <div class="panel-heading">รายการ</div>
      <div class="panel-body">
        <div class="table-responsive">
          <table class="table table-striped">
            <thead>
              <tr>
                <th>#</th>
                <th>สมาชิก</th>
                <th>วันที่ยืม</th>
                <th>กำหนดคืน</th>
                <th>สถานะ</th>
                <th>จำนวนรายการ</th>
                <th>การทำงาน</th>
              </tr>
            </thead>
            <tbody>
              <?php $i=1; foreach ($orders as $o) { ?>
                <tr>
                  <td><?php echo $i++; ?></td>
                  <td><?php echo htmlentities($o['MemberName']); ?></td>
                  <td><?php echo date('d/m/Y H:i', strtotime($o['BookingDate'])); ?></td>
                  <td><?php echo date('d/m/Y', strtotime($o['PlannedReturnDate'])); ?></td>
                  <td><span class="badge bg-secondary"><?php echo htmlentities($o['Status']); ?></span></td>
                  <td><?php echo (int)$o['TotalItems']; ?></td>
                  <td>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="booking_id" value="<?php echo (int)$o['BookingId']; ?>">
                      <button name="action" value="approve" class="btn btn-sm btn-info">อนุมัติ</button>
                      <button name="action" value="borrow" class="btn btn-sm btn-warning">เริ่มยืม</button>
                      <button name="action" value="return" class="btn btn-sm btn-success">คืนแล้ว</button>
                      <button name="action" value="cancel" class="btn btn-sm btn-danger" onclick="return confirm('ยกเลิกคำสั่งนี้?');">ยกเลิก</button>
                      <a class="btn btn-sm btn-outline-primary" href="view-booking.php?id=<?php echo (int)$o['BookingId']; ?>">รายละเอียด</a>
                    </form>
                  </td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include('includes/footer.php'); ?>
<script src="assets/js/jquery-1.10.2.js"></script>
<script src="assets/js/bootstrap.js"></script>
<script src="assets/js/custom.js"></script>
</body>
</html>
