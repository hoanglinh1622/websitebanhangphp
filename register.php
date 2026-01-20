<?php
session_start();
include 'includes/db.php';

// Cấu hình email - Sử dụng PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Kiểm tra xem có Composer không
if (file_exists('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    // Nếu không có Composer, dùng file PHPMailer trực tiếp
    require 'PHPMailer/src/Exception.php';
    require 'PHPMailer/src/PHPMailer.php';
    require 'PHPMailer/src/SMTP.php';
}

$username = $email = "";
$error = $success = "";
$step = isset($_SESSION['register_step']) ? $_SESSION['register_step'] : 1;

// BƯỚC 1: Nhập thông tin và gửi OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_otp'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Vui lòng điền đầy đủ thông tin!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email không hợp lệ!";
    } elseif (strlen($password) < 6) {
        $error = "Mật khẩu phải có ít nhất 6 ký tự!";
    } elseif ($password !== $confirm_password) {
        $error = "Mật khẩu xác nhận không khớp!";
    } else {
        // Kiểm tra email đã tồn tại chưa
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $error = "Email đã được đăng ký!";
        } else {
            // Tạo mã OTP 6 số
            $otp = sprintf("%06d", mt_rand(0, 999999));
            $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Lưu thông tin vào session
            $_SESSION['temp_username'] = $username;
            $_SESSION['temp_email'] = $email;
            $_SESSION['temp_password'] = password_hash($password, PASSWORD_BCRYPT);
            $_SESSION['otp'] = $otp;
            $_SESSION['otp_expiry'] = $otp_expiry;
            $_SESSION['register_step'] = 2;
            
            // Gửi email OTP
            if (sendOTPEmail($email, $username, $otp)) {
                $success = "Mã OTP đã được gửi đến email của bạn!";
                $step = 2;
            } else {
                $error = "Không thể gửi email. Vui lòng thử lại!";
            }
        }
    }
}

// BƯỚC 2: Xác thực OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $input_otp = trim($_POST['otp']);
    
    if (empty($input_otp)) {
        $error = "Vui lòng nhập mã OTP!";
    } elseif (!isset($_SESSION['otp']) || !isset($_SESSION['otp_expiry'])) {
        $error = "Phiên làm việc đã hết hạn. Vui lòng đăng ký lại!";
        $step = 1;
        session_destroy();
    } elseif (strtotime($_SESSION['otp_expiry']) < time()) {
        $error = "Mã OTP đã hết hạn!";
    } elseif ($input_otp !== $_SESSION['otp']) {
        $error = "Mã OTP không chính xác!";
    } else {
        // OTP đúng - Tạo tài khoản
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, email_verified) VALUES (?, ?, ?, 1)");
        if ($stmt->execute([$_SESSION['temp_username'], $_SESSION['temp_email'], $_SESSION['temp_password']])) {
            $success = "Đăng ký thành công! Đang chuyển đến trang đăng nhập...";
            session_destroy();
            header("refresh:2; url=login.php");
            exit;
        } else {
            $error = "Đã xảy ra lỗi. Vui lòng thử lại!";
        }
    }
}

// Gửi lại OTP
if (isset($_GET['resend_otp']) && $_GET['resend_otp'] == 1) {
    if (isset($_SESSION['temp_email']) && isset($_SESSION['temp_username'])) {
        $otp = sprintf("%06d", mt_rand(0, 999999));
        $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        $_SESSION['otp'] = $otp;
        $_SESSION['otp_expiry'] = $otp_expiry;
        
        if (sendOTPEmail($_SESSION['temp_email'], $_SESSION['temp_username'], $otp)) {
            $success = "Mã OTP mới đã được gửi!";
        } else {
            $error = "Không thể gửi lại OTP!";
        }
    }
}

