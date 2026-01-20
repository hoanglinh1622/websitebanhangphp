<?php
// Bypass ngrok browser warning
header('ngrok-skip-browser-warning: true');

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

include 'includes/db.php';

$order_id = $_GET['order_id'] ?? null;
$status = $_GET['status'] ?? $_GET['apptransid'] ?? -1;

// Nếu có apptransid từ ZaloPay nghĩa là thành công
$isSuccess = false;
if (isset($_GET['apptransid']) || isset($_GET['status']) && $_GET['status'] == 1) {
    $isSuccess = true;
}

// Cập nhật đơn hàng nếu thành công
if ($isSuccess && $order_id) {
    $stmt = $conn->prepare("UPDATE orders SET status = 'confirmed', payment_status = 'paid' WHERE id = ?");
    $stmt->execute([$order_id]);
}

// Lấy thông tin đơn hàng
$order = null;
if ($order_id) {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$order_id, $_SESSION['user']['id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kết quả thanh toán ZaloPay</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #0068ff 0%, #0050cc 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            width: 100%;
            background: white;
            border-radius: 24px;
            padding: 48px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .icon-container {
            width: 100px;
            height: 100px;
            margin: 0 auto 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
        }
        .success-icon {
            background: linear-gradient(135deg, #52c41a, #73d13d);
            animation: scaleIn 0.5s ease-out 0.2s both;
        }
        .error-icon {
            background: linear-gradient(135deg, #ff4d4f, #ff7875);
            animation: shake 0.5s ease-out;
        }
        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        h1 {
            font-size: 28px;
            color: #1a1a1a;
            margin-bottom: 12px;
        }
        .message {
            font-size: 16px;
            color: #666;
            margin-bottom: 32px;
        }
        .info-box {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 32px;
            text-align: left;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-size: 14px;
            color: #666;
        }
        .info-value {
            font-size: 15px;
            font-weight: 600;
            color: #1a1a1a;
        }
        .info-value.amount {
            font-size: 24px;
            color: #0068ff;
        }
        .buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .btn {
            flex: 1;
            min-width: 150px;
            padding: 14px 24px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #0068ff, #0050cc);
            color: white;
            box-shadow: 0 4px 16px rgba(0,104,255,0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(0,104,255,0.4);
        }
        .btn-secondary {
            background: white;
            color: #0068ff;
            border: 2px solid #0068ff;
        }
        .btn-secondary:hover {
            background: #0068ff;
            color: white;
        }
        .zalopay-logo {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #f0f0f0;
        }
        .logo-text {
            font-size: 13px;
            color: #999;
            margin-bottom: 8px;
        }
        .logo {
            font-size: 24px;
            font-weight: 800;
        }
        .logo-zalo { color: #0068ff; }
        .logo-pay { color: #00a8ff; }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($isSuccess): ?>
            <!-- Success -->
            <div class="icon-container success-icon">✅</div>
            <h1>Thanh toán thành công!</h1>
            <p class="message">Cảm ơn bạn đã thanh toán qua ZaloPay</p>
            
            <?php if ($order): ?>
            <div class="info-box">
                <div class="info-row">
                    <span class="info-label">Mã đơn hàng</span>
                    <span class="info-value">#<?= htmlspecialchars($order_id) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Người nhận</span>
                    <span class="info-value"><?= htmlspecialchars($order['receiver_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Số tiền</span>
                    <span class="info-value amount"><?= number_format($order['total_price'], 0, ',', '.') ?>đ</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Trạng thái</span>
                    <span class="info-value" style="color: #52c41a;">✓ Đã thanh toán</span>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="buttons">
                <a href="orders.php?id=<?= $order_id ?>" class="btn btn-primary">
                    📋 Xem chi tiết đơn hàng
                </a>
                <a href="index.php" class="btn btn-secondary">
                    🏠 Về trang chủ
                </a>
            </div>
            
        <?php else: ?>
            <!-- Failed -->
            <div class="icon-container error-icon">❌</div>
            <h1>Thanh toán thất bại</h1>
            <p class="message">Đã có lỗi xảy ra trong quá trình thanh toán</p>
            
            <div class="info-box">
                <div class="info-row">
                    <span class="info-label">Mã đơn hàng</span>
                    <span class="info-value">#<?= htmlspecialchars($order_id) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Trạng thái</span>
                    <span class="info-value" style="color: #ff4d4f;">✗ Chưa thanh toán</span>
                </div>
            </div>
            
            <div class="buttons">
                <a href="checkout.php" class="btn btn-primary">
                    🔄 Thử lại
                </a>
                <a href="cart.php" class="btn btn-secondary">
                    🛒 Về giỏ hàng
                </a>
            </div>
        <?php endif; ?>
        
        <div class="zalopay-logo">
            <div class="logo-text">Thanh toán bởi</div>
            <div class="logo">
                <span class="logo-zalo">Zalo</span><span class="logo-pay">Pay</span>
            </div>
        </div>
    </div>

    <script>
        // Tự động cập nhật trạng thái sau 2 giây nếu thành công
        <?php if ($isSuccess && $order_id): ?>
        setTimeout(() => {
            // Có thể redirect tự động hoặc giữ nguyên
            console.log('Payment successful for order #<?= $order_id ?>');
        }, 2000);
        <?php endif; ?>
    </script>
</body>
</html>