<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
error_reporting(0);
include('includes/config.php');
if(strlen($_SESSION['login']) == 0) {
    header('location:index.php');
} else { 
    if(isset($_GET['del'])) {
        $id = $_GET['del'];
        $sql = "DELETE FROM tblbooks WHERE id = :id";
        $query = $dbh->prepare($sql);
        $query->bindParam(':id', $id, PDO::PARAM_STR);
        $query->execute();
        $_SESSION['delmsg'] = "ลบอุปกรณ์กีฬาสำเร็จ";
        header('location:manage-books.php');
    }
?>

<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>E-Sports | Sports </title>
    <!-- BOOTSTRAP CORE STYLE  -->
    <link href="assets/css/bootstrap.css" rel="stylesheet" />
    <!-- FONT AWESOME STYLE  -->
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <!-- DATATABLE STYLE  -->
    <link href="assets/js/dataTables/dataTables.bootstrap.css" rel="stylesheet" />
    <!-- CUSTOM STYLE  -->
    <link href="assets/css/style.css" rel="stylesheet" />
    <!-- GOOGLE FONT -->
    <link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css' />
</head>
<body>
    <!-- MENU SECTION START-->
    <?php include('includes/header.php');?>
    <!-- MENU SECTION END-->

    <div class="content-wrapper">
        <div class="container">
            <div class="row pad-botm">
                <div class="col-md-12">
                    <h4 class="header-line">จัดการอุปกรณ์กีฬา</h4>
                </div>
                <div class="row">
                    <?php if($_SESSION['error']!="") {?>
                        <div class="col-md-6">
                            <div class="alert alert-danger">
                                <strong>พบข้อผิดพลาด: </strong> <?php echo htmlentities($_SESSION['error']);?>
                                <?php echo htmlentities($_SESSION['error']="");?>
                            </div>
                        </div>
                    <?php } ?>
                    <?php if($_SESSION['msg']!="") {?>
                        <div class="col-md-6">
                            <div class="alert alert-success">
                                <strong>สำเร็จ: </strong> <?php echo htmlentities($_SESSION['msg']);?>
                                <?php echo htmlentities($_SESSION['msg']="");?>
                            </div>
                        </div>
                    <?php } ?>
                    <?php if($_SESSION['updatemsg']!="") {?>
                        <div class="col-md-6">
                            <div class="alert alert-success">
                                <strong>สำเร็จ: </strong> <?php echo htmlentities($_SESSION['updatemsg']);?>
                                <?php echo htmlentities($_SESSION['updatemsg']="");?>
                            </div>
                        </div>
                    <?php } ?>
                    <?php if($_SESSION['delmsg']!="") {?>
                        <div class="col-md-6">
                            <div class="alert alert-success">
                                <strong>สำเร็จ: </strong> <?php echo htmlentities($_SESSION['delmsg']);?>
                                <?php echo htmlentities($_SESSION['delmsg']="");?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <!-- Advanced Tables -->
                    <div class="panel panel-default">
                        <div class="panel-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered table-hover" id="dataTables-example">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>ชื่ออุปกรณ์กีฬา</th>
                                            <th>หมวดหมู่</th>
                                            <th>ผู้รับผิดชอบ</th>
                                            <th>รหัสอุปกรณ์กีฬา</th>
                                            <th>จำนวนคงเหลือ</th>
                                            <th>รูปภาพ</th>
                                            <th>จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                            $sql = "SELECT tblbooks.BookName, tblcategory.CategoryName, tblauthors.AuthorName, tblbooks.ISBNNumber, tblbooks.BookPrice, tblbooks.Quantity, tblbooks.BookImage, tblbooks.id as bookid FROM tblbooks JOIN tblcategory ON tblcategory.id=tblbooks.CatId JOIN tblauthors ON tblauthors.id=tblbooks.AuthorId";
                                            $query = $dbh->prepare($sql);
                                            $query->execute();
                                            $results = $query->fetchAll(PDO::FETCH_OBJ);
                                            $cnt = 1;
                                            if($query->rowCount() > 0) {
                                                foreach($results as $result) {
                                        ?>                                      
                                        <tr class="odd gradeX">
                                            <td class="center"><?php echo htmlentities($cnt);?></td>
                                            <td class="center"><?php echo htmlentities($result->BookName);?></td>
                                            <td class="center"><?php echo htmlentities($result->CategoryName);?></td>
                                            <td class="center"><?php echo htmlentities($result->AuthorName);?></td>
                                            <td class="center"><?php echo htmlentities($result->ISBNNumber);?></td>
                                            <td class="center"><?php echo htmlentities($result->Quantity);?></td>
                                            <td class="center">
                                                <?php if(!empty($result->BookImage)) { ?>
                                                    <img src="/Library/uploads/<?php echo htmlentities($result->BookImage);?>" alt="image" style="width:50px;height:50px;">
                                                <?php } else { ?>
                                                    <img src="path/to/no_image_available1.png" alt="No Image" style="width:50px;height:50px;">
                                                <?php } ?>
                                            </td>


                                            <td class="center">
                                                <a href="edit-book.php?bookid=<?php echo htmlentities($result->bookid);?>"><button class="btn btn-primary"><i class="fa fa-edit "></i> แก้ไข</button></a> 
                                                <a href="manage-books.php?del=<?php echo htmlentities($result->bookid);?>" onclick="return confirm('คุณต้องการที่จะลบรายการนี้?');"><button class="btn btn-danger"><i class="fa fa-pencil"></i> ลบรายการนี้</button></a>
                                            </td>
                                        </tr>
                                        <?php $cnt=$cnt+1;}} ?>                                      
                                    </tbody>
                                </table>
                            </div>
                        </div>                  
                    </div>
                    <!-- End Advanced Tables -->
                </div>
            </div>
        </div>
    </div>

    <!-- CONTENT-WRAPPER SECTION END-->
    <?php include('includes/footer.php');?>
    <!-- FOOTER SECTION END-->

    <!-- JAVASCRIPT FILES PLACED AT THE BOTTOM TO REDUCE THE LOADING TIME  -->
    <!-- CORE JQUERY  -->
    <script src="assets/js/jquery-1.10.2.js"></script>
    <!-- BOOTSTRAP SCRIPTS  -->
    <script src="assets/js/bootstrap.js"></script>
    <!-- DATATABLE SCRIPTS  -->
    <script src="assets/js/dataTables/jquery.dataTables.js"></script>
    <script src="assets/js/dataTables/dataTables.bootstrap.js"></script>
    <!-- CUSTOM SCRIPTS  -->
    <script src="assets/js/custom.js"></script>
</body>
</html>
<?php } ?>
