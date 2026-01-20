<?php
require_once 'includes/db.php';
require_once 'zalopay_config.php';

$result = [];

try {
    $postdata = file_get_contents('php://input');
    $postdatajson = json_decode($postdata, true);
    
    // Xác thực callback
    $mac = hash_hmac("sha256", $postdatajson["data"], ZaloPayConfig::KEY2);
    
    if (strcmp($mac, $postdatajson["mac"]) == 0) {
        $dataJson = json_decode($postdatajson["data"], true);
        
        // Lưu log
        if (!file_exists('logs')) {
            mkdir('logs', 0777, true);
        }
        file_put_contents('logs/zalopay_callback.log', date('Y-m-d H:i:s') . " - " . $postdata . "\n", FILE_APPEND);
        
        $result["return_code"] = 1;
        $result["return_message"] = "success";
    } else {
        $result["return_code"] = -1;
        $result["return_message"] = "mac not equal";
    }
} catch (Exception $e) {
    $result["return_code"] = 0;
    $result["return_message"] = $e->getMessage();
}

echo json_encode($result);