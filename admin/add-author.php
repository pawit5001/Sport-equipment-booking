<?php
session_start();
error_reporting(0);
include('includes/config.php');

if(strlen($_SESSION['login']) == 0) {
    header('location:index.php');
} else { 
    if(isset($_POST['create'])) {
        $author = $_POST['author'];
        $sql = "INSERT INTO  tblauthors(AuthorName) VALUES(:author)";
        $query = $dbh->prepare($sql);
        $query->bindParam(':author', $author, PDO::PARAM_STR);
        $query->execute();
        $lastInsertId = $dbh->lastInsertId();
        
        if($lastInsertId) {
            $_SESSION['msg'] = "เพิ่มผู้รับผิดชอบสำเร็จ";
            header('location:manage-authors.php');
        } else {
            $_SESSION['error'] = "เกิดข้อผิดพลาดบางอย่าง กรุณาลองใหม่อีกครั้ง";
            header('location:manage-authors.php');
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
                    <h4 class="header-line">เพิ่มผู้รับผิดชอบ</h4>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 col-sm-6 col-xs-12 col-md-offset-3">
                    <div class="panel panel-info">
                        <div class="panel-heading">
                            รายละเอียด
                        </div>
                        <div class="panel-body">
                            <form role="form" method="post">
                                <div class="form-group">
                                    <label>ชื่อ-นามสกุล </label>
                                    <input class="form-control" type="text" name="author" value="<?php echo htmlentities($result->AuthorName);?>" pattern="[a-zA-Zก-๏\s]+" title="รูปแบบข้อมูลไม่ถูกต้อง" required />
                                </div>
                                <button type="submit" name="create" class="btn btn-info">เพิ่ม</button>
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
