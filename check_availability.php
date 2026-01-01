<?php 
require_once("includes/config.php");

// Code for checking email or StudentID availability
$type = isset($_POST['type']) ? $_POST['type'] : '';

if($type === 'email' && !empty($_POST["emailid"])) {
    $email = $_POST["emailid"];
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        echo "<span class='d-block mt-1' style='color: #ef4444; font-size: 0.85rem;'><i class='fa fa-exclamation-circle me-1'></i>รูปแบบอีเมลไม่ถูกต้อง</span>";
    } else {
        $sql = "SELECT Email FROM tblmembers WHERE Email=:email";
        $query = $dbh->prepare($sql);
        $query->bindParam(':email', $email, PDO::PARAM_STR);
        $query->execute();
        if($query->rowCount() > 0) {
            echo "<span class='d-block mt-1' style='color: #ef4444; font-size: 0.85rem;'><i class='fa fa-exclamation-circle me-1'></i>อีเมลนี้ถูกใช้ไปแล้ว</span>";
        } else {
            echo "<span class='d-block mt-1' style='color: #10b981; font-size: 0.85rem;'><i class='fa fa-check-circle me-1'></i>อีเมลนี้ใช้ได้</span>";
        }
    }
}
elseif($type === 'studentid' && !empty($_POST["studentid"])) {
    $studentid = $_POST["studentid"];
    if (!preg_match("/^\d{12}-\d$/", $studentid)) {
        echo "<span class='d-block mt-1' style='color: #ef4444; font-size: 0.85rem;'><i class='fa fa-exclamation-circle me-1'></i>รูปแบบรหัสนักศึกษาไม่ถูกต้อง (12 ตัวเลข-1 ตัวเลข)</span>";
    } else {
        $sql = "SELECT StudentID FROM tblmembers WHERE StudentID=:studentid";
        $query = $dbh->prepare($sql);
        $query->bindParam(':studentid', $studentid, PDO::PARAM_STR);
        $query->execute();
        if($query->rowCount() > 0) {
            echo "<span class='d-block mt-1' style='color: #ef4444; font-size: 0.85rem;'><i class='fa fa-exclamation-circle me-1'></i>รหัสนักศึกษานี้ถูกใช้ไปแล้ว</span>";
        } else {
            echo "<span class='d-block mt-1' style='color: #10b981; font-size: 0.85rem;'><i class='fa fa-check-circle me-1'></i>รหัสนักศึกษานี้ใช้ได้</span>";
        }
    }
}
?>
