<?php
ob_start();
session_start();
error_reporting(0);

// Handle AJAX request FIRST - before any other includes
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change'])) {
    include('includes/config.php');
    
    // Clear all output
    ob_end_clean();
    
    // Set header for JSON
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $mobile = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
        $newpassword = isset($_POST['newpassword']) ? trim($_POST['newpassword']) : '';
        $confirmpassword = isset($_POST['confirmpassword']) ? trim($_POST['confirmpassword']) : '';
        $vercode = isset($_POST['vercode']) ? trim($_POST['vercode']) : '';
        
        // Validate required fields FIRST (in order from top to bottom)
        if (empty($email)) {
            echo json_encode(array(
                'type' => 'warning',
                'title' => 'ข้อมูลไม่ครบ',
                'message' => '<i class="fa fa-envelope me-2"></i>กรุณากรอกอีเมล',
                'redirect' => null
            ));
            exit;
        }
        
        if (empty($mobile)) {
            echo json_encode(array(
                'type' => 'warning',
                'title' => 'ข้อมูลไม่ครบ',
                'message' => '<i class="fa fa-id-card me-2"></i>กรุณากรอกรหัสนักศึกษา',
                'redirect' => null
            ));
            exit;
        }
        
        if (empty($newpassword)) {
            echo json_encode(array(
                'type' => 'warning',
                'title' => 'ข้อมูลไม่ครบ',
                'message' => '<i class="fa fa-lock me-2"></i>กรุณากรอกรหัสผ่านใหม่',
                'redirect' => null
            ));
            exit;
        }
        
        if (empty($confirmpassword)) {
            echo json_encode(array(
                'type' => 'warning',
                'title' => 'ข้อมูลไม่ครบ',
                'message' => '<i class="fa fa-lock me-2"></i>กรุณายืนยันรหัสผ่าน',
                'redirect' => null
            ));
            exit;
        }
        
        if (empty($vercode)) {
            echo json_encode(array(
                'type' => 'warning',
                'title' => 'ข้อมูลไม่ครบ',
                'message' => '<i class="fa fa-shield me-2"></i>กรุณากรอกรหัสยืนยัน',
                'redirect' => null
            ));
            exit;
        }
        
        // Validate captcha
        if ($vercode != $_SESSION["vercode"]) {
            echo json_encode(array(
                'type' => 'error',
                'title' => 'รหัสยืนยันไม่ถูกต้อง',
                'message' => '<i class="fa fa-shield me-2"></i>กรุณากรอกรหัสยืนยันให้ถูกต้อง',
                'redirect' => null
            ));
            exit;
        }
        
        // Validate password match
        if ($newpassword !== $confirmpassword) {
            echo json_encode(array(
                'type' => 'error',
                'title' => 'รหัสผ่านไม่ตรงกัน',
                'message' => '<i class="fa fa-exclamation-circle me-2"></i>กรุณากรอกรหัสผ่านให้ตรงกัน',
                'redirect' => null
            ));
            exit;
        }
        
        // Check if email and student ID exist and get current password
        $sql = "SELECT Email, Password FROM tblmembers WHERE Email=:email and StudentID=:mobile";
        $query = $dbh->prepare($sql);
        $query->bindParam(':email', $email, PDO::PARAM_STR);
        $query->bindParam(':mobile', $mobile, PDO::PARAM_STR);
        $query->execute();
        
        if($query->rowCount() > 0) {
            $result = $query->fetch(PDO::FETCH_ASSOC);
            $currentPassword = $result['Password'];
            
            // Check if new password is the same as current password
            $hashedPassword = md5($newpassword);
            if ($hashedPassword === $currentPassword) {
                echo json_encode(array(
                    'type' => 'error',
                    'title' => 'รหัสผ่านซ้ำกับรหัสผ่านปัจจุบัน',
                    'message' => '<i class="fa fa-exclamation-circle me-2"></i>กรุณาสร้างรหัสผ่านใหม่ ที่ต่างจากรหัสผ่านปัจจุบัน',
                    'redirect' => null
                ));
                exit;
            }
            
            // Update password
            $con = "UPDATE tblmembers SET Password=:newpassword WHERE Email=:email AND StudentID=:mobile";
            $chngpwd1 = $dbh->prepare($con);
            $chngpwd1->bindParam(':email', $email, PDO::PARAM_STR);
            $chngpwd1->bindParam(':mobile', $mobile, PDO::PARAM_STR);
            $chngpwd1->bindParam(':newpassword', $hashedPassword, PDO::PARAM_STR);
            
            if($chngpwd1->execute()) {
                echo json_encode(array(
                    'type' => 'success',
                    'title' => 'รีเซ็ตรหัสผ่านสำเร็จ!',
                    'message' => '<i class="fa fa-check-circle me-2"></i>รหัสผ่านของคุณถูกเปลี่ยนแล้ว<br/>กรุณาเข้าสู่ระบบด้วยรหัสผ่านใหม่',
                    'redirect' => 'index.php'
                ));
            } else {
                echo json_encode(array(
                    'type' => 'error',
                    'title' => 'เกิดข้อผิดพลาด',
                    'message' => '<i class="fa fa-exclamation-circle me-2"></i>ไม่สามารถรีเซ็ตรหัสผ่านได้',
                    'redirect' => null
                ));
            }
        } else {
            echo json_encode(array(
                'type' => 'error',
                'title' => 'ข้อมูลไม่ถูกต้อง',
                'message' => '<i class="fa fa-exclamation-circle me-2"></i>อีเมลหรือรหัสนักศึกษาไม่ถูกต้อง',
                'redirect' => null
            ));
        }
        exit;
        
    } catch (Exception $e) {
        echo json_encode(array(
            'type' => 'error',
            'title' => 'เกิดข้อผิดพลาด',
            'message' => '<i class="fa fa-exclamation-circle me-2"></i>' . htmlspecialchars($e->getMessage()),
            'redirect' => null
        ));
        exit;
    }
}

