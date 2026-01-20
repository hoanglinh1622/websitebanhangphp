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

$error = $success = "";
$step = isset($_SESSION['reset_step']) ? $_SESSION['reset_step'] : 1;

// BƯỚC 1: Nhập email và gửi OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_otp'])) {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $error = "Vui lòng nhập email!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email không hợp lệ!";
    } else {
        // Kiểm tra email có tồn tại không
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $error = "Email không tồn tại trong hệ thống!";
        } else {
            // Tạo mã OTP 6 số
            $otp = sprintf("%06d", mt_rand(0, 999999));
            $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Lưu thông tin vào session
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_user_id'] = $user['id'];
            $_SESSION['reset_otp'] = $otp;
            $_SESSION['reset_otp_expiry'] = $otp_expiry;
            $_SESSION['reset_step'] = 2;
            
            // Gửi email OTP
            if (sendOTPEmail($email, $user['username'], $otp)) {
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
    } elseif (!isset($_SESSION['reset_otp']) || !isset($_SESSION['reset_otp_expiry'])) {
        $error = "Phiên làm việc đã hết hạn. Vui lòng thử lại!";
        $step = 1;
        session_destroy();
    } elseif (strtotime($_SESSION['reset_otp_expiry']) < time()) {
        $error = "Mã OTP đã hết hạn!";
    } elseif ($input_otp !== $_SESSION['reset_otp']) {
        $error = "Mã OTP không chính xác!";
    } else {
        // OTP đúng - Chuyển sang bước 3
        $_SESSION['reset_step'] = 3;
        $step = 3;
        $success = "Xác thực thành công! Vui lòng đặt mật khẩu mới.";
    }
}

// BƯỚC 3: Đặt mật khẩu mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = "Vui lòng điền đầy đủ thông tin!";
    } elseif (strlen($new_password) < 6) {
        $error = "Mật khẩu phải có ít nhất 6 ký tự!";
    } elseif ($new_password !== $confirm_password) {
        $error = "Mật khẩu xác nhận không khớp!";
    } else {
        // Cập nhật mật khẩu mới
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        
        if ($stmt->execute([$hashed_password, $_SESSION['reset_user_id']])) {
            $success = "Đặt lại mật khẩu thành công! Đang chuyển đến trang đăng nhập...";
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
    if (isset($_SESSION['reset_email'])) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$_SESSION['reset_email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $otp = sprintf("%06d", mt_rand(0, 999999));
            $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            $_SESSION['reset_otp'] = $otp;
            $_SESSION['reset_otp_expiry'] = $otp_expiry;
            
            if (sendOTPEmail($_SESSION['reset_email'], $user['username'], $otp)) {
                $success = "Mã OTP mới đã được gửi!";
            } else {
                $error = "Không thể gửi lại OTP!";
            }
        }
    }
}

