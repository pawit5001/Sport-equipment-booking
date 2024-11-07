<?php
session_start();
error_reporting(0);
include('includes/config.php');
if(strlen($_SESSION['login'])==0)
{   
    header('location:index.php');
}
else{ 
    $student_id_from_db = $_SESSION['login'];

    if(isset($_POST['return'])) {
        $rid = intval($_GET['rid']);
        $fine = $_POST['fine'];
        $quantityReturned = intval($_POST['quantityReturned']);
        $rstatus = 1;
    
        // ดึงข้อมูลจำนวนที่ยืมไป
        $sqlGetBorrowedQuantity = "SELECT Quantity FROM tblissuedbookdetails WHERE id=:rid";
        $queryGetBorrowedQuantity = $dbh->prepare($sqlGetBorrowedQuantity);
        $queryGetBorrowedQuantity->bindParam(':rid', $rid, PDO::PARAM_STR);
        $queryGetBorrowedQuantity->execute();
        $resultBorrowedQuantity = $queryGetBorrowedQuantity->fetch(PDO::FETCH_ASSOC);
        $borrowedQuantity = $resultBorrowedQuantity['Quantity'];
    
        // ตรวจสอบว่าจำนวนที่คืนไม่เกินจำนวนที่ถูกยืม
        if ($quantityReturned > $borrowedQuantity) {
            $_SESSION['error'] = "ไม่สามารถคืนเกินจำนวนที่ถูกยืมไปได้";
            header('location:manage-issued-books.php');
            exit;
        }
    
        // ตรวจสอบว่าจำนวนที่คืนมีค่าไม่น้อยกว่าศูนย์
        if ($quantityReturned <= 0) {
            $_SESSION['error'] = "กรุณาระบุจำนวนที่คืนเป็นจำนวนที่มากกว่า 0";
            header('location:manage-issued-books.php');
            exit;
        }
    
        // อัปเดตจำนวนที่คืนในตาราง tblissuedbookdetails
        $sqlUpdateIssuedBook = "UPDATE tblissuedbookdetails SET fine=:fine, QuantityReturned=:quantityReturned, RetrunStatus=:rstatus WHERE id=:rid";
        $queryUpdateIssuedBook = $dbh->prepare($sqlUpdateIssuedBook);
        $queryUpdateIssuedBook->bindParam(':rid', $rid, PDO::PARAM_STR);
        $queryUpdateIssuedBook->bindParam(':fine', $fine, PDO::PARAM_STR);
        $queryUpdateIssuedBook->bindParam(':quantityReturned', $quantityReturned, PDO::PARAM_INT);
        $queryUpdateIssuedBook->bindParam(':rstatus', $rstatus, PDO::PARAM_STR);
        $queryUpdateIssuedBook->execute();
    
        // อัปเดตจำนวนที่ในตาราง tblbooks
        $sqlUpdateBooks = "UPDATE tblbooks SET Quantity = Quantity + :quantityReturned WHERE id IN (SELECT BookId FROM tblissuedbookdetails WHERE id=:rid)";
        $queryUpdateBooks = $dbh->prepare($sqlUpdateBooks);
        $queryUpdateBooks->bindParam(':quantityReturned', $quantityReturned, PDO::PARAM_INT);
        $queryUpdateBooks->bindParam(':rid', $rid, PDO::PARAM_STR);
        $queryUpdateBooks->execute();
    
        $_SESSION['msg'] = "คืนอุปกรณ์กีฬาสำเร็จ";
        header('location:manage-issued-books.php');
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
    <script>
        function getstudent() {
            $("#loaderIcon").show();
            jQuery.ajax({
                url: "get_student.php",
                data:'mobileno='+$("#mobileno").val(),
                type: "POST",
                success:function(data){
                    $("#get_student_name").html(data);
                    $("#loaderIcon").hide();
                },
                error:function (){}
            });
        }

        function getbook() {
            $("#loaderIcon").show();
            jQuery.ajax({
                url: "get_book.php",
                data:'bookid='+$("#bookid").val(),
                type: "POST",
                success:function(data){
                    $("#get_book_name").html(data);
                    $("#loaderIcon").hide();
                },
                error:function (){}
            });
        }
    </script>
    <style type="text/css">
        .others {
            color:red;
        }
    </style>
</head>
<body>
    <?php include('includes/header.php');?>
    <div class="content-wrapper">
        <div class="container">
            <div class="row pad-botm">
                <div class="col-md-12">
                    <h4 class="header-line">คืนอุปกรณ์</h4>
                </div>
            </div>
            <div class="row">
                <div class="col-md-10 col-sm-6 col-xs-12 col-md-offset-1">
                    <div class="panel panel-info">
                        <div class="panel-heading">
                            รายละเอียด
                        </div>
                        <div class="panel-body">
                            <form role="form" method="post">
                                <?php 
                                $rid=intval($_GET['rid']);
                                $sql = "SELECT tblstudents.FullName,tblbooks.BookName,tblbooks.ISBNNumber,tblissuedbookdetails.IssuesDate,tblissuedbookdetails.ReturnDate, tblissuedbookdetails.RetrunStatus, tblissuedbookdetails.QuantityReturned , tblissuedbookdetails.id as rid,tblissuedbookdetails.fine,tblissuedbookdetails.RetrunStatus from  tblissuedbookdetails join tblstudents on tblstudents.MobileNumber=tblissuedbookdetails.MobileNumber join tblbooks on tblbooks.id=tblissuedbookdetails.BookId where tblissuedbookdetails.id=:rid";
                                $query = $dbh->prepare($sql);
                                $query->bindParam(':rid',$rid,PDO::PARAM_STR);
                                $query->execute();
                                $results=$query->fetchAll(PDO::FETCH_OBJ);
                                $cnt=1;
                                if($query->rowCount() > 0)
                                {
                                    foreach($results as $result)
                                    { ?>
                                        <div class="form-group">
                                            <label>ชื่อ นามสกุล :</label>
                                            <?php echo htmlentities($result->FullName);?>
                                        </div>

                                        <div class="form-group">
                                            <label>ชื่ออุปกรณ์กีฬา: </label>
                                            <?php echo htmlentities($result->BookName);?>
                                        </div>

                                        <div class="form-group">
                                            <label>รหัสอุปกรณ์กีฬา :</label>
                                            <?php echo htmlentities($result->ISBNNumber);?>
                                        </div>

                                        <div class="form-group">
                                            <label>วัน/เดือน/ปี ที่ยืม :</label>
                                            <?php echo htmlentities($result->IssuesDate);?>
                                        </div>

                                        <div class="form-group">
                                            <label>วัน/เดือน/ปี ที่คืน :</label>
                                            <?php 
                                            if($result->ReturnDate=="")
                                            {
                                                echo '<span style="color: red;">' . htmlentities("ยังไม่คืน") . '</span>';
                                            } 
                                            else {
                                                echo htmlentities($result->ReturnDate);
                                            }
                                            ?>
                                        </div>

                                        <!-- จำนวนที่คืน -->
                                        <div class="form-group">
                                            <label>จำนวนที่คืน :</label>
                                            <?php 
                                            if(($result->ReturnStatus == 0) && ($result->QuantityReturned=="")) {
                                                echo '<input class="form-control" type="number" name="quantityReturned" id="quantityReturned" required />';
                                            } elseif ($result->ReturnStatus == 0) {
                                                echo htmlentities($result->QuantityReturned);
                                            }
                                            ?>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>ค่าปรับ (บาท) :</label>
                                            <?php 
                                            if($result->fine=="")
                                            {?>
                                                <input class="form-control" type="text" name="fine" id="fine" pattern="{1,6}" title="กรุณาระบุตัวเลขไม่เกิน 6 หลัก" value="-" />
                                            <?php }
                                            else {
                                                echo htmlentities($result->fine);
                                            }
                                            ?>
                                        </div>

                                        <?php if($result->RetrunStatus==0){?>
    <!-- ปุ่มคืนอุปกรณ์กีฬา -->
    <a href="manage-issued-books.php" class="btn btn-primary"><i class="fa fa-arrow-left"></i> ย้อนกลับ</a>
    <button type="submit" name="return" id="submit" class="btn btn-success">คืนอุปกรณ์กีฬา</button>
<?php } else { ?>
    <!-- ปุ่มย้อนกลับ -->
    <a href="manage-issued-books.php" class="btn btn-primary"><i class="fa fa-arrow-left"></i> ย้อนกลับ</a>
<?php } ?>

                                    <?php } 
                                } ?>
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
