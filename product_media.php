<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

include 'includes/db.php';

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$error = "";
$success = "";

// Lấy thông tin sản phẩm
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: list_product.php');
    exit();
}

// Xử lý upload nhiều ảnh
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_images'])) {
    $target_dir = "uploads/products/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $uploaded_count = 0;
    $error_count = 0;
    
    if (!empty($_FILES['media_files']['name'][0])) {
        $total_files = count($_FILES['media_files']['name']);
        
        for ($i = 0; $i < $total_files; $i++) {
            if ($_FILES['media_files']['error'][$i] === UPLOAD_ERR_OK) {
                $file_extension = strtolower(pathinfo($_FILES['media_files']['name'][$i], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $new_filename = time() . '_' . uniqid() . '_' . $i . '.' . $file_extension;
                    $target_file = $target_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['media_files']['tmp_name'][$i], $target_file)) {
                        $stmt = $conn->prepare("INSERT INTO product_media (product_id, media_type, media_url) VALUES (?, 'image', ?)");
                        $stmt->execute([$product_id, $target_file]);
                        $uploaded_count++;
                    } else {
                        $error_count++;
                    }
                } else {
                    $error_count++;
                }
            }
        }
        
        if ($uploaded_count > 0) {
            $success = "Đã upload thành công $uploaded_count ảnh!";
        }
        if ($error_count > 0) {
            $error = "Có $error_count ảnh không upload được!";
        }
    } else {
        $error = "Vui lòng chọn ít nhất 1 ảnh!";
    }
}

// Xử lý thêm nhiều video
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_videos'])) {
    $video_urls = $_POST['video_urls'] ?? [];
    $added_count = 0;
    
    foreach ($video_urls as $video_url) {
        $video_url = trim($video_url);
        if (!empty($video_url)) {
            $stmt = $conn->prepare("INSERT INTO product_media (product_id, media_type, media_url) VALUES (?, 'video', ?)");
            $stmt->execute([$product_id, $video_url]);
            $added_count++;
        }
    }
    
    if ($added_count > 0) {
        $success = "Đã thêm thành công $added_count video!";
    } else {
        $error = "Không có video nào được thêm!";
    }
}

// Xử lý xóa media
if (isset($_GET['delete_media'])) {
    $media_id = (int)$_GET['delete_media'];
    
    $stmt = $conn->prepare("SELECT * FROM product_media WHERE id = ? AND product_id = ?");
    $stmt->execute([$media_id, $product_id]);
    $media = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($media) {
        if ($media['media_type'] === 'image' && file_exists($media['media_url'])) {
            unlink($media['media_url']);
        }
        
        $stmt = $conn->prepare("DELETE FROM product_media WHERE id = ?");
        $stmt->execute([$media_id]);
        $success = "Xóa media thành công!";
    }
}

