<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Xử lý cập nhật giảm giá
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $product_id = $_POST['product_id'];
    
    if ($_POST['action'] === 'update_discount') {
        $discount_percent = floatval($_POST['discount_percent']);
        $start_date = $_POST['start_date'] ?: null;
        $end_date = $_POST['end_date'] ?: null;
        
        // Validate discount percent
        if ($discount_percent < 0 || $discount_percent > 100) {
            $error = "Mức giảm giá phải từ 0% đến 100%";
        } else {
            $sql = "UPDATE products SET 
                    discount_percent = ?, 
                    discount_start_date = ?, 
                    discount_end_date = ? 
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("dssi", $discount_percent, $start_date, $end_date, $product_id);
            
            if ($stmt->execute()) {
                $success = "Cập nhật giảm giá thành công!";
            } else {
                $error = "Có lỗi xảy ra khi cập nhật!";
            }
        }
    } elseif ($_POST['action'] === 'remove_discount') {
        $sql = "UPDATE products SET 
                discount_percent = 0, 
                discount_start_date = NULL, 
                discount_end_date = NULL 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $product_id);
        
        if ($stmt->execute()) {
            $success = "Đã xóa giảm giá!";
        } else {
            $error = "Có lỗi xảy ra khi xóa!";
        }
    }
}

// Lấy danh sách sản phẩm
$sql = "SELECT * FROM products ORDER BY name ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Giảm giá</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Segoe UI", Arial, sans-serif;
        }
        body {
            background: #eef1f5;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            color: #333;
            font-size: 28px;
        }
        .back-btn {
            background: #3498db;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            transition: 0.3s;
        }
        .back-btn:hover {
            background: #2980b9;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        .product-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: 0.3s;
        }
        .product-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
        }
        .product-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 15px;
        }
        .product-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            flex: 1;
        }
        .product-price {
            font-size: 16px;
            color: #e67e22;
            font-weight: 600;
            margin-bottom: 15px;
        }
        .discount-badge {
            display: inline-block;
            background: #e74c3c;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-left: 10px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: 0.3s;
        }
        .form-control:focus {
            outline: none;
            border-color: #f39c12;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: 0.3s;
        }
        .btn-primary {
            background: #f39c12;
            color: white;
        }
        .btn-primary:hover {
            background: #e67e22;
        }
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        .btn-danger:hover {
            background: #c0392b;
        }
        .discount-info {
            background: #fff3cd;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #856404;
        }
        .date-range {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1><i class="fas fa-percent"></i> Quản lý Giảm giá</h1>
        <a href="admin.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Quay lại Dashboard
        </a>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="products-grid">
        <?php while ($product = $result->fetch(PDO::FETCH_ASSOC)): ?>
            <div class="product-card">
                <div class="product-header">
                    <?php if ($product['image']): ?>
                        <img src="uploads/<?php echo htmlspecialchars($product['image']); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>" 
                             class="product-image">
                    <?php else: ?>
                        <div class="product-image" style="background:#ddd;display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-image" style="color:#999;"></i>
                        </div>
                    <?php endif; ?>
                    <div class="product-name">
                        <?php echo htmlspecialchars($product['name']); ?>
                    </div>
                </div>

                <div class="product-price">
                    Giá gốc: <?php echo number_format($product['price'], 0, ',', '.'); ?>đ
                    <?php if ($product['discount_percent'] > 0): ?>
                        <span class="discount-badge">-<?php echo $product['discount_percent']; ?>%</span>
                    <?php endif; ?>
                </div>

                <?php if ($product['discount_percent'] > 0): ?>
                    <div class="discount-info">
                        <strong>Giảm còn:</strong> 
                        <?php 
                        $discounted_price = $product['price'] * (1 - $product['discount_percent'] / 100);
                        echo number_format($discounted_price, 0, ',', '.'); 
                        ?>đ<br>
                        <?php if ($product['discount_start_date'] && $product['discount_end_date']): ?>
                            <small>
                                Từ <?php echo date('d/m/Y', strtotime($product['discount_start_date'])); ?> 
                                đến <?php echo date('d/m/Y', strtotime($product['discount_end_date'])); ?>
                            </small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    <input type="hidden" name="action" value="update_discount">
                    
                    <div class="form-group">
                        <label>Mức giảm giá (%)</label>
                        <input type="number" 
                               name="discount_percent" 
                               class="form-control" 
                               min="0" 
                               max="100" 
                               step="0.01"
                               value="<?php echo $product['discount_percent']; ?>"
                               placeholder="Nhập % giảm giá (0-100)">
                    </div>

                    <div class="date-range">
                        <div class="form-group">
                            <label>Ngày bắt đầu</label>
                            <input type="date" 
                                   name="start_date" 
                                   class="form-control"
                                   value="<?php echo $product['discount_start_date']; ?>">
                        </div>
                        <div class="form-group">
                            <label>Ngày kết thúc</label>
                            <input type="date" 
                                   name="end_date" 
                                   class="form-control"
                                   value="<?php echo $product['discount_end_date']; ?>">
                        </div>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Cập nhật
                        </button>
                        <?php if ($product['discount_percent'] > 0): ?>
                            <button type="submit" 
                                    class="btn btn-danger" 
                                    onclick="return confirm('Xóa giảm giá cho sản phẩm này?')"
                                    formaction="?action=remove"
                                    name="action"
                                    value="remove_discount">
                                <i class="fas fa-times"></i> Xóa
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        <?php endwhile; ?>
    </div>
</div>
</body>
</html>