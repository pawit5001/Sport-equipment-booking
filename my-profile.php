<?php
session_start();
include('includes/config.php');
error_reporting(0);

if (strlen($_SESSION['login']) == 0) {
    header('location:index.php');
} else {
    if (isset($_POST['update'])) {
        $sid = $_SESSION['stdid'];
        $fname = $_POST['fullanme'];
        $mobileno = $_POST['mobileno'];

        $checkDuplicateSql = "SELECT * FROM tblstudents WHERE (FullName = :fname OR MobileNumber = :mobileno) AND StudentId != :sid";
        $checkDuplicateQuery = $dbh->prepare($checkDuplicateSql);
        $checkDuplicateQuery->bindParam(':fname', $fname, PDO::PARAM_STR);
        $checkDuplicateQuery->bindParam(':mobileno', $mobileno, PDO::PARAM_STR);
        $checkDuplicateQuery->bindParam(':sid', $sid, PDO::PARAM_STR);
        $checkDuplicateQuery->execute();
        $duplicateResult = $checkDuplicateQuery->fetch(PDO::FETCH_ASSOC);

        if ($duplicateResult) {
            echo '<div class="errorWrap"><strong>ไม่สำเร็จ</strong>: ในระบบมีข้อมูลนี้อยู่แล้ว</div>';
        } else {
            $checkChangesSql = "SELECT * FROM tblstudents WHERE (FullName != :fname OR MobileNumber != :mobileno) AND StudentId = :sid";
            $checkChangesQuery = $dbh->prepare($checkChangesSql);
            $checkChangesQuery->bindParam(':fname', $fname, PDO::PARAM_STR);
            $checkChangesQuery->bindParam(':mobileno', $mobileno, PDO::PARAM_STR);
            $checkChangesQuery->bindParam(':sid', $sid, PDO::PARAM_STR);
            $checkChangesQuery->execute();
            $changesResult = $checkChangesQuery->fetch(PDO::FETCH_ASSOC);

            if ($changesResult) {
                $updateSql = "UPDATE tblstudents SET FullName=:fname,MobileNumber=:mobileno WHERE StudentId=:sid";
                $updateQuery = $dbh->prepare($updateSql);
                $updateQuery->bindParam(':sid', $sid, PDO::PARAM_STR);
                $updateQuery->bindParam(':fname', $fname, PDO::PARAM_STR);
                $updateQuery->bindParam(':mobileno', $mobileno, PDO::PARAM_STR);
                $updateQuery->execute();

                $rowsAffected = $updateQuery->rowCount();
                if ($rowsAffected > 0) {
                    echo '<script>alert("โปรไฟล์ของคุณถูกอัปเดตแล้ว");</script>';
                } else {
                    echo '<script>alert("ไม่พบการเปลี่ยนแปลง");</script>';
                }
            }
        }
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
    <!--[if IE]>
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <![endif]-->
    <title>E-Sports | My Profile</title>
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
                    <h4 class="header-line">โปรไฟล์ของฉัน</h4>
                </div>
            </div>
            <div class="row">
                <div class="col-md-9 col-md-offset-1">
                    <div class="panel panel-danger">
                        <div class="panel-heading">
                            รายละเอียด
                        </div>
                        <div class="panel-body">
                            <form name="signup" method="post">
                                <?php 
                                $sid=$_SESSION['stdid'];
                                $sql="SELECT StudentId,FullName,EmailId,MobileNumber,RegDate,UpdationDate,Status from  tblstudents  where StudentId=:sid ";
                                $query = $dbh->prepare($sql);
                                $query->bindParam(':sid', $sid, PDO::PARAM_STR);
                                $query->execute();
                                $results=$query->fetchAll(PDO::FETCH_OBJ);
                                $cnt=1;
                                if($query->rowCount() > 0) {
                                    foreach($results as $result) { ?>
                                        <div class="form-group">
                                            <label>ไอดี : </label>
                                            <?php echo htmlentities($result->StudentId);?>
                                        </div>

                                        <div class="form-group">
                                            <label>วัน/เดือน/ปี ที่ลงทะเบียน : </label>
                                            <?php echo htmlentities($result->RegDate);?>
                                        </div>
                                        <?php if($result->UpdationDate!=""){?>
                                            <div class="form-group">
                                                <label>อัปเดตล่าสุด : </label>
                                                <?php echo htmlentities($result->UpdationDate);?>
                                            </div>
                                        <?php } ?>

                                        <div class="form-group">
                                            <label>สถานะ : </label>
                                            <?php if($result->Status==1){?>
                                                <span style="color: green">ยังคงใช้งาน</span>
                                            <?php } else { ?>
                                                <span style="color: red">ยกเลิกการใช้งาน</span>
                                            <?php }?>
                                        </div>

                                        <div class="form-group">
                                        <label>ชื่อ นามสกุล</label>
                                        <input class="form-control" type="text" name="fullanme" value="<?php echo htmlentities($result->FullName);?>" autocomplete="off" pattern="[a-zA-Zก-๏\s]+" title="รูปแบบข้อมูลไม่ถูกต้อง" required />
                                        </div>


                                        <div class="form-group">
                                            <label>รหัสนักศึกษา :</label>
                                            <input class="form-control" type="text" name="mobileno" value="<?php echo htmlentities($result->MobileNumber);?>" maxlength="14" autocomplete="off" pattern="\d{12}-\d" title="รูปแบบข้อมูลไม่ถูกต้อง กรุณากรอกตามรูปแบบ xxxxxxxxxxxx-x (12 ตัวเลขตามด้วย - และตัวเลขอีก 1 ตัว)" required />
                                        </div>

                                        <div class="form-group">
                                            <label>อีเมล</label>
                                            <input class="form-control" type="email" name="email" id="emailid" value="<?php echo htmlentities($result->EmailId);?>" autocomplete="off" required readonly />
                                        </div>
                                <?php 
                                    }
                                } 
                                ?>
                                <button type="submit" name="update" class="btn btn-primary" id="submit" <?php if (isset($rowsAffected) && $rowsAffected == 0) echo 'disabled'; ?>>แก้ไข</button>
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