// Close output buffering if still active
if (ob_get_level() > 0) {
    ob_end_clean();
}

// Include config for normal page load
include('includes/config.php');
?>
<!DOCTYPE html>>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>E-Sports | Password Reset </title>
    <!-- BOOTSTRAP CORE STYLE  -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <!-- FONT AWESOME STYLE  -->
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <!-- CUSTOM STYLE  -->
    <link href="assets/css/style.css" rel="stylesheet" />
    <!-- MODERN STYLE -->
    <link href="assets/css/modern-style.css" rel="stylesheet" />
    <!-- GOOGLE FONT -->
    <link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css' />
    <script type="text/javascript">
        // Modern Modal Alert Function
        function showAlert(type, title, message, redirectUrl = null) {
            const modalEl = document.getElementById('alertModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            const modalHeader = document.getElementById('modalHeader');
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');
            const modalButton = document.getElementById('modalButton');
            
            const alertConfig = {
                'success': { header: '#10b981', icon: 'fa-check-circle', btnColor: 'btn-success' },
                'error': { header: '#ef4444', icon: 'fa-exclamation-circle', btnColor: 'btn-danger' },
                'warning': { header: '#f59e0b', icon: 'fa-exclamation-triangle', btnColor: 'btn-warning' },
                'info': { header: '#3b82f6', icon: 'fa-info-circle', btnColor: 'btn-info' }
            };
            const config = alertConfig[type] || alertConfig['info'];
            
            modalHeader.style.background = `linear-gradient(135deg, ${config.header} 0%, ${config.header}dd 100%)`;
            modalTitle.innerHTML = `<i class="fa ${config.icon} me-2"></i>${title}`;
            modalBody.innerHTML = `<p class="mb-0" style="font-size: 0.95rem;">${message}</p>`;
            
            modalButton.className = `btn ${config.btnColor}`;
            modalButton.textContent = redirectUrl ? 'ไปต่อ' : 'ตกลง';
            
            modalButton.onclick = null;
            const handler = redirectUrl ? () => window.location.href = redirectUrl : () => modal.hide();
            modalButton.onclick = handler;
            if (redirectUrl) {
                setTimeout(handler, 1500);
            }
            
            modal.show();
        }

        // Captcha countdown variables
        let captchaCountdown = 30;
        let captchaTimer = null;

        // Captcha refresh function with countdown
        function refreshCaptcha() {
            const captchaImg = document.getElementById('captchaImg');
            const timestamp = new Date().getTime();
            captchaImg.src = 'captcha.php?t=' + timestamp;
            
            // Reset countdown
            captchaCountdown = 30;
            updateCountdownDisplay();
            
            // Start countdown timer
            if (captchaTimer) clearInterval(captchaTimer);
            captchaTimer = setInterval(function() {
                captchaCountdown--;
                updateCountdownDisplay();
                
                if (captchaCountdown <= 0) {
                    clearInterval(captchaTimer);
                    refreshCaptcha(); // Auto-refresh
                }
            }, 1000);
        }

        function updateCountdownDisplay() {
            const countdownEl = document.getElementById('captchaCountdown');
            if (!countdownEl) return;
            
            countdownEl.textContent = captchaCountdown;
            
            // Change badge color based on time remaining
            const badgeEl = countdownEl.parentElement;
            if (captchaCountdown <= 10) {
                badgeEl.classList.remove('bg-warning');
                badgeEl.classList.add('bg-danger');
                badgeEl.classList.remove('text-dark');
                badgeEl.classList.add('text-white');
            } else {
                badgeEl.classList.add('bg-warning');
                badgeEl.classList.remove('bg-danger');
                badgeEl.classList.add('text-dark');
                badgeEl.classList.remove('text-white');
            }
        }

        // Initialize countdown when page loads
        document.addEventListener('DOMContentLoaded', function() {
            refreshCaptcha();
        });

        // Form Validation and Submission
        function submitResetForm(event) {
            event.preventDefault();
            const form = event.target;
            
            // Client-side validation - check in order from top to bottom
            const email = form.email.value.trim();
            const mobile = form.mobile.value.trim();
            const newpassword = form.newpassword.value;
            const confirmpassword = form.confirmpassword.value;
            const vercode = form.vercode.value.trim();
            
            // 1. Email validation
            if (!email) {
                showAlert('warning', 'ข้อมูลไม่ครบ', '<i class="fa fa-envelope me-2"></i>กรุณากรอกอีเมล');
                form.email.focus();
                return false;
            }
            
            // 2. Student ID validation
            if (!mobile) {
                showAlert('warning', 'ข้อมูลไม่ครบ', '<i class="fa fa-id-card me-2"></i>กรุณากรอกรหัสนักศึกษา');
                form.mobile.focus();
                return false;
            }
            
            // 3. New password validation
            if (!newpassword) {
                showAlert('warning', 'ข้อมูลไม่ครบ', '<i class="fa fa-lock me-2"></i>กรุณากรอกรหัสผ่านใหม่');
                form.newpassword.focus();
                return false;
            }
            
            if (newpassword.length < 8) {
                showAlert('warning', 'รหัสผ่านสั้นเกินไป', '<i class="fa fa-exclamation-circle me-2"></i>รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร');
                form.newpassword.focus();
                return false;
            }
            
            // 4. Confirm password validation
            if (!confirmpassword) {
                showAlert('warning', 'ข้อมูลไม่ครบ', '<i class="fa fa-lock me-2"></i>กรุณายืนยันรหัสผ่าน');
                form.confirmpassword.focus();
                return false;
            }
            
            if (newpassword !== confirmpassword) {
                showAlert('warning', 'รหัสผ่านไม่ตรงกัน', '<i class="fa fa-exclamation-circle me-2"></i>กรุณากรอกรหัสผ่านให้ตรงกัน');
                form.confirmpassword.focus();
                return false;
            }
            
            // 5. Captcha validation
            if (!vercode) {
                showAlert('warning', 'ข้อมูลไม่ครบ', '<i class="fa fa-shield me-2"></i>กรุณากรอกรหัสยืนยัน');
                form.vercode.focus();
                return false;
            }
            
            // Disable submit button
            const submitBtn = form.querySelector('button[type="submit"]');
            const defaultHTML = '<i class="fa fa-check me-2"></i>รีเซ็ตรหัสผ่าน';
            const spinnerHTML = '<i class="fa fa-spinner fa-spin me-2"></i>กำลังประมวลผล...';
            submitBtn.disabled = true;
            submitBtn.innerHTML = spinnerHTML;
            
            // Prepare form data
            const formData = new FormData(form);
            formData.append('change', '1');  // Add the change parameter
            
            console.log('FormData contents:');
            for (let [key, value] of formData.entries()) {
                console.log(key + ':', value);
            }
            
            // Submit via AJAX
            fetch('user-forgot-password.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers.get('content-type'));
                return response.text();
            })
            .then(text => {
                console.log('Response text:', text);
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed JSON:', data);
                    if (data.type === 'success') {
                        showAlert(data.type, data.title, data.message, data.redirect);
                    } else {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = defaultHTML;
                        showAlert(data.type, data.title, data.message, null);
                    }
                } catch (e) {
                    console.error('JSON Parse error:', e);
                    console.error('Raw response:', text);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = defaultHTML;
                    showAlert('error', 'เกิดข้อผิดพลาด', '<i class="fa fa-exclamation-circle me-2"></i>ตอบสนองจากเซิร์ฟเวอร์ไม่ถูกต้อง<br/>เปิด F12 เพื่อดูรายละเอียด');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                submitBtn.disabled = false;
                submitBtn.innerHTML = defaultHTML;
                showAlert('error', 'เกิดข้อผิดพลาด', '<i class="fa fa-exclamation-circle me-2"></i>' + error.message);
            });
            
            return false;
        }
    </script>

