<?php
session_start();
include 'includes/db.php';

// Lấy ID sản phẩm
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    header("Location: index.php");
    exit;
}

// Lấy thông tin sản phẩm
$stmt = $conn->prepare("
    SELECT p.*, c.name AS category_name, b.name AS brand_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN brands b ON p.brand_id = b.id
    WHERE p.id = ?
");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: index.php");
    exit;
}

// Tính giá sale
$sale_price = $product['price'];
if ($product['discount'] > 0) {
    $sale_price = $product['price'] * (100 - $product['discount']) / 100;
}

// Lấy số lượng trong giỏ hàng
$cart_count = 0;
if (isset($_SESSION['user'])) {
    $stmt = $conn->prepare("
        SELECT SUM(ci.quantity)
        FROM cart_items ci
        JOIN carts c ON ci.cart_id = c.id
        WHERE c.user_id = ?
    ");
    $stmt->execute([$_SESSION['user']['id']]);
    $cart_count = $stmt->fetchColumn() ?? 0;
}

// Xử lý mua ngay
if (isset($_GET['buy_now']) && isset($_SESSION['user'])) {
    $quantity = isset($_GET['quantity']) ? max(1, (int)$_GET['quantity']) : 1;
    
    if ($product['stock'] < $quantity) {
        $error_msg = "Không đủ hàng trong kho để mua ngay.";
    } else {
        $user_id = $_SESSION['user']['id'];
        
        // Tạo hoặc lấy cart
        $stmt = $conn->prepare("SELECT id FROM carts WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $cart_id = $stmt->fetchColumn();

        if (!$cart_id) {
            $stmt = $conn->prepare("INSERT INTO carts (user_id) VALUES (?)");
            $stmt->execute([$user_id]);
            $cart_id = $conn->lastInsertId();
        }

        // Kiểm tra xem sản phẩm đã có trong giỏ chưa
        $stmt = $conn->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?");
        $stmt->execute([$cart_id, $product_id]);
        $existing_item = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_item) {
            // Cập nhật số lượng
            $new_qty = $existing_item['quantity'] + $quantity;
            if ($new_qty > $product['stock']) {
                $error_msg = "Không đủ hàng trong kho.";
            } else {
                $stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
                $stmt->execute([$new_qty, $existing_item['id']]);
                
                // Chuyển đến checkout với cart_item_id
                header("Location: checkout.php?selected[]=" . $existing_item['id']);
                exit;
            }
        } else {
            // Thêm mới vào giỏ
            $stmt = $conn->prepare("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$cart_id, $product_id, $quantity]);
            $cart_item_id = $conn->lastInsertId();
            
            // Chuyển đến checkout với cart_item_id mới
            header("Location: checkout.php?selected[]=" . $cart_item_id);
            exit;
        }
    }
}

// Xử lý thêm vào giỏ hàng
if (isset($_GET['add_to_cart']) && isset($_SESSION['user'])) {
    $quantity = isset($_GET['quantity']) ? max(1, (int)$_GET['quantity']) : 1;
    
    // Kiểm tra tồn kho
    if ($product['stock'] < $quantity) {
        $error_msg = "Không đủ hàng trong kho. Chỉ còn {$product['stock']} sản phẩm.";
    } else {
        $user_id = $_SESSION['user']['id'];
        
        $stmt = $conn->prepare("SELECT id FROM carts WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $cart_id = $stmt->fetchColumn();

        if (!$cart_id) {
            $stmt = $conn->prepare("INSERT INTO carts (user_id) VALUES (?)");
            $stmt->execute([$user_id]);
            $cart_id = $conn->lastInsertId();
        }

        $stmt = $conn->prepare("SELECT quantity FROM cart_items WHERE cart_id = ? AND product_id = ?");
        $stmt->execute([$cart_id, $product_id]);
        $existing_qty = $stmt->fetchColumn();

        if ($existing_qty) {
            $new_qty = $existing_qty + $quantity;
            if ($new_qty > $product['stock']) {
                $error_msg = "Không thể thêm. Tổng số lượng vượt quá tồn kho ({$product['stock']} sản phẩm).";
            } else {
                $stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND product_id = ?");
                $stmt->execute([$new_qty, $cart_id, $product_id]);
                header("Location: product_detail.php?id=$product_id&added=1");
                exit;
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$cart_id, $product_id, $quantity]);
            header("Location: product_detail.php?id=$product_id&added=1");
            exit;
        }
    }
}

// Xử lý mua ngay
if (isset($_GET['buy_now']) && isset($_SESSION['user'])) {
    $quantity = isset($_GET['quantity']) ? max(1, (int)$_GET['quantity']) : 1;
    
    if ($product['stock'] >= $quantity) {
        // Tạo session để lưu thông tin mua ngay
        $_SESSION['buy_now'] = [
            'product_id' => $product_id,
            'quantity' => $quantity,
            'price' => $sale_price
        ];
        header("Location: checkout.php");
        exit;
    } else {
        $error_msg = "Không đủ hàng trong kho để mua ngay.";
    }
}

// Lấy số lượng đã bán từ database (chỉ đếm đơn đã thanh toán)
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(oi.quantity), 0) as total_sold
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.product_id = ? 
    AND o.status IN ('completed', 'shipped', 'delivered')
");
$stmt->execute([$product_id]);
$sold_result = $stmt->fetch(PDO::FETCH_ASSOC);
$total_sold = $sold_result['total_sold'] ?? 0;

// Lấy số lượng đã bán từ database (chỉ đếm đơn đã thanh toán)
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(oi.quantity), 0) as total_sold
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.product_id = ? 
    AND o.status IN ('pending', 'completed', 'shipped', 'delivered')
