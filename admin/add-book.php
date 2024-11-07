<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include('includes/config.php');

$targetDir = $_SERVER['DOCUMENT_ROOT'] . "/Library/uploads/";
$defaultImage = "no_image_available1.png"; // ชื่อรูปภาพที่ใช้เป็นค่าเริ่มต้น

if(strlen($_SESSION['login']) == 0) {
    header('location:index.php');
} else {
    if(isset($_POST['add'])) {
        $bookname = $_POST['bookname'];
        $category = $_POST['category'];
        $author = $_POST['author'];
        $isbn = $_POST['isbn'];
        $quantity = $_POST['quantity'];

        // Check if ISBNNumber or BookName already exists in the database
        $checkSql = "SELECT * FROM tblbooks WHERE ISBNNumber = :isbn OR BookName = :bookname";
        $checkQuery = $dbh->prepare($checkSql);
        $checkQuery->bindParam(':isbn', $isbn, PDO::PARAM_STR);
        $checkQuery->bindParam(':bookname', $bookname, PDO::PARAM_STR);
        $checkQuery->execute();
        $count = $checkQuery->rowCount();

        if ($count > 0) {
            $_SESSION['error'] = "ในระบบมีข้อมูลนี้อยู่แล้ว";
            header('location:manage-books.php');
            exit();
        }

        // Set the default image if no image is uploaded
        $file_name_with_ext = $defaultImage;

        // Check if file is uploaded successfully
        if(isset($_FILES['bookimage']) && $_FILES['bookimage']['error'] === UPLOAD_ERR_OK) {
            // Generate a unique ID starting with "RMUTTO-IMG"
            $file_name = 'RMUTTO-IMG' . uniqid() . rand(100000, 999999);

            $file_size = $_FILES['bookimage']['size'];
            $file_tmp = $_FILES['bookimage']['tmp_name'];
            $file_parts = explode('.', $_FILES['bookimage']['name']);
            $file_ext = strtolower(end($file_parts));

            $extensions = array("jpeg", "jpg", "png");

            if(!in_array($file_ext, $extensions)) {
                $_SESSION['error'] = "รูปแบบไฟล์ไม่ถูกต้อง, กรุณาเลือกไฟล์ JPEG หรือ PNG.";
                header('location:manage-books.php');
                exit();
            }

            if($file_size > 2097152) {
                $_SESSION['error'] = 'ไฟล์รูปภาพขนาดใหญ่เกินไป (ขนาดไม่เกิน 2 MB)';
                header('location:manage-books.php');
                exit();
            }

            // Combine the generated filename with the file extension
            $file_name_with_ext = $file_name . '.' . $file_ext;
            // Combine the target directory with the complete filename
            $image_path = $targetDir . $file_name_with_ext;

            // Move the uploaded file to the target directory with the generated filename
            move_uploaded_file($file_tmp, $image_path);
        }

        $sql = "INSERT INTO  tblbooks(BookName, CatId, AuthorId, ISBNNumber, Quantity, BookImage) VALUES(:bookname, :category, :author, :isbn, :quantity, :image)";
        $query = $dbh->prepare($sql);
        $query->bindParam(':bookname', $bookname, PDO::PARAM_STR);
        $query->bindParam(':category', $category, PDO::PARAM_STR);
        $query->bindParam(':author', $author, PDO::PARAM_STR);
        $query->bindParam(':isbn', $isbn, PDO::PARAM_STR);
        $query->bindParam(':quantity', $quantity, PDO::PARAM_INT);
        $query->bindParam(':image', $file_name_with_ext, PDO::PARAM_STR);
        $query->execute();

        $lastInsertId = $dbh->lastInsertId();

        if($lastInsertId) {
            $_SESSION['msg'] = "อุปกรณ์กีฬาได้ถูกเพิ่มลงในรายการแล้ว";
            header('location:manage-books.php');
            exit();
        } else {
            $_SESSION['error'] = "เกิดข้อผิดพลาดบางอย่าง กรุณาลองใหม่อีกครั้ง";
            header('location:manage-books.php');
            exit();
        }
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
                    <h4 class="header-line">เพิ่มอุปกรณ์กีฬา</h4>
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
                                <div class="form-group">
                                    <label>ชื่ออุปกรณ์กีฬา<span style="color:red;">*</span></label>
                                    <input class="form-control" type="text" name="bookname" value="<?php echo isset($result->BookName) ? htmlentities($result->BookName) : ''; ?>" required />
                                </div>
                                <div class="form-group">
                                    <label> หมวดหมู่<span style="color:red;">*</span></label>
                                    <select class="form-control" name="category" id="categorySelect" required="required">
                                        <option value=""> เลือกหมวดหมู่ </option>
                                        <?php
                                        $status=1;
                                        $sql = "SELECT * from  tblcategory where Status=:status";
                                        $query = $dbh -> prepare($sql);
                                        $query -> bindParam(':status',$status, PDO::PARAM_STR);
                                        $query->execute();
                                        $results=$query->fetchAll(PDO::FETCH_OBJ);
                                        $cnt=1;
                                        if($query->rowCount() > 0) {
                                            foreach($results as $result) {
                                        ?>
                                        <option value="<?php echo htmlentities($result->id);?>">
                                            <?php echo htmlentities($result->CategoryName);?>
                                        </option>
                                        <?php }} ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label> ผู้รับผิดชอบ<span style="color:red;">*</span></label>
                                    <select class="form-control" name="author" required="required">
                                        <option value="">เลือกผู้รับผิดชอบ</option>
                                        <?php
                                        $sql = "SELECT * from  tblauthors ";
                                        $query = $dbh -> prepare($sql);
                                        $query->execute();
                                        $results=$query->fetchAll(PDO::FETCH_OBJ);
                                        $cnt=1;
                                        if($query->rowCount() > 0) {
                                            foreach($results as $result) {
                                        ?>
                                        <option value="<?php echo htmlentities($result->id);?>">
                                            <?php echo htmlentities($result->AuthorName);?>
                                        </option>
                                        <?php }} ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>รหัสอุปกรณ์กีฬา<span style="color:red;">*</span></label>
                                    <input class="form-control" type="text" name="isbn" title="รูปแบบข้อมูลไม่ถูกต้อง รองรับตัวเลขเท่านั้น" pattern="[1-9][0-9]{0,4}" maxlength="5" value="<?php echo isset($result->ISBNNumber) ? htmlentities($result->ISBNNumber) : ''; ?>" required />
                                </div>
                                <div class="form-group">
                                    <label>จำนวน<span style="color:red;">*</span></label>
                                    <input class="form-control" type="number" name="quantity" value="<?php echo isset($result->Quantity) ? htmlentities($result->Quantity) : ''; ?>" required max="999" />
                                </div>
                                <div class="form-group">
                                    <label>อัปโหลดรูปภาพ</label>
                                    <input class="form-control" type="file" name="bookimage" accept="image/*" />
                                </div>
                                <button type="submit" name="add" class="btn btn-info">เพิ่มอุปกรณ์กีฬา</button>
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
