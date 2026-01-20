<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

include 'includes/db.php';
$user_id = $_SESSION['user']['id'];

// 🔹 Lấy ID giỏ hàng
$stmt = $conn->prepare("SELECT id FROM carts WHERE user_id = ?");
$stmt->execute([$user_id]);
$cart = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cart) {
    $stmt = $conn->prepare("INSERT INTO carts (user_id) VALUES (?)");
    $stmt->execute([$user_id]);
    $cart_id = $conn->lastInsertId();
} else {
    $cart_id = $cart['id'];
}

// 🟢 Cập nhật số lượng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_qty'])) {
    foreach ($_POST['quantities'] as $cart_item_id => $quantity) {
        if ($quantity > 0) {
            $stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
            $stmt->execute([$quantity, $cart_item_id]);
        }
    }
    header("Location: cart.php");
    exit();
}

// 🟠 Xóa sản phẩm
if (isset($_GET['remove_from_cart'])) {
    $cart_item_id = $_GET['remove_from_cart'];
    $stmt = $conn->prepare("DELETE FROM cart_items WHERE id = ?");
    $stmt->execute([$cart_item_id]);
    header("Location: cart.php");
    exit();
}

// ✅ Lấy danh sách sản phẩm trong giỏ hàng + TÍNH GIÁ SALE
$stmt = $conn->prepare("
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
    JOIN products p ON ci.product_id = p.id
    WHERE ci.cart_id = ?
");
$stmt->execute([$cart_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Giỏ hàng của tôi</title>
    <style>
        body {font-family: "Segoe UI", Arial, sans-serif; background-color: #f0f2f5; margin: 0; padding: 0;}
        .container {width: 85%; margin: 40px auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); padding: 25px;}
        h1 {color: #333; text-align: center;}
        table {width: 100%; border-collapse: collapse; margin-top: 15px;}
        th {background-color: #007bff; color: white; padding: 12px;}
        td {padding: 12px; border-bottom: 1px solid #ddd; text-align: center;}
        img {width: 80px; border-radius: 8px;}
        .remove-btn {background: #dc3545; color: #fff; padding: 7px 12px; border: none; border-radius: 5px; cursor: pointer;}
        .remove-btn:hover {background: #b71c1c;}
        .checkout-btn, .update-btn, .select-all-btn {
            padding: 12px 18px; border: none; border-radius: 8px; cursor: pointer; margin-left: 10px; font-size: 15px;
        }
        .checkout-btn {background: #28a745; color: #fff;}
        .checkout-btn:hover {background: #218838;}
        .select-all-btn {background: #17a2b8; color: #fff;}
        .select-all-btn:hover {background: #138496;}
        .total-box {font-size: 18px; font-weight: bold; color: #333; margin-bottom: 10px;}
        .action-bar {text-align: right; margin-top: 25px;}
        .back-home {display: inline-block; margin-bottom: 20px; background: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 6px;}
        .back-home:hover {background: #0056b3;}
        input[type="number"] {width: 60px; text-align: center; padding: 5px; border: 1px solid #ddd; border-radius: 4px;}
        .qty-btn {padding: 4px 8px; margin:0 2px; cursor:pointer; border-radius: 4px; background:#007bff; color:white; border:none;}
        .qty-btn:hover {background:#0056b3;}
    </style>
</head>
<body>
<div class="container">
    <h1>🛒 Giỏ hàng của tôi</h1>
    <a href="index.php" class="back-home">🏠 Quay lại trang chủ</a>

    <form method="POST" id="cartForm" action="checkout.php">
        <table>
            <tr>
                <th>Chọn</th>
                <th>Hình ảnh</th>
                <th>Tên sản phẩm</th>
                <th>Giá</th>
                <th>Số lượng</th>
                <th>Thành tiền</th>
                <th>Xóa</th>
            </tr>
            <?php if (count($cart_items) > 0): ?>
                <?php foreach ($cart_items as $item): ?>
                    <tr>
                        <td><input type="checkbox" class="item-check" name="selected_items[]" value="<?= $item['cart_item_id']; ?>"></td>
                        <td><img src="<?= htmlspecialchars($item['image']); ?>" alt="Sản phẩm"></td>
                        <td><?= htmlspecialchars($item['name']); ?></td>
                        
                        <!-- ✅ HIỂN THỊ GIÁ VỚI SALE -->
                        <td class="price" data-price="<?= $item['sale_price']; ?>">
                            <?php if (!empty($item['discount']) && $item['discount'] > 0): ?>
                                <div style="text-decoration:line-through; color:#888; font-size:13px;">
                                    <?= number_format($item['price'], 0, ',', '.'); ?> VNĐ
                                </div>
                                <div style="color:#d93025; font-weight:700; font-size:16px;">
                                    <?= number_format($item['sale_price'], 0, ',', '.'); ?> VNĐ
                                    <span style="background:#ff4444; color:white; padding:2px 6px; border-radius:4px; font-size:11px; margin-left:5px;">
                                        -<?= intval($item['discount']); ?>%
                                    </span>
                                </div>
                            <?php else: ?>
                                <?= number_format($item['price'], 0, ',', '.'); ?> VNĐ
                            <?php endif; ?>
                        </td>
                        
                        
                        <td>
                            <button type="button" class="qty-btn" onclick="changeQty(this,-1)">-</button>
                            <input type="number" class="qty-input" name="quantities[<?= $item['cart_item_id']; ?>]" value="<?= $item['quantity']; ?>" min="1">
                            <button type="button" class="qty-btn" onclick="changeQty(this,1)">+</button>
                        </td>
                        
                        <!-- ✅ TÍNH THÀNH TIỀN THEO GIÁ SALE -->
                        <td class="item-total"><?= number_format($item['sale_price'] * $item['quantity'], 0, ',', '.'); ?> VNĐ</td>
                        
                        <td><button type="button" class="remove-btn" onclick="confirmDelete(<?= $item['cart_item_id']; ?>)">Xóa</button></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7">Giỏ hàng trống.</td></tr>
            <?php endif; ?>
        </table>
        

        <?php if (count($cart_items) > 0): ?>
            <div class="action-bar">
                <div class="total-box">Tổng tiền: <span id="totalAmount">0 VNĐ</span></div>
                <button type="button" id="toggleSelectAll" class="select-all-btn">✅ Chọn tất cả</button>
                <button type="submit" class="checkout-btn" name="checkout">💳 Đặt hàng ngay</button>
            </div>
        <?php endif; ?>
    </form>
</div>

<script>
function confirmDelete(cartItemId) {
    if (confirm("Bạn có chắc muốn xóa sản phẩm này khỏi giỏ hàng?")) {
        window.location.href = "?remove_from_cart=" + cartItemId;
    }
}

function updateTotal() {
    let total = 0;
    document.querySelectorAll('tr').forEach(row => {
        const checkbox = row.querySelector('.item-check');
        if (!checkbox || !checkbox.checked) return;
        const price = parseFloat(row.querySelector('.price').dataset.price) || 0;
        const qty = parseInt(row.querySelector('.qty-input').value) || 0;
        const itemTotal = price * qty;
        row.querySelector('.item-total').textContent = itemTotal.toLocaleString('vi-VN') + " VNĐ";
        total += itemTotal;
    });
    document.getElementById('totalAmount').textContent = total.toLocaleString('vi-VN') + " VNĐ";
}

function changeQty(button, delta) {
    const input = button.parentElement.querySelector('.qty-input');
    let value = parseInt(input.value) || 1;
    value += delta;
    if (value < 1) value = 1;
    input.value = value;
    updateTotal();
}

document.querySelectorAll('.item-check').forEach(cb => cb.addEventListener('change', updateTotal));
document.querySelectorAll('.qty-input').forEach(input => input.addEventListener('input', updateTotal));

const toggleBtn = document.getElementById('toggleSelectAll');
toggleBtn.addEventListener('click', () => {
    const checkboxes = document.querySelectorAll('.item-check');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => cb.checked = !allChecked);
    toggleBtn.textContent = allChecked ? '✅ Chọn tất cả' : '❌ Bỏ chọn tất cả';
    updateTotal();
});

const form = document.getElementById('cartForm');
form.addEventListener('submit', function(e){
    const selected = document.querySelectorAll('.item-check:checked');
    if(selected.length === 0){
        e.preventDefault();
        alert('Vui lòng chọn ít nhất một sản phẩm để đặt hàng!');
    }
});

updateTotal();
</script>
</body>
</html>