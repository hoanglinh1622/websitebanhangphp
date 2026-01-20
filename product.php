<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

include 'includes/db.php';

$name = $price = $stock = "";
$category_id = $brand_id = null;
$edit_mode = false;
$error = "";
$success = "";
$image = "";

// Lấy danh sách danh mục
$categories = $conn->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách thương hiệu
$brands = $conn->query("SELECT * FROM brands")->fetchAll(PDO::FETCH_ASSOC);

// Kiểm tra nếu là chế độ chỉnh sửa
if (isset($_GET['edit_id'])) {
    $edit_mode = true;
    $product_id = $_GET['edit_id'];

    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        $name = htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8');
        $price = $product['price'];
        $stock = $product['stock'] ?? 0;
        $category_id = $product['category_id'];
        $brand_id = $product['brand_id'];
        $image = $product['image'];
    }
}

// Xử lý thêm/sửa sản phẩm
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0.0;
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
    $category_id = $_POST['category_id'];
    $brand_id = $_POST['brand_id'];
    $product_id = $_POST['product_id'] ?? null;

    if (empty($name) || empty($price) || empty($category_id) || empty($brand_id)) {
        $error = "Vui lòng nhập đầy đủ thông tin!";
    } elseif ($price <= 0) {
        $error = "Giá sản phẩm phải lớn hơn 0!";
    } elseif ($stock < 0) {
        $error = "Số lượng tồn kho không được âm!";
    } else {
        // Upload hình ảnh
        if (!empty($_FILES['image']['name'])) {
            $target_dir = "uploads/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $image_name = time() . "_" . basename($_FILES['image']['name']);
            $target_file = $target_dir . $image_name;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image = $target_file;
            }
        }

        if ($product_id) {
            $stmt = $conn->prepare("UPDATE products SET name = ?, price = ?, stock = ?, category_id = ?, brand_id = ?, image = ? WHERE id = ?");
            $stmt->execute([$name, $price, $stock, $category_id, $brand_id, $image, $product_id]);
            $success = "Cập nhật sản phẩm thành công!";
        } else {
            $stmt = $conn->prepare("INSERT INTO products (name, price, stock, category_id, brand_id, image) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $price, $stock, $category_id, $brand_id, $image]);
            $success = "Thêm sản phẩm mới thành công!";
        }

        if (!$edit_mode) {
            $name = $price = $stock = "";
            $category_id = $brand_id = null;
            $image = "";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?php echo $edit_mode ? "Chỉnh sửa sản phẩm" : "Thêm sản phẩm mới"; ?></title>
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

        .container {
            width: 50%;
            margin: 50px auto;
            background: white;
            padding: 25px 30px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
        }

        .btn-back {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 15px;
            transition: 0.3s;
        }

        .btn-back:hover {
            background: #0056b3;
        }

        label {
            font-weight: bold;
            display: block;
            margin-top: 10px;
        }

        input, select {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
        }

        input[type="file"] {
            padding: 5px;
        }

        .btn-submit {
            background: #28a745;
            color: white;
            padding: 12px;
            border: none;
            cursor: pointer;
            width: 100%;
            margin-top: 15px;
            border-radius: 6px;
            font-size: 16px;
            transition: background 0.3s;
        }

        .btn-submit:hover {
            background: #218838;
        }

        .product-image {
            display: block;
            margin: 10px auto;
            max-width: 150px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }

        .message {
            color: green;
            font-weight: bold;
            text-align: center;
        }

        .error {
            color: red;
            font-weight: bold;
            text-align: center;
        }

        .stock-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo $edit_mode ? "Chỉnh sửa sản phẩm" : "Thêm sản phẩm mới"; ?></h1>
        <a href="list_product.php" class="btn-back">⮜ Quay lại danh sách sản phẩm</a>

        <?php if ($error): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>

        <?php if ($success): ?>
            <p class="message"><?php echo $success; ?></p>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="product_id" value="<?php echo $edit_mode ? $product['id'] : ''; ?>">

            <label for="name">Tên sản phẩm:</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" required>

            <label for="category_id">Danh mục:</label>
            <select name="category_id" required>
                <option value="">-- Chọn danh mục --</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id']; ?>" <?php echo ($category['id'] == $category_id) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="brand_id">Thương hiệu:</label>
            <select name="brand_id" required>
                <option value="">-- Chọn thương hiệu --</option>
                <?php foreach ($brands as $brand): ?>
                    <option value="<?php echo $brand['id']; ?>" <?php echo ($brand['id'] == $brand_id) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($brand['name'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="price">Giá sản phẩm (VNĐ):</label>
            <input type="number" name="price" step="0.01" value="<?php echo htmlspecialchars($price, ENT_QUOTES, 'UTF-8'); ?>" required>

            <label for="stock">Số lượng tồn kho:</label>
            <input type="number" name="stock" min="0" step="1" value="<?php echo htmlspecialchars($stock, ENT_QUOTES, 'UTF-8'); ?>" required>
            <div class="stock-info">
                💡 Nhập số lượng sản phẩm có sẵn trong kho. Để 0 nếu hết hàng.
            </div>

            <label for="image">Hình ảnh sản phẩm:</label>
            <input type="file" name="image">
            <?php if (!empty($image)): ?>
                <p style="text-align:center;">Ảnh hiện tại:</p>
                <img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" class="product-image">
            <?php endif; ?>

            <button type="submit" class="btn-submit">
                <?php echo $edit_mode ? "Cập nhật sản phẩm" : "Thêm sản phẩm"; ?>
            </button>
            
            <?php if ($edit_mode): ?>
                <a href="product_media.php?product_id=<?php echo $product['id']; ?>" 
                   style="display: block; text-align: center; margin-top: 15px; padding: 12px; background: #17a2b8; color: white; text-decoration: none; border-radius: 6px; font-weight: 600;">
                    <i class="fa fa-images"></i> Quản lý ảnh/video sản phẩm
                </a>
            <?php endif; ?>
        </form>
    </div>
</body>
</html>