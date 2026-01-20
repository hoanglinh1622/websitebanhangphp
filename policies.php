<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chính Sách Cửa Hàng Xe Máy</title>

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        /* ===== RESET ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Poppins", sans-serif;
        }

        body {
            background: #f2f6fc;
            color: #222;
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* ===== ANIMATION ===== */
        .fade-up {
            opacity: 0;
            transform: translateY(40px);
            animation: fadeUp 0.8s ease-out forwards;
        }
        @keyframes fadeUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Glow light */
        .glow {
            text-shadow: 0 0 12px rgba(0, 123, 255, 0.6);
        }

        /* ===== HEADER ===== */
        header {
            background: #007bff;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,.15);
        }

        .header-flex {
            width: 90%;
            margin: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            color: white;
            font-size: 22px;
            font-weight: bold;
        }

        nav ul {
            list-style: none;
            display: flex;
            gap: 22px;
            margin: 0;
            padding: 0;
        }

        nav ul li a {
            text-decoration: none;
            color: white;
            font-weight: 500;
            padding: 8px 14px;
            border-radius: 8px;
            transition: 0.3s;
        }

        nav ul li a:hover {
            background: white;
            color: #007bff;
        }
        /* ===== PAGE BANNER ===== */
        .banner {
            background: linear-gradient(rgba(0,87,216,0.75), rgba(0,87,216,0.75)),
                        url('https://images.pexels.com/photos/102129/pexels-photo-102129.jpeg') center/cover;
            color: white;
            padding: 70px 0;
            text-align: center;
            margin-bottom: 20px;
        }
        .banner h1 {
            font-size: 40px;
            font-weight: 700;
        }

        /* ===== POLICY CONTENT ===== */
        .policy-page {
            background: #fff;
            padding: 30px;
            border-radius: 14px;
            margin-top: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            animation-delay: 0.2s;
            margin: auto; 
        }

        .policy-title {
            font-size: 32px;
            font-weight: 700;
            color: #0057d8;
            text-align: center;
            margin-bottom: 10px;
            margin: auto; 
        }

        .policy-intro {
            text-align: center;
            color: #444;
            font-size: 16px;
            margin-bottom: 25px;
            
        }

        .policy-section {
            margin-bottom: 25px;
            padding: 20px;
            background: #f4f8ff;
            border-left: 6px solid #0057d8;
            border-radius: 10px;
            animation-delay: 0.4s;
        }

        .policy-section h2 {
            color: #003c9d;
            font-size: 22px;
            margin-bottom: 10px;
        }

        .policy-section ul {
            margin-left: 20px;
        }
        .policy-section ul li {
            font-size: 15px;
            margin-bottom: 6px;
        }
        .policy-section.fade-up {
            width: 80%;
            max-width: 900px;
            margin: auto;    
        }


        /* ===== FOOTER ===== */
        footer {
            margin-top: 40px;
            background: #003c9d;
            color: #e6ebff;
            padding: 25px 0;
            text-align: center;
        }
        footer a {
            color: #aecdff;
            text-decoration: none;
        }
        footer a:hover {
            text-decoration: underline;
        }
        
    </style>

    <script>
        // Auto fade-up animation on scroll
        document.addEventListener("DOMContentLoaded", () => {
            const els = document.querySelectorAll(".fade-up");
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) entry.target.classList.add("visible");
                });
            }, {threshold: 0.2});

            els.forEach(el => observer.observe(el));
        });
    </script>

</head>
<body>

<!-- HEADER -->
<header class="fade-up">
    <div class="container header-flex">
        <div class="logo glow">Cửa hàng Mô tô – Xe máy NPL</div>
        <nav>
            <ul>
                <li><a href="index.php">Trang chủ</a></li>
                <li><a href="about.php">Giới thiệu</a></li>
                <li><a href="policies.php">Chính sách</a></li>
                <li><a href="contact.php">Liên hệ</a></li>

                <?php if (!isset($_SESSION['user'])): ?>
                    <li><a href="login.php">Đăng nhập</a></li>
                <?php else: ?>
                    <li><a href="cart.php">Giỏ hàng</a></li>
                    <li><a href="logout.php">Đăng xuất</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>

<!-- BANNER -->
<div class="banner fade-up">
    <h1 class="glow">Chính Sách Cửa Hàng Xe Máy</h1>
        <p class="glow">MotorShop cam kết mang đến trải nghiệm tốt nhất cho khách hàng với các chính sách minh bạch và chuyên nghiệp.</p>
</div>

<!-- POLICY PAGE -->
<div class="container fade-up">
    <div class="policy-page fade-up">

        

        <div class="policy-section fade-up">
            <h2>1. Chính sách bảo hành</h2>
            <ul>
                <li>Bảo hành theo tiêu chuẩn hãng 2–3 năm hoặc 20.000–30.000 km.</li>
                <li>Bảo hành phụ tùng 6–12 tháng.</li>
                <li>Không bảo hành nếu xe bị tai nạn, ngập nước, tự ý sửa chữa.</li>
            </ul>
        </div>

        <div class="policy-section fade-up">
            <h2>2. Chính sách đổi trả</h2>
            <ul>
                <li>Đổi xe trong 3–7 ngày nếu có lỗi kỹ thuật.</li>
                <li>Xe phải còn nguyên bản, chưa tháo ráp.</li>
                <li>Không đổi trả nếu xe trầy xước hoặc đã thay phụ tùng.</li>
            </ul>
        </div>

        <div class="policy-section fade-up">
            <h2>3. Chính sách thanh toán</h2>
            <ul>
                <li>Chấp nhận: tiền mặt, QR Code, MoMo, ZaloPay.</li>
            </ul>
        </div>

        <div class="policy-section fade-up">
            <h2>4. Chính sách giao nhận xe</h2>
            <ul>
                <li>Chỉ nhận xe tại cửa hàng.</li>
                <li>Do giá trị xe lớn, không hỗ trợ giao xe tận nhà.</li>
                <li>Khách phải xuất trình CCCD khi nhận xe.</li>
            </ul>
        </div>

        <div class="policy-section fade-up">
            <h2>5. Chính sách bảo mật thông tin</h2>
            <ul>
                <li>Cam kết bảo mật thông tin khách hàng.</li>
                <li>Không chia sẻ cho bên thứ ba.</li>
                <li>Sử dụng chuẩn mã hóa SSL nâng cao.</li>
            </ul>
        </div>

    </div>
</div>

<!-- FOOTER -->
<footer class="fade-up">
    © 2025 MotorShop – Cửa hàng xe máy chính hãng.
</footer>

</body>
</html>
