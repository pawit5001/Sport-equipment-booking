<?php
session_start();
error_reporting(0);
include('includes/config.php');
if(strlen($_SESSION['login'])==0) {   
    header('location:index.php');
} else {
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
    <!-- DATATABLE STYLE  -->
    <link href="assets/js/dataTables/dataTables.bootstrap.css" rel="stylesheet" />
    <!-- CUSTOM STYLE  -->
    <link href="assets/css/style.css" rel="stylesheet" />
    <!-- GOOGLE FONT -->
    <link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css' />
</head>
<body>
    <!------MENU SECTION START-->
    <?php include('includes/header.php');?>
    <!-- MENU SECTION END-->
    <div class="content-wrapper">
        <div class="container">
            <div class="row pad-botm">
                <div class="col-md-12">
                    <h4 class="header-line">รายการ ยืม/คืน ทั้งหมด</h4>
                </div>
                <div class="row">
                    <?php if($_SESSION['error']!="") { ?>
                        <div class="col-md-6">
                            <div class="alert alert-danger" >
                                <strong>พบข้อผิดพลาด: </strong> 
                                <?php echo htmlentities($_SESSION['error']);?>
                                <?php echo htmlentities($_SESSION['error']="");?>
                            </div>
                        </div>
                    <?php } ?>
                    <?php if($_SESSION['msg']!="") { ?>
                        <div class="col-md-6">
                            <div class="alert alert-success" >
                                <strong>สำเร็จ :</strong> 
                                <?php echo htmlentities($_SESSION['msg']);?>
                                <?php echo htmlentities($_SESSION['msg']="");?>
                            </div>
                        </div>
                    <?php } ?>
                    <?php if($_SESSION['delmsg']!="") { ?>
                        <div class="col-md-6">
                            <div class="alert alert-success" >
                                <strong>สำเร็จ :</strong> 
                                <?php echo htmlentities($_SESSION['delmsg']);?>
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
                        <div class="panel-heading">
                            รายละเอียด
                        </div>
                        <div class="panel-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered table-hover" id="dataTables-example">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>ชื่อ นามสกุล</th>
                                            <th>ชื่ออุปกรณ์กีฬา</th>
                                            <th>รหัสอุปกณ์กีฬา</th>
                                            <th>วัน/เดือน/ปี ที่ยืม</th>
                                            <th>จำนวนที่ยืม</th>
                                            <th>วัน/เดือน/ปี ที่คืน</th>
                                            <th>จำนวนที่คืน</th>
                                            <th>คืนอุปกรณ์กีฬา</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $sql = "SELECT tblstudents.FullName, tblbooks.BookName, tblbooks.ISBNNumber, tblissuedbookdetails.IssuesDate, tblissuedbookdetails.Quantity, tblissuedbookdetails.RetrunStatus, tblissuedbookdetails.QuantityReturned, tblissuedbookdetails.ReturnDate, tblissuedbookdetails.id as rid FROM tblissuedbookdetails JOIN tblstudents ON tblstudents.MobileNumber=tblissuedbookdetails.MobileNumber JOIN tblbooks ON tblbooks.id=tblissuedbookdetails.BookId ORDER BY tblissuedbookdetails.id DESC";
                                        $query = $dbh->prepare($sql);
                                        $query->execute();
                                        $results = $query->fetchAll(PDO::FETCH_OBJ);
                                        $cnt = 1;
                                        if($query->rowCount() > 0) {
                                            foreach($results as $result) { ?>
                                                <tr class="odd gradeX">
                                                    <td class="center"><?php echo htmlentities($cnt);?></td>
                                                    <td class="center"><?php echo htmlentities($result->FullName);?></td>
                                                    <td class="center"><?php echo htmlentities($result->BookName);?></td>
                                                    <td class="center"><?php echo htmlentities($result->ISBNNumber);?></td>
                                                    <td class="center"><?php echo htmlentities($result->IssuesDate);?></td>
                                                    <td class="center"><?php echo htmlentities($result->Quantity);?></td>
                                                    <td class="center">
                                                        <?php
                                                        if($result->RetrunStatus == 0) {
                                                            echo '<span style="color: red;">ยังไม่คืน</span>';
                                                        } elseif($result->RetrunStatus == 1) {
                                                            echo htmlentities($result->ReturnDate);
                                                        }
                                                        ?>
                                                    </td>
                                                    <td class="center"><?php echo htmlentities($result->QuantityReturned);?></td>
                                                    <td class="center">
                                                        <?php
                                                        if($result->RetrunStatus == 0) {
                                                            echo '<a href="update-issue-bookdeails.php?rid=' . htmlentities($result->rid) . '"><button class="btn btn-primary"><i class="fa fa-edit"></i> คืนอุปกรณ์กีฬา</button></a>';
                                                        } elseif($result->RetrunStatus == 1) {
                                                            echo '<a href="update-issue-bookdeails.php?rid=' . htmlentities($result->rid) . '"><button class="btn btn-success"><i class="fa fa-history"></i> ประวัติการทำรายการ</button></a>';
                                                        }
                                                        ?>
                                                    </td>
                                                </tr>
                                                <?php $cnt = $cnt + 1;
                                            }
                                        } ?>                                      
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
