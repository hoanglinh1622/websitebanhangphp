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

// Xử lý AJAX cập nhật giảm giá
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_discount') {
    header('Content-Type: application/json');
    
    $product_id = intval($_POST['product_id']);
    $discount = floatval($_POST['discount']);
    
    if ($discount < 0) $discount = 0;
    if ($discount > 100) $discount = 100;
    
    try {
        $sql = "UPDATE products SET discount = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute([$discount, $product_id])) {
            echo json_encode([
                'success' => true,
                'discount' => $discount,
                'message' => 'Cập nhật thành công'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Lỗi cập nhật database'
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Lỗi: ' . $e->getMessage()
        ]);
    }
    exit();
}

// Xử lý xóa sản phẩm
$delete_msg = '';
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    try {
        $sql = "DELETE FROM products WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute([$id])) {
            $delete_msg = "✅ Xóa sản phẩm thành công!";
        } else {
            $delete_msg = "❌ Có lỗi xảy ra khi xóa!";
        }
    } catch (PDOException $e) {
        $delete_msg = "❌ Lỗi: " . $e->getMessage();
    }
}

// Phân trang và tìm kiếm
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Lấy danh sách sản phẩm
$products = [];
$total_pages = 1;
$total_products = 0;

try {
    if ($search) {
        // Tìm kiếm
        $sql = "SELECT p.*, 
                COALESCE(c.name, 'Chưa phân loại') as category_name, 
                COALESCE(b.name, 'Chưa có thương hiệu') as brand_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                LEFT JOIN brands b ON p.brand_id = b.id 
                WHERE p.name LIKE ? OR p.description LIKE ?
                ORDER BY p.id DESC";
        $stmt = $conn->prepare($sql);
        $search_param = "%$search%";
        $stmt->execute([$search_param, $search_param]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_products = count($products);
        $total_pages = 1;
    } else {
        // Đếm tổng số sản phẩm
        $count_sql = "SELECT COUNT(*) as total FROM products";
        $stmt = $conn->query($count_sql);
        $count_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_products = $count_result['total'];
        $total_pages = ceil($total_products / $limit);
        
        // Lấy sản phẩm theo trang
        $sql = "SELECT p.*, 
                COALESCE(c.name, 'Chưa phân loại') as category_name, 
                COALESCE(b.name, 'Chưa có thương hiệu') as brand_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                LEFT JOIN brands b ON p.brand_id = b.id 
                ORDER BY p.id DESC 
                LIMIT :limit OFFSET :offset";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error_message = "❌ Lỗi khi lấy dữ liệu: " . $e->getMessage();
    $products = [];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Sản phẩm - Admin</title>
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
        .admin-home {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            margin-bottom: 20px;
            transition: 0.3s;
        }
        .admin-home:hover {
            background: #2980b9;
        }
        h1 {
            color: #d8e1ebff;
            font-size: 32px;
            margin-bottom: 20px;
        }
        .message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        .search-box {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .search-box input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        .search-box input:focus {
            outline: none;
            border-color: #3498db;
        }
        .search-box button {
            padding: 12px 24px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.3s;
        }
        .search-box button:hover {
            background: #2980b9;
        }
        table {
            width: 100%;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-collapse: collapse;
        }
        thead {
            background: #2ecc71;
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
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            font-size: 13px;
            font-weight: 600;
            transition: 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn.add {
            background: #27ae60;
            color: white;
        }
        .btn.add:hover {
            background: #229954;
        }
        .btn.edit {
            background: #f39c12;
            color: white;
            margin-right: 5px;
        }
        .btn.edit:hover {
            background: #e67e22;
        }
        .btn.delete {
            background: #e74c3c;
            color: white;
        }
        .btn.delete:hover {
            background: #c0392b;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 30px;
        }
        .pagination a {
            padding: 10px 15px;
            background: white;
            border-radius: 6px;
            text-decoration: none;
            color: #333;
            font-weight: 600;
            transition: 0.3s;
        }
        .pagination a:hover,
        .pagination a.active {
            background: #3498db;
            color: white;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            color: #f3f8f8ff;
            font-size: 14px;
        }
        .discount-cell {
            position: relative;
        }
        .discount-display {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 6px 10px;
            border-radius: 6px;
            transition: 0.3s;
        }
        .discount-display:hover {
            background: #f0f0f0;
        }
        .discount-value {
            font-weight: 700;
            color: #e74c3c;
            font-size: 16px;
        }
        .discount-value.zero {
            color: #95a5a6;
        }
        .discount-icon {
            color: #95a5a6;
            font-size: 14px;
        }
        .discount-editor {
            display: none;
            gap: 5px;
            align-items: center;
        }
        .discount-editor.active {
            display: flex;
        }
        .discount-input {
            width: 70px;
            padding: 8px;
            border: 2px solid #3498db;
            border-radius: 6px;
            font-size: 14px;
            text-align: center;
            font-weight: 600;
        }
        .discount-input:focus {
            outline: none;
            border-color: #2980b9;
        }
        .discount-btn {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.3s;
        }
        .discount-btn.save {
            background: #27ae60;
            color: white;
        }
        .discount-btn.save:hover {
            background: #229954;
        }
        .discount-btn.cancel {
            background: #e74c3c;
            color: white;
        }
        .discount-btn.cancel:hover {
            background: #c0392b;
        }
        .discount-cell.updating {
            opacity: 0.6;
            pointer-events: none;
        }
        .price-cell {
            font-weight: 600;
            color: #333;
        }
        .original-price {
            text-decoration: line-through;
            color: #999;
            font-size: 12px;
            display: block;
        }
        .discounted-price {
            color: #dc3545;
            font-weight: 700;
            font-size: 14px;
        }
        
        /* Styles cho cột tồn kho - chỉ hiển thị */
        .stock-cell {
            text-align: center;
            font-weight: 700;
            font-size: 16px;
        }
        .stock-value.in-stock {
            color: #27ae60;
        }
        .stock-value.low-stock {
            color: #f39c12;
        }
        .stock-value.out-of-stock {
            color: #e74c3c;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 12px;
            color: #7f8c8d;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>
<div class="container">

    <a href="admin.php" class="admin-home">🏠 Trang quản trị</a>
    <h1>📊 Quản lý Sản phẩm</h1>
    
    <?php if ($delete_msg): ?>
        <p class="message"><?php echo $delete_msg; ?></p>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <p class="error-message"><?php echo $error_message; ?></p>
    <?php endif; ?>

    <!-- Tìm kiếm & Thêm sản phẩm -->
    <form method="GET" class="search-box">
        <input type="text" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="🔍 Nhập tên sản phẩm để tìm kiếm...">
        <button type="submit">Tìm kiếm</button>
        <?php if ($search): ?>
            <a href="list_product.php" class="btn edit">✖️ Xóa tìm kiếm</a>
        <?php endif; ?>
        <a href="product.php" class="btn add">➕ Thêm sản phẩm mới</a>
    </form>

    <!-- Bảng sản phẩm -->
    <?php if (!empty($products)): ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Tên sản phẩm</th>
                <th>Danh mục</th>
                <th>Thương hiệu</th>
                <th>Giá</th>
                <th>Tồn kho</th>
                <th>Giảm giá (%)</th>
                <th>Hình ảnh</th>
                <th>Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $product): ?>
            <tr>
                <td><?php echo htmlspecialchars($product['id']); ?></td>
                <td><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($product['category_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($product['brand_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="price-cell">
                    <?php 
                    $price = floatval($product['price']);
                    $discount = floatval($product['discount'] ?? 0);
                    if ($discount > 0):
                        $discounted = $price * (100 - $discount) / 100;
                    ?>
                        <span class="original-price"><?php echo number_format($price, 0, ',', '.'); ?> VNĐ</span>
                        <span class="discounted-price"><?php echo number_format($discounted, 0, ',', '.'); ?> VNĐ</span>
                    <?php else: ?>
                        <?php echo number_format($price, 0, ',', '.'); ?> VNĐ
                    <?php endif; ?>
                </td>
                
                <!-- Cột Tồn kho - CHỈ HIỂN THỊ, KHÔNG CHO SỬA -->
                <td class="stock-cell">
                    <?php 
                    $stock = intval($product['stock'] ?? 0);
                    $stockClass = $stock > 10 ? 'in-stock' : ($stock > 0 ? 'low-stock' : 'out-of-stock');
                    ?>
                    <span class="stock-value <?php echo $stockClass; ?>">
                        <?php echo $stock; ?>
                    </span>
                </td>

                <!-- Cột Giảm giá - CÓ THỂ SỬA -->
                <td class="discount-cell" data-product-id="<?php echo $product['id']; ?>">
                    <div class="discount-display" onclick="editDiscount(<?php echo $product['id']; ?>)">
                        <span class="discount-value <?php echo ($product['discount'] ?? 0) == 0 ? 'zero' : ''; ?>" 
                              data-value="<?php echo $product['discount'] ?? 0; ?>">
                            <?php echo $product['discount'] ?? 0; ?>%
                        </span>
                        <i class="fa fa-edit discount-icon"></i>
                    </div>
                    <div class="discount-editor">
                        <input type="number" class="discount-input" 
                               value="<?php echo $product['discount'] ?? 0; ?>"
                               min="0" max="100" step="1">
                        <button type="button" class="discount-btn save" onclick="saveDiscount(<?php echo $product['id']; ?>)">
                            <i class="fa fa-check"></i>
                        </button>
                        <button type="button" class="discount-btn cancel" onclick="cancelDiscount(<?php echo $product['id']; ?>)">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </td>
                
                <td>
                    <img src="<?php echo htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8'); ?>" 
                         alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>" 
                         onerror="this.src='https://via.placeholder.com/60x60?text=No+Image'">
                </td>
                <td>
                    <a href="product.php?edit_id=<?php echo $product['id']; ?>" class="btn edit">✏️ Sửa</a>
                    <a href="#" onclick="confirmDelete(<?php echo $product['id']; ?>); return false;" class="btn delete">🗑️ Xóa</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <div class="no-data">
            <h2>⚠️ Không tìm thấy sản phẩm nào!</h2>
            <?php if ($search): ?>
                <p>Không có sản phẩm nào khớp với từ khóa "<strong><?php echo htmlspecialchars($search); ?></strong>"</p>
                <a href="list_product.php" class="btn edit" style="margin-top: 15px;">← Quay lại danh sách</a>
            <?php else: ?>
                <p>Vui lòng thêm sản phẩm vào hệ thống!</p>
                <a href="product.php" class="btn add" style="margin-top: 15px;">➕ Thêm sản phẩm đầu tiên</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!$search && $total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>">← Trước</a>
        <?php endif; ?>
        
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?php echo $i; ?>" class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>">Sau →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="footer">
        <p>© 2025 Hệ thống quản lý cửa hàng. Thiết kế bởi <strong>Admin</strong>.</p>
    </div>

</div>

<script>
function confirmDelete(id) {
    if (confirm("⚠️ Bạn có chắc chắn muốn xóa sản phẩm này không?\n\nHành động này không thể hoàn tác!")) {
        window.location.href = "list_product.php?delete_id=" + id;
    }
}

function editDiscount(productId) {
    const cell = document.querySelector(`.discount-cell[data-product-id="${productId}"]`);
    const display = cell.querySelector('.discount-display');
    const editor = cell.querySelector('.discount-editor');
    const input = cell.querySelector('.discount-input');
    
    display.style.display = 'none';
    editor.classList.add('active');
    input.focus();
    input.select();
    
    input.onkeydown = function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveDiscount(productId);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            cancelDiscount(productId);
        }
    };
}

function cancelDiscount(productId) {
    const cell = document.querySelector(`.discount-cell[data-product-id="${productId}"]`);
    const display = cell.querySelector('.discount-display');
    const editor = cell.querySelector('.discount-editor');
    const input = cell.querySelector('.discount-input');
    const valueSpan = cell.querySelector('.discount-value');
    
    input.value = valueSpan.dataset.value;
    editor.classList.remove('active');
    display.style.display = 'flex';
}

function saveDiscount(productId) {
    const cell = document.querySelector(`.discount-cell[data-product-id="${productId}"]`);
    const input = cell.querySelector('.discount-input');
    let discount = parseInt(input.value);
    
    if (isNaN(discount) || discount < 0) discount = 0;
    if (discount > 100) discount = 100;
    
    input.value = discount;
    cell.classList.add('updating');
    
    fetch('list_product.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update_discount&product_id=${productId}&discount=${discount}`
    })
    .then(response => response.json())
    .then(data => {
        cell.classList.remove('updating');
        
        if (data.success) {
            const valueSpan = cell.querySelector('.discount-value');
            valueSpan.textContent = data.discount + '%';
            valueSpan.dataset.value = data.discount;
            
            if (data.discount == 0) {
                valueSpan.classList.add('zero');
            } else {
                valueSpan.classList.remove('zero');
            }
            
            const display = cell.querySelector('.discount-display');
            const editor = cell.querySelector('.discount-editor');
            editor.classList.remove('active');
            display.style.display = 'flex';
            
            setTimeout(() => {
                location.reload();
            }, 500);
        } else {
            alert('❌ Cập nhật thất bại: ' + (data.message || 'Lỗi không xác định'));
            cancelDiscount(productId);
        }
    })
    .catch(error => {
        cell.classList.remove('updating');
        console.error('Error:', error);
        alert('❌ Có lỗi xảy ra khi cập nhật giảm giá. Vui lòng thử lại!');
        cancelDiscount(productId);
    });
}
</script>
</body>
</html>