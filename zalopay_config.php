<?php
class ZaloPayConfig {
    // Thông tin test ZaloPay
    const APP_ID = 2553;
    const KEY1 = "PcY4iZIKFCIdgZvA6ueMcMHHUbRLYjPL";
    const KEY2 = "kLtgPl8HHhfvMuDHPwKfgfsY4Ydm9eIz";
    const ENDPOINT = "https://sb-openapi.zalopay.vn/v2/create";
    
    // URL callback (thay bằng domain thật của bạn)
    const CALLBACK_URL = "https://2cf3aa552d4f.ngrok-free.app/websitebanhang/zalopay_callback.php";
    const REDIRECT_URL = "https://2cf3aa552d4f.ngrok-free.app/websitebanhang/zalopay_return.php";
}