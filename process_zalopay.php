<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

include 'includes/db.php';
require_once 'zalopay_config.php';

$order_id = $_GET['order_id'] ?? null;
$amount_from_url = $_GET['amount'] ?? 0; // Lấy amount từ URL

if (!$order_id) {
    die("Thiếu thông tin đơn hàng");
}

// Lấy thông tin đơn hàng
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $_SESSION['user']['id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Đơn hàng không tồn tại");
}

// QUAN TRỌNG: Tính tổng tiền bao gồm VAT
if ($amount_from_url > 0) {
    // Ưu tiên dùng amount từ URL (đã tính VAT từ checkout.php)
    $amount = (int)$amount_from_url;
} else {
    // Nếu không có amount từ URL, tính lại từ database
    $subtotal = (int)$order['total_price']; // Giá trị sản phẩm (chưa có VAT)
    $vat_amount = (int)($order['vat_amount'] ?? 0); // Thuế VAT
    $amount = $subtotal + $vat_amount; // Tổng tiền phải trả (bao gồm VAT)
}

// Embed data: CHỈ CÓ Ở ĐÂY MỚI ĐƯỢC ZALO PAY ĐỌC REDIRECT URL
$embed_data = [
    "order_id" => $order_id,
    "redirecturl" => ZaloPayConfig::REDIRECT_URL . "?order_id=" . $order_id
];

// Tạo tham số gửi ZaloPay
$config = [
    "app_id" => ZaloPayConfig::APP_ID,
    "app_user" => "user_" . $_SESSION['user']['id'],
    "app_time" => round(microtime(true) * 1000),
    "app_trans_id" => date("ymd") . "_" . $order_id . "_" . time(),

    // ĐÚNG CHUẨN
    "embed_data" => json_encode($embed_data),

    "item" => json_encode([
        [
            "itemid" => "item_" . $order_id,
            "itemname" => "Đơn hàng #" . $order_id,
            "itemprice" => $amount, // ← Dùng số tiền đã bao gồm VAT
            "itemquantity" => 1
        ]
    ]),

    "amount" => $amount, // ← Số tiền đã bao gồm VAT
    "description" => "Thanh toán đơn hàng #" . $order_id . " - Moto ABC (bao gồm VAT)",
    "bank_code" => "",
    "callback_url" => ZaloPayConfig::CALLBACK_URL
];

// Tạo MAC
$data_mac = $config["app_id"] . "|" . $config["app_trans_id"] . "|" . $config["app_user"] . "|" .
            $config["amount"] . "|" . $config["app_time"] . "|" . 
            $config["embed_data"] . "|" . $config["item"];

$config["mac"] = hash_hmac("sha256", $data_mac, ZaloPayConfig::KEY1);

// Gọi API ZaloPay
$context = stream_context_create([
    "http" => [
        "header" => "Content-type: application/x-www-form-urlencoded\r\n",
        "method" => "POST",
        "content" => http_build_query($config)
    ]
]);

$response = file_get_contents(ZaloPayConfig::ENDPOINT, false, $context);
$result = json_decode($response, true);

// Xử lý phản hồi
if ($result['return_code'] == 1) {

    // Lưu giao dịch
    $stmt = $conn->prepare("UPDATE orders SET zalopay_trans_id = ? WHERE id = ?");
    $stmt->execute([$config["app_trans_id"], $order_id]);

    // Redirect sang trang thanh toán ZaloPay
    header("Location: " . $result['order_url']);
    exit();
}

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Lỗi thanh toán</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #0068ff, #0080ff);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .error-container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .error-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        h2 {
            color: #dc3545;
            margin-bottom: 15px;
        }
        .error-message {
            color: #666;
            margin-bottom: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .amount-info {
            color: #0068ff;
            font-weight: bold;
            margin: 15px 0;
            font-size: 18px;
        }
        .btn-back {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 30px;
            background: linear-gradient(135deg, #0068ff, #0080ff);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn-back:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class='error-container'>
        <div class='error-icon'>❌</div>
        <h2>Không thể tạo thanh toán</h2>
        <div class='error-message'>" . ($result['return_message'] ?? 'Lỗi không xác định') . "</div>
        <div class='amount-info'>Số tiền thanh toán: " . number_format($amount, 0, ',', '.') . " VNĐ</div>
        <a href='checkout.php' class='btn-back'>← Quay lại giỏ hàng</a>
    </div>
</body>
</html>";
?>