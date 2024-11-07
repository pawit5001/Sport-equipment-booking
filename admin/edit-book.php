<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include('includes/config.php');

$targetDir = $_SERVER['DOCUMENT_ROOT'] . "/Library/uploads/";

if(strlen($_SESSION['login']) == 0) {
    header('location:index.php');
} else {
    if(isset($_POST['update'])) {
        $bookname = $_POST['bookname'];
        $category = $_POST['category'];
        $author = $_POST['author'];
        $isbn = $_POST['isbn'];
        $quantity = $_POST['quantity'];
        $bookid = intval($_GET['bookid']);

        // Check if ISBNNumber or BookName already exists in the database
        $checkSql = "SELECT * FROM tblbooks WHERE (ISBNNumber = :isbn OR BookName = :bookname) AND id != :bookid";
        $checkQuery = $dbh->prepare($checkSql);
        $checkQuery->bindParam(':isbn', $isbn, PDO::PARAM_STR);
        $checkQuery->bindParam(':bookname', $bookname, PDO::PARAM_STR);
        $checkQuery->bindParam(':bookid', $bookid, PDO::PARAM_INT);
        $checkQuery->execute();
        $count = $checkQuery->rowCount();

        if ($count > 0) {
            $_SESSION['error'] = "ในระบบมีข้อมูลนี้อยู่แล้ว";
            header('location:manage-books.php');
            exit;
        }

        // Upload new image if selected
        if(isset($_FILES['bookimage']['name']) && $_FILES['bookimage']['name'] != '') {
            // Delete old image
            $old_image_path = "/Library/uploads/" . htmlentities($_POST['old_image']);
            if (file_exists($old_image_path)) {
                unlink($old_image_path);
            }

            // Upload new image
            $img_file = $_FILES['bookimage']['name'];
            $img_temp = $_FILES['bookimage']['tmp_name'];
            $img_type = $_FILES['bookimage']['type'];
            $img_ext = strtolower(pathinfo($img_file, PATHINFO_EXTENSION));

            $upload_dir = '/Library/uploads/';
            $img_name = uniqid() . '.' . $img_ext;
            $img_path = $upload_dir . $img_name;

            move_uploaded_file($img_temp, $img_path);

            // Update the database with the new image
            $sql_update_image = "UPDATE tblbooks SET BookImage=:bookimage WHERE id=:bookid";
            $query_update_image = $dbh->prepare($sql_update_image);
            $query_update_image->bindParam(':bookimage', $img_name, PDO::PARAM_STR);
            $query_update_image->bindParam(':bookid', $bookid, PDO::PARAM_INT);
            $query_update_image->execute();
        }

        // Update book details
        $sql = "UPDATE tblbooks SET BookName=:bookname, CatId=:category, AuthorId=:author, ISBNNumber=:isbn, Quantity=:quantity WHERE id=:bookid";
        $query = $dbh->prepare($sql);
        $query->bindParam(':bookname', $bookname, PDO::PARAM_STR);
        $query->bindParam(':category', $category, PDO::PARAM_STR);
        $query->bindParam(':author', $author, PDO::PARAM_STR);
        $query->bindParam(':isbn', $isbn, PDO::PARAM_STR);
        $query->bindParam(':quantity', $quantity, PDO::PARAM_INT);
        $query->bindParam(':bookid', $bookid, PDO::PARAM_INT);
        $query->execute();

        $_SESSION['msg'] = "แก้ไขรายละเอียดสำเร็จ";
        header('location:manage-books.php');
        exit;
    }

    $bookid=intval($_GET['bookid']);
    $sql = "SELECT tblbooks.BookName, tblcategory.CategoryName, tblcategory.id as cid, tblauthors.AuthorName, tblauthors.id as athrid, tblbooks.ISBNNumber, tblbooks.Quantity, tblbooks.BookImage, tblbooks.id as bookid FROM tblbooks
    JOIN tblcategory ON tblcategory.id=tblbooks.CatId
    JOIN tblauthors ON tblauthors.id=tblbooks.AuthorId
    WHERE tblbooks.id=:bookid";

    $query = $dbh->prepare($sql);
    $query->bindParam(':bookid', $bookid, PDO::PARAM_INT);
    $query->execute();
    $results = $query->fetchAll(PDO::FETCH_OBJ);
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
    <!------MENU SECTION START-->
    <?php include('includes/header.php');?>
    <!-- MENU SECTION END-->
    <div class="content-wrapper">
        <div class="container">
            <div class="row pad-botm">
                <div class="col-md-12">
                    <h4 class="header-line">แก้ไขรายละเอียดอุปกรณ์กีฬา</h4>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 col-sm-6 col-xs-12 col-md-offset-3">
                    <div class="panel panel-info">
                        <div class="panel-heading">
                            รายละเอียด
                        </div>
                        <div class="panel-body">
                            <form role="form" method="post" enctype="multipart/form-data">
                                <?php 
                                $bookid=intval($_GET['bookid']);
                                $sql = "SELECT tblbooks.BookName,tblcategory.CategoryName,tblcategory.id as cid,tblauthors.AuthorName,tblauthors.id as athrid,tblbooks.ISBNNumber,tblbooks.Quantity,tblbooks.BookImage,tblbooks.id as bookid FROM tblbooks 
                                JOIN tblcategory ON tblcategory.id=tblbooks.CatId
                                JOIN tblauthors ON tblauthors.id=tblbooks.AuthorId
                                WHERE tblbooks.id=:bookid";
                                $query = $dbh -> prepare($sql);
                                $query-> bindParam(':bookid', $bookid, PDO::PARAM_STR);
                                $query-> execute();
                                $results = $query -> fetchAll(PDO::FETCH_OBJ);
                                $cnt=1;
                                if($query -> rowCount() > 0) {
                                    foreach($results as $result) { ?>
                                        <div class="form-group">
                                            <label>ชื่ออุปกรณ์กีฬา <span style="color:red;">*</span></label>
                                            <input class="form-control" type="text" name="bookname" value="<?php echo htmlentities($result->BookName);?>" required />
                                        </div>
                                        <div class="form-group">
                                            <label> หมวดหมู่<span style="color:red;">*</span></label>
                                            <select class="form-control" name="category" required="required">
                                                <option value="<?php echo htmlentities($result->cid);?>"> <?php echo htmlentities($result->CategoryName);?></option>
                                                <?php 
                                                $status=1;
                                                $sql1 = "SELECT * from  tblcategory where Status=:status";
                                                $query1 = $dbh -> prepare($sql1);
                                                $query1-> bindParam(':status',$status, PDO::PARAM_STR);
                                                $query1->execute();
                                                $resultss=$query1->fetchAll(PDO::FETCH_OBJ);
                                                if($query1->rowCount() > 0) {
                                                    foreach($resultss as $row) {           
                                                        if($result->CategoryName==$row->CategoryName) {
                                                            continue;
                                                        } else { ?>
                                                            <option value="<?php echo htmlentities($row->id);?>"><?php echo htmlentities($row->CategoryName);?></option>
                                                        <?php }
                                                    }
                                                } ?> 
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label> ผู้รับผิดชอบ<span style="color:red;">*</span></label>
                                            <select class="form-control" name="author" required="required">
                                                <option value="<?php echo htmlentities($result->athrid);?>"> <?php echo htmlentities($result->AuthorName);?></option>
                                                <?php 
                                                $sql2 = "SELECT * from  tblauthors ";
                                                $query2 = $dbh -> prepare($sql2);
                                                $query2->execute();
                                                $result2=$query2->fetchAll(PDO::FETCH_OBJ);
                                                if($query2->rowCount() > 0) {
                                                    foreach($result2 as $ret) {           
                                                        if($result->AuthorName==$ret->AuthorName) {
                                                            continue;
                                                        } else { ?>  
                                                            <option value="<?php echo htmlentities($ret->id);?>"><?php echo htmlentities($ret->AuthorName);?></option>
                                                        <?php }
                                                    }
                                                } ?> 
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>รหัสอุปกรณ์กีฬา<span style="color:red;">*</span></label>
                                            <input class="form-control" type="text" name="isbn" title="รูปแบบข้อมูลไม่ถูกต้อง รองรับตัวเลขเท่านั้น" pattern="[1-9][0-9]{0,4}" maxlength="5" value="<?php echo isset($result->ISBNNumber) ? htmlentities($result->ISBNNumber) : ''; ?>" required />

                                        </div>
                                        <div class="form-group">
                                            <label>จำนวน<span style="color:red;">*</span></label>
                                            <input class="form-control" type="number" name="quantity" value="<?php echo htmlentities($result->Quantity);?>" required max="999" />
                                        </div>
                                        
                                        <div class="form-group">
                                        <label>รูปภาพปัจจุบัน</label><br>
                                        <img src="/Library/uploads/<?php echo htmlentities($result->BookImage);?>" width="250" height="">
                                        <input type="hidden" name="old_image" value="<?php echo htmlentities($result->BookImage);?>">
                                        <?php if(empty($result->BookImage)) { ?>
                                            <label for="bookimage">เลือกรูปภาพใหม่</label>
                                            <input type="file" name="bookimage" accept="image/*" />
                                        <?php } ?>
                                    </div>

                                    <?php }
                                } ?>
                                <a href="manage-books.php" class="btn btn-primary"><i class="fa fa-arrow-left"></i> ย้อนกลับ</a>
                                <button type="submit" name="update" class="btn btn-success">แก้ไขรายละเอียดอุปกรณ์ </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include('includes/footer.php');?>
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
