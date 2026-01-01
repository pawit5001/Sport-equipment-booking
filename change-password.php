<?php
session_start();
include('includes/config.php');
error_reporting(0);

if (strlen($_SESSION['login']) == 0) {
    header('location:index.php');
    exit;
}

// Handle AJAX password change request
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $sid = $_SESSION['stdid'];
        $currentPassword = $_POST['currentPassword'] ?? '';
        $newPassword = $_POST['newPassword'] ?? '';
        $confirmPassword = $_POST['confirmPassword'] ?? '';
        
        // Validate required fields
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            echo json_encode([
                'type' => 'error',
                'title' => 'ข้อมูลไม่ครบ',
                'message' => 'กรุณากรอกข้อมูลทั้งหมด'
            ]);
            exit;
        }
        
        // Verify current password
        $currentHashedPassword = md5($currentPassword);
        $sql = "SELECT Password FROM tblmembers WHERE id=:sid AND Password=:password";
        $query = $dbh->prepare($sql);
        $query->bindParam(':sid', $sid, PDO::PARAM_STR);
        $query->bindParam(':password', $currentHashedPassword, PDO::PARAM_STR);
        $query->execute();
        
        if($query->rowCount() == 0) {
            echo json_encode([
                'type' => 'error',
                'title' => 'รหัสผ่านไม่ถูกต้อง',
                'message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง'
            ]);
            exit;
        }
        
        // Check if new password is same as current
        if ($currentPassword === $newPassword) {
            echo json_encode([
                'type' => 'warning',
                'title' => 'รหัสผ่านเดียวกัน',
                'message' => 'รหัสผ่านใหม่ต้องไม่เหมือนรหัสผ่านเดิม'
            ]);
            exit;
        }
        
        // Validate new password length
        if (strlen($newPassword) < 8) {
            echo json_encode([
                'type' => 'error',
                'title' => 'รหัสผ่านสั้นเกินไป',
                'message' => 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร'
            ]);
            exit;
        }
        
        // Check password confirmation
        if ($newPassword !== $confirmPassword) {
            echo json_encode([
                'type' => 'error',
                'title' => 'รหัสผ่านไม่ตรงกัน',
                'message' => 'รหัสผ่านยืนยันไม่ตรงกัน'
            ]);
            exit;
        }
        
        // Update password
        $newHashedPassword = md5($newPassword);
        $updateSql = "UPDATE tblmembers SET Password=:password, UpdationDate=NOW() WHERE id=:sid";
        $updateQuery = $dbh->prepare($updateSql);
        $updateQuery->bindParam(':sid', $sid, PDO::PARAM_STR);
        $updateQuery->bindParam(':password', $newHashedPassword, PDO::PARAM_STR);
        
        if($updateQuery->execute()) {
            echo json_encode([
                'type' => 'success',
                'title' => 'สำเร็จ!',
                'message' => 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว'
            ]);
        } else {
            echo json_encode([
                'type' => 'error',
                'title' => 'เกิดข้อผิดพลาด',
                'message' => 'ไม่สามารถเปลี่ยนรหัสผ่านได้'
            ]);
        }
        exit;
        
    } catch (Exception $e) {
        echo json_encode([
            'type' => 'error',
            'title' => 'เกิดข้อผิดพลาด',
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// Get user info
$sid = $_SESSION['stdid'];
$sql = "SELECT Name, Surname FROM tblmembers WHERE id=:sid";
$query = $dbh->prepare($sql);
$query->bindParam(':sid', $sid, PDO::PARAM_STR);
$query->execute();
$user = $query->fetch(PDO::FETCH_OBJ);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>E-Sports | เปลี่ยนรหัสผ่าน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <link href="assets/css/modern-style.css" rel="stylesheet" />
    <style>
        .password-card {
            max-width: 480px;
            margin: 0 auto;
        }
        .password-header {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            padding: 2rem;
            text-align: center;
            color: white;
        }
        .password-header .icon-circle {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
        }
        .form-floating > label {
            color: #64748b;
        }
        .form-floating > .form-control:focus ~ label {
            color: #f59e0b;
        }
        .form-control:focus {
            border-color: #f59e0b;
            box-shadow: 0 0 0 0.2rem rgba(245, 158, 11, 0.15);
        }
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 20px;
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            z-index: 10;
            padding: 5px;
        }
        .password-toggle:hover {
            color: #f59e0b;
        }
        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 0.5rem;
            transition: all 0.3s;
        }
        .strength-weak { background: #ef4444; width: 33%; }
        .strength-medium { background: #f59e0b; width: 66%; }
        .strength-strong { background: #10b981; width: 100%; }
        .requirement {
            font-size: 0.8rem;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.25rem;
        }
        .requirement.valid { color: #10b981; }
        .requirement i { font-size: 0.7rem; }
    </style>
</head>
<body>
    <?php include('includes/header.php'); ?>
    
    <div class="content-wrapper">
        <div class="container py-5">
            <div class="password-card">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                    <!-- Header -->
                    <div class="password-header">
                        <div class="icon-circle">
                            <i class="fa fa-key"></i>
                        </div>
                        <h4 class="mb-1 fw-bold">เปลี่ยนรหัสผ่าน</h4>
                        <p class="mb-0 opacity-75">
                            <?php echo htmlspecialchars($user->Name . ' ' . $user->Surname); ?>
                        </p>
                    </div>
                    
                    <!-- Form -->
                    <div class="card-body p-4">
                        <form id="passwordForm">
                            <!-- Current Password -->
                            <div class="mb-3 position-relative">
                                <div class="form-floating">
                                    <input type="password" class="form-control" id="currentPassword" name="currentPassword" placeholder="รหัสผ่านปัจจุบัน" required>
                                    <label for="currentPassword"><i class="fa fa-lock me-2"></i>รหัสผ่านปัจจุบัน</label>
                                </div>
                                <button type="button" class="password-toggle" onclick="togglePassword('currentPassword', this)">
                                    <i class="fa fa-eye"></i>
                                </button>
                            </div>
                            
                            <hr class="my-4">
                            
                            <!-- New Password -->
                            <div class="mb-3 position-relative">
                                <div class="form-floating">
                                    <input type="password" class="form-control" id="newPassword" name="newPassword" placeholder="รหัสผ่านใหม่" required>
                                    <label for="newPassword"><i class="fa fa-key me-2"></i>รหัสผ่านใหม่</label>
                                </div>
                                <button type="button" class="password-toggle" onclick="togglePassword('newPassword', this)">
                                    <i class="fa fa-eye"></i>
                                </button>
                                <div class="password-strength" id="strengthBar"></div>
                                <div class="requirement" id="reqLength">
                                    <i class="fa fa-circle"></i> อย่างน้อย 8 ตัวอักษร
                                </div>
                            </div>
                            
                            <!-- Confirm Password -->
                            <div class="mb-4 position-relative">
                                <div class="form-floating">
                                    <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" placeholder="ยืนยันรหัสผ่านใหม่" required>
                                    <label for="confirmPassword"><i class="fa fa-check-circle me-2"></i>ยืนยันรหัสผ่านใหม่</label>
                                </div>
                                <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword', this)">
                                    <i class="fa fa-eye"></i>
                                </button>
                                <div class="requirement" id="reqMatch">
                                    <i class="fa fa-circle"></i> รหัสผ่านตรงกัน
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <button type="submit" class="btn btn-warning btn-lg w-100 fw-bold" id="submitBtn">
                                <i class="fa fa-save me-2"></i>บันทึกรหัสผ่านใหม่
                            </button>
                            
                            <!-- Back Link -->
                            <div class="text-center mt-3">
                                <a href="my-profile.php" class="text-muted text-decoration-none">
                                    <i class="fa fa-arrow-left me-1"></i> กลับไปหน้าโปรไฟล์
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('includes/footer.php'); ?>
    
    <!-- Alert Modal -->
    <div class="modal fade" id="alertModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 rounded-4 overflow-hidden">
                <div class="modal-header border-0 py-3" id="modalHeader">
                    <h6 class="modal-title fw-bold" id="modalTitle"></h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4" id="modalBody"></div>
                <div class="modal-footer border-0 justify-content-center pb-4">
                    <button type="button" class="btn px-4" id="modalBtn" data-bs-dismiss="modal">ตกลง</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Password strength checker
        document.getElementById('newPassword').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            const reqLength = document.getElementById('reqLength');
            
            // Length check
            if (password.length >= 8) {
                reqLength.classList.add('valid');
                reqLength.querySelector('i').className = 'fa fa-check';
            } else {
                reqLength.classList.remove('valid');
                reqLength.querySelector('i').className = 'fa fa-circle';
            }
            
            // Strength bar
            if (password.length === 0) {
                strengthBar.className = 'password-strength';
            } else if (password.length < 8) {
                strengthBar.className = 'password-strength strength-weak';
            } else if (password.length < 12) {
                strengthBar.className = 'password-strength strength-medium';
            } else {
                strengthBar.className = 'password-strength strength-strong';
            }
            
            checkMatch();
        });
        
        // Password match checker
        document.getElementById('confirmPassword').addEventListener('input', checkMatch);
        
        function checkMatch() {
            const newPass = document.getElementById('newPassword').value;
            const confirmPass = document.getElementById('confirmPassword').value;
            const reqMatch = document.getElementById('reqMatch');
            
            if (confirmPass.length > 0 && newPass === confirmPass) {
                reqMatch.classList.add('valid');
                reqMatch.querySelector('i').className = 'fa fa-check';
            } else {
                reqMatch.classList.remove('valid');
                reqMatch.querySelector('i').className = 'fa fa-circle';
            }
        }
        
        // Show alert modal
        function showAlert(type, title, message) {
            const modal = new bootstrap.Modal(document.getElementById('alertModal'));
            const header = document.getElementById('modalHeader');
            const modalTitle = document.getElementById('modalTitle');
            const body = document.getElementById('modalBody');
            const btn = document.getElementById('modalBtn');
            
            const config = {
                success: { bg: '#10b981', icon: 'fa-check-circle', btnClass: 'btn-success' },
                error: { bg: '#ef4444', icon: 'fa-times-circle', btnClass: 'btn-danger' },
                warning: { bg: '#f59e0b', icon: 'fa-exclamation-triangle', btnClass: 'btn-warning' }
            };
            
            const c = config[type] || config.warning;
            header.style.background = c.bg;
            header.style.color = 'white';
            modalTitle.innerHTML = `<i class="fa ${c.icon} me-2"></i>${title}`;
            body.innerHTML = `<p class="mb-0">${message}</p>`;
            btn.className = `btn ${c.btnClass} px-4`;
            
            modal.show();
        }
        
        // Form submit
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('submitBtn');
            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i>กำลังบันทึก...';
            
            fetch('change-password.php', {
                method: 'POST',
                body: new FormData(this)
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
                
                if (data.type === 'success') {
                    this.reset();
                    document.getElementById('strengthBar').className = 'password-strength';
                    document.getElementById('reqLength').classList.remove('valid');
                    document.getElementById('reqLength').querySelector('i').className = 'fa fa-circle';
                    document.getElementById('reqMatch').classList.remove('valid');
                    document.getElementById('reqMatch').querySelector('i').className = 'fa fa-circle';
                }
                
                showAlert(data.type, data.title, data.message);
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
                showAlert('error', 'เกิดข้อผิดพลาด', err.message);
            });
        });
    </script>
</body>
</html>
