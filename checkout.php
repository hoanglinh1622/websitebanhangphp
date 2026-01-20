<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

include 'includes/db.php';
$user_id = $_SESSION['user']['id'];

$error = "";
$success = "";

// Cấu hình thuế VAT (10%)
$vat_rate = 0.10; // 10%

// Lấy danh sách cart_item id được chọn (từ cart.php)
$selected_items = $_POST['selected_items'] ?? [];

// Nếu truy cập trực tiếp (không có selected_items), thử lấy từ GET
if (empty($selected_items) && isset($_GET['selected']) && is_array($_GET['selected'])) {
    $selected_items = $_GET['selected'];
}

// nếu vẫn rỗng, hiển thị lỗi
if (empty($selected_items)) {
    $cart_items = [];
    $subtotal = 0;
    $vat_amount = 0;
    $total_price = 0;
    $error = "Vui lòng chọn ít nhất một sản phẩm từ giỏ hàng để tiếp tục thanh toán.";
} else {
    // Chuẩn hóa các id thành số nguyên
    $selected_items = array_map('intval', $selected_items);

    // ✅ SỬA: Lấy những cart_items tương ứng + TÍNH GIÁ SALE
    $placeholders = implode(',', array_fill(0, count($selected_items), '?'));
    $params = array_merge([$user_id], $selected_items);

    $sql = "
        SELECT ci.id AS cart_item_id, 
               p.id AS product_id, 
               p.name, 
               p.price, 
               p.discount,
               p.image, 
               ci.quantity,
               CASE 
                   WHEN p.discount > 0 THEN p.price * (100 - p.discount) / 100
                   ELSE p.price
               END as sale_price
        FROM cart_items ci
        JOIN carts c ON ci.cart_id = c.id
        JOIN products p ON ci.product_id = p.id
        WHERE c.user_id = ? AND ci.id IN ($placeholders)
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ✅ Tính tổng phụ (subtotal - chưa có VAT) DÙNG GIÁ SALE
    $subtotal = 0;
    foreach ($cart_items as $it) {
        $subtotal += $it['sale_price'] * $it['quantity'];
    }
    
    // Tính VAT
    $vat_amount = $subtotal * $vat_rate;
    
    // Tổng tiền phải trả (bao gồm VAT)
    $total_price = $subtotal + $vat_amount;

    if (empty($cart_items)) {
        $error = "Không tìm thấy sản phẩm trong giỏ (có thể đã bị xóa).";
    }
}

