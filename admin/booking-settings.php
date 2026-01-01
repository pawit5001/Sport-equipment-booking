<?php
session_start();
error_reporting(0);
include('includes/config.php');

// Simple admin guard: require admin login in legacy admin session
if (strlen($_SESSION['alogin']) == 0) {
    header('location:index.php');
    exit;
}
if (strlen($_SESSION['alogin']) == 0) {
  header('location:../adminlogin.php');
  exit;
}

$msg = '';
$error = '';

// Fetch current settings row (first/only)
function getSettings(PDO $dbh)
{
    $sql = "SELECT SettingId, LateFeesPerDay, MaxLateDays, MaxLateFee, DamageFeesRate, LostItemFeesRate FROM tblbooking_settings ORDER BY SettingId ASC LIMIT 1";
    $q = $dbh->prepare($sql);
    $q->execute();
    return $q->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = (isset($_POST['ajax']) && $_POST['ajax'] == '1') ||
              (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');
    
    $latePerDay = (float)($_POST['LateFeesPerDay'] ?? 50.00);
    $maxLateDays = (int)($_POST['MaxLateDays'] ?? 7);
    $maxLateFee = (float)($_POST['MaxLateFee'] ?? 500.00);
    $damageRate = (float)($_POST['DamageFeesRate'] ?? 0.50);
    $lostRate = (float)($_POST['LostItemFeesRate'] ?? 1.00);

    $current = getSettings($dbh);
    if ($current) {
        $sql = "UPDATE tblbooking_settings SET LateFeesPerDay = :lpd, MaxLateDays = :mld, MaxLateFee = :mlf, DamageFeesRate = :dr, LostItemFeesRate = :lr WHERE SettingId = :sid";
        $q = $dbh->prepare($sql);
        $q->bindParam(':lpd', $latePerDay);
        $q->bindParam(':mld', $maxLateDays, PDO::PARAM_INT);
        $q->bindParam(':mlf', $maxLateFee);
        $q->bindParam(':dr', $damageRate);
        $q->bindParam(':lr', $lostRate);
        $q->bindParam(':sid', $current['SettingId'], PDO::PARAM_INT);
        try {
            $q->execute();
            $msg = 'บันทึกการตั้งค่าเรียบร้อย';
            
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => true, 'msg' => $msg]);
                exit;
            }
        } catch (Exception $ex) {
            $error = 'บันทึกการตั้งค่าล้มเหลว: ' . $ex->getMessage();
            
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => false, 'error' => $error]);
                exit;
            }
        }
    } else {
        $sql = "INSERT INTO tblbooking_settings (LateFeesPerDay, MaxLateDays, MaxLateFee, DamageFeesRate, LostItemFeesRate) VALUES (:lpd, :mld, :mlf, :dr, :lr)";
        $q = $dbh->prepare($sql);
        $q->bindParam(':lpd', $latePerDay);
        $q->bindParam(':mld', $maxLateDays, PDO::PARAM_INT);
        $q->bindParam(':mlf', $maxLateFee);
        $q->bindParam(':dr', $damageRate);
        $q->bindParam(':lr', $lostRate);
        try {
            $q->execute();
            $msg = 'สร้างการตั้งค่าใหม่เรียบร้อย';
            
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => true, 'msg' => $msg]);
                exit;
            }
        } catch (Exception $ex) {
            $error = 'สร้างการตั้งค่าล้มเหลว: ' . $ex->getMessage();
            
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => false, 'error' => $error]);
                exit;
            }
        }
    }
}

$settings = getSettings($dbh);
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>E-Sports | ตั้งค่าค่าปรับ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <link href="../assets/css/modern-style.css" rel="stylesheet" />
    <link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css' />
