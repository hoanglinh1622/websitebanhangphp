<?php
session_start();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Giới thiệu</title>
    <style>
            /* ================================
        🔵 GLOBAL STYLE
        ================================ */
        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: #f0f6ff;
            margin: 0;
            padding: 0;
            scroll-behavior: smooth;
            color: #333;
        }

        /* ================================
        🔵 HEADER
        ================================ */
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

        /* ================================
        🔵 PAGE TITLE
        ================================ */
        .header-title {
            background: linear-gradient(to bottom right, #007bff, #0056c9);
            color: white;
            padding: 40px 0;
            text-align: center;
            font-size: 32px;
            font-weight: bold;
            box-shadow: 0 4px 15px rgba(0,0,0,.15);
        }

        /* ================================
        🔵 MAIN CONTAINER
        ================================ */
        .container {
            width: 75%;
            margin: 40px auto;
            background: #fff;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 5px 25px rgba(0, 100, 220, 0.15);
        }

        h2 {
            color: #007BFF;
            font-size: 26px;
        }
        h3 {
            color: #0056c9;
            margin-top: 30px;
        }

        /* ================================
        🔵 TEXT
        ================================ */
        p {
            line-height: 1.7;
            font-size: 16px;
        }

        .highlight-box {
            background: #e8f1ff;
            border-left: 5px solid #007bff;
            padding: 15px 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        /* ================================
        🔵 TEAM SECTION
        ================================ */
        .team-section {
            margin-top: 50px;
        }

        .team-list {
            display: flex;
            justify-content: space-between;
            gap: 25px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .team-card {
            flex: 1;
            min-width: 28%;
            background: #ffffff;
            padding: 22px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0, 100, 220, .13);
            transition: 0.35s;
        }

        .team-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 28px rgba(0,123,255,.35);
        }

        /* 🔵 Glow Avatar */
        .team-card img {
            width: 95px;
            height: 95px;
            border-radius: 50%;
            border: 4px solid #007bff;
            margin-bottom: 12px;
            box-shadow: 0 0 20px rgba(0, 123, 255, .55);
            transition: 0.4s;
        }

        .team-card img:hover {
            transform: scale(1.07);
            box-shadow: 0 0 35px rgba(0, 123, 255, .9),
                        0 0 55px rgba(0, 123, 255, .7);
        }

        .team-card h4 {
            font-size: 18px;
            color: #007bff;
            margin-top: 10px;
        }

        /* ================================
        🔵 ANIMATION fade-up
        ================================ */
        .fade-up {
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 0.9s ease, transform 0.9s ease;
        }

        .fade-up.show {
            opacity: 1;
            transform: translateY(0);
        }

        /* ================================
        🔵 RESPONSIVE
        ================================ */
        @media (max-width: 900px) {
            .team-card { min-width: 45%; }
            header { text-align: center; }
            .header-flex { flex-direction: column; gap: 10px; }
        }

        @media (max-width: 550px) {
            .team-card { min-width: 100%; }
            .container { width: 90%; padding: 25px; }
            nav ul { flex-wrap: wrap; justify-content: center; }
        }
        /* ================================
        🔵 PAGE BANNER
        ================================ */
        .page-banner {
            background: linear-gradient(135deg, #f9fcffff, #fefeffff);
            color: #007bff;
            padding: 55px 0;
            text-align: center;
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 1px;
            border-bottom: 4px solid #007bff;
            box-shadow: 0 5px 18px rgba(0, 0, 0, .25);
            text-transform: uppercase;

            /* Hiệu ứng fade + zoom nhẹ */
            opacity: 0;
            transform: translateY(30px) scale(0.97);
            transition: 0.9s ease;
        }

        .page-banner.show {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

    </style>
</head>
<body>
<header>
    <div class="header-flex">
        <div class="logo">Cửa hàng Mô tô – Xe máy NPL</div>
        <nav>
            <ul>
                <li><a href="index.php">Trang chủ</a></li>
                <li><a href="policies.php">Chính sách</a></li>
                <li><a href="about.php">Giới thiệu</a></li>

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
<div class="page-banner fade-up">Giới thiệu về chúng tôi</div>


<div class="container fade-up">

    <h2 class="fade-up">Chào mừng bạn đến với Website của chúng tôi!</h2>
    <p class="fade-up">
        Chúng tôi là một trong những nền tảng mua sắm trực tuyến đáng tin cậy, 
        mang đến trải nghiệm mua hàng nhanh chóng, tiện lợi và an toàn.
    </p>

    <h3 class="fade-up">Tầm nhìn</h3>
    <p class="fade-up">
        Trở thành nền tảng thương mại điện tử hàng đầu tại Việt Nam.
    </p>

    <h3 class="fade-up">Sứ mệnh</h3>
    <p class="fade-up">
        - Cung cấp sản phẩm chất lượng.<br>
        - Trải nghiệm mua sắm bảo mật.<br>
        - Hỗ trợ khách hàng 24/7.<br>
    </p>

    <div class="highlight-box fade-up">
        <b>🔒 Cam kết chất lượng:</b> Kiểm tra kỹ lưỡng trước khi đến tay khách hàng.
    </div>

    <h3 class="fade-up">Lịch sử hình thành</h3>
    <p class="fade-up">
        Thành lập năm <b>2025</b> và đã phục vụ hàng chục nghìn khách hàng.
    </p>

    <!-- ================== TEAM SECTION =================== -->
    <div class="team-section fade-up">
        <h3>Đội ngũ phát triển</h3>

        <div class="team-list">

            <div class="team-card fade-up">
                <img src="https://i.imgur.com/0y0y0y0.png">
                <h4>Hoàng Nhật Linh</h4>
                <p>Founder & Project Manager</p>
            </div>

            <div class="team-card fade-up">
                <img src="https://i.imgur.com/0y0y0y0.png">
                <h4>Tân Thành Phát</h4>
                <p>Backend Developer</p>
            </div>

            <div class="team-card fade-up">
                <img src="https://i.imgur.com/0y0y0y0.png">
                <h4>Võ Nguyệt Nhi</h4>
                <p>Frontend Developer</p>
            </div>

        </div>
    </div>

</div>

<!-- ================================================
        ✨ JS Fade-Up Animation
================================================ -->
<script>
    const fadeElements = document.querySelectorAll('.fade-up');

    function handleFade() {
        fadeElements.forEach(el => {
            const pos = el.getBoundingClientRect().top;
            const screen = window.innerHeight - 50;
            if (pos < screen) el.classList.add('show');
        });
    }

    document.addEventListener("scroll", handleFade);
    window.onload = handleFade;
</script>

</body>
</html>
