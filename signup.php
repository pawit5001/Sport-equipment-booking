<?php
session_start();
include('includes/config.php');
error_reporting(0);

if(isset($_POST['signup'])) {
    try {
        // Code for captcha verification
        if ($_POST["vercode"] != $_SESSION["vercode"] OR $_SESSION["vercode"]=='')  {
            echo "<script>alert('รหัสยืนยันไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง!');</script>" ;
        } else {    
            // Code for student ID
            $count_my_page = ("studentid.txt");
            $hits = file($count_my_page);
            $hits[0]++;
            $fp = fopen($count_my_page , "w");
            fputs($fp , "$hits[0]");
            fclose($fp); 
            $StudentId = $hits[0];   
            $fname = $_POST['fullanme'];
            $mobileno = $_POST['mobileno'];
            $email = $_POST['email'];
            $password = md5($_POST['password']);
            $status = 1;

            // Check if the provided email, mobile number, or student ID is already registered
            $checkDuplicateSql = "SELECT * FROM tblstudents WHERE (FullName = :fname OR EmailId = :email OR MobileNumber = :mobileno OR StudentId = :StudentId)";
            $checkDuplicateQuery = $dbh->prepare($checkDuplicateSql);
            $checkDuplicateQuery->bindParam(':fname', $fname, PDO::PARAM_STR);
            $checkDuplicateQuery->bindParam(':email', $email, PDO::PARAM_STR);
            $checkDuplicateQuery->bindParam(':mobileno', $mobileno, PDO::PARAM_STR);
            $checkDuplicateQuery->bindParam(':StudentId', $StudentId, PDO::PARAM_STR);
            $checkDuplicateQuery->execute();
            $duplicateResult = $checkDuplicateQuery->fetch(PDO::FETCH_ASSOC);

            if ($duplicateResult) {
                echo '<script>alert("ข้อมูลนี้มีอยู่ในระบบแล้ว");</script>';
            } else {
                // Insert new user
                $sql = "INSERT INTO tblstudents(StudentId, FullName, MobileNumber, EmailId, Password, Status) VALUES(:StudentId, :fname, :mobileno, :email, :password, :status)";
                $query = $dbh->prepare($sql);
                $query->bindParam(':StudentId', $StudentId, PDO::PARAM_STR);
                $query->bindParam(':fname', $fname, PDO::PARAM_STR);
                $query->bindParam(':mobileno', $mobileno, PDO::PARAM_STR);
                $query->bindParam(':email', $email, PDO::PARAM_STR);
                $query->bindParam(':password', $password, PDO::PARAM_STR);
                $query->bindParam(':status', $status, PDO::PARAM_STR);
                $query->execute();
                $lastInsertId = $dbh->lastInsertId();

                if($lastInsertId) {
                    echo '<script>alert("ลงทะเบียนผู้ใช้สำเร็จ!")</script>';
                } else {
                    echo "<script>alert('เกิดข้อผิดพลาดบางอย่าง กรุณาลองใหม่อีกครั้ง!');</script>";
                }
            }
        }
    } catch (Exception $e) {
        echo "<script>alert('เกิดข้อผิดพลาด: " . $e->getMessage() . "');</script>";
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
    <link href="assets/css/bootstrap.css" rel="stylesheet" />
    <!-- FONT AWESOME STYLE  -->
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <!-- CUSTOM STYLE  -->
    <link href="assets/css/style.css" rel="stylesheet" />
    <!-- GOOGLE FONT -->
    <link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css' />
    <script type="text/javascript">
        function valid()
        {
            if(document.signup.password.value != document.signup.confirmpassword.value)
            {
                alert("รหัสผ่านไม่ตรงกัน กรุณาลองใหม่อีกครั้ง!");
                document.signup.confirmpassword.focus();
                return false;
            }
            return true;
        }
    </script>
    <script>
        function checkAvailability() {
            $("#loaderIcon").show();
            jQuery.ajax({
                url: "check_availability.php",
                data:'emailid='+$("#emailid").val(),
                type: "POST",
                success:function(data){
                    $("#user-availability-status").html(data);
                    $("#loaderIcon").hide();
                },
                error:function (){}
            });
        }
    </script>    
</head>
<body>
    <!-- เนื่องจากไม่มีปิด tag body ด้านบน จึงไม่ได้เปิดใหม่ -->
    <!------MENU SECTION START-->
    <?php include('includes/header.php');?>
    <!-- MENU SECTION END-->
    <div class="content-wrapper">
        <div class="container">
            <div class="row pad-botm">
                <div class="col-md-12">
                    <h4 class="header-line">ลงทะเบียนบัญชีผู้ใช้</h4>
                </div>
            </div>
            <div class="row">
                <div class="col-md-9 col-md-offset-1">
                    <div class="panel panel-danger">
                        <div class="panel-heading">
                            กรุณาระบุข้อมูลให้ครบถ้วน
                        </div>
                        <div class="panel-body">
                            <form name="signup" method="post" onSubmit="return valid();">
                                <div class="form-group">
                                    <label>ชื่อ-นามสกุล</label>
                                    <input class="form-control" type="text" name="fullanme" autocomplete="off" required />
                                </div>
                                <div class="form-group">
                                    <label>รหัสนักศึกษา</label>
                                    <input class="form-control" type="text" name="mobileno" maxlength="14" autocomplete="off" pattern="\d{12}-\d" title="รูปแบบข้อมูลไม่ถูกต้อง กรุณากรอกตามรูปแบบ xxxxxxxxxxxx-x (12 ตัวเลขตามด้วย - และตัวเลขอีก 1 ตัว)" required />
                                </div>
                                <div class="form-group">
                                    <label>อีเมล</label>
                                    <input class="form-control" type="email" name="email" id="emailid" onBlur="checkAvailability()"  autocomplete="off" required  />
                                    <span id="user-availability-status" style="font-size:12px;"></span> 
                                </div>
                                
                                <div class="form-group">
                                    <label>รหัสผ่าน</label>
                                    <input class="form-control" type="password" name="password" autocomplete="off" pattern=".{8,}" title="รหัสผ่านต้องมีอย่างน้อย 8 ตัว" required />
                                </div>
                                <div class="form-group">
                                    <label>ยืนยันรหัสผ่าน </label>
                                    <input class="form-control" type="password" name="confirmpassword" autocomplete="off" required />
                                </div>

                                <div class="form-group">
                                    <label>กรุณาระบุรหัสยืนยัน : </label>
                                    <input type="text"  name="vercode" maxlength="5" autocomplete="off" required style="width: 150px; height: 25px;" />&nbsp;<img src="captcha.php">
                                </div>                                
                                <button type="submit" name="signup" class="btn btn-danger" id="submit">ลงทะเบียน</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- CONTENT-WRAPPER SECTION END-->
    <?php include('includes/footer.php');?>
    <script src="assets/js/jquery-1.10.2.js"></script>
    <!-- BOOTSTRAP SCRIPTS  -->
    <script src="assets/js/bootstrap.js"></script>
    <!-- CUSTOM SCRIPTS  -->
    <script src="assets/js/custom.js"></script>
</body>
</html>