// Lấy danh sách media
$stmt = $conn->prepare("SELECT * FROM product_media WHERE product_id = ? ORDER BY display_order, id DESC");
$stmt->execute([$product_id]);
$media_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý ảnh/video - <?php echo htmlspecialchars($product['name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Segoe UI", Arial, sans-serif;
        }
        
        body {
            background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)),
                        url("assets/moto2.jpg") no-repeat center center/cover;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }
        
        h1 {
            color: #333;
            font-size: 28px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
            display: inline-block;
            border: none;
            cursor: pointer;
        }
        
        .btn-back {
            background: #3498db;
            color: white;
        }
        
        .btn-back:hover {
            background: #2980b9;
        }
        
        .alert {
            padding: 15px;
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
        
        .upload-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        .upload-section h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 20px;
        }
        
        .tab-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .tab-btn {
            padding: 10px 20px;
            background: white;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.3s;
        }
        
        .tab-btn.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        
        input[type="file"],
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .btn-primary {
            background: #27ae60;
            color: white;
            padding: 12px 30px;
            font-size: 16px;
        }
        
        .btn-primary:hover {
            background: #229954;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
            padding: 8px 16px;
            font-size: 14px;
            margin-top: 10px;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .media-item {
            background: white;
            border: 2px solid #eee;
            border-radius: 12px;
            overflow: hidden;
            transition: 0.3s;
        }
        
        .media-item:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-3px);
        }
        
        .media-preview {
            width: 100%;
            height: 200px;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .media-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .media-preview video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .media-preview .video-placeholder {
            font-size: 48px;
            color: #e74c3c;
        }
        
        .media-info {
            padding: 15px;
        }
        
        .media-type {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .media-type.image {
            background: #3498db;
            color: white;
        }
        
        .media-type.video {
            background: #e74c3c;
            color: white;
        }
        
        .btn-delete {
            background: #e74c3c;
            color: white;
            padding: 8px 16px;
            font-size: 13px;
            width: 100%;
        }
        
        .btn-delete:hover {
            background: #c0392b;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .video-embed {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
        }
        
        .video-embed iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        
        .video-input-group {
            margin-bottom: 15px;
            position: relative;
        }
        
        .file-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
            padding: 10px;
            background: white;
            border-radius: 8px;
        }
        
        .file-preview-item {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #ddd;
        }
        
        .file-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .info-box {
            background: #e3f2fd;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #2196f3;
        }
        
        .info-box i {
            color: #2196f3;
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fa fa-images"></i> Quản lý ảnh/video: <?php echo htmlspecialchars($product['name']); ?></h1>
            <a href="product.php?edit_id=<?php echo $product_id; ?>" class="btn btn-back">
                <i class="fa fa-arrow-left"></i> Quay lại
            </a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fa fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="upload-section">
            <h2><i class="fa fa-upload"></i> Thêm ảnh/video mới</h2>
            
            <div class="tab-buttons">
                <button class="tab-btn active" onclick="switchTab('image')">
                    <i class="fa fa-image"></i> Upload nhiều ảnh
                </button>
                <button class="tab-btn" onclick="switchTab('video')">
                    <i class="fa fa-video"></i> Thêm nhiều video
                </button>
            </div>

            <!-- Tab Upload Nhiều Ảnh -->
            <div id="image-tab" class="tab-content active">
                <div class="info-box">
                    <i class="fa fa-info-circle"></i>
                    <strong>Hướng dẫn:</strong> Bạn có thể chọn nhiều ảnh cùng lúc (Ctrl/Cmd + Click hoặc Shift + Click)
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="media_files">Chọn nhiều ảnh (Giữ Ctrl/Cmd để chọn nhiều)</label>
                        <input type="file" 
                               id="media_files" 
                               name="media_files[]" 
                               accept="image/*" 
                               multiple 
                               required
                               onchange="previewImages(this)">
                        <small style="color: #666; display: block; margin-top: 5px;">
                            Định dạng: JPG, JPEG, PNG, GIF, WEBP. Có thể chọn nhiều ảnh cùng lúc.
                        </small>
                    </div>
                    
                    <div id="image-preview" class="file-preview"></div>
                    
                    <button type="submit" name="upload_images" class="btn btn-primary">
                        <i class="fa fa-upload"></i> Upload tất cả ảnh
                    </button>
                </form>
            </div>

            <!-- Tab Thêm Nhiều Video -->
            <div id="video-tab" class="tab-content">
                <div class="info-box">
                    <i class="fa fa-info-circle"></i>
                    <strong>Hướng dẫn:</strong> Nhập URL của các video (YouTube, Vimeo, hoặc link trực tiếp). Click "Thêm video khác" để nhập thêm.
                </div>
                <form method="POST">
                    <div id="video-inputs">
                        <div class="video-input-group">
                            <label>URL Video 1</label>
                            <input type="text" 
                                   name="video_urls[]" 
                                   placeholder="https://www.youtube.com/watch?v=..." 
                                   required>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-secondary" onclick="addVideoInput()">
                        <i class="fa fa-plus"></i> Thêm video khác
                    </button>
                    
                    <button type="submit" name="upload_videos" class="btn btn-primary" style="margin-left: 10px;">
                        <i class="fa fa-save"></i> Lưu tất cả video
                    </button>
                </form>
            </div>
        </div>

        <h2 style="margin-bottom: 20px; color: #333;">
            <i class="fa fa-photo-film"></i> Danh sách Media (<?php echo count($media_list); ?>)
        </h2>

        <?php if (!empty($media_list)): ?>
            <div class="media-grid">
                <?php foreach ($media_list as $media): ?>
                    <div class="media-item">
                        <div class="media-preview">
                            <?php if ($media['media_type'] === 'image'): ?>
                                <img src="<?php echo htmlspecialchars($media['media_url']); ?>" 
                                     alt="Product media">
                            <?php else: ?>
                                <?php
                                $video_url = $media['media_url'];
                                if (preg_match('/youtube\.com\/watch\?v=([^&]+)/', $video_url, $matches)) {
                                    $video_id = $matches[1];
                                    echo '<div class="video-embed">';
                                    echo '<iframe src="https://www.youtube.com/embed/' . $video_id . '" frameborder="0" allowfullscreen></iframe>';
                                    echo '</div>';
                                } elseif (preg_match('/youtu\.be\/([^?]+)/', $video_url, $matches)) {
                                    $video_id = $matches[1];
                                    echo '<div class="video-embed">';
                                    echo '<iframe src="https://www.youtube.com/embed/' . $video_id . '" frameborder="0" allowfullscreen></iframe>';
                                    echo '</div>';
                                } else {
                                    echo '<i class="fa fa-video video-placeholder"></i>';
                                }
                                ?>
                            <?php endif; ?>
                        </div>
                        <div class="media-info">
                            <span class="media-type <?php echo $media['media_type']; ?>">
                                <i class="fa fa-<?php echo $media['media_type'] === 'image' ? 'image' : 'video'; ?>"></i>
                                <?php echo strtoupper($media['media_type']); ?>
                            </span>
                            <a href="?product_id=<?php echo $product_id; ?>&delete_media=<?php echo $media['id']; ?>" 
                               class="btn btn-delete"
                               onclick="return confirm('Bạn có chắc muốn xóa media này?')">
                                <i class="fa fa-trash"></i> Xóa
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa fa-images"></i>
                <h3>Chưa có ảnh/video nào</h3>
                <p>Hãy thêm ảnh hoặc video để giới thiệu sản phẩm đầy đủ hơn!</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        let videoCount = 1;
        
        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(tab + '-tab').classList.add('active');
        }
        
        function addVideoInput() {
            videoCount++;
            const container = document.getElementById('video-inputs');
            const newInput = document.createElement('div');
            newInput.className = 'video-input-group';
            newInput.innerHTML = `
                <label>URL Video ${videoCount}</label>
                <input type="text" 
                       name="video_urls[]" 
                       placeholder="https://www.youtube.com/watch?v=...">
            `;
            container.appendChild(newInput);
        }
        
        function previewImages(input) {
            const preview = document.getElementById('image-preview');
            preview.innerHTML = '';
            
            if (input.files) {
                const filesAmount = input.files.length;
                
                for (let i = 0; i < filesAmount; i++) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const item = document.createElement('div');
                        item.className = 'file-preview-item';
                        item.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                        preview.appendChild(item);
                    }
                    
                    reader.readAsDataURL(input.files[i]);
                }
            }
        }
    </script>
</body>
</html>