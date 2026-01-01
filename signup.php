<?php
session_start();
include('includes/config.php');

$alertMessage = null; // สำหรับเก็บข้อความ alert
$isAjax = !empty($_POST['name']); // Check if it's form submission

if($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
    try {
        // Get form data
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $surname = isset($_POST['surname']) ? trim($_POST['surname']) : '';
        $studentid = isset($_POST['studentid']) ? trim($_POST['studentid']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        
        // Validate required fields
        if (empty($name) || empty($surname) || empty($studentid) || empty($email) || empty($password)) {
            $alertMessage = array('type' => 'error', 'title' => 'ข้อมูลไม่ครบ', 'message' => '<i class="fa fa-exclamation-circle me-2"></i>กรุณากรอกข้อมูลทั้งหมด', 'redirect' => null);
        }
        else {
            // Generate new Student ID
            $count_my_page = "studentid.txt";
            if(!file_exists($count_my_page)) {
                $createFile = @file_put_contents($count_my_page, "0");
                if($createFile === false) {
                    $alertMessage = array('type' => 'error', 'title' => 'เกิดข้อผิดพลาด', 'message' => '<i class="fa fa-exclamation-circle me-2"></i>ไม่สามารถสร้างไฟล์ได้ กรุณาติดต่อผู้ดูแลระบบ', 'redirect' => null);
                }
            }
            
            if(!$alertMessage) {
                $hits = @file($count_my_page);
                if(!empty($hits)) {
                    $hits[0]++;
                } else {
                    $hits[0] = 1;
                }
                $fp = @fopen($count_my_page, "w");
                if($fp) {
                    fputs($fp, $hits[0]);
                    fclose($fp);
                } else {
                    $alertMessage = array('type' => 'error', 'title' => 'เกิดข้อผิดพลาด', 'message' => '<i class="fa fa-exclamation-circle me-2"></i>ไม่สามารถเขียนไฟล์ได้ กรุณาติดต่อผู้ดูแลระบบ', 'redirect' => null);
                }
            }
            
            if(!$alertMessage) {
                $hashedPassword = md5($password);

                // Check for duplicates
                $checkDuplicateSql = "SELECT * FROM tblmembers WHERE Email = :email OR StudentID = :studentid";
                $checkDuplicateQuery = $dbh->prepare($checkDuplicateSql);
                $checkDuplicateQuery->bindParam(':email', $email, PDO::PARAM_STR);
                $checkDuplicateQuery->bindParam(':studentid', $studentid, PDO::PARAM_STR);
                $checkDuplicateQuery->execute();
                $duplicateResult = $checkDuplicateQuery->fetch(PDO::FETCH_ASSOC);

                if ($duplicateResult) {
                    $alertMessage = array('type' => 'warning', 'title' => 'ข้อมูลซ้ำในระบบ', 'message' => '<i class="fa fa-info-circle me-2"></i>อีเมลหรือรหัสนักศึกษานี้มีอยู่ในระบบแล้ว', 'redirect' => null);
                }
                else {
                    // Insert new user with Status = 1 (active)
                    $sql = "INSERT INTO tblmembers(StudentID, Email, Name, Surname, Password, Role, Status) VALUES(:studentid, :email, :name, :surname, :password, 'student', 1)";
                    $query = $dbh->prepare($sql);
                    $query->bindParam(':studentid', $studentid, PDO::PARAM_STR);
                    $query->bindParam(':email', $email, PDO::PARAM_STR);
                    $query->bindParam(':name', $name, PDO::PARAM_STR);
                    $query->bindParam(':surname', $surname, PDO::PARAM_STR);
                    $query->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
                    
                    if($query->execute()) {
                        $alertMessage = array('type' => 'success', 'title' => 'ลงทะเบียนสำเร็จ!', 'message' => '<i class="fa fa-check-circle me-2"></i>ยินดีต้อนรับเข้าสู่ระบบ<br/>กรุณาเข้าสู่ระบบเพื่อใช้บริการ', 'redirect' => 'index.php');
                    } else {
                        $errorInfo = $query->errorInfo();
                        $alertMessage = array('type' => 'error', 'title' => 'เกิดข้อผิดพลาด', 'message' => '<i class="fa fa-exclamation-circle me-2"></i>ไม่สามารถสมัครสมาชิกได้<br/>Error: ' . htmlspecialchars($errorInfo[2]), 'redirect' => null);
                    }
                }
            }
        }
        
        // Return JSON response for AJAX
        header('Content-Type: application/json');
        echo json_encode($alertMessage);
        exit;
        
    } catch (Exception $e) {
        $alertMessage = array('type' => 'error', 'title' => 'เกิดข้อผิดพลาด', 'message' => '<i class="fa fa-exclamation-circle me-2"></i>Error: ' . htmlspecialchars($e->getMessage()), 'redirect' => null);
        header('Content-Type: application/json');
        echo json_encode($alertMessage);
        exit;
    }
}
?>

<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <!-- เนื่องจากมีการปิด tag head ไว้ด้านบนแล้ว จึงไม่ได้เปิดใหม่ -->
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <!--[if IE]>
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <![endif]-->
    <title>E-Sports | Sign Up </title>
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
    <script type="text/javascript">
        // Form Validation
        function validateForm(form) {
            const name = form.name.value.trim();
            const surname = form.surname.value.trim();
            const studentid = form.studentid.value.trim();
            const email = form.email.value.trim();
            const password = form.password.value;
            const confirmPassword = form.confirmpassword.value;
            
            // Name Validation
            if (name.length === 0) {
                showAlert('warning', 'ข้อมูลไม่ครบ', '<i class="fa fa-user me-2"></i>กรุณากรอกชื่อ');
                form.name.focus();
                return false;
            }
            
            if (name.length < 2) {
                showAlert('warning', 'ข้อมูลไม่ถูกต้อง', '<i class="fa fa-user me-2"></i>ชื่อต้องมีอย่างน้อย 2 ตัวอักษร');
                form.name.focus();
                return false;
            }
            
            // Surname Validation
            if (surname.length === 0) {
                showAlert('warning', 'ข้อมูลไม่ครบ', '<i class="fa fa-user me-2"></i>กรุณากรอกนามสกุล');
                form.surname.focus();
                return false;
            }
            
            if (surname.length < 2) {
                showAlert('warning', 'ข้อมูลไม่ถูกต้อง', '<i class="fa fa-user me-2"></i>นามสกุลต้องมีอย่างน้อย 2 ตัวอักษร');
                form.surname.focus();
                return false;
            }
            
            // Student ID Validation
            if (studentid.length === 0) {
                showAlert('warning', 'ข้อมูลไม่ครบ', '<i class="fa fa-id-card me-2"></i>กรุณากรอก รหัสนักศึกษา');
                form.studentid.focus();
                return false;
            }
            
            if (!/^\d{12}-\d$/.test(studentid)) {
                showAlert('warning', 'รูปแบบไม่ถูกต้อง', '<i class="fa fa-exclamation-circle me-2"></i>รหัสนักศึกษา ต้องมีรูปแบบ: 12 ตัวเลข-1 ตัวเลข (เช่น 123456789012-3)');
                form.studentid.focus();
                return false;
            }
            
            // Email Validation
            if (email.length === 0) {
                showAlert('warning', 'ข้อมูลไม่ครบ', '<i class="fa fa-envelope me-2"></i>กรุณากรอกอีเมล');
                form.email.focus();
                return false;
            }
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showAlert('warning', 'รูปแบบไม่ถูกต้อง', '<i class="fa fa-exclamation-circle me-2"></i>กรุณากรอกอีเมลให้ถูกต้อง (เช่น name@example.com)');
                form.email.focus();
                return false;
            }
            
            // Password Validation
            if (password.length === 0) {
                showAlert('warning', 'ข้อมูลไม่ครบ', '<i class="fa fa-lock me-2"></i>กรุณากรอกรหัสผ่าน');
                form.password.focus();
                return false;
            }
            
            if (password.length < 8) {
                showAlert('warning', 'ข้อมูลไม่ถูกต้อง', '<i class="fa fa-exclamation-circle me-2"></i>รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร (ตอนนี้มี ' + password.length + ' ตัว)');
                form.password.focus();
                return false;
            }
            
            // Confirm Password Validation
            if (confirmPassword.length === 0) {
                showAlert('warning', 'ข้อมูลไม่ครบ', '<i class="fa fa-lock me-2"></i>กรุณายืนยันรหัสผ่าน');
                form.confirmpassword.focus();
                return false;
            }
            
            if (password !== confirmPassword) {
                showAlert('warning', 'ข้อมูลไม่ถูกต้อง', '<i class="fa fa-exclamation-circle me-2"></i>รหัสผ่านไม่ตรงกัน');
                form.confirmpassword.focus();
                return false;
            }
            
            return true;
        }

        // Submit form via AJAX to prevent page refresh
        function submitSignupForm(event) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            const form = event.target;
            let keepLoading = false;
            
            // Validate form BEFORE disabling button
            if (!validateForm(form)) {
                return false;
            }
            
            // Only disable button AFTER validation passes
            const submitBtn = form.querySelector('button[type="submit"]');
            const defaultHTML = submitBtn.getAttribute('data-default-html') || '<i class="fa fa-check me-2"></i>ยืนยันการลงทะเบียน';
            const spinnerHTML = '<i class="fa fa-spinner fa-spin me-2"></i>กำลังลงทะเบียน...';
            submitBtn.disabled = true;
            submitBtn.classList.add('disabled');
            submitBtn.setAttribute('aria-disabled', 'true');
            submitBtn.setAttribute('aria-busy', 'true');
            submitBtn.style.pointerEvents = 'none';
            submitBtn.innerHTML = spinnerHTML;
            submitBtn.blur();

            const restoreButton = (btn) => {
                btn.disabled = false;
                btn.classList.remove('disabled');
                btn.removeAttribute('aria-disabled');
                btn.setAttribute('aria-busy', 'false');
                btn.style.pointerEvents = '';
                btn.innerHTML = defaultHTML;
            };
            
            // Prepare form data
            const formData = new FormData(form);
            formData.append('name', form.name.value);
            formData.append('surname', form.surname.value);
            formData.append('studentid', form.studentid.value);
            formData.append('email', form.email.value);
            formData.append('password', form.password.value);
            
            // Submit via AJAX with timeout
            const controller = new AbortController();
            const timeoutId = setTimeout(() => {
                controller.abort();
            }, 10000); // 10 second timeout
            
            fetch('signup.php', {
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
                    keepLoading = true; // Keep spinner only for success
                    showAlert(data.type, data.title, data.message, data.redirect);
                } else {
                    // Error response - restore button
                    restoreButton(submitBtn);
                    keepLoading = false;
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
                restoreButton(submitBtn);
                keepLoading = false;
                
                showAlert('error', 'เกิดข้อผิดพลาด', '<i class="fa fa-exclamation-circle me-2"></i>' + errorMsg);
            })
            .finally(() => {
                if (!keepLoading) {
                    restoreButton(submitBtn);
                }
            });
            
            return false;
        }
        
        function checkAvailability() {
            const emailid = document.getElementById('email').value.trim();
            if (!emailid) return;
            
            document.getElementById('loaderIcon').style.display = 'inline-block';
            
            fetch('check_availability.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'emailid=' + encodeURIComponent(emailid) + '&type=email'
            })
            .then(response => response.text())
            .then(data => {
                document.getElementById('user-availability-status').innerHTML = data;
                document.getElementById('loaderIcon').style.display = 'none';
            })
            .catch(error => {
                document.getElementById('loaderIcon').style.display = 'none';
            });
        }

        function checkStudentIDAvailability() {
            const studentid = document.getElementById('studentid').value.trim();
            if (!studentid) {
                document.getElementById('studentid-availability-status').innerHTML = '';
                return;
            }
            
            document.getElementById('studentidLoaderIcon').style.display = 'inline-block';
            
            fetch('check_availability.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'studentid=' + encodeURIComponent(studentid) + '&type=studentid'
            })
            .then(response => response.text())
            .then(data => {
                document.getElementById('studentid-availability-status').innerHTML = data;
                document.getElementById('studentidLoaderIcon').style.display = 'none';
            })
            .catch(error => {
                document.getElementById('studentidLoaderIcon').style.display = 'none';
            });
        }


    </script>
