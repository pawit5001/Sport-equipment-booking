<?php
session_start();
error_reporting(0);
include('includes/config.php');
if (strlen($_SESSION['alogin']) == 0) {
    header('location:../adminlogin.php');
    exit;
}
else{ 
    $student_id_from_db = $_SESSION['alogin'];

    if(isset($_POST['return'])) {
        $rid = intval($_GET['rid']);
        $fine = $_POST['fine'];
        $quantityReturned = intval($_POST['quantityReturned']);
        $rstatus = 1;
    
        // ดึงข้อมูลจำนวนที่ยืมไป
        $sqlGetBorrowedQuantity = "SELECT Quantity FROM tblbookingdetails WHERE id=:rid";
        $queryGetBorrowedQuantity = $dbh->prepare($sqlGetBorrowedQuantity);
        $queryGetBorrowedQuantity->bindParam(':rid', $rid, PDO::PARAM_STR);
        $queryGetBorrowedQuantity->execute();
        $resultBorrowedQuantity = $queryGetBorrowedQuantity->fetch(PDO::FETCH_ASSOC);
        $borrowedQuantity = $resultBorrowedQuantity['Quantity'];
    
        // ตรวจสอบว่าจำนวนที่คืนไม่เกินจำนวนที่ถูกยืม
        if ($quantityReturned > $borrowedQuantity) {
            $_SESSION['admin_error'] = "ไม่สามารถคืนเกินจำนวนที่ถูกยืมไปได้";
            header('location:manage-bookings.php');
            exit;
        }
    
        // ตรวจสอบว่าจำนวนที่คืนมีค่าไม่น้อยกว่าศูนย์
        if ($quantityReturned <= 0) {
            $_SESSION['admin_error'] = "กรุณาระบุจำนวนที่คืนเป็นจำนวนที่มากกว่า 0";
            header('location:manage-bookings.php');
            exit;
        }
    
        // อัปเดตจำนวนที่คืนในตาราง tblbookingdetails
        $sqlUpdateIssuedBook = "UPDATE tblbookingdetails SET fine=:fine, QuantityReturned=:quantityReturned, RetrunStatus=:rstatus WHERE id=:rid";
        $queryUpdateIssuedBook = $dbh->prepare($sqlUpdateIssuedBook);
        $queryUpdateIssuedBook->bindParam(':rid', $rid, PDO::PARAM_STR);
        $queryUpdateIssuedBook->bindParam(':fine', $fine, PDO::PARAM_STR);
        $queryUpdateIssuedBook->bindParam(':quantityReturned', $quantityReturned, PDO::PARAM_INT);
        $queryUpdateIssuedBook->bindParam(':rstatus', $rstatus, PDO::PARAM_STR);
        $queryUpdateIssuedBook->execute();
    
        // อัปเดตจำนวนที่ในตาราง tblequipment
        $sqlUpdateBooks = "UPDATE tblequipment SET Quantity = Quantity + :quantityReturned WHERE id IN (SELECT EquipmentId FROM tblbookingdetails WHERE id=:rid)";
        $queryUpdateBooks = $dbh->prepare($sqlUpdateBooks);
        $queryUpdateBooks->bindParam(':quantityReturned', $quantityReturned, PDO::PARAM_INT);
        $queryUpdateBooks->bindParam(':rid', $rid, PDO::PARAM_STR);
        $queryUpdateBooks->execute();
    
        $_SESSION['admin_msg'] = "คืนอุปกรณ์กีฬาสำเร็จ";
        header('location:manage-bookings.php');
    }
     
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>E-Sports | Update Booking</title>
    <!-- BOOTSTRAP CORE STYLE  -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <!-- FONT AWESOME STYLE  -->
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <!-- CUSTOM STYLE  -->
    <link href="assets/css/style.css" rel="stylesheet" />
    <!-- MODERN STYLE (shared at root assets) -->
    <link href="../assets/css/modern-style.css" rel="stylesheet" />
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
                url: "get_equipment.php",
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
                                $sql = "SELECT tblmembers.MemberName,tblequipment.EquipmentName,tblequipment.EquipmentCode,tblbookingdetails.BookingDate,tblbookingdetails.ReturnDate, tblbookingdetails.RetrunStatus, tblbookingdetails.QuantityReturned , tblbookingdetails.id as rid,tblbookingdetails.fine,tblbookingdetails.RetrunStatus from  tblbookingdetails join tblmembers on tblmembers.MobileNumber=tblbookingdetails.MobileNumber join tblequipment on tblequipment.id=tblbookingdetails.EquipmentId where tblbookingdetails.id=:rid";
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
                                            <?php echo htmlentities($result->MemberName);?>
                                        </div>

                                        <div class="form-group">
                                            <label>ชื่ออุปกรณ์กีฬา: </label>
                                            <?php echo htmlentities($result->EquipmentName);?>
                                        </div>

                                        <div class="form-group">
                                            <label>รหัสอุปกรณ์กีฬา :</label>
                                            <?php echo htmlentities($result->EquipmentCode);?>
                                        </div>

                                        <div class="form-group">
                                            <label>วัน/เดือน/ปี ที่ยืม :</label>
                                            <?php echo htmlentities($result->BookingDate);?>
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
    <a href="manage-bookings.php" class="btn btn-primary"><i class="fa fa-arrow-left"></i> ย้อนกลับ</a>
    <button type="submit" name="return" id="submit" class="btn btn-success">คืนอุปกรณ์กีฬา</button>
<?php } else { ?>
    <!-- ปุ่มย้อนกลับ -->
    <a href="manage-bookings.php" class="btn btn-primary"><i class="fa fa-arrow-left"></i> ย้อนกลับ</a>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- CUSTOM SCRIPTS  -->
    <script src="assets/js/custom.js"></script>
    <!-- MODERN INTERACTIONS (shared at root assets) -->
    <script src="../assets/js/interactions.js"></script>
</body>
</html>
<?php } ?>
