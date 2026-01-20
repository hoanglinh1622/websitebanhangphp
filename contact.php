<?php
session_start();

// --- Xử lý form gửi mail ---
$name = "";
$email = "";
$message = "";
$success = "";
$error = "";

if (isset($_SESSION['user'])) {
    $email = $_SESSION['user']['email'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $message = trim($_POST['message']);

    if (empty($name) || empty($email) || empty($message)) {
        $error = "Vui lòng nhập đầy đủ thông tin!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email không hợp lệ!";
    } else {
        @mail("support@website.com", "Tin nhắn liên hệ", "Họ tên: $name\nEmail: $email\n\n$message");

        $success = "Cảm ơn bạn! Chúng tôi đã nhận được tin nhắn.";
        $name = $email = $message = "";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Liên hệ</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:"Poppins",sans-serif;}
body{background:#f4f8ff;color:#333;}

header{
    background:#0057d8;padding:15px 0;
    box-shadow:0 3px 10px rgba(0,0,0,0.15);
}
/* ===== Banner đầu trang ===== */
.header-title{
    text-align:center;
    font-size:36px;
    font-weight:700;
    color:#007bff;
    background:white;
    padding:40px 0;
    margin-bottom:35px;
    letter-spacing:1px;
}

/* Hiệu ứng animation */
.fade-up{opacity:0;transform:translateY(40px);transition:all .8s ease-out;}
.fade-up.show{opacity:1;transform:translateY(0);}
.fade-in{opacity:0;transition:opacity 1s ease-in;}
.fade-in.show{opacity:1;}
.zoom-in{opacity:0;transform:scale(.85);transition:all .6s ease-out;}
.zoom-in.show{opacity:1;transform:scale(1);}

/* Glow effect */
.glow-box{transition:.35s;border-radius:14px;}
.glow-box:hover{box-shadow:0 0 15px rgba(0,150,255,.5),0 0 30px rgba(0,150,255,.3);}

.header-flex{
    width:95%;max-width:1200px;margin:auto;
    display:flex;justify-content:space-between;align-items:center;
}
.logo{font-size:26px;font-weight:600;color:white;}
nav ul{list-style:none;display:flex;gap:20px;}
nav ul li a{
    color:white;text-decoration:none;font-size:16px;
    padding:8px 14px;border-radius:6px;transition:.25s;
}
nav ul li a:hover{background:#003fa5;}

.contact-wrapper{
    width:95%;max-width:1200px;margin:auto;
    display:flex;gap:25px;
}

/* INFO RIGHT */
.contact-info{
    flex:1;background:white;padding:30px;border-radius:14px;
    box-shadow:0 6px 25px rgba(0,70,200,.12);
}
.contact-info h2{text-align:center;color:#0057d8;margin-bottom:15px;}

.info-item{
    display:flex;align-items:center;font-size:16px;margin:12px 0;
}
.info-item i{
    width:32px;height:32px;background:#0057d8;color:white;
    display:flex;justify-content:center;align-items:center;
    border-radius:6px;margin-right:12px;font-size:17px;
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

<!-- ⭐ Banner -->
<div class="header-title fade-up">Liên hệ với chúng tôi</div>

<div class="contact-wrapper">

    <!-- INFO RIGHT -->
    <div class="contact-info glow-box fade-up">
        <h2 class="fade-up">Thông tin liên hệ</h2>

        <div class="info-box">
            <div class="info-item fade-up">
                <i class="fa-solid fa-phone"></i> 0909 999 999
            </div>

            <div class="info-item fade-up">
                <i class="fa-solid fa-envelope"></i> support@website.com
            </div>

            <div class="info-item fade-up">
                <i class="fa-solid fa-location-dot"></i>
                123 Nguyễn Văn Linh, Quận 7, TP. Hồ Chí Minh
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const elements = document.querySelectorAll(".fade-up, .fade-in, .zoom-in");
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if(entry.isIntersecting){
                entry.target.classList.add("show");
            }
        });
    }, { threshold: 0.2 });
    elements.forEach(el => observer.observe(el));
});
</script>

</body>
</html>