// Xử lý đặt hàng khi submit form ở checkout.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order'])) {
    // Lấy lại selected_items để tránh mất khi submit
    $selected_items = $_POST['selected_items'] ?? [];
    $selected_items = array_map('intval', $selected_items);

    // Thông tin người nhận
    $receiver_name = trim($_POST['receiver_name'] ?? '');
    $shipping_address = trim($_POST['shipping_address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $payment_method = $_POST['payment_method'] ?? 'cod';

    // Validate
    if (empty($selected_items)) {
        $error = "Vui lòng chọn ít nhất một sản phẩm để đặt hàng!";
    } elseif (empty($receiver_name) || empty($shipping_address) || empty($phone)) {
        $error = "Vui lòng nhập đầy đủ: tên người nhận, địa chỉ và số điện thoại.";
    } elseif (!preg_match('/^[0-9\+\-\s]{7,20}$/', $phone)) {
        $error = "Số điện thoại không hợp lệ.";
    } else {
        // ✅ SỬA: Lấy lại các sản phẩm hợp lệ + GIÁ SALE
        $placeholders = implode(',', array_fill(0, count($selected_items), '?'));
        $params = array_merge([$user_id], $selected_items);
        $sql = "
            SELECT ci.id AS cart_item_id, 
                   p.id AS product_id, 
                   p.name, 
                   p.price, 
                   p.discount,
                   ci.quantity,
                   CASE 
                       WHEN p.discount > 0 THEN p.price * (100 - p.discount) / 100
                       ELSE p.price
                   END as sale_price
            FROM cart_items ci
            JOIN carts c ON ci.cart_id = c.id
            JOIN products p ON ci.product_id = p.id
            WHERE c.user_id = ? AND ci.id IN ($placeholders)
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $items_to_order = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items_to_order)) {
            $error = "Không có sản phẩm hợp lệ để đặt hàng.";
        } else {
            // ✅ Tính tổng thực tế lại DÙNG GIÁ SALE (chưa bao gồm VAT)
            $subtotal = 0;
            foreach ($items_to_order as $it) {
                $subtotal += $it['sale_price'] * $it['quantity'];
            }
            
            // Tính VAT
            $vat_amount = $subtotal * $vat_rate;
            
            // Tổng tiền khách phải trả (bao gồm VAT)
            $total_price = $subtotal + $vat_amount;

            // Thực hiện transaction: lưu orders, order_items, xóa cart_items tương ứng
            try {
                $conn->beginTransaction();

                // Lưu order - total_price lưu giá trị KHÔNG bao gồm VAT (subtotal)
                $stmt = $conn->prepare("INSERT INTO orders (user_id, receiver_name, shipping_address, phone, note, total_price, vat_amount, payment_method, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
                $stmt->execute([$user_id, $receiver_name, $shipping_address, $phone, $note, $subtotal, $vat_amount, $payment_method]);
                $order_id = $conn->lastInsertId();

                // ✅ SỬA: Lưu order_items với GIÁ SALE
                $stmt_insert = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                foreach ($items_to_order as $it) {
                    $stmt_insert->execute([$order_id, $it['product_id'], $it['quantity'], $it['sale_price']]);
                }

                // Xóa chỉ các cart_items đã đặt
                $placeholders = implode(',', array_fill(0, count($selected_items), '?'));
                $params = array_merge([$user_id], $selected_items);
                $sql = "
                    DELETE ci FROM cart_items ci
                    JOIN carts c ON ci.cart_id = c.id
                    WHERE c.user_id = ? AND ci.id IN ($placeholders)
                ";
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);

                $conn->commit();
                
                // Xử lý thanh toán trực tuyến nếu được chọn
                if ($payment_method === 'momo') {
                    header('Location: momo_payment_selection.php?order_id=' . $order_id . '&amount=' . $total_price);
                    exit();
                } elseif ($payment_method === 'zalopay') {
                    header('Location: process_zalopay.php?order_id=' . $order_id . '&amount=' . $total_price);
                    exit();
                } else {
                    header('Location: checkout_success.php');
                    exit();
                }
            } catch (Exception $e) {
                $conn->rollBack();
                $error = "Đã xảy ra lỗi khi lưu đơn hàng: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Thanh toán - Moto ABC</title>
<style>
    :root{
        --primary:#ff5722;
        --accent:#ff9800;
        --muted:#f3f4f6;
        --card:#fff;
        --success:#28a745;
    }
    body {
        font-family: "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        background: linear-gradient(180deg,#fafafa,#f5f7fb);
        margin:0;padding:30px 12px;
    }
    .container{
        max-width:1000px;margin:0 auto;background:var(--card);
        border-radius:12px;padding:26px;box-shadow:0 10px 30px rgba(10,10,20,0.08);
    }
    .btn-back {
        display: inline-block;
        background: var(--primary);
        color: white;
        padding: 10px 16px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        transition: background 0.25s, transform 0.15s;
    }
    .btn-back:hover {
        background: #e64a19;
        transform: translateY(-2px);
    }
    h1{color:var(--primary);text-align:center;margin:0 0 14px;font-size:24px}
    .flex{display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap}
    .left{flex:1;min-width:320px}
    .right{width:360px;background:#fff;border-radius:10px;padding:18px;border:1px solid #eef3f6}
    .section-title{font-weight:700;color:#333;margin-bottom:10px;border-left:4px solid var(--primary);padding-left:10px}
    table{width:100%;border-collapse:collapse}
    th,td{padding:10px;border-bottom:1px solid #f0f2f4;text-align:left}
    th{background:#fafafa;color:#555;font-weight:600}
    .product-row{display:flex;gap:12px;align-items:center}
    .product-row img{width:72px;height:72px;object-fit:cover;border-radius:8px}
    .muted{color:#666;font-size:13px}
    .price{color:var(--primary);font-weight:700}
    .original-price{text-decoration:line-through;color:#888;font-size:12px;margin-right:6px}
    .discount-badge{background:#ff4444;color:white;padding:2px 6px;border-radius:4px;font-size:11px;margin-left:4px}
    .summary-line{display:flex;justify-content:space-between;margin:8px 0;font-weight:600}
    .vat-line{display:flex;justify-content:space-between;margin:8px 0;color:#666;font-size:14px}
    .total-line{display:flex;justify-content:space-between;margin:8px 0;font-weight:700;font-size:18px}
    label{display:block;margin-top:12px;font-weight:600;color:#333}
    input[type="text"], textarea, select{
        width:100%;padding:10px;border-radius:8px;border:1px solid #e6eaee;font-size:14px;margin-top:6px;
    }
    textarea{min-height:76px;resize:vertical}
    .btn-primary{
        display:inline-block;background:linear-gradient(90deg,var(--primary),var(--accent));
        color:#fff;padding:12px 18px;border-radius:10px;border:none;font-weight:700;cursor:pointer;
        box-shadow:0 6px 18px rgba(255,120,60,0.18);
    }
    .btn-primary:disabled{opacity:.6;cursor:not-allowed}
    .note{font-size:13px;color:#888;margin-top:8px}
    .error{background:#fdecea;border:1px solid #f5c2c0;color:#a94442;padding:10px;border-radius:8px;margin-bottom:12px}
    .success{background:#e6ffef;border:1px solid #bfecc8;color:#117a3a;padding:10px;border-radius:8px;margin-bottom:12px}
    
    .payment-options {
        margin: 16px 0;
    }
    .payment-option {
        display: flex;
        align-items: center;
        padding: 12px;
        border: 1px solid #e6eaee;
        border-radius: 8px;
        margin-bottom: 10px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .payment-option:hover {
        border-color: var(--primary);
        background-color: #fff9f7;
    }
    .payment-option.selected {
        border-color: var(--primary);
        background-color: #fff9f7;
    }
    .payment-option input {
        margin-right: 10px;
    }
    .payment-icon {
        width: 40px;
        height: 40px;
        margin-right: 10px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        color: white;
    }
    .cod-icon {
        background-color: #28a745;
    }
    .zalopay-icon {
        background-color: #0068ff;
    }
    .momo-icon {
        background-color: #a50064;
    }
    .info-box {
        background: #fff9e6;
        border-left: 4px solid #ffc107;
        padding: 12px;
        margin: 12px 0;
        border-radius: 6px;
        font-size: 13px;
    }

    @media (max-width:900px){
        .right{width:100%}
        .flex{flex-direction:column}
    }
</style>
</head>
<body>
<div class="container">
    <a href="cart.php" class="btn-back">← Quay lại giỏ hàng</a>

    <h1>Thanh toán đơn hàng</h1>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="flex">
        <div class="left">
            <div class="section-title">Sản phẩm (<?= count($cart_items) ?>)</div>

            <?php if (!empty($cart_items)): ?>
                <table aria-describedby="cart-items">
                    <thead>
                        <tr>
                            <th>Sản phẩm</th>
                            <th>Đơn giá</th>
                            <th>Số lượng</th>
                            <th>Tạm tính</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($cart_items as $it): ?>
                        <tr>
                            <td>
                                <div class="product-row">
                                    <img src="<?= htmlspecialchars($it['image']) ?>" alt="">
                                    <div>
                                        <div style="font-weight:700"><?= htmlspecialchars($it['name']) ?></div>
                                        <div class="muted">Mã SP: <?= htmlspecialchars($it['product_id']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($it['discount']) && $it['discount'] > 0): ?>
                                    <span class="original-price"><?= number_format($it['price'],0,',','.') ?> VNĐ</span>
                                    <br>
                                    <span class="price"><?= number_format($it['sale_price'],0,',','.') ?> VNĐ</span>
                                    <span class="discount-badge">-<?= intval($it['discount']) ?>%</span>
                                <?php else: ?>
                                    <span class="price"><?= number_format($it['price'],0,',','.') ?> VNĐ</span>
                                <?php endif; ?>
                            </td>
                            <td><?= (int)$it['quantity'] ?></td>
                            <td class="price"><?= number_format($it['sale_price'] * $it['quantity'],0,',','.') ?> VNĐ</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="muted">Không có sản phẩm nào để thanh toán.</p>
            <?php endif; ?>
        </div>

        <div class="right">
            <div class="section-title">Thông tin giao hàng</div>

            <form method="POST" id="checkoutForm" novalidate>
                <!-- giữ selected_items để submit -->
                <?php foreach ($selected_items as $id): ?>
                    <input type="hidden" name="selected_items[]" value="<?= htmlspecialchars($id) ?>">
                <?php endforeach; ?>

                <label for="receiver_name">Tên người nhận</label>
                <input type="text" id="receiver_name" name="receiver_name" required placeholder="Nguyễn Văn A">

                <label for="phone">Số điện thoại</label>
                <input type="text" id="phone" name="phone" required placeholder="0912xxxxxx">

                <label for="shipping_address">Địa chỉ nhận hàng</label>
                <textarea id="shipping_address" name="shipping_address" required placeholder="Số nhà, đường, phường, quận, tỉnh"></textarea>

                <label for="note">Ghi chú (tuỳ chọn)</label>
                <textarea id="note" name="note" placeholder="VD: Giao giờ hành chính, gọi trước khi giao"></textarea>
                
                <div class="section-title">Phương thức thanh toán</div>
                <div class="payment-options">
                    <div class="payment-option selected" data-value="cod">
                        <input type="radio" name="payment_method" value="cod" checked>
                        <div class="payment-icon cod-icon">TM</div>
                        <div>
                            <div style="font-weight:600">Thanh toán khi nhận hàng</div>
                            <div class="muted">Nhận hàng tại cửa hàng</div>
                        </div>
                    </div>  
                    <div class="payment-option" data-value="zalopay">
                        <input type="radio" name="payment_method" value="zalopay">
                        <div class="payment-icon zalopay-icon">ZP</div>
                        <div>
                            <div style="font-weight:600">ZaloPay</div>
                            <div class="muted">Thanh toán qua ứng dụng ZaloPay</div>
                        </div>
                    </div>
                    <div class="payment-option" data-value="momo">
                        <input type="radio" name="payment_method" value="momo">
                        <div class="payment-icon momo-icon">M</div>
                        <div>
                            <div style="font-weight:600">MoMo</div>
                            <div class="muted">Thanh toán qua ứng dụng MoMo</div>
                        </div>
                    </div>
                </div>

                <div style="margin-top:16px;border-top:1px dashed #eee;padding-top:12px">
                    <div class="summary-line">
                        <div>Tạm tính (chưa VAT)</div>
                        <div class="price"><?= number_format($subtotal,0,',','.') ?> VNĐ</div>
                    </div>
                    <div class="vat-line">
                        <div>VAT (<?= ($vat_rate * 100) ?>%)</div>
                        <div><?= number_format($vat_amount,0,',','.') ?> VNĐ</div>
                    </div>
                    <div class="summary-line">
                        <div>Nhận hàng tại cửa hàng</div>
                    </div>
                    
                    <div class="muted" style="margin:8px 0">
                        <div>Do sản phẩm có giá trị lớn</div>
                    </div>
                    <hr style="border:none;border-top:2px solid #f0f2f4;margin:12px 0">
                    <div class="total-line">
                        <div>Tổng thanh toán</div>
                        <div class="price"><?= number_format($total_price,0,',','.') ?> VNĐ</div>
                    </div>
                    
                    <div class="info-box">
                        <strong>📌 Lưu ý:</strong> Thuế VAT đã bao gồm trong tổng thanh toán nhưng không được tính vào doanh thu của cửa hàng.
                    </div>
                </div>

                <div style="margin-top:14px;text-align:right">
                    <button type="submit" name="confirm_order" class="btn-primary" id="confirmBtn">✅ Xác nhận đặt hàng</button>
                </div>

                <div class="note">Bạn sẽ nhận được email/xác nhận đơn hàng sau khi hoàn tất.</div>
            </form>
        </div>
    </div>
</div>

<script>
    (function(){
        const cartCount = <?= count($cart_items) ?>;
        const confirmBtn = document.getElementById('confirmBtn');
        if (cartCount === 0) {
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Không có sản phẩm để đặt';
        }

        const phoneInput = document.getElementById('phone');
        phoneInput && phoneInput.addEventListener('input', function(){
            this.value = this.value.replace(/[^\d\+\-\s]/g,'');
        });

        const paymentOptions = document.querySelectorAll('.payment-option');
        paymentOptions.forEach(option => {
            option.addEventListener('click', function() {
                paymentOptions.forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
            });
        });

        const form = document.getElementById('checkoutForm');
        form.addEventListener('submit', function(e){
            if (cartCount === 0) {
                e.preventDefault();
                alert('Không có sản phẩm để thanh toán.');
                return;
            }
            const name = document.getElementById('receiver_name').value.trim();
            const phone = phoneInput.value.trim();
            const addr = document.getElementById('shipping_address').value.trim();
            if (!name || !phone || !addr) {
                e.preventDefault();
                alert('Vui lòng nhập tên, số điện thoại và địa chỉ giao hàng.');
            }
        });
    })();
</script>
</body>
</html>