<?php
session_start();
include "includes/db.php";

// Kiểm tra đăng nhập
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];

// Lấy ID đơn hàng từ URL
$order_id = $_GET['id'] ?? 0;
if ($order_id <= 0) {
    die("❌ ID đơn hàng không hợp lệ.");
}

// Truy vấn đơn hàng
if ($user['role'] === 'admin') {
    // Admin xem tất cả đơn hàng
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
} else {
    // User chỉ xem đơn hàng của mình
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$order_id, $user['id']]);
}

$order = $stmt->fetch();
if (!$order) {
    die("❌ Không tìm thấy đơn hàng hoặc bạn không có quyền truy cập.");
}

// Tính tổng tiền bao gồm VAT
$subtotal = $order['total_price']; // Giá trị sản phẩm (chưa có VAT)
$vat_amount = $order['vat_amount'] ?? 0; // Thuế VAT
$total_payment = $subtotal + $vat_amount; // Tổng tiền khách đã trả
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết đơn hàng #<?= $order['id'] ?></title>
    <style>
        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px; 
            margin: 0;
        }
        .container { 
            max-width: 650px; 
            margin: auto; 
            background: #fff; 
            padding: 30px; 
            border-radius: 16px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.2); 
        }
        .title { 
            font-size: 24px; 
            margin-bottom: 20px; 
            text-align: center; 
            color: #333;
            font-weight: bold;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #667eea;
            margin: 20px 0 10px 0;
            padding-left: 10px;
            border-left: 4px solid #667eea;
        }
        .row { 
            padding: 12px 0; 
            border-bottom: 1px solid #f0f0f0; 
            display: flex; 
            justify-content: space-between;
            align-items: center;
        }
        .row:last-child {
            border-bottom: none;
        }
        .label { 
            font-weight: 600; 
            color: #555; 
            flex: 1;
        }
        .value { 
            color: #222; 
            text-align: right;
            flex: 1;
        }
        .subtotal-row {
            background: #f8f9fa;
            padding: 12px;
            margin: 10px 0;
            border-radius: 8px;
        }
        .vat-row {
            background: #fff3cd;
            padding: 12px;
            margin: 10px 0;
            border-radius: 8px;
            border: 1px solid #ffc107;
        }
        .total-row {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px;
            margin: 15px 0;
            border-radius: 10px;
            font-size: 18px;
        }
        .total-row .label,
        .total-row .value {
            color: white;
            font-weight: bold;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-confirmed {
            background: #d1ecf1;
            color: #0c5460;
        }
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        .btn { 
            display: inline-block; 
            padding: 12px 30px; 
            background: linear-gradient(90deg, #667eea, #764ba2);
            color: white; 
            text-decoration: none; 
            border-radius: 8px; 
            margin-top: 25px; 
            text-align: center;
            font-weight: 600;
            transition: transform 0.2s;
            cursor: pointer;
            border: none;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .info-note {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 12px;
            margin: 15px 0;
            border-radius: 6px;
            font-size: 13px;
            color: #555;
        }
        @media (max-width: 600px) {
            .container {
                padding: 20px;
            }
            .row {
                flex-direction: column;
                align-items: flex-start;
            }
            .value {
                text-align: left;
                margin-top: 5px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="title">Chi tiết đơn hàng #<?= $order['id'] ?></div>

    <div class="section-title">📦 Thông tin đơn hàng</div>
    
    <div class="subtotal-row row">
        <span class="label">Giá trị đơn hàng:</span>
        <span class="value"><?= number_format($subtotal, 0, ',', '.') ?> VNĐ</span>
    </div>

    <?php if ($vat_amount > 0): ?>
    <div class="vat-row row">
        <span class="label">💰 Thuế VAT (10%):</span>
        <span class="value"><?= number_format($vat_amount, 0, ',', '.') ?> VNĐ</span>
    </div>
    <?php endif; ?>

    <div class="total-row row">
        <span class="label">Tổng thanh toán:</span>
        <span class="value"><?= number_format($total_payment, 0, ',', '.') ?> VNĐ</span>
    </div>

    <?php if ($vat_amount > 0): ?>
    <div class="info-note">
        <strong>📌 Lưu ý:</strong> Tổng thanh toán đã bao gồm thuế VAT <?= number_format($vat_amount, 0, ',', '.') ?> VNĐ. Giá trị đơn hàng chưa VAT là <?= number_format($subtotal, 0, ',', '.') ?> VNĐ.
    </div>
    <?php endif; ?>

    <div class="section-title">👤 Thông tin người nhận</div>
    
    <div class="row">
        <span class="label">Người nhận:</span>
        <span class="value"><?= htmlspecialchars($order['receiver_name']) ?></span>
    </div>
    <div class="row">
        <span class="label">Số điện thoại:</span>
        <span class="value"><?= htmlspecialchars($order['phone']) ?></span>
    </div>
    <div class="row">
        <span class="label">Địa chỉ giao hàng:</span>
        <span class="value"><?= htmlspecialchars($order['shipping_address']) ?></span>
    </div>
    <?php if (!empty($order['note'])): ?>
    <div class="row">
        <span class="label">Ghi chú:</span>
        <span class="value"><?= htmlspecialchars($order['note']) ?></span>
    </div>
    <?php endif; ?>

    <div class="section-title">📊 Trạng thái & Thanh toán</div>
    
    <div class="row">
        <span class="label">Trạng thái đơn hàng:</span>
        <span class="value">
            <span class="status-badge status-<?= htmlspecialchars($order['status']) ?>">
                <?= htmlspecialchars($order['status']) ?>
            </span>
        </span>
    </div>
    <div class="row">
        <span class="label">Phương thức thanh toán:</span>
        <span class="value"><?= strtoupper(htmlspecialchars($order['payment_method'])) ?></span>
    </div>
    <div class="row">
        <span class="label">Trạng thái thanh toán:</span>
        <span class="value">
            <span class="status-badge status-<?= htmlspecialchars($order['payment_status']) ?>">
                <?= htmlspecialchars($order['payment_status']) ?>
            </span>
        </span>
    </div>
    <div class="row">
        <span class="label">Ngày tạo:</span>
        <span class="value"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></span>
    </div>

    <?php if (!empty($order['momo_trans_id'])): ?>
    <div class="row">
        <span class="label">Mã giao dịch MoMo:</span>
        <span class="value"><?= htmlspecialchars($order['momo_trans_id']) ?></span>
    </div>
    <?php endif; ?>

    <?php if (!empty($order['zalopay_trans_id'])): ?>
    <div class="row">
        <span class="label">Mã giao dịch ZaloPay:</span>
        <span class="value"><?= htmlspecialchars($order['zalopay_trans_id']) ?></span>
    </div>
    <?php endif; ?>

    <div style="text-align: center;">
        <button class="btn" onclick="window.history.back()">
            ← Quay lại
        </button>
    </div>
</div>

</body>
</html>