// Hàm gửi email OTP
function sendOTPEmail($to_email, $username, $otp) {
    $mail = new PHPMailer(true);
    
    try {
        // Cấu hình SMTP - Thay đổi theo email của bạn
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // SMTP server
        $mail->SMTPAuth = true;
        $mail->Username = 'hoanglinhk47@gmail.com'; // Email của bạn
        $mail->Password = 'yedh zlaj cmyh dezs'; // Mật khẩu ứng dụng Gmail
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        
        // Người gửi và người nhận
        $mail->setFrom('hoanglinhk47@gmail.com', 'Hệ thống đăng ký');
        $mail->addAddress($to_email, $username);
        
        // Nội dung email
        $mail->isHTML(true);
        $mail->Subject = 'Mã OTP xác thực đăng ký';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #28a745;'>Xác thực đăng ký tài khoản</h2>
                <p>Xin chào <strong>{$username}</strong>,</p>
                <p>Mã OTP của bạn là:</p>
                <div style='background: #f4f4f4; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #28a745;'>
                    {$otp}
                </div>
                <p>Mã này có hiệu lực trong <strong>10 phút</strong>.</p>
                <p>Nếu bạn không thực hiện yêu cầu này, vui lòng bỏ qua email.</p>
                <hr>
                <p style='color: #666; font-size: 12px;'>Email tự động, vui lòng không trả lời.</p>
            </div>
        ";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng ký tài khoản</title>
    <style>
        * {
            margin: 0; padding: 0;
            box-sizing: border-box;
            font-family: "Poppins", Arial, sans-serif;
        }
        
        body {
            background:
                linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)),
                url("assets/moto.jpg") no-repeat center center/cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .register-container {
            background: #fff;
            width: 100%;
            max-width: 420px;
            padding: 40px 30px;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            animation: fadeIn 0.6s ease;
            text-align: center;
        }
        @keyframes fadeIn {
            from {opacity: 0; transform: translateY(-15px);}
            to {opacity: 1; transform: translateY(0);}
        }
        h1 {
            font-size: 26px;
            color: #333;
            margin-bottom: 8px;
        }
        p.subtitle {
            color: #666;
            margin-bottom: 25px;
            font-size: 14px;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 25px;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #666;
        }
        .step.active {
            background: #28a745;
            color: white;
        }
        .error, .success {
            font-weight: 600;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .error {
            color: #d93025;
            background: #fdecea;
            border: 1px solid #f5c2c0;
        }
        .success {
            color: #155724;
            background: #d4edda;
            border: 1px solid #c3e6cb;
        }
        label {
            display: block;
            text-align: left;
            color: #555;
            font-weight: 500;
            margin-bottom: 5px;
            font-size: 14px;
        }
        .input-group {
            margin-bottom: 18px;
            position: relative;
        }
        input {
            width: 100%;
            padding: 12px 40px 12px 12px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        input:focus {
            border-color: #28a745;
            box-shadow: 0 0 4px rgba(40,167,69,0.3);
            outline: none;
        }
        .otp-input {
            font-size: 24px;
            text-align: center;
            letter-spacing: 10px;
            padding: 15px;
        }
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 35px;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 18px;
            color: #777;
        }
        .toggle-password:hover {
            color: #28a745;
        }
        .btn-submit {
            background: linear-gradient(90deg, #28a745, #00c851);
            color: white;
            padding: 12px;
            border: none;
            cursor: pointer;
            width: 100%;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            transition: 0.3s ease;
        }
        .btn-submit:hover {
            background: linear-gradient(90deg, #218838, #00b14f);
            transform: translateY(-2px);
        }
        .resend-link {
            margin-top: 15px;
            font-size: 14px;
        }
        .resend-link a {
            color: #28a745;
            text-decoration: none;
            font-weight: 600;
        }
        .resend-link a:hover {
            text-decoration: underline;
        }
        .form-footer {
            margin-top: 25px;
            font-size: 14px;
        }
        .form-footer a {
            color: #28a745;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .form-footer a:hover {
            color: #1e7e34;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h1>Đăng ký</h1>
        <p class="subtitle"><?= $step == 1 ? 'Tạo tài khoản mới để bắt đầu' : 'Xác thực email của bạn' ?></p>

        <div class="step-indicator">
            <div class="step <?= $step == 1 ? 'active' : '' ?>">1</div>
            <div class="step <?= $step == 2 ? 'active' : '' ?>">2</div>
        </div>

        <?php if (!empty($error)): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <p class="success"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>

        <?php if ($step == 1): ?>
        <!-- BƯỚC 1: Nhập thông tin -->
        <form method="POST">
            <div class="input-group">
                <label for="username">Tên người dùng</label>
                <input type="text" name="username" placeholder="Nhập tên của bạn" 
                       value="<?= htmlspecialchars($username ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <div class="input-group">
                <label for="email">Email</label>
                <input type="email" name="email" placeholder="Nhập email" 
                       value="<?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <div class="input-group">
                <label for="password">Mật khẩu</label>
                <input type="password" id="password" name="password" placeholder="Nhập mật khẩu" required>
                <span class="toggle-password" onclick="togglePassword('password', this)">👁</span>
            </div>

            <div class="input-group">
                <label for="confirm_password">Xác nhận mật khẩu</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Nhập lại mật khẩu" required>
                <span class="toggle-password" onclick="togglePassword('confirm_password', this)">👁</span>
            </div>

            <button type="submit" name="send_otp" class="btn-submit">Gửi mã OTP</button>
        </form>

        <?php else: ?>
        <!-- BƯỚC 2: Nhập OTP -->
        <form method="POST">
            <p style="margin-bottom: 20px; color: #555;">
                Mã OTP đã được gửi đến email:<br>
                <strong><?= htmlspecialchars($_SESSION['temp_email'] ?? '') ?></strong>
            </p>

            <div class="input-group">
                <label for="otp">Nhập mã OTP (6 số)</label>
                <input type="text" name="otp" class="otp-input" placeholder="000000" 
                       maxlength="6" pattern="\d{6}" required autofocus>
            </div>

            <button type="submit" name="verify_otp" class="btn-submit">Xác thực</button>
        </form>

        <div class="resend-link">
            <p>Không nhận được mã? <a href="?resend_otp=1">Gửi lại OTP</a></p>
        </div>
        <?php endif; ?>

        <div class="form-footer">
            <p>Đã có tài khoản? <a href="login.php">Đăng nhập ngay</a></p>
        </div>
    </div>

    <script>
        function togglePassword(id, el) {
            const input = document.getElementById(id);
            if (input.type === "password") {
                input.type = "text";
                el.textContent = "🙈";
            } else {
                input.type = "password";
                el.textContent = "👁";
            }
        }

        // Auto-format OTP input
        document.addEventListener('DOMContentLoaded', function() {
            const otpInput = document.querySelector('.otp-input');
            if (otpInput) {
                otpInput.addEventListener('input', function(e) {
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
            }
        });
    </script>
</body>
</html>