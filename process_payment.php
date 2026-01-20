<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

include 'includes/db.php';
require_once 'momo_config.php';

$order_id = $_GET['order_id'] ?? null;
$method = $_GET['method'] ?? null;
$amount_from_url = $_GET['amount'] ?? 0; // Lấy amount từ URL

if (!$order_id || $method !== 'momo') {
    die("Thông tin thanh toán không hợp lệ");
}

// Lấy thông tin đơn hàng
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $_SESSION['user']['id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Đơn hàng không tồn tại");
}

// Lấy loại thanh toán
$momo_payment_type = $_GET['payment_type'] ?? 'atm'; // Mặc định dùng thẻ ATM

$requestTypeMapping = [
    'wallet' => 'captureWallet',
    'atm' => 'payWithATM',
    'credit_card' => 'payWithCC'
];

$requestType = $requestTypeMapping[$momo_payment_type] ?? 'payWithATM';

// Tạo thanh toán MoMo
$orderId = "ORDER_" . $order_id . "_" . time();

// QUAN TRỌNG: Tính tổng tiền bao gồm VAT
if ($amount_from_url > 0) {
    // Ưu tiên dùng amount từ URL (đã tính VAT từ checkout.php)
    $amount = (int)$amount_from_url;
} else {
    // Nếu không có amount từ URL, tính lại từ database
    $subtotal = (int)$order['total_price']; // Giá trị sản phẩm (chưa có VAT)
    $vat_amount = (int)($order['vat_amount'] ?? 0); // Thuế VAT
    $amount = $subtotal + $vat_amount; // Tổng tiền phải trả
}

$orderInfo = "Thanh toan don hang #" . $order_id;

$partnerCode = MoMoConfig::PARTNER_CODE;
$accessKey = MoMoConfig::ACCESS_KEY;
$secretKey = MoMoConfig::SECRET_KEY;
$endpoint = MoMoConfig::ENDPOINT;
$returnUrl = MoMoConfig::RETURN_URL . "?order_id=" . $order_id;
$notifyUrl = MoMoConfig::NOTIFY_URL;

$requestId = time() . "";
$extraData = base64_encode(json_encode(['order_id' => $order_id]));

// Tạo signature
$rawHash = "accessKey=" . $accessKey . 
           "&amount=" . $amount . 
           "&extraData=" . $extraData . 
           "&ipnUrl=" . $notifyUrl . 
           "&orderId=" . $orderId . 
           "&orderInfo=" . $orderInfo . 
           "&partnerCode=" . $partnerCode . 
           "&redirectUrl=" . $returnUrl . 
           "&requestId=" . $requestId . 
           "&requestType=" . $requestType;

$signature = hash_hmac("sha256", $rawHash, $secretKey);

$data = array(
    'partnerCode' => $partnerCode,
    'partnerName' => "Moto ABC Shop",
    'storeId' => "MotoABC_Store",
    'requestId' => $requestId,
    'amount' => $amount, // ← Số tiền đã bao gồm VAT
    'orderId' => $orderId,
    'orderInfo' => $orderInfo,
    'redirectUrl' => $returnUrl,
    'ipnUrl' => $notifyUrl,
    'lang' => 'vi',
    'extraData' => $extraData,
    'requestType' => $requestType,
    'signature' => $signature
);

function execPostRequest($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data))
    );
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

$result = execPostRequest($endpoint, json_encode($data));
$jsonResult = json_decode($result, true);

if (isset($jsonResult['payUrl'])) {
    header('Location: ' . $jsonResult['payUrl']);
    exit();
} else {
    echo "<!DOCTYPE html>
    <html>
    <head><meta charset='UTF-8'><title>Lỗi</title></head>
    <body style='font-family: Arial; text-align: center; padding: 50px;'>
        <h2 style='color: red;'>❌ Không thể tạo thanh toán</h2>
        <p>" . ($jsonResult['message'] ?? 'Lỗi không xác định') . "</p>
        <p style='color: #666; font-size: 14px;'>Số tiền thanh toán: " . number_format($amount, 0, ',', '.') . " VNĐ</p>
        <a href='checkout.php' style='display: inline-block; margin-top: 20px; padding: 10px 20px; background: #ff5722; color: white; text-decoration: none; border-radius: 5px;'>Quay lại</a>
    </body>
    </html>";
}
?>