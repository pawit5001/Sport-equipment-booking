<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
error_reporting(0);
include('includes/config.php');

$response = array('success' => false, 'message' => '');

if(!isset($_SESSION['alogin']) || strlen($_SESSION['alogin']) == 0) {
    $response['message'] = 'กรุณาเข้าสู่ระบบ';
    echo json_encode($response);
    exit();
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $id = intval($_POST['id']);
        $status = intval($_POST['status']);
        
        // Validate status
        if($status !== 0 && $status !== 1) {
            $response['message'] = 'ข้อมูลสถานะไม่ถูกต้อง';
            echo json_encode($response);
            exit();
        }
        
        // Check if user exists and is not admin
        $checkSql = "SELECT Role FROM tblmembers WHERE id=:id";
        $checkQuery = $dbh->prepare($checkSql);
        $checkQuery->bindParam(':id', $id, PDO::PARAM_INT);
        $checkQuery->execute();
        $result = $checkQuery->fetch(PDO::FETCH_OBJ);
        
        if(!$result) {
            $response['message'] = 'ไม่พบข้อมูลสมาชิก';
            echo json_encode($response);
            exit();
        }
        
        if($result->Role == 'admin') {
            $response['message'] = 'ไม่สามารถแก้ไขสถานะของผู้ดูแลระบบได้';
            echo json_encode($response);
            exit();
        }
        
        // Update status
        $sql = "UPDATE tblmembers SET Status=:status, UpdationDate=NOW() WHERE id=:id";
        $query = $dbh->prepare($sql);
        $query->bindParam(':status', $status, PDO::PARAM_INT);
        $query->bindParam(':id', $id, PDO::PARAM_INT);
        $query->execute();
        
        $response['success'] = true;
        $response['message'] = 'บันทึกข้อมูลเรียบร้อยแล้ว';
        
    } catch(PDOException $e) {
        $response['message'] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'คำขอไม่ถูกต้อง';
}

echo json_encode($response);
?>
