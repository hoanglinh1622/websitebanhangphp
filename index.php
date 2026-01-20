<?php
session_start();
include 'includes/db.php'; // phải tạo sẵn và trả về PDO $conn

// ---------------------- ĐẾM SỐ SẢN PHẨM TRONG GIỎ ----------------------
$cart_count = 0;
$cart_items = [];
$cart_total = 0;

if (isset($_SESSION['user'])) {
    $stmt = $conn->prepare("
        SELECT SUM(ci.quantity)
        FROM cart_items ci
        JOIN carts c ON ci.cart_id = c.id
        WHERE c.user_id = ?
    ");
    $stmt->execute([$_SESSION['user']['id']]);
    $cart_count = $stmt->fetchColumn() ?? 0;
    
    // ✅ Lấy chi tiết sản phẩm trong giỏ + tính giá sale
    $stmt = $conn->prepare("
        SELECT p.*, ci.quantity, ci.product_id,
               CASE 
                   WHEN p.discount > 0 THEN p.price * (100 - p.discount) / 100
                   ELSE p.price
               END as sale_price,
               CASE 
                   WHEN p.discount > 0 THEN (p.price * (100 - p.discount) / 100) * ci.quantity
                   ELSE p.price * ci.quantity
               END as subtotal
        FROM cart_items ci
        JOIN carts c ON ci.cart_id = c.id
        JOIN products p ON ci.product_id = p.id
        WHERE c.user_id = ?
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user']['id']]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($cart_items as $item) {
        $cart_total += $item['subtotal'];
    }
}

// ---------------------- LẤY DANH MỤC & THƯƠNG HIỆU ----------------------
$categories = $conn->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC);
$brands = $conn->query("SELECT * FROM brands")->fetchAll(PDO::FETCH_ASSOC);

