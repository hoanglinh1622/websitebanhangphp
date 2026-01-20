<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảng điều khiển Admin</title>
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
            width: 100%;
            max-width: 1100px;
        }
        h1 {
            font-size: 32px;
            color: #f0eaeaff;
            margin-bottom: 10px;
            text-align: center;
        }
        p {
            text-align: center;
            font-size: 18px;
            color: #e6e0e0ff;
            margin-bottom: 30px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
        }
        .card {
            background: white;
            padding: 25px;
            border-radius: 14px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: 0.3s;
            text-align: center;
            cursor: pointer;
            border-left: 6px solid transparent;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        .card i {
            font-size: 42px;
            margin-bottom: 15px;
            display: block;
        }
        a {
            text-decoration: none;
            color: inherit;
            font-weight: 600;
            font-size: 18px;
        }
        .logout-btn {
            background: #ff4757;
            color: white;
        }
        .logout-btn:hover {
            background: #e84118;
        }
        .inventory-card {
            border-left-color: #3498db;
        }
        .inventory-card i {
            color: #3498db;
        }
        .discount-card {
            border-left-color: #f39c12;
        }
        .discount-card i {
            color: #f39c12;
        }
    </style>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
<div class="container">
    <h1>👑 Xin chào, Quản trị viên</h1>
    <p>Bạn có toàn quyền quản lý toàn bộ hệ thống website.</p>
    <div class="grid">
        <a href="index.php">
            <div class="card">
                <i class="fas fa-home" style="color:#3498db;"></i>
                Trang chủ
            </div>
        </a>
        <a href="list_product.php">
            <div class="card">
                <i class="fas fa-boxes" style="color:#e67e22;"></i>
                Quản lý sản phẩm
            </div>
        </a>
        <!-- Thêm card Quản lý kho hàng -->
        <a href="admin_stock.php">
            <div class="card inventory-card">
                <i class="fas fa-warehouse"></i>
                Quản lý kho hàng
            </div>
        </a>
        <a href="user.php">
            <div class="card">
                <i class="fas fa-users" style="color:#2ecc71;"></i>
                Quản lý người dùng
            </div>
        </a>
        <a href="admin_orders.php">
            <div class="card">
                <i class="fas fa-shopping-cart" style="color:#9b59b6;"></i>
                Quản lý đơn hàng
            </div>
        </a>
        <a href="admin_statistics.php">
            <div class="card">
                <i class="fas fa-chart-line" style="color:#1abc9c;"></i>
                Thống kê doanh thu
            </div>
        </a>
        <a href="logout.php">
            <div class="card logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Đăng xuất
            </div>
        </a>
    </div>
</div>
</body>
</html>