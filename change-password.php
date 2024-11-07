<?php
session_start();
include('includes/config.php');
error_reporting(0);

if (strlen($_SESSION['login']) == 0) {
    header('location:index.php');
} else {
    if (isset($_POST['change'])) {
        $password = md5($_POST['password']);
        $newpassword = md5($_POST['newpassword']);
        $confirmpassword = md5($_POST['confirmpassword']);
        $email = $_SESSION['login'];

        $sql = "SELECT Password FROM tblstudents WHERE EmailId=:email and Password=:password";
        $query = $dbh->prepare($sql);
        $query->bindParam(':email', $email, PDO::PARAM_STR);
        $query->bindParam(':password', $password, PDO::PARAM_STR);
        $query->execute();
        $results = $query->fetchAll(PDO::FETCH_OBJ);

        if ($query->rowCount() > 0) {
            // เพิ่มเงื่อนไขตรวจสอบรหัสผ่านใหม่ไม่ซ้ำกับรหัสผ่านเก่า
            if ($newpassword != $password) {
                if ($newpassword == $confirmpassword) {
                    $con = "UPDATE tblstudents SET Password=:newpassword WHERE EmailId=:email";
                    $chngpwd1 = $dbh->prepare($con);
                    $chngpwd1->bindParam(':email', $email, PDO::PARAM_STR);
                    $chngpwd1->bindParam(':newpassword', $newpassword, PDO::PARAM_STR);
                    $chngpwd1->execute();
                    $msg = "รหัสผ่านของคุณถูกอัปเดตแล้ว";
                } else {
                    $error = "รหัสผ่านไม่ตรงกัน";
                }
            } else {
                $error = "ไม่สามารถใช้รหัสผ่านเก่าได้";
            }
        } else {
            $error = "พบข้อผิดพลาด กรุณาลองใหม่อีกครั้ง";
        }
    }
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>E-Sports | Password Reset </title>
    <!-- BOOTSTRAP CORE STYLE  -->
    <link href="assets/css/bootstrap.css" rel="stylesheet" />
    <!-- FONT AWESOME STYLE  -->
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <!-- CUSTOM STYLE  -->
    <link href="assets/css/style.css" rel="stylesheet" />
    <!-- GOOGLE FONT -->
    <link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css' />
  <style>
    .errorWrap {
    padding: 10px;
    margin: 0 0 20px 0;
    background: #fff;
    border-left: 4px solid #dd3d36;
    -webkit-box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
    box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
}
.succWrap{
    padding: 10px;
    margin: 0 0 20px 0;
    background: #fff;
    border-left: 4px solid #5cb85c;
    -webkit-box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
    box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
}
    </style>
</head>
<body>
    <!------MENU SECTION START-->
<?php include('includes/header.php');?>
<!-- MENU SECTION END-->
<div class="content-wrapper">
<div class="container">
<div class="row pad-botm">
<div class="col-md-12">
<h4 class="header-line">เปลี่ยนรหัสผ่าน</h4>
</div>
</div>
 <?php if($error){?><div class="errorWrap"><strong>ไม่สำเร็จ</strong>:<?php echo htmlentities($error); ?> </div><?php } 
        else if($msg){?><div class="succWrap"><strong>สำเร็จ</strong>:<?php echo htmlentities($msg); ?> </div><?php }?>            
<!--LOGIN PANEL START-->           
<div class="row">
<div class="col-md-6 col-sm-6 col-xs-12 col-md-offset-3" >
<div class="panel panel-info">
<div class="panel-heading">
รายละเอียด
</div>
<div class="panel-body">
<form role="form" method="post" onSubmit="return valid();" name="chngpwd">

<div class="form-group">
<label>รหัสผ่านปัจจุบัน </label>
<input class="form-control" type="password" name="password" autocomplete="off" required  />
</div>

<div class="form-group">
    <label>รหัสผ่านใหม่ </label>
    <input class="form-control" type="password" name="newpassword" autocomplete="off" pattern=".{8,}" title="รหัสผ่านต้องมีอย่างน้อย 8 ตัว" required />
</div>

<div class="form-group">
    <label>ยืนยันรหัสผ่าน </label>
    <input class="form-control" type="password" name="confirmpassword" autocomplete="off" pattern=".{8,}" title="รหัสผ่านต้องมีอย่างน้อย 8 ตัว" required />
</div>


 <button type="submit" name="change" class="btn btn-info">เปลี่ยนรหัสผ่าน </button> 
</form>
 </div>
</div>
</div>
</div>  
<!---LOGIN PABNEL END-->            
             
 
    </div>
    </div>
     <!-- CONTENT-WRAPPER SECTION END-->
 <?php include('includes/footer.php');?>
      <!-- FOOTER SECTION END-->
    <script src="assets/js/jquery-1.10.2.js"></script>
    <!-- BOOTSTRAP SCRIPTS  -->
    <script src="assets/js/bootstrap.js"></script>
      <!-- CUSTOM SCRIPTS  -->
    <script src="assets/js/custom.js"></script>
</body>
</html>
<?php } ?>
