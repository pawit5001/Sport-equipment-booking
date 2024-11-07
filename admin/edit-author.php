<?php
session_start();
error_reporting(0);
include('includes/config.php');
if(strlen($_SESSION['login']) == 0)
{
    header('location:index.php');
}
else{
    if(isset($_POST['update']))
    {
        $athrid=intval($_GET['athrid']);
        $author=$_POST['author'];
        $sql="update  tblauthors set AuthorName=:author where id=:athrid";
        $query = $dbh->prepare($sql);
        $query->bindParam(':author',$author,PDO::PARAM_STR);
        $query->bindParam(':athrid',$athrid,PDO::PARAM_STR);
        $query->execute();
        $_SESSION['updatemsg']="แก้ไขรายละเอียดสำเร็จ";
        header('location:manage-authors.php');
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
                    <h4 class="header-line">แก้ไขรายละเอียดผู้รับผิดชอบ</h4>
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
                                    <label>ชื่อ-นามสกุล</label>
                                    <?php 
                                    $athrid=intval($_GET['athrid']);
                                    $sql = "SELECT * from  tblauthors where id=:athrid";
                                    $query = $dbh -> prepare($sql);
                                    $query->bindParam(':athrid',$athrid,PDO::PARAM_STR);
                                    $query->execute();
                                    $results=$query->fetchAll(PDO::FETCH_OBJ);
                                    $cnt=1;
                                    if($query->rowCount() > 0)
                                    {
                                        foreach($results as $result)
                                        { ?>
                                            <input class="form-control" type="text" name="author" value="<?php echo htmlentities($result->AuthorName);?>" pattern="[a-zA-Zก-๏\s]+" title="รูปแบบข้อมูลไม่ถูกต้อง" required />
                                    <?php }} ?>
                                </div>

                                <a href="manage-authors.php" class="btn btn-primary"><i class="fa fa-arrow-left"></i> ย้อนกลับ</a>

                                <button type="submit" name="update" class="btn btn-success">แก้ไขรายละเอียดผู้รับผิดชอบ </button>

                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- FOOTER SECTION END-->
    <?php include('includes/footer.php');?>
    <!-- JAVASCRIPT FILES PLACED AT THE BOTTOM TO REDUCE THE LOADING TIME  -->
    <!-- CORE JQUERY  -->
    <script src="assets/js/jquery-1.10.2.js"></script>
    <!-- BOOTSTRAP SCRIPTS  -->
    <script src="assets/js/bootstrap.js"></script>
    <!-- CUSTOM SCRIPTS  -->
    <script src="assets/js/custom.js"></script>
</body>
</html>
<?php } ?>
