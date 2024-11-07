<?php
session_start();
error_reporting(0);
include('includes/config.php');

if(strlen($_SESSION['login'])==0) {   
    header('location:index.php');
} else { 
    if(isset($_POST['issue'])) {
        $mobileno = strtoupper($_POST['mobileno']);
        $bookid = $_POST['bookdetails'];
        $quantity = $_POST['quantity'];
    
        // ดึงจำนวนอุปกรณ์ที่เหลือ
        $sqlQuantity = "SELECT Quantity FROM tblbooks WHERE id = :bookid";
        $queryQuantity = $dbh->prepare($sqlQuantity);
        $queryQuantity->bindParam(':bookid', $bookid, PDO::PARAM_STR);
        $queryQuantity->execute();
        $resultQuantity = $queryQuantity->fetch(PDO::FETCH_ASSOC);
    
        if ($resultQuantity['Quantity'] >= $quantity) {
            // ลดจำนวนอุปกรณ์ตามจำนวนที่ยืม
            $newQuantity = $resultQuantity['Quantity'] - $quantity;
            $updateQuantitySql = "UPDATE tblbooks SET Quantity = :newQuantity WHERE id = :bookid";
            $updateQuantityQuery = $dbh->prepare($updateQuantitySql);
            $updateQuantityQuery->bindParam(':newQuantity', $newQuantity, PDO::PARAM_INT);
            $updateQuantityQuery->bindParam(':bookid', $bookid, PDO::PARAM_STR);
            $updateQuantityQuery->execute();
    
            // เพิ่มรายการยืม
            $sql = "INSERT INTO tblissuedbookdetails(MobileNumber, BookId, Quantity) VALUES(:mobileno, :bookid, :quantity)";
            $query = $dbh->prepare($sql);
            $query->bindParam(':mobileno', $mobileno, PDO::PARAM_STR);
            $query->bindParam(':bookid', $bookid, PDO::PARAM_STR);
            $query->bindParam(':quantity', $quantity, PDO::PARAM_INT);
            $query->execute();
    
            $lastInsertId = $dbh->lastInsertId();
    
            if ($lastInsertId) {
                $_SESSION['msg'] = "เพิ่มรายการยืมสำเร็จ";
                header('location:manage-issued-books.php');
            } else {
                $_SESSION['error'] = "พบข้อผิดพลาด กรุณาลองใหม่อีกครั้ง";
                header('location:manage-issued-books.php');
            }
        } else {
            $_SESSION['error'] = "อุปกรณ์ไม่เพียงพอ";
            header('location:manage-issued-books.php');
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
    <script>
        // function for get student name
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

        //function for book details
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
        .others{
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
                    <h4 class="header-line">ยืมอุปกรณ์กีฬา</h4>
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
                                <div class="form-group">
                                    <label>รหัสนักศึกษา <span style="color:red;">*</span></label>
                                    <input class="form-control" type="text" name="mobileno" id="mobileno" onBlur="getstudent()" autocomplete="off"  required />
                                </div>
                                <div class="form-group">
                                    <span id="get_student_name" style="font-size:16px;"></span> 
                                </div>
                                <div class="form-group">
                                    <label>รหัสอุปกรณ์กีฬา<span style="color:red;">*</span></label>
                                    <input class="form-control" type="text" name="bookid" id="bookid" onBlur="getbook()"  required="required" />
                                </div>
                                <div class="form-group">
                                    <select class="form-control" name="bookdetails" id="get_book_name" readonly>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>จำนวนที่ต้องการยืม<span style="color:red;">*</span></label>
                                    <input class="form-control" type="number" name="quantity" id="quantity" required="required" />
                                </div>


                                <button type="submit" name="issue" id="submit" class="btn btn-info">ยืมอุปกรณ์กีฬา </button>
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
