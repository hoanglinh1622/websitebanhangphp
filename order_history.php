<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

include "includes/db.php";

// Kiểm tra vai trò
$isAdmin = $_SESSION['user']['role'] === 'admin';

if ($isAdmin) {
    // Admin xem tất cả đơn hàng kèm thông tin sản phẩm
    $stmt = $conn->query("SELECT o.*, u.username,
                          GROUP_CONCAT(p.name SEPARATOR ', ') as product_names
                          FROM orders o
                          JOIN users u ON o.user_id = u.id
                          LEFT JOIN order_items oi ON o.id = oi.order_id
                          LEFT JOIN products p ON oi.product_id = p.id
                          GROUP BY o.id
                          ORDER BY o.created_at DESC");
    $orders = $stmt->fetchAll();
} else {
    // Người dùng bình thường chỉ xem đơn hàng của họ kèm thông tin sản phẩm
    $user_id = $_SESSION['user']['id'];
    $stmt = $conn->prepare("SELECT o.*,
                           GROUP_CONCAT(p.name SEPARATOR ', ') as product_names
                           FROM orders o
                           LEFT JOIN order_items oi ON o.id = oi.order_id
                           LEFT JOIN products p ON oi.product_id = p.id
                           WHERE o.user_id = ?
                           GROUP BY o.id
                           ORDER BY o.created_at DESC");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Lịch sử đơn hàng</title>
    <style>
        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 30px;
        }
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }
        .container {
            max-width: 1200px;
            margin: auto;
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        .back-btn {
            display: inline-block;
            margin-bottom: 20px;
            background: #ff5722;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 6px;
            transition: 0.2s;
        }
        .back-btn:hover {
            background: #e64a19;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
            min-width: 900px;
        }
        th, td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #ff5722;
            color: white;
            font-weight: 600;
            white-space: nowrap;
        }
        tr:nth-child(even) { background: #f9f9f9; }
        tr:hover { background: #f1f1f1; }
        a.btn {
            padding: 6px 12px;
            background: #007bff;
            color: white;
            border-radius: 5px;
            text-decoration: none;
            transition: 0.2s;
            white-space: nowrap;
        }
        a.btn:hover {
            background: #0056b3;
        }
        .product-names {
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: inline-block;
        }
        .product-names:hover {
            white-space: normal;
            overflow: visible;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Lịch sử đơn hàng<?= $isAdmin ? " của tất cả người dùng" : "" ?></h2>

    <!-- Nút quay lại -->
    <a href="index.php" class="back-btn">⬅ Quay lại trang chủ</a>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <?php if ($isAdmin): ?><th>Người dùng</th><?php endif; ?>
                <th>Tên xe</th>
                <th>Tổng tiền</th>
                <th>Ngày tạo</th>
                <th>Trạng thái</th>
                <th>Thanh toán</th>
                <th>Chi tiết</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($orders)): ?>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?= $order['id'] ?></td>
                        <?php if ($isAdmin): ?><td><?= htmlspecialchars($order['username']) ?></td><?php endif; ?>
                        <td>
                            <span class="product-names" title="<?= htmlspecialchars($order['product_names'] ?? 'N/A') ?>">
                                <?= htmlspecialchars($order['product_names'] ?? 'N/A') ?>
                            </span>
                        </td>
                        <td><?= number_format($order['total_price'], 0, ',', '.') ?> VNĐ</td>
                        <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                        <td><?= htmlspecialchars($order['status']) ?></td>
                        <td><?= htmlspecialchars($order['payment_status']) ?></td>
                        <td><a class="btn" href="orders.php?id=<?= $order['id'] ?>">Xem</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?= $isAdmin ? 8 : 7 ?>" style="padding:20px;">Chưa có đơn hàng nào.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>