</head>
    <!-- เนื่องจากไม่มีปิด tag body ด้านบน จึงไม่ได้เปิดใหม่ -->
    <!------MENU SECTION START-->
    <?php include('includes/header.php');?>
    <!-- MENU SECTION END-->

    <!-- Alert Modal -->
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

    <div class="content-wrapper">
        <div class="container-wrapper">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-md-5 col-lg-4">
                        <div class="auth-card">
                            <div class="auth-card-header">
                                <h2>
                                    <i class="fa fa-user-plus me-3"></i>ลงทะเบียน
                                </h2>
                            </div>

                            <div class="auth-card-body">
                                <form id="signupForm" name="signup" method="post" data-no-auto-loading="1" onSubmit="return submitSignupForm(event);" novalidate>
                                    <div class="auth-form-group">
                                        <label for="name" class="auth-form-label">
                                            <i class="fa fa-user text-primary me-2"></i>ชื่อ
                                        </label>
                                        <input type="text" class="form-control auth-form-input" id="name" name="name" 
                                            placeholder="สมชาย" autocomplete="off">
                                    </div>

                                    <div class="auth-form-group">
                                        <label for="surname" class="auth-form-label">
                                            <i class="fa fa-user text-primary me-2"></i>นามสกุล
                                        </label>
                                        <input type="text" class="form-control auth-form-input" id="surname" name="surname" 
                                            placeholder="ใจดี" autocomplete="off">
                                    </div>

                                    <div class="auth-form-group">
                                        <label for="studentid" class="auth-form-label">
                                            <i class="fa fa-id-card text-primary me-2"></i>รหัสนักศึกษา
                                        </label>
                                        <div class="input-group">
                                            <input type="text" class="form-control auth-form-input rounded-start-2" id="studentid" name="studentid" 
                                                placeholder="123456789012-3" maxlength="14" onBlur="checkStudentIDAvailability()" autocomplete="off">
                                            <span class="input-group-text bg-transparent" id="studentidLoaderIcon" style="display:none;">
                                                <span class="spinner-border spinner-border-sm"></span>
                                            </span>
                                        </div>
                                        <span id="studentid-availability-status"></span>
                                    </div>

                                    <div class="auth-form-group">
                                        <label for="email" class="auth-form-label">
                                            <i class="fa fa-envelope text-primary me-2"></i>อีเมล
                                        </label>
                                        <div class="input-group">
                                            <input type="email" class="form-control auth-form-input rounded-start-2" id="email" name="email" 
                                                placeholder="your.email@example.com" onBlur="checkAvailability()" autocomplete="off">
                                            <span class="input-group-text bg-transparent" id="loaderIcon" style="display:none;">
                                                <span class="spinner-border spinner-border-sm"></span>
                                            </span>
                                        </div>
                                        <span id="user-availability-status"></span>
                                    </div>

                                    <div class="auth-form-group">
                                        <label for="password" class="auth-form-label">
                                            <i class="fa fa-lock text-primary me-2"></i>รหัสผ่าน
                                        </label>
                                        <input type="password" class="form-control auth-form-input" id="password" name="password" 
                                            placeholder="อย่างน้อย 8 ตัวอักษร" autocomplete="off">
                                    </div>

                                    <div class="auth-form-group last-field">
                                        <label for="confirmpassword" class="auth-form-label">
                                            <i class="fa fa-lock text-primary me-2"></i>ยืนยันรหัสผ่าน
                                        </label>
                                        <input type="password" class="form-control auth-form-input" id="confirmpassword" name="confirmpassword" 
                                            placeholder="กรอกรหัสผ่านอีกครั้ง" autocomplete="off">
                                    </div>

                                    <button type="submit" name="signup" value="1" class="btn btn-primary btn-lg auth-submit-btn" id="submit">
                                        <i class="fa fa-check me-2"></i>ยืนยันการลงทะเบียน
                                    </button>

                                    <div class="auth-divider">
                                        <hr>
                                        <span>หรือ</span>
                                        <hr>
                                    </div>

                                    <p class="auth-footer-text">
                                        มีบัญชีอยู่แล้ว? <a href="index.php" class="text-decoration-none fw-600">เข้าสู่ระบบ</a>
                                    </p>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- CONTENT-WRAPPER SECTION END-->
    <?php include('includes/footer.php');?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- BOOTSTRAP SCRIPTS  -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- CUSTOM SCRIPTS  -->
    <script src="assets/js/custom.js"></script>
    <!-- MODERN INTERACTIONS -->
    <script src="assets/js/interactions.js"></script>
</body>
</html>
