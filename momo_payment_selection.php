<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$order_id = $_GET['order_id'] ?? 0;
$amount = $_GET['amount'] ?? 0;
if (!$order_id) {
    die("Thiếu thông tin đơn hàng");
}

include 'includes/db.php';
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $_SESSION['user']['id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Đơn hàng không tồn tại");
}

// Tính tổng tiền phải trả (bao gồm VAT)
$subtotal = $order['total_price']; // Giá trị sản phẩm (không có VAT)
$vat_amount = $order['vat_amount'] ?? 0; // Thuế VAT
$total_payment = $subtotal + $vat_amount; // Tổng tiền khách phải trả

// Nếu có amount từ URL thì dùng (ưu tiên)
if ($amount > 0) {
    $total_payment = $amount;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chọn phương thức thanh toán MoMo</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Segoe UI", Arial, sans-serif;
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
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo-momo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #a50064, #d4006a);
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: bold;
            color: white;
            margin-bottom: 10px;
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
            font-size: 24px;
        }
        .order-info {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .order-info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .order-info-row:last-child {
            border-bottom: none;
        }
        .subtotal-row {
            font-size: 14px;
            color: #666;
        }
        .vat-row {
            font-size: 14px;
            color: #666;
        }
        .total-row {
            font-weight: bold;
            font-size: 18px;
            color: #a50064;
            margin-top: 10px;
            padding-top: 15px;
            border-top: 2px solid #dee2e6 !important;
        }
        .payment-options {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .payment-option {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .payment-option:hover {
            border-color: #a50064;
            background: #fff5fb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(165,0,100,0.15);
        }
        .payment-option.selected {
            border-color: #a50064;
            background: #fff5fb;
        }
        .payment-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }
        .wallet-icon { background: linear-gradient(135deg, #a50064, #d4006a); }
        .payment-info h3 {
            font-size: 16px;
            color: #333;
            margin-bottom: 5px;
        }
        .payment-info p {
            font-size: 13px;
            color: #666;
        }
        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #a50064, #d4006a);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.3s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(165,0,100,0.3);
        }
        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .note {
            text-align: center;
            margin-top: 15px;
            font-size: 13px;
            color: #666;
        }
        input[type="radio"] {
            width: 20px;
            height: 20px;
            margin-left: auto;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <div class="logo-momo">M</div>
            <h1>Xác nhận thanh toán</h1>
        </div>

        <div class="order-info">
            <div class="order-info-row">
                <span>Mã đơn hàng:</span>
                <span><strong>#<?= htmlspecialchars($order_id) ?></strong></span>
            </div>
            <div class="order-info-row">
                <span>Người nhận:</span>
                <span><?= htmlspecialchars($order['receiver_name']) ?></span>
            </div>
            <div class="order-info-row subtotal-row">
                <span>Giá trị đơn hàng:</span>
                <span><?= number_format($subtotal, 0, ',', '.') ?> VNĐ</span>
            </div>
            <?php if ($vat_amount > 0): ?>
            <div class="order-info-row vat-row">
                <span>Thuế VAT (10%):</span>
                <span><?= number_format($vat_amount, 0, ',', '.') ?> VNĐ</span>
            </div>
            <?php endif; ?>
            <div class="order-info-row total-row">
                <span>Tổng thanh toán:</span>
                <span><?= number_format($total_payment, 0, ',', '.') ?> VNĐ</span>
            </div>
        </div>

        <form id="paymentForm" method="GET" action="process_payment.php">
            <input type="hidden" name="order_id" value="<?= htmlspecialchars($order_id) ?>">
            <input type="hidden" name="amount" value="<?= htmlspecialchars($total_payment) ?>">
            <input type="hidden" name="method" value="momo">
            <input type="hidden" name="payment_type" id="payment_type_input" value="wallet">

            <div class="payment-options">
                <div class="payment-option selected" data-type="wallet">
                    <div class="payment-icon wallet-icon">💰</div>
                    <div class="payment-info">
                        <h3>Ví MoMo</h3>
                        <p>Thanh toán qua ví điện tử MoMo</p>
                    </div>
                    <input type="radio" name="payment_method" value="wallet" checked>
                </div>
            </div>

            <button type="submit" class="btn-submit">
                Thanh toán <?= number_format($total_payment, 0, ',', '.') ?> VNĐ
            </button>

            <p class="note">
                🔒 Giao dịch được bảo mật bởi MoMo
            </p>
        </form>
    </div>

    <script>
        const paymentOptions = document.querySelectorAll('.payment-option');
        const paymentTypeInput = document.getElementById('payment_type_input');

        paymentOptions.forEach(option => {
            option.addEventListener('click', function() {
                paymentOptions.forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
                
                paymentTypeInput.value = this.dataset.type;
            });
        });

        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
            if (!selectedMethod) {
                e.preventDefault();
                alert('Vui lòng chọn phương thức thanh toán');
            }
        });
    </script>
</body>
</html>