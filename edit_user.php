<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

include 'includes/db.php';

$user_id = $_GET['id'] ?? null;
$error = "";
$success = "";

if (!$user_id) {
    header('Location: user.php');
    exit();
}

// Lấy thông tin người dùng từ database
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: user.php');
    exit();
}

// Xử lý cập nhật thông tin người dùng
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];

    // Kiểm tra đầu vào
    if (empty($name) || empty($email)) {
        $error = "Please fill in all information!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email!";
    } else {
        // Cập nhật người dùng
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
        $stmt->execute([$name, $email, $role, $user_id]);
        $success = "Update successful!";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit user</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
        body { background-color: #f4f4f4; text-align: center; padding: 20px; }
        .container { max-width: 400px; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); margin: auto; }
        h1 { color: #333; margin-bottom: 10px; }
        label { font-weight: bold; display: block; margin-top: 10px; text-align: left; }
        input, select { width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; }
        button { background: #28a745; color: white; padding: 10px; border: none; cursor: pointer; width: 100%; margin-top: 15px; }
        button:hover { background: #218838; }
        .message { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .back { display: block; margin-top: 15px; background: #007bff; color: white; padding: 10px; text-decoration: none; border-radius: 5px; }
        .back:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Edit user</h1>

        <?php if ($error): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="message"><?php echo $success; ?></p>
        <?php endif; ?>

        <form method="POST">
            <label for="name">Name:</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>" required>

            <label for="email">Email:</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>" required>

            <label for="role">Role:</label>
            <select name="role" required>
                <option value="user" <?php echo ($user['role'] === 'user') ? 'selected' : ''; ?>>User</option>
                <option value="admin" <?php echo ($user['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
            </select>

            <button type="submit">Update</button>
        </form>
        <a href="user.php" class="back">🔙 Back to list</a>
    </div>
</body>
</html>