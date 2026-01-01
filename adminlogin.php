<?php
session_start();
error_reporting(0);
include('includes/config.php');

// Reset previous login state for a clean admin auth (user-side)
if (!empty($_SESSION['login'])) {
    $_SESSION['login'] = '';
}

// Handle AJAX admin login request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['emailid'])) {
    header('Content-Type: application/json');
    $reqId = uniqid('adminlogin_', true); // correlate logs for this request
    
    try {
        $email = isset($_POST['emailid']) ? trim($_POST['emailid']) : '';
        $passwordInput = isset($_POST['password']) ? trim($_POST['password']) : '';
        $vercode = isset($_POST['vercode']) ? trim($_POST['vercode']) : '';

        error_log("[$reqId] START email={$email} sessionVercode=" . (isset($_SESSION['vercode']) ? $_SESSION['vercode'] : 'missing'));

        // Validate required fields
        if (!$email || !$passwordInput) {
            error_log("[$reqId] FAIL missing_fields email={$email}");
            echo json_encode(array(
                'type' => 'warning',
                'title' => 'ข้อมูลไม่ครบ',
                'message' => '<i class="fa fa-exclamation-circle me-2"></i>กรุณากรอกอีเมลและรหัสผ่าน',
                'redirect' => null
            ));
            exit;
        }

        // Validate captcha
        if (empty($vercode) || strtolower($vercode) !== strtolower($_SESSION["vercode"])) {
            error_log("[$reqId] FAIL captcha email={$email} submitted={$vercode} session=" . (isset($_SESSION['vercode']) ? $_SESSION['vercode'] : 'missing'));
            echo json_encode(array(
                'type' => 'error',
                'title' => 'รหัสยืนยันไม่ถูกต้อง',
                'message' => '<i class="fa fa-shield me-2"></i>กรุณากรอกรหัสยืนยันให้ถูกต้อง',
                'redirect' => null
            ));
            exit;
        }

        // Check admin credentials
        $hashedPassword = md5($passwordInput);
        $sql = "SELECT Email, Password, id, Role, Status FROM tblmembers WHERE Email=:email AND Password=:password LIMIT 1";
        $query = $dbh->prepare($sql);
        $query->bindParam(':email', $email, PDO::PARAM_STR);
        $query->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
        $query->execute();
        $result = $query->fetch(PDO::FETCH_OBJ);
        error_log("[$reqId] DB query rows=" . $query->rowCount());

        $isAdminRole = false;
        if ($result) {
            $role = isset($result->Role) ? strtolower($result->Role) : '';
            $isAdminRole = ($role === 'admin');
            error_log("[$reqId] FOUND user role={$role} id={$result->id}");
        }

        if ($result && $isAdminRole) {
            // Check if admin is banned (status != 1)
            if((int)$result->Status !== 1) {
                error_log("[$reqId] BLOCKED admin is banned - Status: " . $result->Status);
                echo json_encode(array(
                    'type' => 'warning',
                    'title' => 'บัญชีถูกแบน ❌',
                    'message' => '<i class="fa fa-ban me-2"></i><strong>บัญชีของคุณถูกแบนจากการใช้ระบบแล้ว</strong><br/>หากคุณเชื่อว่านี่เป็นข้อผิดพลาด กรุณาติดต่อเจ้าหน้าที่ผู้ดูแลระบบ',
                    'redirect' => null
                ));
                exit;
            }
            
            $_SESSION['stdid'] = $result->id;      // keep for compatibility with member flows
            $_SESSION['login'] = $email;           // legacy
            $_SESSION['alogin'] = $email;          // admin session used across admin pages
            $_SESSION['adminid'] = $result->id;    // admin id for ownership/audit
            error_log("[$reqId] SUCCESS id={$result->id} email={$email}");
            
            echo json_encode(array(
                'type' => 'success',
                'title' => 'เข้าสู่ระบบสำเร็จ',
                'message' => '<i class="fa fa-check-circle me-2"></i>ยินดีต้อนรับ',
                'redirect' => 'admin/dashboard.php'
            ));
        } else {
            error_log("[$reqId] FAIL auth email={$email} isAdminRole=" . ($isAdminRole ? 'yes' : 'no'));
            echo json_encode(array(
                'type' => 'error',
                'title' => 'เข้าสู่ระบบล้มเหลว',
                'message' => '<i class="fa fa-exclamation-circle me-2"></i>อีเมล/รหัสผ่านไม่ถูกต้อง',
                'redirect' => null
            ));
        }
        exit;

    } catch (Exception $e) {
        error_log("[$reqId] EXCEPTION " . $e->getMessage());
        echo json_encode(array(
            'type' => 'error',
            'title' => 'เกิดข้อผิดพลาด',
            'message' => '<i class="fa fa-exclamation-circle me-2"></i>' . htmlspecialchars($e->getMessage()),
            'redirect' => null
        ));
        exit;
    }
}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="Sports Equipment Booking System - Admin Login" />
    <title>E-Sports | Admin Login</title>
    <!-- BOOTSTRAP 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <!-- FONT AWESOME STYLE  -->
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <!-- MODERN CUSTOM STYLE  -->
    <link href="assets/css/modern-style.css" rel="stylesheet" />
    <!-- GOOGLE FONT -->
    <link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css' />
    <script type="text/javascript">
        // Modern Modal Alert Function (simple & consistent)
        function showAlert(type, title, message, redirectUrl = null) {
            const modalEl = document.getElementById('alertModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            const modalHeader = document.getElementById('modalHeader');
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');
            const modalButton = document.getElementById('modalButton');
            
            // Set colors and icons based on type
            const alertConfig = {
                'success': { header: '#10b981', icon: 'fa-check-circle', btnColor: 'btn-success' },
                'error': { header: '#ef4444', icon: 'fa-exclamation-circle', btnColor: 'btn-danger' },
                'warning': { header: '#f59e0b', icon: 'fa-exclamation-triangle', btnColor: 'btn-warning' },
                'info': { header: '#3b82f6', icon: 'fa-info-circle', btnColor: 'btn-info' }
            };
            const config = alertConfig[type] || alertConfig['info'];
            
            // Update modal styles
            modalHeader.style.background = `linear-gradient(135deg, ${config.header} 0%, ${config.header}dd 100%)`;
            modalTitle.innerHTML = `<i class="fa ${config.icon} me-2"></i>${title}`;
            modalBody.innerHTML = `<p class="mb-0" style="font-size: 0.95rem;">${message}</p>`;
            
            // Update button
            modalButton.className = `btn ${config.btnColor}`;
            modalButton.textContent = redirectUrl ? 'ไปต่อ' : 'ตกลง';
            
            // Reset handlers then attach fresh one
            modalButton.onclick = null;
            modalButton.onchange = null;
            const handler = redirectUrl ? () => window.location.href = redirectUrl : () => modal.hide();
            modalButton.onclick = handler;
            if (redirectUrl) {
                setTimeout(handler, 1500);
            }
            
            // Show immediately for consistent feel
            modal.show();
        }
    </script>
</head>
<body>
    <?php include('includes/header.php'); ?>

    <div class="content-wrapper">
        <div class="container-wrapper">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-md-5 col-lg-4">
                        <div class="auth-card">
                            <div class="auth-card-header">
                                <h2>
                                    <i class="fa fa-lock me-3"></i>เข้าสู่ระบบเจ้าหน้าที่
                                </h2>
                                <p class="auth-subtitle">เฉพาะบัญชีที่มีสิทธิ์เท่านั้น</p>
                            </div>
                            <div class="auth-card-body">
                                <form id="adminLoginForm" method="post" data-no-auto-loading="1" onsubmit="return submitAdminLoginForm(event)" novalidate>
                                    <div class="auth-form-group">
                                        <label for="emailid" class="auth-form-label">
                                            <i class="fa fa-envelope text-primary me-2"></i>อีเมล
                                        </label>
                                        <input type="email" class="form-control auth-form-input" id="emailid" name="emailid" 
                                            placeholder="admin@example.com" autocomplete="off" />
                                    </div>

                                    <div class="auth-form-group">
                                        <label for="password" class="auth-form-label">
                                            <i class="fa fa-lock text-primary me-2"></i>รหัสผ่าน
                                        </label>
                                        <input type="password" class="form-control auth-form-input" id="password" name="password" 
                                            placeholder="กรอกรหัสผ่าน" autocomplete="off" />
                                    </div>

                                    <div class="auth-form-group last-field">
                                        <label class="auth-form-label">
                                            <i class="fa fa-shield text-primary me-2"></i>รหัสยืนยัน
                                        </label>
                                        <div class="d-flex gap-2 align-items-stretch">
                                            <input type="text" class="form-control auth-form-input flex-grow-1" id="vercode" name="vercode" 
                                                placeholder="กรอกรหัส" maxlength="5" autocomplete="off" style="font-weight: 600;" />
                                            <div class="position-relative" style="width: 100px; flex-shrink: 0;">
                                                <div class="d-flex align-items-center justify-content-center h-100 rounded-2" 
                                                    style="background-color: #f8f9fa; cursor: pointer; overflow: hidden;" 
                                                    onclick="refreshCaptcha()" title="คลิกเพื่อเปลี่ยนรหัส">
                                                    <img id="captchaImg" src="captcha.php" alt="Captcha" 
                                                        style="height: 100%; width: 100%; object-fit: contain; padding: 2px;">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" name="login" class="btn btn-primary btn-lg auth-submit-btn" id="submitBtn">
                                        <i class="fa fa-sign-in me-2"></i>เข้าสู่ระบบเจ้าหน้าที่
                                    </button>
                                    
                                    <div class="auth-divider">
                                        <hr>
                                        <span>หรือ</span>
                                        <hr>
                                    </div>

                                    <p class="auth-footer-text">
                                        ต้องการกลับหน้าเข้าสู่ระบบทั่วไป? <a href="index.php" class="fw-600 text-decoration-none">คลิกที่นี่</a>
                                    </p>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include('includes/footer.php'); ?>

    <div class="modal fade" id="alertModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-3" style="overflow: hidden;">
                <div class="modal-header border-0" id="modalHeader" style="padding: 1.25rem; background: linear-gradient(135deg, #3b82f6 0%, #3b82f6dd 100%); color: white;">
                    <h6 class="modal-title fw-700" id="modalTitle" style="margin: 0;"><i class="fa fa-info-circle me-2"></i>แจ้งเตือน</h6>
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/custom.js"></script>
    <script src="assets/js/interactions.js"></script>

    <script>
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

        function validateAdminLoginForm(form) {
            const email = form.emailid.value.trim();
            const password = form.password.value;
            const vercode = form.vercode.value.trim();
            
            if (!email) {
                showAlert('warning', 'ข้อมูลไม่ครบ', '<i class="fa fa-envelope me-2"></i>กรุณากรอกอีเมล');
                return false;
            }
            
            if (!/^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/.test(email)) {
                showAlert('warning', 'รูปแบบไม่ถูกต้อง', '<i class="fa fa-exclamation-circle me-2"></i>กรุณากรอกอีเมลให้ถูกต้อง');
                return false;
            }
            
            if (!password) {
                showAlert('warning', 'ข้อมูลไม่ครบ', '<i class="fa fa-lock me-2"></i>กรุณากรอกรหัสผ่าน');
                return false;
            }
            
            if (!vercode) {
                showAlert('warning', 'ข้อมูลไม่ครบ', '<i class="fa fa-shield me-2"></i>กรุณากรอกรหัสยืนยัน');
                return false;
            }
            
            if (vercode.length !== 5) {
                showAlert('warning', 'ข้อมูลไม่ถูกต้อง', '<i class="fa fa-exclamation-circle me-2"></i>รหัสยืนยันต้องมี 5 หลัก');
                return false;
            }
            
            return true;
        }

        function submitAdminLoginForm(event) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            const form = event.target;
            let keepLoading = false; // flag to decide if we keep spinner (success case only)

            const restoreButton = (btn, html, label) => {
                btn.disabled = false;
                btn.classList.remove('disabled');
                btn.removeAttribute('aria-disabled');
                btn.style.pointerEvents = '';
                btn.setAttribute('aria-busy', 'false');
                btn.innerHTML = html;
            };
            
            // Validate form BEFORE disabling button
            if (!validateAdminLoginForm(form)) {
                return false;
            }
            
            // Only disable button AFTER validation passes
            const submitBtn = form.querySelector('button[type="submit"]');
            const defaultHTML = submitBtn.getAttribute('data-default-html') || '<i class="fa fa-sign-in me-2"></i>เข้าสู่ระบบเจ้าหน้าที่';
            const spinnerHTML = '<i class="fa fa-spinner fa-spin me-2"></i>กำลังเข้าสู่ระบบ...';
            submitBtn.disabled = true;
            submitBtn.classList.add('disabled');
            submitBtn.setAttribute('aria-disabled', 'true');
            submitBtn.setAttribute('aria-busy', 'true');
            submitBtn.style.pointerEvents = 'none';
            submitBtn.innerHTML = spinnerHTML;
            
            // Prepare form data
            const formData = new FormData(form);
            formData.append('login', '1');

            // Added blur to avoid ripple/focus state sticking
            submitBtn.blur();
            
            // Submit via AJAX with timeout
            const controller = new AbortController();
            const timeoutId = setTimeout(() => {
                controller.abort();
            }, 10000); // 10 second timeout
            
            fetch('adminlogin.php', {
                method: 'POST',
                body: formData,
                signal: controller.signal
            })
            .then(response => {
                clearTimeout(timeoutId);
                // Check if response is ok (200-299)
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                // Try to parse JSON
                return response.json().catch(err => {
                    throw new Error('ไม่สามารถประมวลผลข้อมูลจากเซิร์ฟเวอร์');
                });
            })
            .then(data => {
                // Success response received
                if (data.type === 'success') {
                    // Keep button in loading state for success
                    keepLoading = true;
                    showAlert(data.type, data.title, data.message, data.redirect);
                } else {
                    // Error response - restore button
                    document.getElementById('vercode').value = '';
                    refreshCaptcha();
                    keepLoading = false;
                    restoreButton(submitBtn, defaultHTML, 'after error restore');
                    showAlert(data.type, data.title, data.message, null);
                }
            })
            .catch(error => {
                clearTimeout(timeoutId);
                let errorMsg = error.message;
                if (error.name === 'AbortError') {
                    errorMsg = 'เซิร์ฟเวอร์ตอบสนองช้า กรุณาลองใหม่';
                }
                
                // Restore button on error
                keepLoading = false;
                restoreButton(submitBtn, defaultHTML, 'after catch restore');
                
                showAlert('error', 'เกิดข้อผิดพลาด', '<i class="fa fa-exclamation-circle me-2"></i>' + errorMsg);
            })
            .finally(() => {
                // If not success, ensure button is restored
                if (!keepLoading) {
                    restoreButton(submitBtn, defaultHTML, 'finally');
                } else {
                    // Lightweight debug helper to avoid undefined reference
                    if (typeof logBtnState === 'function') {
                        logBtnState('finally (kept loading)', submitBtn);
                    }
                }
            });
            
            return false;
        }

        // Optional debug helper used in the login flow; safe no-op if not needed
        function logBtnState(label, btn) {
            try {
                const state = btn ? {
                    disabled: btn.disabled,
                    busy: btn.getAttribute('aria-busy'),
                    classes: btn.className,
                    html: btn.innerHTML
                } : 'no-button';
                console.debug('[adminlogin]', label, state);
            } catch (e) {
                // ignore logging errors
            }
        }
    </script>
</body>
</html>
