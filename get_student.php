<?php 
header('Content-Type: text/html; charset=utf-8');
require_once("includes/config.php");

if (!empty($_POST["mobileno"])) {
    $mobileno = strtoupper($_POST["mobileno"]);

    $sql = "SELECT Name,Role FROM tblmembers WHERE StudentID=:mobileno";
    $query = $dbh->prepare($sql);
    $query->bindParam(':mobileno', $mobileno, PDO::PARAM_STR);
    $query->execute();
    $results = $query->fetchAll(PDO::FETCH_OBJ);
    $cnt = 1;
    
    if ($query->rowCount() > 0) {
        foreach ($results as $result) {
            if ($result->Role != 'student') {
                echo "<span style='color:red'> บัญชีถูกล็อก </span>" . "<br />";
                echo "<b>Name-</b>" . htmlentities($result->Name);
                echo "<script>$('#submit').prop('disabled',true);</script>";
            } else {
                echo htmlentities($result->Name);
                echo "<script>$('#submit').prop('disabled',false);</script>";
            }
        }
    } else {
        echo "<span style='color:red'> ไม่พบ Student Number นี้ กรุณาลองใหม่อีกครั้ง</span>";
        echo "<script>$('#submit').prop('disabled',true);</script>";
    }
}
?>
