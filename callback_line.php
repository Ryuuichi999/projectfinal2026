<?php
session_start();
require 'includes/db.php';

define('LINE_LOGIN_CHANNEL_ID', '2008891589');
define('LINE_LOGIN_CHANNEL_SECRET', '18d3225d0acdbfb87a3671037ea27d90');
define('LINE_LOGIN_CALLBACK_URL', 'http://localhost/Project2026/callback_line.php');

// 1. กรณีได้รับ Code จาก LINE
if (isset($_GET['code'])) {
    $code = $_GET['code'];

    // 2. ขอ Access Token
    $token_url = "https://api.line.me/oauth2/v2.1/token";
    $data = [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => LINE_LOGIN_CALLBACK_URL,
        'client_id' => LINE_LOGIN_CHANNEL_ID,
        'client_secret' => LINE_LOGIN_CHANNEL_SECRET
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($response, true);

    if (isset($json['access_token'])) {
        $access_token = $json['access_token'];

        // 3. ดึงข้อมูลโปรไฟล์ผู้ใช้
        $profile_url = "https://api.line.me/v2/profile";
        $headers = [
            'Authorization: Bearer ' . $access_token
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $profile_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $profile_response = curl_exec($ch);
        curl_close($ch);

        $profile = json_decode($profile_response, true);
        $line_user_id = $profile['userId'];
        $line_display_name = $profile['displayName'];
        $line_picture_url = isset($profile['pictureUrl']) ? $profile['pictureUrl'] : '';

        // 4. ตรวจสอบว่ามีผู้ใช้นี้ในระบบหรือไม่
        $stmt = $conn->prepare("SELECT * FROM users WHERE line_user_id = ?");
        $stmt->bind_param("s", $line_user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            // == เก่า: มีบัญชีแล้ว -> Login เลย ==
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];

            // Redirect ตาม Role
            if ($user['role'] === 'admin' || $user['role'] === 'employee') {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: users/index.php");
            }
            exit;
        } else {

            // == ใหม่: ยังไม่มีบัญชี -> สมัครให้อัตโนมัติเลย (Auto Register) ==
            // ใช้ LINE User ID เป็นทั้ง Username และส่วนหนึ่งของ Password (เพื่อความง่ายและปลอดภัยระดับหนึ่ง)
            // หมายเหตุ: การทำแบบนี้จะทำให้ login ด้วย username/password ปกติไม่ได้ (เพราะไม่รู้ password) 
            // แต่จะ login ผ่าน LINE ได้ตลอดไป ซึ่งสะดวกกับผู้สูงอายุ

            $password = password_hash($line_user_id, PASSWORD_DEFAULT); // ใช้ LINE ID เป็นรหัสผ่าน (hash)
            $citizen_id = "LINE_" . substr($line_user_id, 0, 8); // สร้าง ID ชั่วคราว (เพราะระบบบังคับ)
            $first_name = $line_display_name;
            $last_name = "(LINE)";

            // ตรวจสอบว่า citizen_id ชั่วคราวซ้ำไหม (โอกาสน้อยมากแต่กันไว้)
            $check = $conn->prepare("SELECT id FROM users WHERE citizen_id = ?");
            $check->bind_param("s", $citizen_id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $citizen_id = "LINE_" . substr($line_user_id, 0, 8) . rand(10, 99);
            }

            $insert = $conn->prepare("INSERT INTO users (citizen_id, password, title_name, first_name, last_name, line_user_id, role) VALUES (?, ?, 'คุณ', ?, ?, ?, 'user')");
            $insert->bind_param("sssss", $citizen_id, $password, $first_name, $last_name, $line_user_id);

            if ($insert->execute()) {
                // สมัครเสร็จ -> Login เลย
                $_SESSION['user_id'] = $insert->insert_id;
                $_SESSION['role'] = 'user';

                header("Location: users/index.php");
                exit;
            } else {
                echo "Error auto-registering: " . $conn->error;
            }
        }

    } else {
        echo "Error getting access token: " . $json['error_description'];
    }
} else {
    echo "No code received from LINE.";
}
?>