<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

include 'includes/db.php';

// Lấy danh sách người dùng
$users = $conn->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý người dùng</title>
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Segoe UI', Arial, sans-serif; 
        }
    
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
            max-width: 900px; 
            background: white; 
            padding: 25px 30px; 
            border-radius: 12px; 
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1); 
            margin: auto; 
        }

        h1 { 
            color: #28a745; 
            margin-bottom: 15px; 
        }

        p { 
            color: #555; 
            margin-bottom: 20px; 
        }

        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
        }

        th, td { 
            padding: 12px 10px; 
            border-bottom: 1px solid #ddd; 
            text-align: center; 
        }

        th { 
            background: #28a745; 
            color: white; 
            text-transform: uppercase; 
        }

        tr:hover { 
            background-color: #f8f9fa; 
        }

        .back { 
            display: inline-block; 
            margin-top: 25px; 
            background: #007bff; 
            color: white; 
            padding: 10px 16px; 
            text-decoration: none; 
            border-radius: 6px; 
            font-weight: bold; 
        }

        .back:hover { 
            background: #0056b3; 
        }

        @media (max-width: 600px) {
            table, thead, tbody, th, td, tr {
                display: block;
            }
            tr { margin-bottom: 15px; }
            td { text-align: right; padding-left: 50%; position: relative; }
            td::before {
                content: attr(data-label);
                position: absolute;
                left: 10px;
                width: 45%;
                text-align: left;
                font-weight: bold;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>👥 Quản lý người dùng</h1>
        <p>Trang dành cho quản trị viên theo dõi thông tin người dùng.</p>

        <table>
            <tr>
                <th>ID</th>
                <th>Tên người dùng</th>
                <th>Email</th>
                <th>Vai trò</th>
            </tr>

            <?php foreach ($users as $user): ?>
                <tr>
                    <td data-label="ID"><?php echo $user['id']; ?></td>
                    <td data-label="Tên người dùng"><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td data-label="Email"><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td data-label="Vai trò"><?php echo $user['role']; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <a href="admin.php" class="back">🏠 Quay lại trang quản trị</a>
    </div>
</body>
</html>
