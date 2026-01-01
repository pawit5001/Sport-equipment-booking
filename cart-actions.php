<?php
/**
 * Cart Actions - Handle AJAX requests for shopping cart
 * Cart structure: $_SESSION['cart'][equipment_id] = quantity
 */
session_start();
error_reporting(0);
header('Content-Type: application/json; charset=UTF-8');

include('includes/config.php');

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$response = ['success' => false, 'message' => 'Invalid action'];

switch ($action) {
    case 'add':
        $equipmentId = (int)($_POST['equipment_id'] ?? $_POST['id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 1);
        
        if ($equipmentId <= 0 || $quantity <= 0) {
            $response = ['success' => false, 'ok' => false, 'message' => 'ข้อมูลไม่ถูกต้อง', 'error' => 'ข้อมูลไม่ถูกต้อง'];
            break;
        }
        
        // Check equipment availability
        $sql = "SELECT id, EquipmentName, Quantity, IsActive FROM tblequipment WHERE id = :id";
        $query = $dbh->prepare($sql);
        $query->bindParam(':id', $equipmentId, PDO::PARAM_INT);
        $query->execute();
        $equipment = $query->fetch(PDO::FETCH_ASSOC);
        
        if (!$equipment) {
            $response = ['success' => false, 'ok' => false, 'message' => 'ไม่พบอุปกรณ์', 'error' => 'ไม่พบอุปกรณ์'];
            break;
        }
        
        if ($equipment['IsActive'] != 1) {
            $response = ['success' => false, 'ok' => false, 'message' => 'อุปกรณ์นี้ปิดการยืมชั่วคราว', 'error' => 'อุปกรณ์นี้ปิดการยืมชั่วคราว'];
            break;
        }
        
        // Check current cart quantity for this item
        $currentInCart = isset($_SESSION['cart'][$equipmentId]) ? $_SESSION['cart'][$equipmentId] : 0;
        $totalRequested = $currentInCart + $quantity;
        
        if ($totalRequested > $equipment['Quantity']) {
            $response = ['success' => false, 'ok' => false, 'message' => 'จำนวนไม่เพียงพอ (คงเหลือ ' . $equipment['Quantity'] . ' ชิ้น)', 'error' => 'จำนวนไม่เพียงพอ (คงเหลือ ' . $equipment['Quantity'] . ' ชิ้น)'];
            break;
        }
        
        // Add to cart (simple: id => quantity)
        if (isset($_SESSION['cart'][$equipmentId])) {
            $_SESSION['cart'][$equipmentId] += $quantity;
        } else {
            $_SESSION['cart'][$equipmentId] = $quantity;
        }
        
        $response = [
            'success' => true,
            'ok' => true,
            'msg' => 'เพิ่ม "' . $equipment['EquipmentName'] . '" ลงตะกร้าแล้ว',
            'message' => 'เพิ่มลงตะกร้าแล้ว',
            'count' => getCartCount(),
            'cartCount' => getCartCount()
        ];
        break;
        
    case 'update':
        $equipmentId = (int)($_POST['equipment_id'] ?? $_POST['id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
        
        if ($equipmentId <= 0) {
            $response = ['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง'];
            break;
        }
        
        if ($quantity <= 0) {
            // Remove from cart
            unset($_SESSION['cart'][$equipmentId]);
            $response = [
                'success' => true,
                'message' => 'ลบรายการออกจากตะกร้าแล้ว',
                'count' => getCartCount()
            ];
            break;
        }
        
        // Check availability
        $sql = "SELECT Quantity FROM tblequipment WHERE id = :id";
        $query = $dbh->prepare($sql);
        $query->bindParam(':id', $equipmentId, PDO::PARAM_INT);
        $query->execute();
        $availableQty = $query->fetchColumn();
        
        if ($quantity > $availableQty) {
            $response = ['success' => false, 'message' => 'จำนวนไม่เพียงพอ (คงเหลือ ' . $availableQty . ' ชิ้น)'];
            break;
        }
        
        $_SESSION['cart'][$equipmentId] = $quantity;
        
        $response = [
            'success' => true,
            'message' => 'อัปเดตจำนวนแล้ว',
            'count' => getCartCount()
        ];
        break;
        
    case 'remove':
        $equipmentId = (int)($_POST['equipment_id'] ?? $_POST['id'] ?? 0);
        
        if (isset($_SESSION['cart'][$equipmentId])) {
            unset($_SESSION['cart'][$equipmentId]);
            $response = [
                'success' => true,
                'message' => 'ลบออกจากตะกร้าแล้ว',
                'count' => getCartCount()
            ];
        } else {
            $response = ['success' => false, 'message' => 'ไม่พบรายการในตะกร้า'];
        }
        break;
        
    case 'clear':
        $_SESSION['cart'] = [];
        $response = [
            'success' => true,
            'message' => 'ล้างตะกร้าแล้ว',
            'count' => 0
        ];
        break;
        
    case 'get':
        $response = [
            'success' => true,
            'cart' => $_SESSION['cart'],
            'count' => getCartCount()
        ];
        break;
        
    case 'count':
        $response = [
            'success' => true,
            'ok' => true,
            'count' => getCartCount(),
            'cartCount' => getCartCount()
        ];
        break;
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

// Helper function - cart stores id => quantity
function getCartCount() {
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        return array_sum($_SESSION['cart']);
    }
    return 0;
}