// ---------------------- LẤY SẢN PHẨM BÁN CHẠY & GIẢM GIÁ CHO SIDEBAR ----------------------
$bestSellers = [];
try {
    $stmt = $conn->query("
        SELECT p.id, p.name, SUM(oi.quantity) as total_sold
        FROM products p
        JOIN order_items oi ON p.id = oi.product_id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.status IN ('pending', 'completed', 'shipped', 'delivered')
        GROUP BY p.id, p.name
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $bestSellers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $bestSellers = [];
}

$stmt = $conn->prepare("SELECT id, name, discount FROM products WHERE discount > 0 ORDER BY discount DESC LIMIT 5");
$stmt->execute();
$discountProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------------------- XỬ LÝ TÌM KIẾM & LỌC ----------------------
$whereClause = "1=1";
$params = [];
$current_filter = "";

if (!empty($_GET['category'])) {
    $whereClause .= " AND p.category_id = ?";
    $params[] = $_GET['category'];
    $current_filter = "category";
} elseif (!empty($_GET['brand'])) {
    $whereClause .= " AND p.brand_id = ?";
    $params[] = $_GET['brand'];
    $current_filter = "brand";
} elseif (!empty($_GET['best_sellers'])) {
    $current_filter = "best_sellers";
}

if (!empty($_GET['search'])) {
    $whereClause .= " AND (p.name LIKE ? OR c.name LIKE ? OR b.name LIKE ?)";
    $keyword = "%" . trim($_GET['search']) . "%";
    $params = array_merge($params, [$keyword, $keyword, $keyword]);
}

$orderBy = "p.id DESC";

// ---------------------- LẤY DANH SÁCH SẢN PHẨM ----------------------
if ($current_filter === "best_sellers") {
    $stmt = $conn->prepare("
        SELECT p.*, 
               c.name AS category_name, 
               b.name AS brand_name,
               SUM(oi.quantity) as total_sold
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        JOIN order_items oi ON p.id = oi.product_id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.status IN ('delivered', 'pending')
        GROUP BY p.id
        ORDER BY total_sold DESC, p.id DESC
    ");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $conn->prepare("
        SELECT p.*, c.name AS category_name, b.name AS brand_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE $whereClause
        ORDER BY $orderBy
    ");
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------------------- XỬ LÝ AJAX CẬP NHẬT GIỎ HÀNG ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_SESSION['user'])) {
    header('Content-Type: application/json');
    
    $user_id = $_SESSION['user']['id'];
    $stmt = $conn->prepare("SELECT id FROM carts WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cart_id = $stmt->fetchColumn();
    
    if (!$cart_id) {
        $stmt = $conn->prepare("INSERT INTO carts (user_id) VALUES (?)");
        $stmt->execute([$user_id]);
        $cart_id = $conn->lastInsertId();
    }
    
    if ($_POST['action'] === 'update_quantity') {
        $product_id = (int)$_POST['product_id'];
        $quantity = (int)$_POST['quantity'];
        
        if ($quantity > 0) {
            $stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND product_id = ?");
            $stmt->execute([$quantity, $cart_id, $product_id]);
        }
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($_POST['action'] === 'remove_item') {
        $product_id = (int)$_POST['product_id'];
        $stmt = $conn->prepare("DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?");
        $stmt->execute([$cart_id, $product_id]);
        
        echo json_encode(['success' => true]);
        exit;
    }
}

// ---------------------- XỬ LÝ THÊM SẢN PHẨM VÀO GIỎ (GET add_to_cart) ----------------------
if (isset($_GET['add_to_cart']) && isset($_SESSION['user'])) {
    $product_id = (int)$_GET['add_to_cart'];
    $user_id = $_SESSION['user']['id'];
    $stmt = $conn->prepare("SELECT id FROM carts WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cart_id = $stmt->fetchColumn();

    if (!$cart_id) {
        $stmt = $conn->prepare("INSERT INTO carts (user_id) VALUES (?)");
        $stmt->execute([$user_id]);
        $cart_id = $conn->lastInsertId();
    }

    $stmt = $conn->prepare("SELECT id FROM cart_items WHERE cart_id = ? AND product_id = ?");
    $stmt->execute([$cart_id, $product_id]);
    $exists = $stmt->fetchColumn();

    if ($exists) {
        $stmt = $conn->prepare("UPDATE cart_items SET quantity = quantity + 1 WHERE cart_id = ? AND product_id = ?");
        $stmt->execute([$cart_id, $product_id]);
    } else {
        $stmt = $conn->prepare("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, 1)");
        $stmt->execute([$cart_id, $product_id]);
    }

    header("Location: index.php?added=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cửa Hàng Xe Máy - Moto ABC</title>
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
            flex-wrap: wrap; 
            gap: 15px; 
            position: sticky; 
            top: 0; 
            z-index: 100; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.15); 
        }
        
        .header-left { display: flex; align-items: center; }
        
        .header a { 
            color: white; 
            text-decoration: none; 
            padding: 10px 15px; 
            border-radius: 5px; 
            font-weight: 500; 
            transition: background 0.3s ease; 
            white-space: nowrap;
        }
        
        .header a:hover { background: rgba(255, 255, 255, 0.25); }
        
        .search-box { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            background: white; 
            padding: 5px 10px; 
            border-radius: 25px; 
            box-shadow: 0 0 10px rgba(0,0,0,0.15);
            flex: 1;
            max-width: 600px;
            margin: 0 20px;
        }
        
        .search-box input[type="text"] { 
            border: none; 
            outline: none; 
            padding: 10px 15px;
            border-radius: 25px; 
            flex: 1; 
            font-size: 15px; 
            color: #333; 
        }
        
        .search-box button { 
            background: #28a745; 
            color: white; 
            border: none; 
            padding: 10px 18px; 
            border-radius: 25px; 
            cursor: pointer; 
            font-weight: 600; 
            transition: background 0.3s ease, transform 0.2s ease; 
        }
        
        .search-box button:hover { 
            background: #218838; 
            transform: scale(1.05); 
        }
        
        .header-right { 
            display: flex; 
            align-items: center; 
            gap: 15px; 
        }
        
        .cart-icon { 
            position: relative; 
            display: inline-block; 
        }
        
        .cart-icon > a { 
            padding: 10px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: white;
        }
        
        .cart-icon .count {
            position: absolute; 
            top: -6px; 
            right: -6px; 
            background: red; 
            color: white; 
            font-size: 12px; 
            padding: 2px 6px; 
            border-radius: 50%; 
            min-width: 20px; 
            text-align: center; 
        }
        
        /* Mini Cart Dropdown */
        .mini-cart {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            width: 360px;
            max-height: 0;
            overflow: hidden;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
            margin-top: 10px;
        }
        
        .cart-icon:hover .mini-cart {
            max-height: 500px;
            opacity: 1;
            transform: translateY(0);
        }
        
        .mini-cart-header {
            background: linear-gradient(90deg, #007bff, #00aaff);
            color: white;
            padding: 15px;
            border-radius: 10px 10px 0 0;
            font-weight: 600;
        }
        
        .mini-cart-items {
            max-height: 300px;
            overflow-y: auto;
            padding: 10px;
        }
        
        .mini-cart-item {
            display: flex;
            gap: 10px;
            padding: 10px;
            border-bottom: 1px solid #eee;
            transition: background 0.3s ease;
            position: relative;
        }
        
        .mini-cart-item:hover {
            background: #f8f9fa;
        }
        
        .mini-cart-item:last-child {
            border-bottom: none;
        }
        
        .mini-cart-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .mini-cart-item-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .mini-cart-item-name {
            font-weight: 600;
            color: #333;
            font-size: 14px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .mini-cart-item-price {
            font-size: 13px;
        }
        
        .mini-cart-item-controls {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 5px;
        }
        
        .mini-cart-qty-btn {
            width: 24px;
            height: 24px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 14px;
            color: #333;
        }
        
        .mini-cart-qty-btn:hover {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .mini-cart-qty-input {
            width: 40px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 4px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .mini-cart-item-remove {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #dc3545;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: all 0.3s ease;
            font-size: 12px;
        }
        
        .mini-cart-item:hover .mini-cart-item-remove {
            opacity: 1;
        }
        
        .mini-cart-item-remove:hover {
            background: #c82333;
            transform: scale(1.1);
        }
        
        .mini-cart-empty {
            padding: 40px 20px;
            text-align: center;
            color: #999;
        }
        
        .mini-cart-empty i {
            font-size: 48px;
            margin-bottom: 10px;
            opacity: 0.5;
        }
        
        .mini-cart-footer {
            padding: 15px;
            border-top: 2px solid #eee;
            background: #f8f9fa;
            border-radius: 0 0 10px 10px;
        }
        
        .mini-cart-total {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-weight: 600;
            font-size: 16px;
            color: #333;
        }
        
        .mini-cart-total span:last-child {
            color: #007bff;
        }
        
        .mini-cart-actions {
            display: flex;
            gap: 10px;
        }
        
        .mini-cart-btn {
            flex: 1;
            padding: 10px;
            text-align: center;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .mini-cart-btn-view {
            background: #007bff;
            color: white;
        }
        
        .mini-cart-btn-view:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }
        
        .mini-cart-btn-checkout {
            background: #28a745;
            color: white;
        }
        
        .mini-cart-btn-checkout:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .sidebar p a.active {
            background: #007bff !important;
            color: white !important;
            transform: translateX(5px);
        }
        
        .sidebar-best-seller.active {
            background: #007bff !important;
            color: white !important;
            transform: translateX(5px);
        }
        
        .mini-cart-items::-webkit-scrollbar {
            width: 6px;
        }
        
        .mini-cart-items::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .mini-cart-items::-webkit-scrollbar-thumb {
            background: #007bff;
            border-radius: 3px;
        }
        
        .mini-cart-items::-webkit-scrollbar-thumb:hover {
            background: #0056b3;
        }
        
        /* Slideshow Styles */
        .slideshow-container {
            position: relative;
            width: 100%;
            max-width: 100%;
            height: 450px;
            overflow: hidden;
            background: #000;
            margin-bottom: 30px;
        }
        
        .slide {
            display: none;
            position: absolute;
            width: 100%;
            height: 100%;
            animation: fadeIn 1s ease-in-out;
        }
        
        .slide.active {
            display: block;
        }
        
        .slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: brightness(0.7);
        }
        
        .slide-caption {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: white;
            z-index: 2;
            width: 80%;
            max-width: 800px;
        }
        
        .slide-caption h2 {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 15px;
            text-shadow: 2px 2px 10px rgba(0,0,0,0.7);
            animation: slideUp 1s ease-out;
        }
        
        .slide-caption p {
            font-size: 20px;
            margin-bottom: 25px;
            text-shadow: 1px 1px 5px rgba(0,0,0,0.7);
            animation: slideUp 1s ease-out 0.2s both;
        }
        
        .slide-btn {
            display: inline-block;
            background: linear-gradient(90deg, #007bff, #00aaff);
            color: white;
            padding: 14px 35px;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,123,255,0.4);
            animation: slideUp 1s ease-out 0.4s both;
        }
        
        .slide-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,123,255,0.6);
        }
        
        .slide-prev, .slide-next {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 15px 20px;
            text-decoration: none;
            font-size: 24px;
            border-radius: 5px;
            cursor: pointer;
            z-index: 3;
            transition: all 0.3s ease;
            backdrop-filter: blur(5px);
            user-select: none;
        }
        
        .slide-prev:hover, .slide-next:hover {
            background: rgba(255,255,255,0.4);
        }
        
        .slide-prev {
            left: 20px;
        }
        
        .slide-next {
            right: 20px;
        }
        
        .slide-dots {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 12px;
            z-index: 3;
        }
        
        .dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .dot:hover {
            background: rgba(255,255,255,0.8);
            transform: scale(1.2);
        }
        
        .dot.active {
            background: white;
            width: 35px;
            border-radius: 7px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(30px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade {
            animation-name: fade;
            animation-duration: 1s;
        }
        
        @keyframes fade {
            from { opacity: 0.4; }
            to { opacity: 1; }
        }
        
        .container {
            width: 90%; 
            margin: auto; 
            padding-top: 25px; 
            display: flex; 
            gap: 30px; 
        }
        
        .sidebar { 
            width: 25%; 
            background: white; 
            padding: 20px; 
            border-radius: 15px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 100px;
        }
        
        .sidebar h3 { 
            border-bottom: 2px solid #007bff; 
            padding-bottom: 8px; 
            color: #007bff; 
            margin-bottom: 15px;
        }
        
        .sidebar p { margin-bottom: 5px; }
        
        .sidebar p a { 
            color: #333; 
            text-decoration: none; 
            display: block; 
            padding: 8px 10px; 
            transition: all 0.3s ease;
            border-radius: 5px;
        }
        
        .sidebar p a:hover { 
            color: #007bff; 
            background: #f0f4ff;
            transform: translateX(5px); 
        }
        
        .content { flex: 1; }
        
        h1 { 
            color: #007bff; 
            text-align: center; 
            margin-bottom: 25px; 
        }
        
        .product-list {
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 25px; 
        }
        
        .product { 
            background: white; 
            padding: 15px; 
            border-radius: 15px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1); 
            text-align: center; 
            transition: transform 0.3s ease, box-shadow 0.3s ease; 
            position: relative; 
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .product:hover { 
            transform: translateY(-8px); 
            box-shadow: 0 10px 25px rgba(0,0,0,0.15); 
            text-decoration: none;
            color: inherit;
        }
        
        .product img { 
            width: 100%; 
            height: 200px; 
            object-fit: cover; 
            border-radius: 10px; 
            transition: transform 0.3s ease;
            margin-bottom: 10px;
        }
        
        .product:hover img { transform: scale(1.05); }
        
        .product h3 {
            margin: 10px 0;
            font-size: 18px;
            color: #333;
        }
        
        .product p {
            margin: 10px 0;
            color: #666;
        }
        
        .btn { 
            background: #007bff; 
            color: white; 
            padding: 10px 20px; 
            text-decoration: none; 
            border-radius: 25px; 
            display: inline-block; 
            transition: background 0.3s ease, transform 0.2s ease;
            cursor: pointer;
            border: none;
            font-size: 14px;
            font-weight: 500;
        }
        
        .btn:hover { 
            background: #0056b3; 
            transform: scale(1.05); 
        }
        
        .footer {
            background: #343a40; 
            color: white; 
            text-align: center; 
            padding: 20px 0; 
            margin-top: 40px; 
            box-shadow: 0 -3px 10px rgba(0,0,0,0.2); 
        }
        
        .footer p { margin: 10px 0; }
        
        .footer a { 
            color: #f8f9fa; 
            text-decoration: none; 
            margin: 0 10px; 
        }
        
        .footer a:hover { text-decoration: underline; }
        
        .flying-img { 
            position: fixed; 
            z-index: 9999; 
            width: 100px; 
            height: 100px; 
            transition: all 1s cubic-bezier(0.68, -0.55, 0.265, 1.55); 
            pointer-events: none; 
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .shake {
            animation: shake 0.5s;
        }
        
        @keyframes shake { 
            0%, 100% { transform: translate(0,0);} 
            25% { transform: translate(-5px,0);} 
            75% { transform: translate(5px,0);} 
        }
        
        .success-message {
            position: fixed;
            top: 100px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            z-index: 1000;
            animation: slideIn 0.5s ease;
        }
        
        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @media (max-width: 1024px) {
            .container { width: 95%; gap: 20px; }
            .search-box { max-width: 480px; }
            .slideshow-container { height: 350px; }
            .slide-caption h2 { font-size: 32px; }
            .slide-caption p { font-size: 16px; }
        }
        @media (max-width: 768px) { 
            .container { flex-direction: column; } 
            .sidebar { width: 100%; position: relative; top: 0; }
            .header { flex-direction: column; }
            .search-box { max-width: 100%; }
            .product-list { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
            .slideshow-container { height: 280px; }
            .slide-caption h2 { font-size: 24px; }
            .slide-caption p { font-size: 14px; }
            .slide-btn { padding: 10px 25px; font-size: 14px; }
            .slide-prev, .slide-next { padding: 10px 15px; font-size: 18px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <a href="index.php"><i class="fa fa-motorcycle"></i> Cửa hàng moto xe máy NPL</a>
            <a href="">Xin chào "<?php echo htmlspecialchars($_SESSION['user']['username'] ?? 'Khách'); ?>"</a> 
        </div>

        <form method="GET" class="search-box">
            <input type="text" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="🔍 Tìm kiếm...">
            <button type="submit">Tìm</button>
        </form>

        <div class="header-right">
            <div class="cart-icon" id="cartIcon">
                <a href="cart.php">
                    <i class="fa fa-shopping-cart fa-lg"></i>
                    <span class="count" id="cartCount"><?php echo $cart_count; ?></span>
                </a>

                <!-- Mini Cart Dropdown -->
                <div class="mini-cart" id="miniCartDropdown">
                    <div class="mini-cart-header">
                        <i class="fa fa-shopping-cart"></i> Giỏ hàng của bạn
                    </div>
                    
                    <?php if (empty($cart_items)): ?>
                        <div class="mini-cart-empty">
                            <i class="fa fa-shopping-basket"></i>
                            <p>Giỏ hàng trống</p>
                        </div>
                    <?php else: ?>
                        <div class="mini-cart-items">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="mini-cart-item" data-product-id="<?php echo $item['product_id']; ?>">
                                    <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                    <div class="mini-cart-item-info">
                                        <div class="mini-cart-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                        <div class="mini-cart-item-price" data-price="<?php echo $item['sale_price']; ?>">
                                            <?php if (!empty($item['discount']) && $item['discount'] > 0): ?>
                                                <span style="text-decoration:line-through; color:#888; font-size:11px;">
                                                    <?php echo number_format($item['price'], 0, ',', '.'); ?> VNĐ
                                                </span>
                                                <br>
                                                <span style="color:#d93025; font-weight:700;">
                                                    <?php echo number_format($item['sale_price'], 0, ',', '.'); ?> VNĐ
                                                </span>
                                            <?php else: ?>
                                                <span style="color:#007bff; font-weight:600;">
                                                    <?php echo number_format($item['price'], 0, ',', '.'); ?> VNĐ
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="mini-cart-item-controls">
                                            <button class="mini-cart-qty-btn" onclick="updateQuantity(<?php echo $item['product_id']; ?>, -1)">
                                                <i class="fa fa-minus"></i>
                                            </button>
                                            <input type="number" class="mini-cart-qty-input" 
                                                   value="<?php echo $item['quantity']; ?>" 
                                                   min="1" 
                                                   onchange="updateQuantityDirect(<?php echo $item['product_id']; ?>, this.value)">
                                            <button class="mini-cart-qty-btn" onclick="updateQuantity(<?php echo $item['product_id']; ?>, 1)">
                                                <i class="fa fa-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mini-cart-item-remove" onclick="removeItem(<?php echo $item['product_id']; ?>)">
                                        <i class="fa fa-times"></i>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mini-cart-footer">
                            <div class="mini-cart-total">
                                <span>Tổng cộng:</span>
                                <span id="miniCartTotal"><?php echo number_format($cart_total, 0, ',', '.'); ?> VNĐ</span>
                            </div>
                            <div class="mini-cart-actions">
                                <a href="cart.php" class="mini-cart-btn mini-cart-btn-view">Xem giỏ hàng</a>
                                <a href="checkout.php" class="mini-cart-btn mini-cart-btn-checkout">Thanh toán</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <a href="order_history.php" class="btn">Lịch sử đơn hàng</a>

            <?php if (isset($_SESSION['user'])): ?>
                <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                    <a href="admin.php" class="btn">🔧 Quản trị</a>
                <?php endif; ?>
                <a href="logout.php" class="btn">Đăng xuất</a>
            <?php else: ?>
                <a href="login.php" class="btn">Đăng nhập</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_GET['added'])): ?>
        <div class="success-message" id="successMsg">
            <i class="fa fa-check-circle"></i> Đã thêm sản phẩm vào giỏ hàng!
        </div>
    <?php endif; ?>

    <!-- Slideshow Banner -->
    <div class="slideshow-container">
        <div class="slide fade active">
            <img src="https://images.unsplash.com/photo-1558981403-c5f9899a28bc?w=1920&h=600&fit=crop" alt="Khuyến mãi xe máy">
            <div class="slide-caption">
                <h2>🏍️ Khuyến mãi lớn - Giảm đến 15%</h2>
                <p>Mua xe máy chính hãng với giá tốt nhất</p>
            </div>
        </div>

        <div class="slide fade">
            <img src="https://images.unsplash.com/photo-1568772585407-9361f9bf3a87?w=1920&h=600&fit=crop" alt="Xe máy mới 2024">
            <div class="slide-caption">
                <h2>🔥Với những chiếc xe</h2>
                <p>Công nghệ hiện đại - Thiết kế đột phá</p>
            </div>
        </div>

        <div class="slide fade">
            <img src="https://images.unsplash.com/photo-1609630875171-b1321377ee65?w=1920&h=600&fit=crop" alt="Trả góp 0%">
            <div class="slide-caption">
                <h2> Theo đuổi đam mê của bạn </h2>
                <p>Sở hữu xe mơ ước </p>
            </div>
        </div>

        <!-- Navigation buttons -->
        <a class="slide-prev" onclick="changeSlide(-1)">&#10094;</a>
        <a class="slide-next" onclick="changeSlide(1)">&#10095;</a>

        <!-- Dots indicator -->
        <div class="slide-dots">
            <span class="dot active" onclick="currentSlide(1)"></span>
            <span class="dot" onclick="currentSlide(2)"></span>
            <span class="dot" onclick="currentSlide(3)"></span>
        </div>
    </div>

    <div class="container">
        <div class="sidebar">
            <h3>📌 Danh mục sản phẩm</h3>
            <?php foreach ($categories as $cat): ?>
                <p><a href="?category=<?php echo $cat['id']; ?>" 
                      class="<?php echo (!empty($_GET['category']) && $_GET['category'] == $cat['id']) ? 'active' : ''; ?>">
                    👉 <?php echo htmlspecialchars($cat['name']); ?>
                </a></p>
            <?php endforeach; ?>

            <h3>🏷️ Thương hiệu</h3>
            <?php foreach ($brands as $brand): ?>
                <p><a href="?brand=<?php echo $brand['id']; ?>" 
                      class="<?php echo (!empty($_GET['brand']) && $_GET['brand'] == $brand['id']) ? 'active' : ''; ?>">
                    🔹 <?php echo htmlspecialchars($brand['name']); ?>
                </a></p>
            <?php endforeach; ?>

            <h3 style="margin-top:18px;">🔥 Sản phẩm bán chạy</h3>
            <?php if (!empty($bestSellers)): ?>
                <ul style="list-style:none; padding-left:0;">
                    <?php foreach ($bestSellers as $bs): ?>
                        <li style="padding:8px 10px; border-radius:5px; transition:all 0.3s ease; margin-bottom:5px;">
                            <a href="product_detail.php?id=<?php echo $bs['id']; ?>" 
                               style="text-decoration:none; color:#333; display:flex; justify-content:space-between; align-items:center;">
                                <span>🔥 <?php echo htmlspecialchars($bs['name']); ?></span>
                                <span style="background:#e74c3c; color:white; padding:3px 8px; border-radius:12px; font-size:12px; font-weight:700;">
                                    <?php echo $bs['total_sold']; ?>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p style="margin-top:10px;">
                    <a href="?best_sellers=1" 
                       class="<?php echo (!empty($_GET['best_sellers'])) ? 'active' : ''; ?>"
                       style="display:block; padding:8px 10px; border-radius:5px; transition:all 0.3s ease; background:#f0f4ff; color:#007bff; text-align:center; font-weight:600;">
                        👉 Xem tất cả sản phẩm bán chạy
                    </a>
                </p>
            <?php else: ?>
                <p style="color:#999; padding:10px;">Chưa có sản phẩm bán chạy</p>
            <?php endif; ?>

            <h3 style="margin-top:18px;">💸 Sản phẩm đang giảm giá</h3>
            <ul style="list-style:none; padding-left:0; color:#333;">
                <?php if (!empty($discountProducts)): ?>
                    <?php foreach ($discountProducts as $dp): ?>
                        <li style="padding:6px 10px; border-radius:6px;">
                            <a href="product_detail.php?id=<?php echo $dp['id']; ?>" style="text-decoration:none; color:#333;">
                                🏷️ <?php echo htmlspecialchars($dp['name']); ?>
                                <span style="color:#d93025; font-weight:700; font-size:13px; margin-left:6px;">-<?php echo intval($dp['discount']); ?>%</span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li style="color:#999; padding:10px;">Không có sản phẩm giảm giá</li>
                <?php endif; ?>
            </ul>
        </div>
        
        <div class="content">
            <?php if ($current_filter === "best_sellers"): ?>
                <h1>🔥 Sản phẩm bán chạy</h1>
            <?php elseif (!empty($_GET['category']) && !empty($products)): ?>
                <h1>Danh mục: <?php echo htmlspecialchars($products[0]['category_name']); ?></h1>
            <?php elseif (!empty($_GET['brand']) && !empty($products)): ?>
                <h1>Thương hiệu: <?php echo htmlspecialchars($products[0]['brand_name']); ?></h1>
            <?php elseif (!empty($_GET['search'])): ?>
                <h1>Kết quả tìm kiếm: "<?php echo htmlspecialchars($_GET['search']); ?>"</h1>
            <?php else: ?>
                <h1>Danh sách sản phẩm</h1>
            <?php endif; ?>

            <?php
            $products_per_page = 12; // Hiển thị 12 sản phẩm = 3 hàng x 4 cột
            $page = max(1, (int)($_GET['page'] ?? 1));
            $total_products = count($products);
            $total_pages = max(1, ceil($total_products / $products_per_page));
            $current_products = array_slice($products, ($page - 1) * $products_per_page, $products_per_page);
            ?>

            <div class="product-list">
                <?php foreach ($current_products as $product): ?>
                    <a href="product_detail.php?id=<?php echo $product['id']; ?>" class="product">
                        <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                        <?php if (!empty($product['discount']) && $product['discount'] > 0): ?>
                            <?php
                                $discounted = $product['price'] * (100 - $product['discount']) / 100;
                            ?>
                            <p><strong>Giá:</strong> <span style="text-decoration:line-through; color:#888;"><?php echo number_format($product['price'], 0, ',', '.'); ?> VNĐ</span>
                            <span style="color:#d93025; font-weight:700; margin-left:8px;"><?php echo number_format($discounted, 0, ',', '.'); ?> VNĐ</span></p>
                        <?php else: ?>
                            <p><strong>Giá:</strong> <?php echo number_format($product['price'], 0, ',', '.'); ?> VNĐ</p>
                        <?php endif; ?>

                        <?php if ($current_filter === "best_sellers"): ?>
                            <p style="color: #e74c3c; font-weight: 600;">
                                <i class="fa fa-fire"></i> Đã bán: <?php echo intval($product['total_sold']); ?>
                            </p>
                        <?php endif; ?>

                        <button type="button" class="btn" onclick="event.preventDefault(); event.stopPropagation(); flyToCart(event, this, <?php echo $product['id']; ?>);">
                            🛒 Thêm vào giỏ
                        </button>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if (empty($products)): ?>
                <div style="text-align:center; padding:40px; color:#666;">
                    <i class="fa fa-search" style="font-size:48px; margin-bottom:15px; opacity:0.5;"></i>
                    <h3>Không tìm thấy sản phẩm nào</h3>
                    <p>Hãy thử tìm kiếm với từ khóa khác hoặc duyệt các danh mục khác.</p>
                </div>
            <?php endif; ?>

            <div style="text-align:center; margin-top:25px;">
                <?php if ($total_pages > 1): ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo !empty($_GET['category']) ? '&category=' . $_GET['category'] : ''; ?><?php echo !empty($_GET['brand']) ? '&brand=' . $_GET['brand'] : ''; ?><?php echo !empty($_GET['best_sellers']) ? '&best_sellers=1' : ''; ?><?php echo !empty($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>" 
                           style="margin:0 5px; padding:8px 12px; border-radius:6px; 
                                  background:<?php echo $i == $page ? '#007bff' : '#f0f4ff'; ?>; 
                                  color:<?php echo $i == $page ? 'white' : '#007bff'; ?>; 
                                  text-decoration:none; 
                                  font-weight:600;">
                           <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> Cửa Hàng Moto - Xe Máy ABC. Bảo lưu mọi quyền.</p>
        <p>
            <a href="about.php">Giới thiệu</a> | 
            <a href="contact.php">Liên hệ</a> | 
            <a href="policies.php">Chính sách</a>
        </p>
    </div>

    <script>
        function flyToCart(event, button, productId) {
            event.preventDefault();
            event.stopPropagation();
            
            const product = button.closest('.product');
            const img = product.querySelector('img');
            const flyingImg = img.cloneNode(true);
            flyingImg.classList.add('flying-img');

            const imgRect = img.getBoundingClientRect();
            flyingImg.style.left = imgRect.left + 'px';
            flyingImg.style.top = imgRect.top + 'px';
            flyingImg.style.position = 'fixed';
            document.body.appendChild(flyingImg);

            const cart = document.getElementById('cartIcon');
            const cartRect = cart.getBoundingClientRect();

            setTimeout(() => {
                flyingImg.style.left = cartRect.left + 'px';
                flyingImg.style.top = cartRect.top + 'px';
                flyingImg.style.width = '30px';
                flyingImg.style.height = '30px';
                flyingImg.style.opacity = '0';
            }, 100);

            setTimeout(() => {
                flyingImg.remove();
                
                const cartIcon = document.getElementById('cartIcon');
                cartIcon.classList.add('shake');
                setTimeout(() => cartIcon.classList.remove('shake'), 500);
                
                window.location.href = '?add_to_cart=' + productId;
            }, 1100);
        }

        const successMsg = document.getElementById('successMsg');
        if (successMsg) {
            setTimeout(() => {
                successMsg.style.opacity = '0';
                successMsg.style.transform = 'translateX(400px)';
                setTimeout(() => successMsg.remove(), 500);
            }, 3000);
        }
        
        function updateQuantity(productId, change) {
            const item = document.querySelector(`[data-product-id="${productId}"]`);
            if (!item) return;
            const input = item.querySelector('.mini-cart-qty-input');
            let newQty = parseInt(input.value) + change;
            
            if (isNaN(newQty) || newQty < 1) newQty = 1;
            
            input.value = newQty;
            updateCart(productId, newQty);
        }
        
        function updateQuantityDirect(productId, quantity) {
            quantity = parseInt(quantity);
            if (isNaN(quantity) || quantity < 1) quantity = 1;
            updateCart(productId, quantity);
        }
        
        function updateCart(productId, quantity) {
            fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_quantity&product_id=${productId}&quantity=${quantity}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    recalculateTotal();
                    updateCartCount();
                }
            }).catch(err => {
                console.error('Update cart error', err);
            });
        }
        
        function removeItem(productId) {
            if (!confirm('Bạn có chắc muốn xóa sản phẩm này?')) return;
            
            fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=remove_item&product_id=${productId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const item = document.querySelector(`[data-product-id="${productId}"]`);
                    if (item) {
                        item.style.opacity = '0';
                        item.style.transform = 'translateX(-100%)';
                        
                        setTimeout(() => {
                            item.remove();
                            recalculateTotal();
                            updateCartCount();
                            
                            const remainingItems = document.querySelectorAll('.mini-cart-item');
                            if (remainingItems.length === 0) {
                                location.reload();
                            }
                        }, 300);
                    } else {
                        location.reload();
                    }
                }
            }).catch(err => {
                console.error('Remove item error', err);
            });
        }
        
        function recalculateTotal() {
            let total = 0;
            document.querySelectorAll('.mini-cart-item').forEach(item => {
                const price = parseFloat(item.querySelector('.mini-cart-item-price').dataset.price);
                const quantity = parseInt(item.querySelector('.mini-cart-qty-input').value);
                if (!isNaN(price) && !isNaN(quantity)) {
                    total += price * quantity;
                }
            });
            
            const totalElement = document.getElementById('miniCartTotal');
            if (totalElement) {
                totalElement.textContent = total.toLocaleString('vi-VN') + ' VNĐ';
            }
        }
        
        function updateCartCount() {
            let count = 0;
            document.querySelectorAll('.mini-cart-qty-input').forEach(input => {
                const v = parseInt(input.value);
                if (!isNaN(v)) count += v;
            });
            
            const countElement = document.getElementById('cartCount');
            if (countElement) {
                countElement.textContent = count;
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            updateCartCount();
            recalculateTotal();
        });
        
        // ==================== SLIDESHOW FUNCTIONALITY ====================
        let slideIndex = 1;
        let slideTimer;
        
        // Auto slide every 5 seconds
        function autoSlide() {
            slideTimer = setInterval(() => {
                changeSlide(1);
            }, 5000);
        }
        
        function changeSlide(n) {
            clearInterval(slideTimer);
            showSlide(slideIndex += n);
            autoSlide();
        }
        
        function currentSlide(n) {
            clearInterval(slideTimer);
            showSlide(slideIndex = n);
            autoSlide();
        }
        
        function showSlide(n) {
            const slides = document.querySelectorAll('.slide');
            const dots = document.querySelectorAll('.dot');
            
            if (n > slides.length) { slideIndex = 1; }
            if (n < 1) { slideIndex = slides.length; }
            
            slides.forEach(slide => {
                slide.classList.remove('active');
            });
            
            dots.forEach(dot => {
                dot.classList.remove('active');
            });
            
            if (slides[slideIndex - 1]) {
                slides[slideIndex - 1].classList.add('active');
            }
            if (dots[slideIndex - 1]) {
                dots[slideIndex - 1].classList.add('active');
            }
        }
        
        // Start slideshow when page loads
        if (document.querySelector('.slideshow-container')) {
            autoSlide();
        }
    </script>
</body>
</html>