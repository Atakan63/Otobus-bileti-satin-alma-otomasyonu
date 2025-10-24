<?php
session_start();
require_once 'functions.php';


header('Content-Type: application/json');


if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'user') {
    echo json_encode(['success' => false, 'message' => 'Kupon kullanmak için giriş yapmalısınız.']);
    exit;
}


$data = json_decode(file_get_contents('php://input'), true);
$coupon_code = $data['code'] ?? '';

if (empty($coupon_code)) {
    echo json_encode(['success' => false, 'message' => 'Kupon kodu girilmedi.']);
    exit;
}


$coupon = findCoupon($pdo, $coupon_code);

if ($coupon) {
    
    echo json_encode([
        'success' => true, 
        'discount' => $coupon['discount'] 
    ]);
} else {
    
    echo json_encode(['success' => false, 'message' => 'Geçersiz veya süresi dolmuş kupon kodu.']);
}
?>