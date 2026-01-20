<?php
session_start();
include 'includes/db.php';

// Kiểm tra quyền admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Xử lý cập nhật tồn kho
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    $product_id = (int)$_POST['product_id'];
    $stock = max(0, (int)$_POST['stock']);
    
    $stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
    $stmt->execute([$stock, $product_id]);
    
    $success_msg = "Cập nhật tồn kho thành công!";
}

// Xử lý nhập kho
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_stock'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = max(1, (int)$_POST['quantity']);
    
    $stmt = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
    $stmt->execute([$quantity, $product_id]);
    
    $success_msg = "Nhập kho thành công!";
}

// Xử lý xuất kho
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_stock'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = max(1, (int)$_POST['quantity']);
    
    $stmt = $conn->prepare("SELECT stock FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $current_stock = $stmt->fetchColumn();
    
    if ($current_stock >= $quantity) {
        $stmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $stmt->execute([$quantity, $product_id]);
        $success_msg = "Xuất kho thành công!";
    } else {
        $error_msg = "Không đủ hàng trong kho để xuất!";
    }
}

// Lấy danh sách sản phẩm với tồn kho
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';

$where = "1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (p.name LIKE ? OR c.name LIKE ? OR b.name LIKE ?)";
    $keyword = "%$search%";
    $params = [$keyword, $keyword, $keyword];
}

switch ($filter) {
    case 'in_stock':
        $where .= " AND p.stock > 10";
        break;
    case 'low_stock':
        $where .= " AND p.stock > 0 AND p.stock <= 10";
        break;
    case 'out_of_stock':
        $where .= " AND p.stock = 0";
        break;
}