// Hàm gửi email OTP
function sendOTPEmail($to_email, $username, $otp) {
    $mail = new PHPMailer(true);
    
    try {
        // Cấu hình SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'hoanglinhk47@gmail.com'; // Email của bạn
        $mail->Password = 'yedh zlaj cmyh dezs'; // Mật khẩu ứng dụng Gmail
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        
        // Người gửi và người nhận
        $mail->setFrom('hoanglinhk47@gmail.com', 'Hệ thống quên mật khẩu');
        $mail->addAddress($to_email, $username);
        
        // Nội dung email
        $mail->isHTML(true);
        $mail->Subject = 'Mã OTP đặt lại mật khẩu';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #dc3545;'>Đặt lại mật khẩu</h2>
                <p>Xin chào <strong>{$username}</strong>,</p>
                <p>Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.</p>
                <p>Mã OTP của bạn là:</p>
                <div style='background: #f4f4f4; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #dc3545;'>
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quên mật khẩu</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Poppins", Arial, sans-serif;
        }
        
        body {
            background:
                linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)),
                url("assets/moto1.jpg") no-repeat center center/cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .reset-container {
            background: #fff;
            width: 100%;
            max-width: 440px;
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
            font-size: 28px;
            color: #333;
            margin-bottom: 8px;
        }
        
        .icon {
            font-size: 60px;
            margin-bottom: 15px;
        }
        
        p.subtitle {
            color: #666;
            margin-bottom: 25px;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .step {
            position: relative;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #666;
            transition: all 0.3s ease;
        }
        
        .step.active {
            background: #dc3545;
            color: white;
            transform: scale(1.1);
        }
        
        .step.completed {
            background: #28a745;
            color: white;
        }
        
        .step::after {
            content: '';
            position: absolute;
            width: 40px;
            height: 2px;
            background: #e0e0e0;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .step:last-child::after {
            display: none;
        }
        
        .error, .success {
            font-weight: 600;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
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
            margin-bottom: 6px;
            font-size: 14px;
        }
        
        .input-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        input {
            width: 100%;
            padding: 13px 40px 13px 13px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        input:focus {
            border-color: #dc3545;
            box-shadow: 0 0 4px rgba(220,53,69,0.3);
            outline: none;
        }
        
        .otp-input {
            font-size: 28px;
            text-align: center;
            letter-spacing: 10px;
            padding: 18px;
            font-weight: bold;
        }
        
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 38px;
            cursor: pointer;
            font-size: 20px;
            color: #777;
            transition: color 0.3s ease;
        }
        
        .toggle-password:hover {
            color: #dc3545;
        }
        
        .btn-submit {
            background: linear-gradient(90deg, #dc3545, #c82333);
            color: white;
            padding: 13px;
            border: none;
            cursor: pointer;
            width: 100%;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .btn-submit:hover {
            background: linear-gradient(90deg, #c82333, #bd2130);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220,53,69,0.3);
        }
        
        .resend-link {
            margin-top: 20px;
            font-size: 14px;
        }
        
        .resend-link a {
            color: #dc3545;
            text-decoration: none;
            font-weight: 600;
        }
        
        .resend-link a:hover {
            text-decoration: underline;
        }
        
        .form-footer {
            margin-top: 25px;
            font-size: 14px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .form-footer a {
            color: #dc3545;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .form-footer a:hover {
            color: #bd2130;
            text-decoration: underline;
        }
        
        .email-display {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .email-display strong {
            color: #dc3545;
        }
        
        .password-requirements {
            text-align: left;
            font-size: 13px;
            color: #666;
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .password-requirements ul {
            margin: 8px 0 0 20px;
        }
        
        .password-requirements li {
            margin: 4px 0;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="icon">🔐</div>
        <h1>Quên mật khẩu</h1>
        
        <?php if ($step == 1): ?>
            <p class="subtitle">Nhập email của bạn để nhận mã xác thực</p>
        <?php elseif ($step == 2): ?>
            <p class="subtitle">Nhập mã OTP đã được gửi đến email</p>
        <?php else: ?>
            <p class="subtitle">Đặt mật khẩu mới cho tài khoản của bạn</p>
        <?php endif; ?>

        <div class="step-indicator">
            <div class="step <?= $step == 1 ? 'active' : 'completed' ?>">1</div>
            <div class="step <?= $step == 2 ? 'active' : ($step > 2 ? 'completed' : '') ?>">2</div>
            <div class="step <?= $step == 3 ? 'active' : '' ?>">3</div>
        </div>

        <?php if (!empty($error)): ?>
            <p class="error">❌ <?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <p class="success">✅ <?= htmlspecialchars($success) ?></p>
        <?php endif; ?>

        <?php if ($step == 1): ?>
        <!-- BƯỚC 1: Nhập email -->
        <form method="POST">
            <div class="input-group">
                <label for="email">📧 Email đã đăng ký</label>
                <input type="email" name="email" placeholder="Nhập email của bạn" required autofocus>
            </div>

            <button type="submit" name="send_otp" class="btn-submit">Gửi mã OTP</button>
        </form>

        <?php elseif ($step == 2): ?>
        <!-- BƯỚC 2: Nhập OTP -->
        <div class="email-display">
            📧 Mã OTP đã được gửi đến:<br>
            <strong><?= htmlspecialchars($_SESSION['reset_email'] ?? '') ?></strong>
        </div>

        <form method="POST">
            <div class="input-group">
                <label for="otp">Nhập mã OTP (6 số)</label>
                <input type="text" name="otp" class="otp-input" placeholder="000000" 
                       maxlength="6" pattern="\d{6}" required autofocus>
            </div>

            <button type="submit" name="verify_otp" class="btn-submit">Xác thực OTP</button>
        </form>

        <div class="resend-link">
            <p>Không nhận được mã? <a href="?resend_otp=1">Gửi lại OTP</a></p>
        </div>

        <?php else: ?>
        <!-- BƯỚC 3: Đặt mật khẩu mới -->
        <div class="password-requirements">
            <strong>Yêu cầu mật khẩu:</strong>
            <ul>
                <li>✓ Tối thiểu 6 ký tự</li>
                <li>✓ Nên có chữ hoa, chữ thường</li>
                <li>✓ Nên có số và ký tự đặc biệt</li>
            </ul>
        </div>

        <form method="POST">
            <div class="input-group">
                <label for="new_password">🔒 Mật khẩu mới</label>
                <input type="password" id="new_password" name="new_password" 
                       placeholder="Nhập mật khẩu mới" required>
                <span class="toggle-password" onclick="togglePassword('new_password', this)">👁️</span>
            </div>

            <div class="input-group">
                <label for="confirm_password">🔒 Xác nhận mật khẩu</label>
                <input type="password" id="confirm_password" name="confirm_password" 
                       placeholder="Nhập lại mật khẩu mới" required>
                <span class="toggle-password" onclick="togglePassword('confirm_password', this)">👁️</span>
            </div>

            <button type="submit" name="reset_password" class="btn-submit">Đặt lại mật khẩu</button>
        </form>
        <?php endif; ?>

        <div class="form-footer">
            <p><a href="login.php">← Quay lại đăng nhập</a></p>
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
                el.textContent = "👁️";
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