<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    die("Bạn không có quyền truy cập!");
}

include "includes/db.php";

// Số đơn hàng mỗi trang
$limit = 10;

// Lấy số trang hiện tại (mặc định = 1)
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// Tính offset
$offset = ($page - 1) * $limit;

// Lấy tổng số đơn hàng
$total_orders = $conn->query("SELECT COUNT(*) FROM orders")->fetchColumn();

// Tính tổng số trang
$total_pages = ceil($total_orders / $limit);

// Lấy danh sách đơn hàng kèm thông tin sản phẩm
$stmt = $conn->prepare("
    SELECT 
        o.*,
        GROUP_CONCAT(p.name SEPARATOR ', ') as product_names
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    GROUP BY o.id
    ORDER BY o.created_at DESC 
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Quản lý đơn hàng</title>
    <style>
        body {
            background:
                linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)),
                url("assets/moto2.jpg") no-repeat center center/cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        h2 { 
            text-align: center; 
            color: #333; 
            margin-bottom: 25px; 
        }
        .container {
            max-width: 1200px;
            margin: auto;
            background: #fff;
            padding: 20px 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
        }
        th {
            background: #2196f3;
            color: white;
            font-weight: 600;
            white-space: nowrap;
        }
        tr {
            border-bottom: 1px solid #eee;
        }
        tr:nth-child(even) { background: #f9f9f9; }
        tr:hover { background: #f1f1f1; }
        .btn {
            padding: 6px 14px;
            border-radius: 6px;
            text-decoration: none;
            color: white;
            font-size: 14px;
            display: inline-block;
            transition: 0.2s;
            white-space: nowrap;
        }
        .btn-view { background: #007bff; }
        .btn-view:hover { background: #0069d9; }
        .btn-confirm { background: #4caf50; }
        .btn-confirm:hover { background: #45a049; }
        td .btn { margin-right: 5px; margin-bottom: 5px; }
        .product-names {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .product-names:hover {
            white-space: normal;
            overflow: visible;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Quản lý đơn hàng</h2>
    
    <!-- Nút quay lại -->
    <a href="admin.php" class="btn" style="background:#ff5722; margin-bottom:15px; display:inline-block;">⬅ Quay lại</a>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Người nhận</th>
                <th>Tên xe</th>
                <th>Tổng tiền</th>
                <th>Ngày tạo</th>
                <th>Trạng thái</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($orders)): ?>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?= $order['id'] ?></td>
                        <td><?= htmlspecialchars($order['receiver_name']) ?></td>
                        <td>
                            <div class="product-names" title="<?= htmlspecialchars($order['product_names'] ?? 'N/A') ?>">
                                <?= htmlspecialchars($order['product_names'] ?? 'N/A') ?>
                            </div>
                        </td>
                        <td><?= number_format($order['total_price'], 0, ',', '.') ?> VNĐ</td>
                        <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                        <td><?= htmlspecialchars($order['status']) ?></td>
                        <td>
                            <a class="btn btn-view" href="order_detail.php?id=<?= $order['id'] ?>">Xem</a>
                            <?php if ($order['status'] != 'delivered'): ?>
                                <a class="btn btn-confirm" href="order_delivered.php?id=<?= $order['id'] ?>">Đã giao hàng</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align:center; padding:20px;">Chưa có đơn hàng nào.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- PHÂN TRANG -->
    <div style="text-align:center; margin-top:20px;">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>" class="btn" style="background:#2196f3;">⬅ Trước</a>
        <?php endif; ?>

        <?php
        for ($i = 1; $i <= $total_pages; $i++):
            $active = ($i == $page) ? "background:#4caf50;" : "background:#9e9e9e;";
        ?>
            <a href="?page=<?= $i ?>" class="btn" style="<?= $active ?> margin:2px;"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?>" class="btn" style="background:#2196f3;">Tiếp ➡</a>
        <?php endif; ?>
    </div>
</div>

</body>
</html>