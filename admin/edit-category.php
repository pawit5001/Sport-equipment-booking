<?php
session_start();
error_reporting(0);
include('includes/config.php');

if(strlen($_SESSION['login']) == 0) {   
    header('location:index.php');
} else { 
    if(isset($_POST['update'])) {
        $category = $_POST['category'];
        $status = $_POST['status'];
        $catid = intval($_GET['catid']);
        
        $sql = "UPDATE tblcategory SET CategoryName=:category, Status=:status WHERE id=:catid";
        $query = $dbh->prepare($sql);
        $query->bindParam(':category', $category, PDO::PARAM_STR);
        $query->bindParam(':status', $status, PDO::PARAM_STR);
        $query->bindParam(':catid', $catid, PDO::PARAM_STR);
        $query->execute();
        
        $_SESSION['updatemsg'] = "แก้ไขหมวดหมู่สำเร็จ";
        header('location:manage-categories.php');
    }
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>E-Sports | Sports</title>
    <!-- BOOTSTRAP CORE STYLE  -->
    <link href="assets/css/bootstrap.css" rel="stylesheet" />
    <!-- FONT AWESOME STYLE  -->
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <!-- CUSTOM STYLE  -->
    <link href="assets/css/style.css" rel="stylesheet" />
    <!-- GOOGLE FONT -->
    <link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css' />
</head>
<body>
    <?php include('includes/header.php');?>
    <div class="content-wrapper">
        <div class="container">
            <div class="row pad-botm">
                <div class="col-md-12">
                    <h4 class="header-line">แก้ไขหมวดหมู่</h4>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 col-sm-6 col-xs-12 col-md-offset-3">
                    <div class="panel panel-info">
                        <div class="panel-heading">
                            กรุณาระบุข้อมูลให้ครบถ้วน
                        </div>
                        <div class="panel-body">
                            <form role="form" method="post">
                                <?php 
                                $catid = intval($_GET['catid']);
                                $sql = "SELECT * FROM tblcategory WHERE id=:catid";
                                $query = $dbh->prepare($sql);
                                $query->bindParam(':catid', $catid, PDO::PARAM_STR);
                                $query->execute();
                                $results = $query->fetchAll(PDO::FETCH_OBJ);
                                
                                if($query->rowCount() > 0) {
                                    foreach($results as $result) {               
                                ?> 
                                <div class="form-group">
                                    <label>หมวดหมู่</label>
                                    <input class="form-control" type="text" name="category" value="<?php echo htmlentities($result->CategoryName);?>" pattern="[a-zA-Zก-๏\s]+" title="รูปแบบข้อมูลไม่ถูกต้อง" required />
                                </div>
                                <div class="form-group">
                                    <label>สถานะ</label>
                                    <?php if($result->Status == 1) { ?>
                                        <div class="radio">
                                            <label>
                                                <input type="radio" name="status" id="status" value="1" checked="checked">เปิดใช้งาน
                                            </label>
                                        </div>
                                        <div class="radio">
                                            <label>
                                                <input type="radio" name="status" id="status" value="0">ปิดใช้งาน
                                            </label>
                                        </div>
                                    <?php } else { ?>
                                        <div class="radio">
                                            <label>
                                                <input type="radio" name="status" id="status" value="0" checked="checked">ปิดใช้งาน
                                            </label>
                                        </div>
                                        <div class="radio">
                                            <label>
                                                <input type="radio" name="status" id="status" value="1">เปิดใช้งาน
                                            </label>
                                        </div>
                                    <?php } ?>
                                </div>
                                <?php }} ?>
                                <a href="manage-categories.php" class="btn btn-primary"><i class="fa fa-arrow-left"></i> ย้อนกลับ</a>
                                <button type="submit" name="update" class="btn btn-info">แก้ไขหมวดหมู่ </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include('includes/footer.php');?>
    <script src="assets/js/jquery-1.10.2.js"></script>
    <script src="assets/js/bootstrap.js"></script>
    <script src="assets/js/custom.js"></script>
</body>
</html>
<?php } ?>
