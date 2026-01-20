<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    die("Bạn không có quyền thực hiện hành động này!");
}

include "includes/db.php";

$order_id = $_GET['id'] ?? 0;

if ($order_id > 0) {
    $stmt = $conn->prepare("UPDATE orders SET status = 'delivered' WHERE id = ?");
    $stmt->execute([$order_id]);
}

header("Location: admin_orders.php");
exit();
