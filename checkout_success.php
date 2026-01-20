<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

include 'includes/db.php';
$user_id = $_SESSION['user']['id'];

// Lấy order_id từ URL (nếu có)
$order_id = $_GET['order_id'] ?? null;

// Nếu không có order_id, lấy đơn hàng mới nhất của user
if (!$order_id) {
    $stmt = $conn->prepare("SELECT id FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $order_id = $result['id'] ?? null;
}

// Lấy thông tin đơn hàng
$order = null;
$order_items = [];

if ($order_id) {
    $stmt = $conn->prepare("
        SELECT o.*, u.email, u.full_name 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.id = ? AND o.user_id = ?
    ");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        // Lấy chi tiết sản phẩm trong đơn hàng
        $stmt = $conn->prepare("
            SELECT oi.*, p.name, p.image 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Nếu không tìm thấy đơn hàng, redirect về trang chủ
if (!$order) {
    header('Location: index.php');
    exit();
}

// Mapping payment method
$payment_method_text = [
    'cod' => 'Thanh toán khi nhận hàng (COD)',
    'momo' => 'Ví MoMo',
    'zalopay' => 'ZaloPay'
];

// Mapping trạng thái
$status_text = [
    'pending' => 'Đang chờ xử lý',
    'confirmed' => 'Đã xác nhận',
    'shipping' => 'Đang giao hàng',
    'completed' => 'Hoàn thành',
    'cancelled' => 'Đã hủy'
];

$status_color = [
    'pending' => '#ffa726',
    'confirmed' => '#29b6f6',
    'shipping' => '#ab47bc',
    'completed' => '#66bb6a',
    'cancelled' => '#ef5350'
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt hàng thành công - Moto ABC</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 700px;
            width: 100%;
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #66bb6a, #43a047);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }
        .success-icon {
            width: 100px;
            height: 100px;
            background: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            margin-bottom: 20px;
            animation: scaleIn 0.5s ease-out;
        }
        @keyframes scaleIn {
            0% { transform: scale(0); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .header p {
            font-size: 16px;
            opacity: 0.95;
        }
        .content {
            padding: 30px;
        }
        .order-info {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
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
            text-align: right;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            color: white;
        }
        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin: 25px 0 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f2f4;
        }
        .product-item {
            display: flex;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 12px;
        }
        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            flex-shrink: 0;
        }
        .product-details {
            flex: 1;
        }
        .product-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        .product-price {
            color: #ff5722;
            font-weight: 700;
        }
        .product-quantity {
            color: #666;
            font-size: 14px;
        }
        .total-section {
            background: linear-gradient(135deg, #fff5f5, #ffe8e8);
            padding: 20px;
            border-radius: 12px;
            margin-top: 20px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 16px;
        }
        .total-row.final {
            font-size: 20px;
            font-weight: 700;
            color: #ff5722;
            border-top: 2px dashed #ff5722;
            padding-top: 15px;
            margin-top: 10px;
        }
        .actions {
            display: flex;
            gap: 12px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        .btn {
            flex: 1;
            min-width: 150px;
            padding: 14px 24px;
            border-radius: 10px;
            text-align: center;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }
        .btn-secondary:hover {
            background: #667eea;
            color: white;
        }
        .note-box {
            background: #fff9e6;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-top: 20px;
            border-radius: 8px;
        }
        .note-box h4 {
            color: #ff9800;
            margin-bottom: 8px;
            font-size: 15px;
        }
        .note-box p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }
        @media (max-width: 600px) {
            .header h1 { font-size: 22px; }
            .actions { flex-direction: column; }
            .btn { min-width: 100%; }
            .info-row { flex-direction: column; gap: 5px; }
            .value { text-align: left; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="success-icon">✅</div>
            <h1>Đặt hàng thành công!</h1>
            <p>Cảm ơn bạn đã tin tưởng Moto ABC</p>
        </div>

        <div class="content">
            <div class="order-info">
                <div class="info-row">
                    <span class="label">📦 Mã đơn hàng:</span>
                    <span class="value"><strong>#<?= htmlspecialchars($order_id) ?></strong></span>
                </div>
                <div class="info-row">
                    <span class="label">📅 Ngày đặt:</span>
                    <span class="value"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">👤 Người nhận:</span>
                    <span class="value"><?= htmlspecialchars($order['receiver_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">📞 Số điện thoại:</span>
                    <span class="value"><?= htmlspecialchars($order['phone']) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">📍 Địa chỉ giao hàng:</span>
                    <span class="value"><?= htmlspecialchars($order['shipping_address']) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">💳 Phương thức thanh toán:</span>
                    <span class="value"><?= $payment_method_text[$order['payment_method']] ?? $order['payment_method'] ?></span>
                </div>
                <div class="info-row">
                    <span class="label">📊 Trạng thái:</span>
                    <span class="value">
                        <span class="status-badge" style="background: <?= $status_color[$order['status']] ?? '#999' ?>">
                            <?= $status_text[$order['status']] ?? $order['status'] ?>
                        </span>
                    </span>
                </div>
                <?php if (!empty($order['note'])): ?>
                <div class="info-row">
                    <span class="label">📝 Ghi chú:</span>
                    <span class="value"><?= htmlspecialchars($order['note']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="section-title">🛍️ Chi tiết đơn hàng</div>
            
            <?php foreach ($order_items as $item): ?>
            <div class="product-item">
                <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="product-image">
                <div class="product-details">
                    <div class="product-name"><?= htmlspecialchars($item['name']) ?></div>
                    <div class="product-quantity">Số lượng: <?= (int)$item['quantity'] ?></div>
                    <div class="product-price"><?= number_format($item['price'], 0, ',', '.') ?> VNĐ</div>
                </div>
                <div style="text-align: right; align-self: center;">
                    <div class="product-price" style="font-size: 18px;">
                        <?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?> VNĐ
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="total-section">
                <div class="total-row">
                    <span>Tạm tính:</span>
                    <span><?= number_format($order['total_price'], 0, ',', '.') ?> VNĐ</span>
                </div>
                <div class="total-row">
                    <span>Phí vận chuyển:</span>
                    <span style="color: #28a745;">Miễn phí</span>
                </div>
                <div class="total-row final">
                    <span>Tổng cộng:</span>
                    <span><?= number_format($order['total_price'], 0, ',', '.') ?> VNĐ</span>
                </div>
            </div>

            <?php if ($order['payment_method'] === 'cod'): ?>
            <div class="note-box">
                <h4>💵 Lưu ý thanh toán COD</h4>
                <p>
                    • Vui lòng chuẩn bị <strong><?= number_format($order['total_price'], 0, ',', '.') ?> VNĐ</strong> tiền mặt khi nhận hàng.<br>
                    • Kiểm tra kỹ sản phẩm trước khi thanh toán.<br>
                    • Nếu có vấn đề, vui lòng liên hệ ngay với shipper hoặc hotline của chúng tôi.
                </p>
            </div>
            <?php endif; ?>

            <div class="actions">
                <a href="order_details.php?id=<?= $order_id ?>" class="btn btn-primary">
                    📄 Xem chi tiết đơn hàng
                </a>
                <a href="index.php" class="btn btn-secondary">
                    🏠 Về trang chủ
                </a>
            </div>

            <div style="text-align: center; margin-top: 25px; padding-top: 25px; border-top: 1px solid #e9ecef;">
                <p style="color: #666; font-size: 14px;">
                    📧 Email xác nhận đã được gửi đến: <strong><?= htmlspecialchars($order['email'] ?? '') ?></strong>
                </p>
                <p style="color: #999; font-size: 13px; margin-top: 8px;">
                    Mọi thắc mắc vui lòng liên hệ: <strong style="color: #667eea;">1900-xxxx</strong>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Tự động scroll lên top
        window.scrollTo(0, 0);
        
        // Có thể thêm confetti effect hoặc animation khác
        console.log('🎉 Đặt hàng thành công! Order ID: <?= $order_id ?>');
    </script>
</body>
</html