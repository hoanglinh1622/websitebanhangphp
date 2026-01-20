<?php
session_start();
include 'includes/db.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error = "Please enter email and password!";
    } else {
        // Lấy thông tin người dùng từ database
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Kiểm tra mật khẩu và vai trò
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user; // Lưu thông tin vào session

            // Chuyển hướng dựa trên vai trò
            if ($user['role'] === 'admin') {
                header('Location: admin.php'); // Chuyển đến trang admin
            } else {
                header('Location: index.php'); // Chuyển đến trang user
            }
            exit();
        } else {
            $error = "Incorrect email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng nhập</title>
    <style>
        /* Tổng thể */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Poppins", Arial, sans-serif;
        }

        body {
            background:
                linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)),
                url("assets/moto1.jpg") no-repeat center center/cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        /* Hộp đăng nhập */
        .login-container {
            background: #fff;
            width: 400px;
            padding: 40px 30px;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            text-align: center;
            animation: fadeIn 0.6s ease;
        }

        @keyframes fadeIn {
            from {opacity: 0; transform: translateY(-15px);}
            to {opacity: 1; transform: translateY(0);}
        }

        h1 {
            font-size: 26px;
            color: #333;
            margin-bottom: 10px;
        }

        p.subtitle {
            color: #666;
            margin-bottom: 25px;
            font-size: 14px;
        }

        /* Ô nhập liệu */
        label {
            display: block;
            text-align: left;
            color: #555;
            font-weight: 500;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
        }

        .input-group input {
            width: 100%;
            padding: 12px 40px 12px 12px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .input-group input:focus {
            border-color: #7a90a7ff;
            box-shadow: 0 0 4px rgba(0, 123, 255, 0.3);
            outline: none;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #999;
            font-size: 18px;
            transition: color 0.3s;
        }

        .toggle-password:hover {
            color: #007bff;
        }

        /* Link quên mật khẩu */
        .forgot-password {
            text-align: right;
            margin-bottom: 20px;
        }

        .forgot-password a {
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .forgot-password a:hover {
            color: #0056b3;
            text-decoration: underline;
        }

        /* Nút đăng nhập */
        .btn-submit {
            background: linear-gradient(90deg, #007bff, #00bfff);
            color: white;
            padding: 12px;
            border: none;
            border-radius: 8px;
            width: 100%;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s ease;
        }

        .btn-submit:hover {
            background: linear-gradient(90deg, #0056b3, #0091d5);
            transform: translateY(-2px);
        }

        /* Đăng ký */
        .form-footer {
            margin-top: 25px;
            font-size: 14px;
        }

        .form-footer a {
            color: #007bff;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .form-footer a:hover {
            color: #0056b3;
            text-decoration: underline;
        }

        /* Thông báo lỗi */
        .error {
            color: #d93025;
            background: #fdecea;
            border: 1px solid #f5c2c0;
            padding: 8px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 14px;
        }

    </style>
</head>
<body>
    <div class="login-container">
        <h1>Đăng nhập</h1>
        <p class="subtitle">Chào mừng bạn trở lại 👋</p>

        <?php if (!empty($error)): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <label for="email">Email</label>
                <input type="email" name="email" placeholder="Nhập email của bạn" required>
            </div>

            <div class="input-group">
                <label for="password">Mật khẩu</label>
                <input type="password" name="password" id="password" placeholder="Nhập mật khẩu" required>
                <span class="toggle-password" onclick="togglePassword()">👁️</span>
            </div>

            <div class="forgot-password">
                <a href="forgot_password.php">Quên mật khẩu?</a>
            </div>

            <button type="submit" class="btn-submit">Đăng nhập</button>
        </form>

        <div class="form-footer">
            <p>Bạn chưa có tài khoản? <a href="register.php">Đăng ký ngay</a></p>
        </div>
    </div>

    <script>
        function togglePassword() {
            const password = document.getElementById("password");
            password.type = password.type === "password" ? "text" : "password";
        }
    </script>
</body>
</html>