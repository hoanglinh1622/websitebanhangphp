<?php
header("ngrok-skip-browser-warning: true");

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

include 'includes/db.php';
require_once 'momo_config.php';

$order_id = $_GET['order_id'] ?? null;
$partnerCode = $_GET['partnerCode'] ?? null;
$orderId = $_GET['orderId'] ?? null;
$requestId = $_GET['requestId'] ?? null;
$amount = $_GET['amount'] ?? null;
$orderInfo = $_GET['orderInfo'] ?? null;
$orderType = $_GET['orderType'] ?? null;
$transId = $_GET['transId'] ?? null;
$resultCode = $_GET['resultCode'] ?? null;
$message = $_GET['message'] ?? null;
$payType = $_GET['payType'] ?? null;
$responseTime = $_GET['responseTime'] ?? null;
$extraData = $_GET['extraData'] ?? null;
$signature = $_GET['signature'] ?? null;

// Xác thực chữ ký từ MoMo
$secretKey = MoMoConfig::SECRET_KEY;
$rawHash = "accessKey=" . MoMoConfig::ACCESS_KEY .
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

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kết quả thanh toán - Moto ABC</title>
    <style>
        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: linear-gradient(180deg, #fafafa, #f5f7fb);
            margin: 0;
            padding: 30px 12px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(10, 10, 20, 0.08);
            text-align: center;
        }
        .success-icon {
            font-size: 80px;
            color: #28a745;
            margin-bottom: 20px;
        }
        .error-icon {
            font-size: 80px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .label {
            font-weight: 600;
            color: #666;
        }
        .value {
            color: #333;
            font-weight: 500;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #ff5722;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin: 10px 5px;
            font-weight: 600;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #e64a19;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($resultCode == 0 && $signature === $expectedSignature): ?>
            <!-- Thanh toán thành công -->
            <div class="success-icon">✅</div>
            <h1>Thanh toán thành công!</h1>
            <p>Cảm ơn bạn đã đặt hàng tại Moto ABC</p>
            
            <div class="info">
                <div class="info-row">
                    <span class="label">Mã đơn hàng:</span>
                    <span class="value">#<?= htmlspecialchars($order_id) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Mã giao dịch MoMo:</span>
                    <span class="value"><?= htmlspecialchars($transId) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Số tiền:</span>
                    <span class="value"><?= number_format($amount, 0, ',', '.') ?> VNĐ</span>
                </div>
                <div class="info-row">
                    <span class="label">Thời gian:</span>
                    <span class="value"><?= date('d/m/Y H:i:s', $responseTime/1000) ?></span>
                </div>
            </div>
            
            <?php
            // Cập nhật trạng thái đơn hàng
            if ($order_id) {
                $stmt = $conn->prepare("UPDATE orders SET payment_status = 'paid', momo_trans_id = ?, status = 'confirmed' WHERE id = ?");
                $stmt->execute([$transId, $order_id]);
            }
            ?>
            
            <a href="orders.php?id=<?= $order_id ?>" class="btn">Xem chi tiết đơn hàng</a>
            <a href="index.php" class="btn btn-secondary">Tiếp tục mua sắm</a>
            
        <?php else: ?>
            <!-- Thanh toán thất bại -->
            <div class="error-icon">❌</div>
            <h1>Thanh toán thất bại</h1>
            <p><?= htmlspecialchars($message) ?></p>
            
            <div class="info">
                <div class="info-row">
                    <span class="label">Mã lỗi:</span>
                    <span class="value"><?= htmlspecialchars($resultCode) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Mã đơn hàng:</span>
                    <span class="value">#<?= htmlspecialchars($order_id) ?></span>
                </div>
            </div>
            
            <?php
            // Cập nhật trạng thái đơn hàng
            if ($order_id) {
                $stmt = $conn->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ?");
                $stmt->execute([$order_id]);
            }
            ?>
            
            <a href="checkout.php" class="btn">Thử lại</a>
            <a href="cart.php" class="btn btn-secondary">Quay lại giỏ hàng</a>
        <?php endif; ?>
    </div>
</body>
</html>