</head>
<body>
    <!------MENU SECTION START-->
<?php include('includes/header.php');?>
<!-- MENU SECTION END-->
<div class="content-wrapper">
    <div class="container-wrapper">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-5 col-lg-4">
                    <div class="auth-card">
                        <div class="auth-card-header">
                            <h2>
                                <i class="fa fa-key me-3"></i>รีเซ็ตรหัสผ่าน
                            </h2>
                            <p class="auth-subtitle">กรุณากรอกข้อมูลให้ครบถ้วน</p>
                        </div>
                        
                        <div class="auth-card-body">
                            <form role="form" name="chngpwd" method="post" data-no-auto-loading="1" onsubmit="return submitResetForm(event);" novalidate>
                                <div class="auth-form-group">
                                    <label for="email" class="auth-form-label">
                                        <i class="fa fa-envelope text-primary me-2"></i>อีเมล
                                    </label>
                                    <input class="form-control auth-form-input" type="email" id="email" name="email" 
                                        placeholder="your.email@example.com" required autocomplete="off" />
                                </div>

                                <div class="auth-form-group">
                                    <label for="mobile" class="auth-form-label">
                                        <i class="fa fa-id-card text-primary me-2"></i>รหัสนักศึกษา
                                    </label>
                                    <input class="form-control auth-form-input" type="text" id="mobile" name="mobile" 
                                        placeholder="123456789012-3" required autocomplete="off" />
                                </div>

                                <div class="auth-form-group">
                                    <label for="newpassword" class="auth-form-label">
                                        <i class="fa fa-lock text-primary me-2"></i>รหัสผ่านใหม่
                                    </label>
                                    <input class="form-control auth-form-input" type="password" id="newpassword" name="newpassword" 
                                        placeholder="อย่างน้อย 8 ตัวอักษร" required autocomplete="off" />
                                </div>

                                <div class="auth-form-group">
                                    <label for="confirmpassword" class="auth-form-label">
                                        <i class="fa fa-lock text-primary me-2"></i>ยืนยันรหัสผ่าน
                                    </label>
                                    <input class="form-control auth-form-input" type="password" id="confirmpassword" name="confirmpassword" 
                                        placeholder="กรอกรหัสผ่านอีกครั้ง" required autocomplete="off" />
                                </div>

                                <div class="auth-form-group last-field">
                                    <label class="auth-form-label">
                                        <i class="fa fa-shield text-primary me-2"></i>รหัสยืนยัน
                                    </label>
                                    <div class="d-flex gap-2 align-items-stretch">
                                        <input type="text" class="form-control auth-form-input flex-grow-1" id="vercode" name="vercode" 
                                            placeholder="กรอกรหัส" maxlength="5" required autocomplete="off" style="font-weight: 600;" />
                                        <div class="position-relative" style="width: 100px; flex-shrink: 0;">
                                            <div class="d-flex align-items-center justify-content-center h-100 rounded-2" 
                                                style="background-color: #f8f9fa; cursor: pointer; overflow: hidden;" 
                                                onClick="refreshCaptcha()" title="คลิกเพื่อเปลี่ยนรหัส">
                                                <img id="captchaImg" src="captcha.php" alt="Captcha" 
                                                    style="height: 100%; width: 100%; object-fit: contain; padding: 2px;">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" name="change" class="btn btn-primary btn-lg auth-submit-btn">
                                    <i class="fa fa-check me-2"></i>รีเซ็ตรหัสผ่าน
                                </button>

                                <div class="auth-divider">
                                    <hr>
                                    <span>หรือ</span>
                                    <hr>
                                </div>

                                <p class="auth-footer-text">
                                    จำรหัสผ่านได้แล้ว? <a href="index.php" class="fw-600 text-decoration-none">กลับสู่หน้าล็อกอิน</a>
                                </p>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
     <!-- CONTENT-WRAPPER SECTION END-->
    </div>
    <!-- CONTAINER WRAPPER END-->
    
    <!-- Modal Alert -->
    <div class="modal fade" id="alertModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-3" style="overflow: hidden;">
                <div class="modal-header border-0" id="modalHeader" style="padding: 1.5rem; background: linear-gradient(135deg, #3b82f6 0%, #3b82f6dd 100%); color: white;">
                    <h6 class="modal-title fw-700" id="modalTitle" style="margin: 0;">
                        <i class="fa fa-info-circle me-2"></i>แจ้งเตือน
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4" id="modalBody" style="font-size: 0.95rem;">
                    <p class="mb-0">ข้อความแจ้งเตือน</p>
                </div>
                <div class="modal-footer border-0 p-3 bg-light">
                    <button type="button" class="btn btn-info px-4" id="modalButton" data-bs-dismiss="modal">ตกลง</button>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('includes/footer.php');?>
      <!-- FOOTER SECTION END-->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- BOOTSTRAP SCRIPTS  -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <!-- CUSTOM SCRIPTS  -->
    <script src="assets/js/custom.js"></script>
    <!-- MODERN INTERACTIONS -->
    <script src="assets/js/interactions.js"></script>

</body>
</html>
