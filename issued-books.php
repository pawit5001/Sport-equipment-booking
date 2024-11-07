<?php
session_start();
error_reporting(0);
include('includes/config.php');
if(strlen($_SESSION['login'])==0)
{
    header('location:index.php');
}
else
{ 
    if(isset($_GET['del']))
    {
        $id=$_GET['del'];
        $sql = "DELETE FROM tblbooks  WHERE id=:id";
        $query = $dbh->prepare($sql);
        $query->bindParam(':id',$id, PDO::PARAM_STR);
        $query->execute();
        $_SESSION['delmsg']="ลบข้อมูลสำเร็จ ";
        header('location:manage-books.php');
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
    <?php include('includes/header.php');?>
    <div class="content-wrapper">
        <div class="container">
            <div class="row pad-botm">
                <div class="col-md-12">
                    <h4 class="header-line">รายการทั้งหมด</h4>
                </div>
            </div>
            <div class="row">
                <?php if($_SESSION['error']!="") {?>
                    <div class="col-md-6">
                        <div class="alert alert-danger" >
                            <strong>พบข้อผิดพลาด: </strong> 
                            <?php echo htmlentities($_SESSION['error']);?>
                            <?php echo htmlentities($_SESSION['error']="");?>
                        </div>
                    </div>
                <?php } ?>
                <?php if($_SESSION['msg']!="") {?>
                    <div class="col-md-6">
                        <div class="alert alert-success" >
                            <strong>สำเร็จ :</strong> 
                            <?php echo htmlentities($_SESSION['msg']);?>
                            <?php echo htmlentities($_SESSION['msg']="");?>
                        </div>
                    </div>
                <?php } ?>

                <div class="row">
                    <div class="col-md-12">
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
                                                <th>ชื่ออุปกรณ์กีฬา</th>
                                                <th>รหัสอุปกณ์กีฬา</th>
                                                <th>วัน/เดือน/ปี ที่ยืม</th>
                                                <th>จำนวนที่ยืม</th>
                                                <th>วัน/เดือน/ปี ที่คืน</th>
                                                <th>จำนวนที่คืน</th>
                                                <th>ค่าปรับ (บาท)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                                $sid=$_SESSION['stdid'];
                                                $sql="SELECT tblbooks.BookName, tblbooks.ISBNNumber, tblissuedbookdetails.IssuesDate, tblissuedbookdetails.ReturnDate, tblissuedbookdetails.id as rid, tblissuedbookdetails.fine, tblissuedbookdetails.Quantity, tblissuedbookdetails.QuantityReturned, tblissuedbookdetails.RetrunStatus FROM tblissuedbookdetails JOIN tblstudents ON tblstudents.MobileNumber=tblissuedbookdetails.MobileNumber JOIN tblbooks ON tblbooks.id=tblissuedbookdetails.BookId WHERE tblstudents.StudentId=:sid ORDER BY tblissuedbookdetails.id DESC";
                                                $query = $dbh->prepare($sql);
                                                $query->bindParam(':sid', $sid, PDO::PARAM_STR);
                                                $query->execute();
                                                $results=$query->fetchAll(PDO::FETCH_OBJ);
                                                $cnt=1;
                                                if($query->rowCount() > 0) {
                                                    foreach($results as $result) { ?>
                                                        <tr class="odd gradeX">
                                                            <td class="center"><?php echo htmlentities($cnt);?></td>
                                                            <td class="center"><?php echo htmlentities($result->BookName);?></td>
                                                            <td class="center"><?php echo htmlentities($result->ISBNNumber);?></td>
                                                            <td class="center"><?php echo htmlentities($result->IssuesDate);?></td>
                                                            <td class="center"><?php echo htmlentities($result->Quantity); ?></td>
                                                            <td class="center">
                                                        <?php
                                                        if($result->RetrunStatus == 0) {
                                                            echo '<span style="color: red;">ยังไม่คืน</span>';
                                                        } elseif($result->RetrunStatus == 1) {
                                                            echo htmlentities($result->ReturnDate);
                                                        }
                                                        ?>
                                                    </td>
                                                    <td class="center"><?php echo htmlentities($result->QuantityReturned); ?></td>
                                                    <td class="center"><?php echo htmlentities($result->fine);?></td>
                                                        </tr>
                                                        <?php $cnt = $cnt + 1;
                                                    }
                                                } ?>                                      
                                        </tbody>
                                    </table>
                                </div>                            
                            </div>
                        </div>
                    </div>
                </div>      
            </div>
        </div>
    </div>

    <?php include('includes/footer.php');?>
    <script src="assets/js/jquery-1.10.2.js"></script>
    <script src="assets/js/bootstrap.js"></script>
    <script src="assets/js/dataTables/jquery.dataTables.js"></script>
    <script src="assets/js/dataTables/dataTables.bootstrap.js"></script>
    <script src="assets/js/custom.js"></script>
</body>
</html>
<?php