");
$stmt->execute([$product_id]);
$sold_result = $stmt->fetch(PDO::FETCH_ASSOC);
$total_sold = $sold_result['total_sold'] ?? 0;

// Lấy danh sách media của sản phẩm
$stmt = $conn->prepare("SELECT * FROM product_media WHERE product_id = ? ORDER BY display_order, id");
$stmt->execute([$product_id]);
$product_media = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy sản phẩm liên quan
$stmt = $conn->prepare("
    SELECT * FROM products 
    WHERE category_id = ? AND id != ? 
    ORDER BY RAND() 
    LIMIT 4
");
$stmt->execute([$product['category_id'], $product_id]);
$related_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - Moto ABC</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script>
        // Khai báo biến toàn cục
        var maxStock = <?php echo max(1, $product['stock']); ?>;
        var currentSlideIndex = 0;
        var autoSlideInterval = null;
        
        // Slideshow functions - Phải khai báo trước khi HTML load
        function showSlide(index) {
            var slides = document.querySelectorAll('.slide');
            var dots = document.querySelectorAll('.slide-dot');
            var thumbnails = document.querySelectorAll('.thumbnail, .video-thumbnail');
            var counter = document.getElementById('currentSlideNum');
            
            if (!slides.length) return;
            
            if (index >= slides.length) {
                currentSlideIndex = 0;
            } else if (index < 0) {
                currentSlideIndex = slides.length - 1;
            } else {
                currentSlideIndex = index;
            }
            
            // Remove active class from all
            for (var i = 0; i < slides.length; i++) {
                slides[i].classList.remove('active');
            }
            for (var i = 0; i < dots.length; i++) {
                dots[i].classList.remove('active');
            }
            for (var i = 0; i < thumbnails.length; i++) {
                thumbnails[i].classList.remove('active');
            }
            
            // Add active class to current
            if (slides[currentSlideIndex]) {
                slides[currentSlideIndex].classList.add('active');
            }
            if (dots[currentSlideIndex]) {
                dots[currentSlideIndex].classList.add('active');
            }
            if (thumbnails[currentSlideIndex]) {
                thumbnails[currentSlideIndex].classList.add('active');
            }
            
            // Update counter
            if (counter) {
                counter.textContent = currentSlideIndex + 1;
            }
        }
        
        function changeSlide(direction) {
            showSlide(currentSlideIndex + direction);
        }
        
        function currentSlide(index) {
            showSlide(index);
        }
        
        function changeQty(delta) {
            var input = document.getElementById('quantity');
            var currentValue = parseInt(input.value) || 1;
            var newValue = currentValue + delta;
            
            if (newValue < 1) newValue = 1;
            if (newValue > maxStock) newValue = maxStock;
            
            input.value = newValue;
        }
        
        function addToCart() {
            var qty = document.getElementById('quantity').value;
            window.location.href = '?id=<?php echo $product_id; ?>&add_to_cart=1&quantity=' + qty;
        }
        
        function buyNow() {
            var qty = document.getElementById('quantity').value;
            window.location.href = '?id=<?php echo $product_id; ?>&buy_now=1&quantity=' + qty;
        }
        
        // Initialize when page loads
        window.onload = function() {
            <?php if (!empty($product_media)): ?>
            // Auto slide every 5 seconds
            autoSlideInterval = setInterval(function() {
                changeSlide(1);
            }, 5000);
            
            // Pause on hover
            var slideshowContainer = document.querySelector('.slideshow-container');
            if (slideshowContainer) {
                slideshowContainer.onmouseenter = function() {
                    clearInterval(autoSlideInterval);
                };
                
                slideshowContainer.onmouseleave = function() {
                    autoSlideInterval = setInterval(function() {
                        changeSlide(1);
                    }, 5000);
                };
            }
            <?php endif; ?>
            
            // Keyboard navigation
            document.onkeydown = function(e) {
                if (e.key === 'ArrowLeft') {
                    changeSlide(-1);
                } else if (e.key === 'ArrowRight') {
                    changeSlide(1);
                }
            };
        };
    </script>
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
            position: sticky; 
            top: 0; 
            z-index: 100; 
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
        
        .header a:hover { background: rgba(255, 255, 255, 0.25); }
        
        .cart-icon { position: relative; }
        
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
        
        .breadcrumb {
            padding: 20px 5%;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .breadcrumb a {
            color: #007bff;
            text-decoration: none;
            margin: 0 5px;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 30px auto;
            padding: 20px;
        }
        
        .product-detail {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 40px;
        }
        
        .product-image {
            position: relative;
            overflow: hidden;
        }
        
        .slideshow-container {
            position: relative;
            width: 100%;
        }
        
        .slide {
            display: none;
            width: 100%;
        }
        
        .slide.active {
            display: block;
        }
        
        .slide img,
        .slide video {
            width: 100%;
            height: 450px;
            object-fit: contain;
            border-radius: 15px;
            background: #f8f9fa;
        }
        
        .slide .video-embed {
            position: relative;
            width: 100%;
            height: 450px;
            border-radius: 15px;
            overflow: hidden;
            background: #000;
        }
        
        .slide .video-embed iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 15px;
        }
        
        .slide-controls {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 8px;
            z-index: 10;
            background: rgba(0, 0, 0, 0.5);
            padding: 10px 15px;
            border-radius: 25px;
        }
        
        .slide-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            cursor: pointer;
            transition: 0.3s;
        }
        
        .slide-dot.active {
            background: white;
            width: 25px;
            border-radius: 5px;
        }
        
        .slide-dot:hover {
            background: rgba(255, 255, 255, 0.9);
        }
        
        .slide-counter {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            z-index: 10;
        }
        
        .slide-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.6);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            transition: 0.3s;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .slide-arrow:hover {
            background: rgba(0, 0, 0, 0.9);
            transform: translateY(-50%) scale(1.1);
        }
        
        .slide-arrow:active {
            transform: translateY(-50%) scale(0.95);
        }
        
        .slide-arrow.prev {
            left: 20px;
        }
        
        .slide-arrow.next {
            right: 20px;
        }
        
        .thumbnail-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 12px;
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .thumbnail {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 10px;
            cursor: pointer;
            border: 3px solid #ddd;
            transition: 0.3s;
            flex-shrink: 0;
            background: white;
        }
        
        .thumbnail.active {
            border-color: #007bff;
            box-shadow: 0 0 10px rgba(0, 123, 255, 0.5);
        }
        
        .thumbnail:hover {
            opacity: 0.8;
            transform: scale(1.05);
            border-color: #007bff;
        }
        
        .video-thumbnail {
            position: relative;
            width: 100%;
            height: 100px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 3px solid #ddd;
            transition: 0.3s;
            flex-shrink: 0;
        }
        
        .video-thumbnail i {
            color: white;
            font-size: 32px;
        }
        
        .video-thumbnail.active {
            border-color: #007bff;
            box-shadow: 0 0 10px rgba(0, 123, 255, 0.5);
        }
        
        .video-thumbnail:hover {
            transform: scale(1.05);
            border-color: #007bff;
        }
        
        .thumbnail-label {
            position: absolute;
            bottom: 5px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            white-space: nowrap;
        }
        
        .product-image img {
            width: 100%;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .discount-badge {
            position: absolute;
            top: 20px;
            left: 20px;
            background: #dc3545;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 700;
            font-size: 18px;
            box-shadow: 0 3px 10px rgba(220, 53, 69, 0.4);
        }
        
        .product-info h1 {
            color: #333;
            font-size: 32px;
            margin-bottom: 15px;
        }
        
        .product-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .meta-tag {
            padding: 8px 15px;
            background: #f0f4ff;
            color: #007bff;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .price-section {
            background: linear-gradient(135deg, #f0f4ff, #e0ecff);
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .old-price {
            text-decoration: line-through;
            color: #888;
            font-size: 18px;
        }
        
        .current-price {
            color: #dc3545;
            font-size: 36px;
            font-weight: 700;
            margin: 10px 0;
        }
        
        .loyalty-points {
            background: #fff3cd;
            color: #856404;
            padding: 10px 15px;
            border-radius: 8px;
            margin-top: 10px;
            font-weight: 600;
        }
        
        .specs-section {
            margin: 25px 0;
        }
        
        .specs-section h3 {
            color: #333;
            margin-bottom: 15px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 8px;
        }
        
        .spec-row {
            display: grid;
            grid-template-columns: 150px 1fr;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        
        .spec-label {
            font-weight: 600;
            color: #666;
        }
        
        .spec-value {
            color: #333;
        }
        
        .stock-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .in-stock {
            background: #d4edda;
            color: #155724;
        }
        
        .low-stock {
            background: #fff3cd;
            color: #856404;
        }
        
        .out-of-stock {
            background: #f8d7da;
            color: #721c24;
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 25px 0;
        }
        
        .quantity-selector label {
            font-weight: 600;
            font-size: 16px;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .qty-btn {
            width: 40px;
            height: 40px;
            border: 2px solid #007bff;
            background: white;
            color: #007bff;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            font-weight: 700;
            transition: all 0.3s ease;
        }
        
        .qty-btn:hover {
            background: #007bff;
            color: white;
        }
        
        .qty-input {
            width: 80px;
            height: 40px;
            text-align: center;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 30px 0;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.3);
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        
        .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.3);
        }
        
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .related-products {
            margin-top: 50px;
        }
        
        .related-products h2 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
            font-size: 28px;
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }
        
        .product-card {
            background: white;
            padding: 15px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .product-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        
        .product-card h3 {
            font-size: 18px;
            margin: 10px 0;
        }
        
        .product-card .price {
            color: #007bff;
            font-weight: 700;
            font-size: 20px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin: 20px 0;
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
        
        .footer {
            background: #343a40;
            color: white;
            text-align: center;
            padding: 20px 0;
            margin-top: 50px;
        }
        
        @media (max-width: 768px) {
            .product-detail {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <a href="index.php"><i class="fa fa-motorcycle"></i> Moto ABC</a>
        </div>
        <div style="display: flex; align-items: center; gap: 15px;">
            <div class="cart-icon">
                <a href="cart.php">
                    <i class="fa fa-shopping-cart fa-lg"></i>
                    <span class="count"><?php echo $cart_count; ?></span>
                </a>
            </div>
            <?php if (isset($_SESSION['user'])): ?>
                <a href="order_history.php">Đơn hàng</a>
                <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                    <a href="admin.php">Quản trị</a>
                <?php endif; ?>
                <a href="logout.php">Đăng xuất</a>
            <?php else: ?>
                <a href="login.php">Đăng nhập</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="breadcrumb">
        <a href="index.php"><i class="fa fa-home"></i> Trang chủ</a> / 
        <a href="?category=<?php echo $product['category_id']; ?>"><?php echo htmlspecialchars($product['category_name']); ?></a> / 
        <span><?php echo htmlspecialchars($product['name']); ?></span>
    </div>

    <div class="container">
        <?php if (isset($_GET['added'])): ?>
            <div class="alert alert-success">
                <i class="fa fa-check-circle"></i> Đã thêm sản phẩm vào giỏ hàng thành công!
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_msg)): ?>
            <div class="alert alert-danger">
                <i class="fa fa-exclamation-circle"></i> <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <div class="product-detail">
            <div class="product-image">
                <?php if ($product['discount'] > 0): ?>
                    <div class="discount-badge">-<?php echo intval($product['discount']); ?>%</div>
                <?php endif; ?>
                
                <?php if (!empty($product_media)): ?>
                    <!-- Slideshow với nhiều ảnh/video -->
                    <div class="slideshow-container">
                        <!-- Counter -->
                        <div class="slide-counter">
                            <span id="currentSlideNum">1</span> / <span id="totalSlides"><?php echo count($product_media) + 1; ?></span>
                        </div>
                        
                        <!-- Ảnh chính của sản phẩm -->
                        <div class="slide active">
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                        </div>
                        
                        <!-- Các media khác -->
                        <?php foreach ($product_media as $media): ?>
                            <div class="slide">
                                <?php if ($media['media_type'] === 'image'): ?>
                                    <img src="<?php echo htmlspecialchars($media['media_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php else: ?>
                                    <?php
                                    $video_url = $media['media_url'];
                                    if (preg_match('/youtube\.com\/watch\?v=([^&]+)/', $video_url, $matches)) {
                                        $video_id = $matches[1];
                                        echo '<div class="video-embed">';
                                        echo '<iframe src="https://www.youtube.com/embed/' . $video_id . '?autoplay=0" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
                                        echo '</div>';
                                    } elseif (preg_match('/youtu\.be\/([^?]+)/', $video_url, $matches)) {
                                        $video_id = $matches[1];
                                        echo '<div class="video-embed">';
                                        echo '<iframe src="https://www.youtube.com/embed/' . $video_id . '?autoplay=0" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
                                        echo '</div>';
                                    } elseif (preg_match('/\.(mp4|webm|ogg)$/i', $video_url)) {
                                        echo '<video controls style="width:100%;height:500px;border-radius:15px;">';
                                        echo '<source src="' . htmlspecialchars($video_url) . '" type="video/mp4">';
                                        echo 'Your browser does not support the video tag.';
                                        echo '</video>';
                                    }
                                    ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Navigation arrows -->
                        <?php if (count($product_media) > 0): ?>
                            <button class="slide-arrow prev" type="button" onclick="changeSlide(-1)">
                                <i class="fa fa-chevron-left"></i>
                            </button>
                            <button class="slide-arrow next" type="button" onclick="changeSlide(1)">
                                <i class="fa fa-chevron-right"></i>
                            </button>
                            
                            <!-- Dots -->
                            <div class="slide-controls">
                                <span class="slide-dot active" onclick="currentSlide(0)"></span>
                                <?php foreach ($product_media as $index => $media): ?>
                                    <span class="slide-dot" onclick="currentSlide(<?php echo $index + 1; ?>)"></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Thumbnails -->
                    <?php if (count($product_media) > 0): ?>
                        <div class="thumbnail-container">
                            <div style="position: relative;">
                                <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                                     class="thumbnail active" 
                                     onclick="currentSlide(0)"
                                     alt="Main">
                                <div class="thumbnail-label">Ảnh chính</div>
                            </div>
                            <?php foreach ($product_media as $index => $media): ?>
                                <?php if ($media['media_type'] === 'image'): ?>
                                    <div style="position: relative;">
                                        <img src="<?php echo htmlspecialchars($media['media_url']); ?>" 
                                             class="thumbnail" 
                                             onclick="currentSlide(<?php echo $index + 1; ?>)"
                                             alt="Image <?php echo $index + 1; ?>">
                                        <div class="thumbnail-label">Ảnh <?php echo $index + 1; ?></div>
                                    </div>
                                <?php else: ?>
                                    <div class="video-thumbnail" onclick="currentSlide(<?php echo $index + 1; ?>)">
                                        <i class="fa fa-play-circle"></i>
                                        <div class="thumbnail-label">Video <?php echo $index + 1; ?></div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Chỉ có ảnh chính nếu không có media -->
                    <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>">
                <?php endif; ?>
            </div>

            <div class="product-info">
                <h1><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <div class="product-meta">
                    <span class="meta-tag"><i class="fa fa-tag"></i> <?php echo htmlspecialchars($product['category_name']); ?></span>
                    <span class="meta-tag"><i class="fa fa-copyright"></i> <?php echo htmlspecialchars($product['brand_name']); ?></span>
                    <span class="meta-tag"><i class="fa fa-shopping-bag"></i> Đã bán: <?php echo number_format($total_sold, 0, ',', '.'); ?></span>
                </div>

                <div class="price-section">
                    <?php if ($product['discount'] > 0): ?>
                        <div class="old-price"><?php echo number_format($product['price'], 0, ',', '.'); ?> VNĐ</div>
                    <?php endif; ?>
                    <div class="current-price"><?php echo number_format($sale_price, 0, ',', '.'); ?> VNĐ</div>
                    
                    <?php if ($product['discount'] > 0): ?>
                        <div class="loyalty-points">
                            <i class="fa fa-gift"></i> Tiết kiệm: <?php echo number_format($product['price'] - $sale_price, 0, ',', '.'); ?> VNĐ
                        </div>
                    <?php endif; ?>
                </div>

                <div class="specs-section">
                    <h3><i class="fa fa-info-circle"></i> Thông số kỹ thuật</h3>
                    <div class="spec-row">
                        <span class="spec-label">Thương hiệu:</span>
                        <span class="spec-value"><?php echo htmlspecialchars($product['brand_name']); ?></span>
                    </div>
                    <div class="spec-row">
                        <span class="spec-label">Danh mục:</span>
                        <span class="spec-value"><?php echo htmlspecialchars($product['category_name']); ?></span>
                    </div>
                    <div class="spec-row">
                        <span class="spec-label">Tình trạng:</span>
                        <span class="spec-value">
                            <?php if ($product['stock'] > 10): ?>
                                <span class="stock-status in-stock">
                                    <i class="fa fa-check-circle"></i> Còn hàng (<?php echo $product['stock']; ?>)
                                </span>
                            <?php elseif ($product['stock'] > 0): ?>
                                <span class="stock-status low-stock">
                                    <i class="fa fa-exclamation-triangle"></i> Sắp hết (còn <?php echo $product['stock']; ?>)
                                </span>
                            <?php else: ?>
                                <span class="stock-status out-of-stock">
                                    <i class="fa fa-times-circle"></i> Hết hàng
                                </span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <div class="quantity-selector">
                    <label>Số lượng:</label>
                    <div class="quantity-controls">
                        <button type="button" class="qty-btn" onclick="changeQty(-1)" <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>>-</button>
                        <input type="number" id="quantity" value="1" min="1" max="<?php echo max(1, $product['stock']); ?>" class="qty-input" readonly>
                        <button type="button" class="qty-btn" onclick="changeQty(1)" <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>>+</button>
                    </div>
                </div>

                <div class="action-buttons">
                    <?php if ($product['stock'] > 0): ?>
                        <?php if (isset($_SESSION['user'])): ?>
                            <a href="?id=<?php echo $product_id; ?>&add_to_cart=1&quantity=1" class="btn btn-primary" onclick="return addToCart(event)">
                                <i class="fa fa-shopping-cart"></i> Thêm vào giỏ
                            </a>
                            <button type="button" class="btn btn-success" onclick="buyNow()">
                                <i class="fa fa-bolt"></i> Mua ngay
                            </button>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-primary" style="grid-column: 1 / -1;">
                                <i class="fa fa-sign-in-alt"></i> Đăng nhập để mua hàng
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <button type="button" class="btn" disabled style="grid-column: 1 / -1;">
                            <i class="fa fa-ban"></i> Hết hàng
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($related_products)): ?>
            <div class="related-products">
                <h2><i class="fa fa-fire"></i> Sản phẩm liên quan</h2>
                <div class="product-grid">
                    <?php foreach ($related_products as $rp): ?>
                        <a href="product_detail.php?id=<?php echo $rp['id']; ?>" class="product-card">
                            <img src="<?php echo htmlspecialchars($rp['image']); ?>" alt="<?php echo htmlspecialchars($rp['name']); ?>">
                            <h3><?php echo htmlspecialchars($rp['name']); ?></h3>
                            <?php
                            $rp_sale_price = $rp['price'];
                            if ($rp['discount'] > 0) {
                                $rp_sale_price = $rp['price'] * (100 - $rp['discount']) / 100;
                            }
                            ?>
                            <p class="price"><?php echo number_format($rp_sale_price, 0, ',', '.'); ?> VNĐ</p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> Cửa Hàng Xe Máy - Moto ABC. Bảo lưu mọi quyền.</p>
    </div>

    <script>
        const maxStock = <?php echo max(1, $product['stock']); ?>;
        
        function changeQty(delta) {
            const input = document.getElementById('quantity');
            let currentValue = parseInt(input.value) || 1;
            let newValue = currentValue + delta;
            
            if (newValue < 1) newValue = 1;
            if (newValue > maxStock) newValue = maxStock;
            
            input.value = newValue;
        }
        
        function addToCart(e) {
            e.preventDefault();
            const qty = document.getElementById('quantity').value;
            window.location.href = `?id=<?php echo $product_id; ?>&add_to_cart=1&quantity=${qty}`;
            return false;
        }
        
        function buyNow() {
            const qty = document.getElementById('quantity').value;
            window.location.href = `?id=<?php echo $product_id; ?>&buy_now=1&quantity=${qty}`;
        }
    </script>
</body>
</html>