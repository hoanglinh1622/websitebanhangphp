<?php
require_once 'includes/db.php';
require_once 'momo_config.php';

// Nhận dữ liệu từ MoMo
$data = file_get_contents('php://input');
$jsonData = json_decode($data, true);

// Lưu log để debug
$logFile = 'logs/momo_ipn_' . date('Y-m-d') . '.log';
if (!file_exists('logs')) {
    mkdir('logs', 0777, true);
}
file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $data . "\n", FILE_APPEND);

if ($jsonData) {
    $partnerCode = $jsonData['partnerCode'] ?? '';
    $orderId = $jsonData['orderId'] ?? '';
    $requestId = $jsonData['requestId'] ?? '';
    $amount = $jsonData['amount'] ?? 0;
    $orderInfo = $jsonData['orderInfo'] ?? '';
    $orderType = $jsonData['orderType'] ?? '';
    $transId = $jsonData['transId'] ?? '';
    $resultCode = $jsonData['resultCode'] ?? -1;
    $message = $jsonData['message'] ?? '';
    $payType = $jsonData['payType'] ?? '';
    $responseTime = $jsonData['responseTime'] ?? '';
    $extraData = $jsonData['extraData'] ?? '';
    $signature = $jsonData['signature'] ?? '';
    
    // Xác thực chữ ký
    $secretKey = MoMoConfig::SECRET_KEY;
    $accessKey = MoMoConfig::ACCESS_KEY;
    
    $rawHash = "accessKey=" . $accessKey .
               "&amount=" . $amount .
               "&extraData=" . $extraData .
               "&message=" . $message .
               "&orderId=" . $orderId .
               "&orderInfo=" . $orderInfo .
               "&orderType=" . $orderType .
               "&partnerCode=" . $partnerCode .
               "&payType=" . $payType .
               "&requestId=" . $requestId .
               "&responseTime=" . $responseTime .
               "&resultCode=" . $resultCode .
               "&transId=" . $transId;
    
    $expectedSignature = hash_hmac("sha256", $rawHash, $secretKey);
    
    if ($signature === $expectedSignature) {
        // Chữ ký hợp lệ
        if ($resultCode == 0) {
            // Thanh toán thành công
            // Lấy order_id từ extraData
            $extraDataDecoded = json_decode(base64_decode($extraData), true);
            $order_id = $extraDataDecoded['order_id'] ?? null;
            
            if ($order_id) {
                // Cập nhật trạng thái đơn hàng
                $stmt = $conn->prepare("UPDATE orders SET payment_status = 'paid', momo_trans_id = ?, status = 'confirmed' WHERE id = ?");
                $stmt->execute([$transId, $order_id]);
                
                // Gửi email xác nhận đơn hàng (tùy chọn)
                // sendOrderConfirmationEmail($order_id);
            }
        }
    }
    
    // Trả về response cho MoMo
    http_response_code(200);
    echo json_encode(['status' => 'success']);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
}
?>