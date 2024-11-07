<?php
session_start();
error_reporting(0);
include('includes/config.php');

if (strlen($_SESSION['login']) == 0) {
    header('location:index.php');
} else {
    $mobileno = $_SESSION['stdid'];
?>
<!DOCTYPE html>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>E-Sports | Dashboard</title>
    <!-- BOOTSTRAP CORE STYLE  -->
    <link href="assets/css/bootstrap.css" rel="stylesheet" />
    <!-- FONT AWESOME STYLE  -->
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <!-- CUSTOM STYLE  -->
    <link href="assets/css/style.css" rel="stylesheet" />
    <!-- GOOGLE FONT -->
    <link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css' />
    <style>
        .row {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start;
        }

        .col-md-3 {
            flex: 0 0 25%;
            max-width: 25%;
            margin-bottom: 15px;
        }

        .thumbnail {
            background-color: #f8f8f8;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 10px;
        }

        .thumbnail img {
            width: 100%;
            height: auto;
        }

        .caption h3 {
        font-size: 14px; 
        color: #333;
        margin-bottom: 3px;
        text-align: left;
         }
    </style>
</head>
<body>
    <!------MENU SECTION START-->
    <?php include('includes/header.php'); ?>
    <!-- MENU SECTION END-->
    <div class="content-wrapper">
        <div class="container">
            <div class="row pad-botm">
                <div class="col-md-12">
                    <h4 class="header-line">ผู้ใช้ทั่วไป</h4>
                </div>
            </div>
            <div class="row">
                <?php
                $sql = "SELECT BookImage, BookName, ISBNNumber, Quantity FROM tblbooks";
                $query = $dbh->prepare($sql);
                $query->execute();
                $results = $query->fetchAll(PDO::FETCH_OBJ);
                foreach ($results as $result) {
                ?>
                    <div class="col-md-3 col-sm-3 col-xs-6">
                        <div class="thumbnail text-center">
                            <img src="/Library/uploads/<?php echo htmlentities($result->BookImage); ?>" alt="Book Image" style="width: 140px; height: 150px;">
                            <div class="caption">
                    
                                <h3 style="font-weight: bold;"><?php echo htmlentities($result->BookName); ?></h3>
                                <h3>- รหัสอุปกณ์กีฬา: <span style="font-weight: bold;"><?php echo htmlentities($result->ISBNNumber); ?></span></h3>
                                <h3>- จำนวนคงเหลือ: <span style="font-weight: bold;"><?php echo htmlentities($result->Quantity); ?></span></h3>

                            </div>
                        </div>
                    </div>
                <?php
                }
                ?>
            </div>
        </div>
    </div>
    <!-- CONTENT-WRAPPER SECTION END-->
    <?php include('includes/footer.php'); ?>
    <!-- FOOTER SECTION END-->
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
