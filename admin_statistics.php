<?php
// Bật hiển thị lỗi để debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'includes/db.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Kiểm tra quyền admin
if (!isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Thống kê tổng quan
$stats = [
    'total_sales' => 0,
    'total_orders' => 0,
    'pending_orders' => 0,
    'completed_orders' => 0,
    'total_products' => 0,
    'total_customers' => 0
];

try {
    // Tổng doanh thu
    $sql = "SELECT SUM(total_price) as total_sales, COUNT(*) as total_orders 
            FROM orders WHERE status = 'delivered'";
    $stmt = $conn->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_sales'] = $result['total_sales'] ?? 0;
    $stats['completed_orders'] = $result['total_orders'] ?? 0;

    // Đơn hàng chờ xử lý
    $sql = "SELECT COUNT(*) as pending FROM orders WHERE status = 'pending'";
    $stmt = $conn->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['pending_orders'] = $result['pending'] ?? 0;

    // Tổng đơn hàng
    $sql = "SELECT COUNT(*) as total FROM orders";
    $stmt = $conn->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_orders'] = $result['total'] ?? 0;

    // Tổng sản phẩm
    $sql = "SELECT COUNT(*) as total FROM products";
    $stmt = $conn->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_products'] = $result['total'] ?? 0;

    // Tổng khách hàng
    $sql = "SELECT COUNT(*) as total FROM users WHERE role = 'user'";
    $stmt = $conn->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_customers'] = $result['total'] ?? 0;

} catch (PDOException $e) {
    $error_message = "Lỗi khi lấy thống kê: " . $e->getMessage();
}

// Top 5 sản phẩm bán chạy (tính cả delivered và pending)
$best_products = [];
try {
    $sql = "SELECT 
                p.id, 
                p.name, 
                p.price,
                p.image,
                COALESCE(c.name, 'Chưa phân loại') as category_name,
                SUM(oi.quantity) as total_sold,
                SUM(oi.quantity * oi.price) as revenue,
                SUM(CASE WHEN o.status = 'delivered' THEN oi.quantity ELSE 0 END) as delivered_qty,
                SUM(CASE WHEN o.status = 'pending' THEN oi.quantity ELSE 0 END) as pending_qty
            FROM products p 
            JOIN order_items oi ON p.id = oi.product_id 
            JOIN orders o ON oi.order_id = o.id 
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE o.status IN ('delivered', 'pending')
            GROUP BY p.id, p.name, p.price, p.image, c.name
            ORDER BY total_sold DESC 
            LIMIT 5";
    $stmt = $conn->query($sql);
    $best_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $best_products = [];
}

// Doanh thu theo tháng (6 tháng gần nhất)
$monthly_revenue = [];
try {
    $sql = "SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(total_price) as revenue,
                COUNT(*) as orders
            FROM orders 
            WHERE status = 'delivered' 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC";
    $stmt = $conn->query($sql);
    $monthly_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $monthly_revenue = [];
}

// Top 5 khách hàng mua nhiều nhất
$top_customers = [];
try {
    $sql = "SELECT 
                u.id,
                u.username,
                u.email,
                COUNT(DISTINCT o.id) as total_orders,
                SUM(o.total_price) as total_spent
            FROM users u
            JOIN orders o ON u.id = o.user_id
            WHERE o.status = 'delivered'
            GROUP BY u.id, u.username, u.email
            ORDER BY total_spent DESC
            LIMIT 5";
    $stmt = $conn->query($sql);
    $top_customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $top_customers = [];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thống Kê Doanh Thu - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Segoe UI", Arial, sans-serif;
        }
      
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
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .back-btn {
            display: inline-block;
            background: white;
            color: #667eea;
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        h1 {
            color: white;
            font-size: 36px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 25px rgba(0,0,0,0.15);
        }
        .stat-card .icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 15px;
        }
        .stat-card.revenue .icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .stat-card.orders .icon {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        .stat-card.products .icon {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        .stat-card.customers .icon {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }
        .stat-card.pending .icon {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
        }
        .stat-card.completed .icon {
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
            color: white;
        }
        .stat-card h3 {
            color: #7f8c8d;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .stat-card .value {
            color: #2c3e50;
            font-size: 32px;
            font-weight: 700;
        }
        .content-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .section-title {
            color: #2c3e50;
            font-size: 24px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid #667eea;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }
        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        tr:hover {
            background: #f8f9fa;
        }
        td img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
        }
.badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge.gold {
            background: #ffd700;
            color: #856404;
        }
        .badge.silver {
            background: #c0c0c0;
            color: #383d41;
        }
        .badge.bronze {
            background: #cd7f32;
            color: white;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
            font-size: 16px;
        }
        .chart-container {
            margin-top: 20px;
        }
        .chart-bar {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .chart-label {
            width: 100px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
        }
        .chart-bar-container {
            flex: 1;
            background: #ecf0f1;
            height: 40px;
            border-radius: 20px;
            overflow: hidden;
            position: relative;
        }
        .chart-bar-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 15px;
            color: white;
            font-weight: 600;
            font-size: 13px;
            transition: width 1s ease;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        .rank-number {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            font-size: 14px;
        }
        .rank-1 { background: linear-gradient(135deg, #ffd700, #ffed4e); color: #856404; }
        .rank-2 { background: linear-gradient(135deg, #c0c0c0, #e8e8e8); color: #383d41; }
        .rank-3 { background: linear-gradient(135deg, #cd7f32, #e8a87c); color: white; }
        .rank-4, .rank-5 { background: linear-gradient(135deg, #95a5a6, #b2bec3); color: white; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📊 Thống Kê & Báo Cáo Doanh Thu</h1>
        <a href="admin.php" class="back-btn">
            <i class="fa fa-arrow-left"></i> Quay lại
        </a>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="error-message">
            <i class="fa fa-exclamation-triangle"></i> <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- Thống kê tổng quan -->
    <div class="stats-grid">
<div class="stat-card revenue">
            <div class="icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <h3>Tổng Doanh Thu</h3>
            <div class="value"><?php echo number_format($stats['total_sales'], 0, ',', '.'); ?> ₫</div>
        </div>

        <div class="stat-card orders">
            <div class="icon">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <h3>Tổng Đơn Hàng</h3>
            <div class="value"><?php echo $stats['total_orders']; ?></div>
        </div>

        <div class="stat-card completed">
            <div class="icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3>Đơn Đã Giao</h3>
            <div class="value"><?php echo $stats['completed_orders']; ?></div>
        </div>

        <div class="stat-card pending">
            <div class="icon">
                <i class="fas fa-clock"></i>
            </div>
            <h3>Đơn Chờ Xử Lý</h3>
            <div class="value"><?php echo $stats['pending_orders']; ?></div>
        </div>

        <div class="stat-card products">
            <div class="icon">
                <i class="fas fa-box"></i>
            </div>
            <h3>Tổng Sản Phẩm</h3>
            <div class="value"><?php echo $stats['total_products']; ?></div>
        </div>

        <div class="stat-card customers">
            <div class="icon">
                <i class="fas fa-users"></i>
            </div>
            <h3>Tổng Khách Hàng</h3>
            <div class="value"><?php echo $stats['total_customers']; ?></div>
        </div>
    </div>

    <!-- Top 5 sản phẩm bán chạy -->
    <div class="content-section">
        <h2 class="section-title">
            <i class="fas fa-fire"></i>
            Top 5 Sản Phẩm Bán Chạy Nhất
        </h2>

        <?php if (!empty($best_products)): ?>
        <table>
            <thead>
                <tr>
                    <th style="width: 60px;">Hạng</th>
                    <th style="width: 80px;">Hình ảnh</th>
                    <th>Tên sản phẩm</th>
                    <th>Danh mục</th>
                    <th>Giá bán</th>
                    <th>Đã bán</th>
                    <th>Đã giao</th>
                    <th>Chờ xử lý</th>
                    <th>Doanh thu</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($best_products as $index => $product): 
                    $rank = $index + 1;
                ?>
                <tr>
                    <td>
                        <span class="rank-number rank-<?php echo $rank; ?>">
                            <?php echo $rank; ?>
                        </span>
                    </td>
                    <td>
                        <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
onerror="this.src='https://via.placeholder.com/50x50?text=No+Image'">
                    </td>
                    <td style="font-weight: 600;">
                        <?php echo htmlspecialchars($product['name']); ?>
                    </td>
                    <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                    <td><?php echo number_format($product['price'], 0, ',', '.'); ?> ₫</td>
                    <td>
                        <strong style="color: #e74c3c; font-size: 16px;">
                            <?php echo $product['total_sold']; ?>
                        </strong>
                    </td>
                    <td>
                        <span style="color: #28a745; font-weight: 600;">
                            <?php echo $product['delivered_qty']; ?>
                        </span>
                    </td>
                    <td>
                        <span style="color: #ffc107; font-weight: 600;">
                            <?php echo $product['pending_qty']; ?>
                        </span>
                    </td>
                    <td>
                        <strong style="color: #27ae60;">
                            <?php echo number_format($product['revenue'], 0, ',', '.'); ?> ₫
                        </strong>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="no-data">
                <i class="fas fa-inbox" style="font-size: 48px; color: #bdc3c7; margin-bottom: 15px;"></i>
                <p>⚠️ Chưa có dữ liệu bán hàng</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Doanh thu theo tháng -->
    <?php if (!empty($monthly_revenue)): ?>
    <div class="content-section">
        <h2 class="section-title">
            <i class="fas fa-chart-line"></i>
            Doanh Thu 6 Tháng Gần Nhất
        </h2>
        <div class="chart-container">
            <?php 
            $max_revenue = max(array_column($monthly_revenue, 'revenue'));
            foreach($monthly_revenue as $month): 
                $percentage = $max_revenue > 0 ? ($month['revenue'] / $max_revenue * 100) : 0;
            ?>
            <div class="chart-bar">
                <div class="chart-label"><?php echo date('m/Y', strtotime($month['month'] . '-01')); ?></div>
                <div class="chart-bar-container">
                    <div class="chart-bar-fill" style="width: <?php echo $percentage; ?>%">
                        <?php echo number_format($month['revenue'], 0, ',', '.'); ?> ₫
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Top 5 khách hàng -->
    <?php if (!empty($top_customers)): ?>
    <div class="content-section">
        <h2 class="section-title">
            <i class="fas fa-crown"></i>
Top 5 Khách Hàng Thân Thiết
        </h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 60px;">Hạng</th>
                    <th>Tên khách hàng</th>
                    <th>Email</th>
                    <th>Số đơn hàng</th>
                    <th>Tổng chi tiêu</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($top_customers as $index => $customer): 
                    $rank = $index + 1;
                ?>
                <tr>
                    <td>
                        <span class="rank-number rank-<?php echo $rank; ?>">
                            <?php echo $rank; ?>
                        </span>
                    </td>
                    <td style="font-weight: 600;">
                        <?php echo htmlspecialchars($customer['username']); ?>
                    </td>
                    <td><?php echo htmlspecialchars($customer['email']); ?></td>
                    <td><?php echo $customer['total_orders']; ?> đơn</td>
                    <td>
                        <strong style="color: #27ae60;">
                            <?php echo number_format($customer['total_spent'], 0, ',', '.'); ?> ₫
                        </strong>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>

<script>
// Animation cho chart bars khi load trang
window.addEventListener('load', function() {
    const bars = document.querySelectorAll('.chart-bar-fill');
    bars.forEach((bar, index) => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.width = width;
        }, index * 100);
    });
});
</script>
</body>
</html>