</head>
<body>
<?php include('includes/header.php'); ?>
<div class="content-wrapper" style="margin-top: 60px; padding-bottom: 40px;">
  <div class="container">
    <div class="row pad-botm">
      <div class="col-md-12">
        <h4 class="header-line">ตั้งค่าระบบค่าปรับและชดเชย</h4>
      </div>
    </div>

    <div class="row justify-content-center">
      <div class="col-lg-10">
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-primary text-white">ตั้งค่าค่าปรับและค่าชดเชย</div>
          <div class="card-body">
            <!-- Success Alert -->
            <?php if (!empty($msg)) { ?>
              <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fa fa-check-circle me-2"></i>
                <strong>สำเร็จ:</strong> <?php echo htmlentities($msg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>
            <?php } ?>
            
            <!-- Error Alert -->
            <?php if (!empty($error)) { ?>
              <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa fa-exclamation-circle me-2"></i>
                <strong>ผิดพลาด:</strong> <?php echo htmlentities($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>
            <?php } ?>

            <form method="post">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">ค่าปรับต่อวันต่อชิ้น (บาท)<span style="color:red;">*</span></label>
                  <input type="number" step="0.01" name="LateFeesPerDay" class="form-control" value="<?php echo isset($settings['LateFeesPerDay']) ? htmlentities($settings['LateFeesPerDay']) : '50.00'; ?>" required>
                  <small class="text-muted">ค่าปรับที่คิดต่อ 1 วัน ต่อ 1 ชิ้น</small>
                </div>
                
                <div class="col-md-6">
                  <label class="form-label">จำนวนวันสูงสุดที่คิดค่าปรับ<span style="color:red;">*</span></label>
                  <input type="number" name="MaxLateDays" class="form-control" value="<?php echo isset($settings['MaxLateDays']) ? (int)$settings['MaxLateDays'] : 7; ?>" required>
                  <small class="text-muted">จำนวนวันที่ใช้คำนวณค่าปรับอีกมากที่สุด</small>
                </div>
                
                <div class="col-md-6">
                  <label class="form-label">ค่าปรับสูงสุด (บาท)<span style="color:red;">*</span></label>
                  <input type="number" step="0.01" name="MaxLateFee" class="form-control" value="<?php echo isset($settings['MaxLateFee']) ? htmlentities($settings['MaxLateFee']) : '500.00'; ?>" required>
                  <small class="text-muted">จำนวนค่าปรับสูงสุดที่จะคิด</small>
                </div>
                
                <div class="col-md-6">
                  <label class="form-label">อัตราค่าชดเชยความเสียหาย (% ของราคา)<span style="color:red;">*</span></label>
                  <input type="number" step="0.01" name="DamageFeesRate" class="form-control" value="<?php echo isset($settings['DamageFeesRate']) ? htmlentities($settings['DamageFeesRate']) : '0.50'; ?>" required>
                  <small class="text-muted">ตัวอย่าง: 0.50 = 50% ของราคาอุปกรณ์</small>
                </div>
                
                <div class="col-md-6">
                  <label class="form-label">อัตราค่าชดเชยสิ่งของหาย (% ของราคา)<span style="color:red;">*</span></label>
                  <input type="number" step="0.01" name="LostItemFeesRate" class="form-control" value="<?php echo isset($settings['LostItemFeesRate']) ? htmlentities($settings['LostItemFeesRate']) : '1.00'; ?>" required>
                  <small class="text-muted">ตัวอย่าง: 1.00 = 100% ของราคาอุปกรณ์</small>
                </div>
              </div>

              <div class="col-12 d-flex gap-2 mt-4" style="gap: 0.5rem !important;">
                <a href="manage-equipment.php" class="btn btn-outline-secondary"><i class="fa fa-arrow-left"></i> ย้อนกลับ</a>
                <button type="submit" id="saveBtn" class="btn btn-success"><i class="fa fa-save"></i> บันทึกการแก้ไข</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include('includes/footer.php'); ?>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="successModalLabel"><i class="fa fa-check-circle me-2"></i>สำเร็จ</h5>
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
                <h5 class="modal-title" id="errorModalLabel"><i class="fa fa-exclamation-circle me-2"></i>ข้อผิดพลาด</h5>
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
$(document).ready(function() {
    $('form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        
        $submitBtn.prop('disabled', true).addClass('disabled');
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: $form.serialize() + '&ajax=1',
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.ok) {
                    $('#successModalBody').html('<i class="fa fa-check-circle text-success me-2" style="font-size: 2rem;"></i>' + htmlentities(resp.msg || 'บันทึกสำเร็จ'));
                    var successModal = new bootstrap.Modal(document.getElementById('successModal'));
                    successModal.show();
                    $('#successModal').on('hidden.bs.modal', function () {
                        window.location.reload();
                    });
                } else {
                    var msg = (resp && resp.error) ? resp.error : 'เกิดข้อผิดพลาด';
                    $('#errorModalBody').text(msg);
                    var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                    errorModal.show();
                    $submitBtn.prop('disabled', false).removeClass('disabled');
                }
            },
            error: function() {
                $('#errorModalBody').text('เกิดข้อผิดพลาดในการส่งข้อมูล');
                var errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
                errorModal.show();
                $submitBtn.prop('disabled', false).removeClass('disabled');
            }
        });
    });
});

function htmlentities(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>

<style>
  body { min-height: 100vh; display: flex; flex-direction: column; }
  .content-wrapper { flex: 1; }
  .footer-section { margin-top: auto; }
</style>
</body>
</html>