$stmt = $conn->prepare("
    SELECT p.*, c.name AS category_name, b.name AS brand_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN brands b ON p.brand_id = b.id
    WHERE $where
    ORDER BY p.stock ASC, p.id DESC
");
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Thống kê tồn kho
$stmt = $conn->query("SELECT COUNT(*) FROM products WHERE stock > 10");
$in_stock_count = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COUNT(*) FROM products WHERE stock > 0 AND stock <= 10");
$low_stock_count = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COUNT(*) FROM products WHERE stock = 0");
$out_of_stock_count = $stmt->fetchColumn();

$stmt = $conn->query("SELECT SUM(stock * price) FROM products");
$total_inventory_value = $stmt->fetchColumn() ?? 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Kho hàng - Moto ABC</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f0f4ff, #ffffff);
            color: #333;
        }
        
        .header {
            background: linear-gradient(90deg, #007bff, #00aaff);
            color: white;
            padding: 15px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
        }
        
        .header a {
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 5px;
            font-weight: 500;
            transition: background 0.3s ease;
        }
        
        .header a:hover {
            background: rgba(255, 255, 255, 0.25);
        }
        
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .page-title {
            text-align: center;
            color: #007bff;
            margin-bottom: 30px;
            font-size: 32px;
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
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 40px;
            margin-bottom: 15px;
        }
        
        .stat-card.in-stock { border-left: 5px solid #28a745; }
        .stat-card.in-stock .stat-icon { color: #28a745; }
        
        .stat-card.low-stock { border-left: 5px solid #ffc107; }
        .stat-card.low-stock .stat-icon { color: #ffc107; }
        
        .stat-card.out-stock { border-left: 5px solid #dc3545; }
        .stat-card.out-stock .stat-icon { color: #dc3545; }
        
        .stat-card.value { border-left: 5px solid #007bff; }
        .stat-card.value .stat-icon { color: #007bff; }
        
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
            font-weight: 500;
        }
        
        .toolbar {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .search-box {
            flex: 1;
            min-width: 250px;
            display: flex;
            gap: 10px;
        }
        
        .search-box input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
        }
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
        }
        
        .filter-btn {
            padding: 10px 20px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .filter-btn.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .products-table {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: linear-gradient(90deg, #007bff, #00aaff);
            color: white;
        }
        
        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .product-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .stock-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .stock-in { background: #d4edda; color: #155724; }
        .stock-low { background: #fff3cd; color: #856404; }
        .stock-out { background: #f8d7da; color: #721c24; }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #000;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .btn-info:hover {
            background: #138496;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #007bff;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
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
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 768px) {
            .toolbar {
                flex-direction: column;
            }
            
            .filter-tabs {
                width: 100%;
                overflow-x: auto;
            }
            
            table {
                font-size: 14px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <a href="admin.php"><i class="fa fa-arrow-left"></i> Quay lại Quản trị</a>
        </div>
        <div>
            <a href="index.php"><i class="fa fa-home"></i> Trang chủ</a>
        </div>
    </div>

    <div class="container">
        <h1 class="page-title"><i class="fa fa-warehouse"></i> Quản lý Kho hàng</h1>

        <?php if (isset($success_msg)): ?>
            <div class="alert alert-success">
                <i class="fa fa-check-circle"></i> <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_msg)): ?>
            <div class="alert alert-danger">
                <i class="fa fa-exclamation-circle"></i> <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card in-stock">
                <div class="stat-icon"><i class="fa fa-check-circle"></i></div>
                <div class="stat-number"><?php echo $in_stock_count; ?></div>
                <div class="stat-label">Còn hàng</div>
            </div>
            
            <div class="stat-card low-stock">
                <div class="stat-icon"><i class="fa fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo $low_stock_count; ?></div>
                <div class="stat-label">Sắp hết hàng</div>
            </div>
            
            <div class="stat-card out-stock">
                <div class="stat-icon"><i class="fa fa-times-circle"></i></div>
                <div class="stat-number"><?php echo $out_of_stock_count; ?></div>
                <div class="stat-label">Hết hàng</div>
            </div>
            
            <div class="stat-card value">
                <div class="stat-icon"><i class="fa fa-coins"></i></div>
                <div class="stat-number"><?php echo number_format($total_inventory_value, 0, ',', '.'); ?></div>
                <div class="stat-label">Giá trị tồn kho (VNĐ)</div>
            </div>
        </div>

        <div class="toolbar">
            <form method="GET" class="search-box">
                <input type="text" name="search" placeholder="Tìm kiếm sản phẩm..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-search"></i> Tìm
                </button>
            </form>
            
            <div class="filter-tabs">
                <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    Tất cả
                </a>
                <a href="?filter=in_stock" class="filter-btn <?php echo $filter === 'in_stock' ? 'active' : ''; ?>">
                    Còn hàng
                </a>
                <a href="?filter=low_stock" class="filter-btn <?php echo $filter === 'low_stock' ? 'active' : ''; ?>">
                    Sắp hết
                </a>
                <a href="?filter=out_of_stock" class="filter-btn <?php echo $filter === 'out_of_stock' ? 'active' : ''; ?>">
                    Hết hàng
                </a>
            </div>
        </div>

        <div class="products-table">
            <table>
                <thead>
                    <tr>
                        <th>Hình ảnh</th>
                        <th>Tên sản phẩm</th>
                        <th>Danh mục</th>
                        <th>Thương hiệu</th>
                        <th>Giá</th>
                        <th>Tồn kho</th>
                        <th>Trạng thái</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td>
                                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="" class="product-img">
                            </td>
                            <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                            <td><?php echo htmlspecialchars($product['brand_name']); ?></td>
                            <td><?php echo number_format($product['price'], 0, ',', '.'); ?> VNĐ</td>
                            <td><strong><?php echo $product['stock']; ?></strong></td>
                            <td>
                                <?php if ($product['stock'] > 10): ?>
                                    <span class="stock-badge stock-in"><i class="fa fa-check"></i> Còn hàng</span>
                                <?php elseif ($product['stock'] > 0): ?>
                                    <span class="stock-badge stock-low"><i class="fa fa-exclamation"></i> Sắp hết</span>
                                <?php else: ?>
                                    <span class="stock-badge stock-out"><i class="fa fa-times"></i> Hết hàng</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                   
                                    <button class="btn btn-info btn-sm" onclick="openModal('update', <?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', <?php echo $product['stock']; ?>)">
                                        <i class="fa fa-edit"></i> Sửa
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Nhập kho -->
    <div id="importModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fa fa-plus-circle"></i> Nhập kho
            </div>
            <form method="POST">
                <input type="hidden" name="product_id" id="import_product_id">
                <div class="form-group">
                    <label>Sản phẩm:</label>
                    <input type="text" id="import_product_name" readonly>
                </div>
                <div class="form-group">
                    <label>Số lượng nhập:</label>
                    <input type="number" name="quantity" min="1" value="1" required>
                </div>
                <div class="modal-actions">
                    <button type="submit" name="import_stock" class="btn btn-success">
                        <i class="fa fa-check"></i> Xác nhận
                    </button>
                    <button type="button" class="btn btn-cancel" onclick="closeModal('importModal')">
                        <i class="fa fa-times"></i> Hủy
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Xuất kho -->
    <div id="exportModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fa fa-minus-circle"></i> Xuất kho
            </div>
            <form method="POST">
                <input type="hidden" name="product_id" id="export_product_id">
                <div class="form-group">
                    <label>Sản phẩm:</label>
                    <input type="text" id="export_product_name" readonly>
                </div>
                <div class="form-group">
                    <label>Số lượng xuất:</label>
                    <input type="number" name="quantity" min="1" value="1" required>
                </div>
                <div class="modal-actions">
                    <button type="submit" name="export_stock" class="btn btn-warning">
                        <i class="fa fa-check"></i> Xác nhận
                    </button>
                    <button type="button" class="btn btn-cancel" onclick="closeModal('exportModal')">
                        <i class="fa fa-times"></i> Hủy
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Cập nhật tồn kho -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fa fa-edit"></i> Cập nhật tồn kho
            </div>
            <form method="POST">
                <input type="hidden" name="product_id" id="update_product_id">
                <div class="form-group">
                    <label>Sản phẩm:</label>
                    <input type="text" id="update_product_name" readonly>
                </div>
                <div class="form-group">
                    <label>Số lượng tồn kho:</label>
                    <input type="number" name="stock" id="update_stock" min="0" required>
                </div>
                <div class="modal-actions">
                    <button type="submit" name="update_stock" class="btn btn-info">
                        <i class="fa fa-check"></i> Cập nhật
                    </button>
                    <button type="button" class="btn btn-cancel" onclick="closeModal('updateModal')">
                        <i class="fa fa-times"></i> Hủy
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(type, productId, productName, currentStock = 0) {
            if (type === 'import') {
                document.getElementById('import_product_id').value = productId;
                document.getElementById('import_product_name').value = productName;
                document.getElementById('importModal').classList.add('active');
            } else if (type === 'export') {
                document.getElementById('export_product_id').value = productId;
                document.getElementById('export_product_name').value = productName;
                document.getElementById('exportModal').classList.add('active');
            } else if (type === 'update') {
                document.getElementById('update_product_id').value = productId;
                document.getElementById('update_product_name').value = productName;
                document.getElementById('update_stock').value = currentStock;
                document.getElementById('updateModal').classList.add('active');
            }
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        // Đóng modal khi click bên ngoài
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>