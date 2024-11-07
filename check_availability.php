<?php 
require_once("includes/config.php");

// Code for checking email availability
if(!empty($_POST["emailid"])) {
    $email = $_POST["emailid"];
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        echo "<span style='color:red'> รูปแบบอีเมลไม่ถูกต้อง! </span>";
    } else {
        $sql = "SELECT EmailId FROM tblstudents WHERE EmailId=:email";
        $query = $dbh->prepare($sql);
        $query->bindParam(':email', $email, PDO::PARAM_STR);
        $query->execute();
        $results = $query->fetchAll(PDO::FETCH_OBJ);
        if($query->rowCount() > 0) {
            echo "<span style='color:red'> อีเมลนี้ถูกใช้ไปแล้ว </span>";
            echo "<script>$('#submit').prop('disabled',true);</script>";
        } else {
            echo "<span style='color:green'> อีเมลนี้สามารถใช้งานได้ </span>";
            echo "<script>$('#submit').prop('disabled',false);</script>";
        }
    }
}

// Code for checking FullName availability
if(!empty($_POST["fullname"])) {
    $fullname = $_POST["fullname"];
    if (!preg_match("/^[A-Za-z\s]+$/", $fullname)) {
        echo "<span style='color:red'> รูปแบบชื่อ-นามสกุลไม่ถูกต้อง! </span>";
    } else {
        $sql = "SELECT FullName FROM tblstudents WHERE FullName=:fullname";
        $query = $dbh->prepare($sql);
        $query->bindParam(':fullname', $fullname, PDO::PARAM_STR);
        $query->execute();
        $results = $query->fetchAll(PDO::FETCH_OBJ);
        if($query->rowCount() > 0) {
            echo "<span style='color:red'> ชื่อ-นามสกุลนี้ถูกใช้ไปแล้ว </span>";
            echo "<script>$('#submit').prop('disabled',true);</script>";
        } else {
            echo "<span style='color:green'> ชื่อ-นามสกุลนี้สามารถใช้งานได้ </span>";
            echo "<script>$('#submit').prop('disabled',false);</script>";
        }
    }
}

// Code for checking MobileNumber availability
if(!empty($_POST["mobileno"])) {
    $mobileno = $_POST["mobileno"];
    if (!preg_match("/^\d{10}-\d$/", $mobileno)) {
        echo "<span style='color:red'> รูปแบบรหัสนักศึกษาไม่ถูกต้อง! </span>";
    } else {
        $sql = "SELECT MobileNumber FROM tblstudents WHERE MobileNumber=:mobileno";
        $query = $dbh->prepare($sql);
        $query->bindParam(':mobileno', $mobileno, PDO::PARAM_STR);
        $query->execute();
        $results = $query->fetchAll(PDO::FETCH_OBJ);
        if($query->rowCount() > 0) {
            echo "<span style='color:red'> รหัสนักศึกษานี้ถูกใช้ไปแล้ว </span>";
            echo "<script>$('#submit').prop('disabled',true);</script>";
        } else {
            echo "<span style='color:green'> รหัสนักศึกษานี้สามารถใช้งานได้ </span>";
            echo "<script>$('#submit').prop('disabled',false);</script>";
        }
    }
}
?>
