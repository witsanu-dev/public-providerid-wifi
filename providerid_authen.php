<?php
// เริ่มต้น session
session_start();

// ตรวจสอบว่ามี Authorization Code ถูกส่งมาหรือไม่
$authorizationCode = $_GET['code'] ?? null;

// echo "<pre>";
// print_r($_GET);
// echo "</pre>";

// แยกค่าจาก $_GET['state']
$state = $_GET['state'];
parse_str($state, $stateParams); // แยก string เป็น array

// echo "<pre>";
// print_r($stateParams);
// echo "</pre>";

// เข้าถึงตัวแปรแยกแต่ละค่า
$auth_redirid = $stateParams['auth_redirid'] ?? '';
$auth_magicid = $stateParams['auth_magicid'] ?? '';
$auth_methodid = $stateParams['auth_methodid'] ?? '';
$auth_posturl = $stateParams['auth_posturl'] ?? '';


// แสดงผลแต่ละตัวแปร
echo "auth_redirid: $auth_redirid<br>";
echo "auth_magicid: $auth_magicid<br>";
echo "auth_methodid: $auth_methodid<br>";
echo "auth_posturl: $auth_posturl<br>";


if (!$authorizationCode) {
    // ถ้าไม่มี Authorization Code ส่งมา ก็ให้ redirect ไปยังหน้า login
    echo "<h3>Error: Authorization Code not found.</h3>";
    echo "<p>You will be redirected to the login page in 5 seconds.</p>";
    echo "<script>
        setTimeout(function() {
            window.location.href = 'providerid_login.php';
        }, 5000); // 5000 milliseconds = 5 วินาที
    </script>";
    exit();
}

// ถ้ามี Authorization Code จะทำการขอ Access Token
// Token Key ส่วนของ Health ID
$tokenEndpoint = "https://moph.id.th/api/v1/token"; // URL สำหรับขอ Token
$clientId = "[CLIENT_ID]"; // Client ID ของคุณ
$clientSecret = "[CLIENT_SECRET]"; // Client Secret ของคุณ
$redirectUri = "https://[IP_ADDRESS]/oauth/providerid_authen.php"; // Redirect URI ที่ลงทะเบียนไว้

// เตรียมข้อมูลสำหรับ Request Body
$postFields = http_build_query([
    'grant_type' => 'authorization_code',
    'code' => $authorizationCode,
    'redirect_uri' => $redirectUri,
    'client_id' => $clientId,
    'client_secret' => $clientSecret
]);

// ส่งคำขอไปยัง Token Endpoint ด้วย cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $tokenEndpoint);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
]);

// ปิดการตรวจสอบ SSL (สำหรับทดสอบ)
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

// รับ Response
$response = curl_exec($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// ตรวจสอบข้อผิดพลาดจาก cURL
if ($response === false) {
    $error = error_get_last();
    echo "<pre>Error: " . print_r($error, true) . "</pre>";
    exit;
}

// การจัดการกับ Response
if ($httpStatus == 200) {
    // แปลง JSON Response เป็น Array
    $responseData = json_decode($response, true);

    // เก็บข้อมูลทั้งหมดจาก response ใน session
    $_SESSION['authorization_code'] = $authorizationCode;

    $_SESSION['auth_redirid'] = $auth_redirid;
    $_SESSION['auth_magicid'] = $auth_magicid;
    $_SESSION['auth_methodid'] = $auth_methodid;
    $_SESSION['auth_posturl'] = $auth_posturl;


    $_SESSION['status'] = $responseData['status'];
    $_SESSION['message'] = $responseData['message'];
    $_SESSION['status_code'] = $responseData['status_code'];
    $_SESSION['data'] = $responseData['data']; // เก็บข้อมูลทั้งหมดใน array data

    // เก็บข้อมูลจาก data โดยตรง
    $_SESSION['access_token'] = $responseData['data']['access_token'];
    $_SESSION['token_type'] = $responseData['data']['token_type'];
    $_SESSION['expires_in'] = $responseData['data']['expires_in'];
    $_SESSION['account_id'] = $responseData['data']['account_id'];

    // คำนวณเวลาหมดอายุของ Access Token
    $_SESSION['token_expiry_time'] = time() + $responseData['data']['expires_in']; // เวลาหมดอายุที่คำนวณจากเวลาปัจจุบัน

    // แสดงข้อมูลสำเร็จ
    echo "<h3>Health ID Access Successful</h3>";
    echo "<pre>" . print_r($responseData, true) . "</pre>";

    // เตรียมข้อมูลสำหรับการร้องขอ API ใหม่
    // Token Key ส่วนของ Provider ID
    $serviceEndpoint = "https://provider.id.th/api/v1/services/token";
    $postData = json_encode([
        "client_id" => "[CLIENT_ID]",
        "secret_key" => "[SECRET_KEY]",
        "token_by" => "Health ID",
        "token" => $_SESSION['access_token']
    ]);

    // ส่งคำขอไปยัง Service Endpoint ด้วย cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $serviceEndpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    // ปิดการตรวจสอบ SSL (สำหรับทดสอบ)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    // รับ Response
    $serviceResponse = curl_exec($ch);
    $serviceHttpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // ตรวจสอบข้อผิดพลาดจาก cURL
    if ($serviceResponse === false) {
        $error = error_get_last();
        echo "<pre>Error: " . print_r($error, true) . "</pre>";
        exit;
    }

    // การจัดการกับ Response
    if ($serviceHttpStatus == 200) {
        $serviceData = json_decode($serviceResponse, true);

        // เก็บ response ของ provider ใน session
        $_SESSION['provider_status'] = $serviceData['status'];
        $_SESSION['provider_message'] = $serviceData['message'];
        $_SESSION['provider_data'] = $serviceData['data'];

        $_SESSION['provider_token_type'] = $serviceData['data']['token_type'];
        $_SESSION['provider_expires_in'] = $serviceData['data']['expires_in'];
        $_SESSION['provider_access_token'] = $serviceData['data']['access_token'];
        $_SESSION['provider_expiration_date'] = $serviceData['data']['expiration_date'];
        $_SESSION['provider_account_id'] = $serviceData['data']['account_id'];
        $_SESSION['provider_result'] = $serviceData['data']['result'];
        $_SESSION['provider_username'] = $serviceData['data']['username'];
        $_SESSION['provider_login_by'] = $serviceData['data']['login_by'];

        // แสดงข้อมูลสำเร็จ
        echo "<h3>Provider ID Access Successful</h3>";
        echo "<pre>" . print_r($serviceData, true) . "</pre>";

        // Redirect ไปยัง Dashboard หรือหน้าอื่นๆ ที่ต้องการ
        $dashboardUrl = "bypass.php";  // URL ของหน้า Dashboard
        header("Location: $dashboardUrl");  // ทำการ Redirect -> callback กลับมาฟอร์ม login ฝั่ง firewall
        exit();  // ปิดการทำงานของ script
    } else {
        echo "<h3>Error: Unable to retrieve Provider Access</h3>";
        echo "<pre>HTTP Status: $serviceHttpStatus</pre>";
        echo "<pre>Response: $serviceResponse</pre>";
    }

} else {
    // เมื่อเกิดข้อผิดพลาด
    echo "<h3>Error: Unable to retrieve Access Token</h3>";
    echo "<pre>HTTP Status: $httpStatus</pre>";
    echo "<pre>Response: $response</pre>";

    // เพิ่ม JavaScript เพื่อ redirect ไปยังหน้า login.php หลังจาก 5 วินาที
    echo "<p>You will be redirected to the login page in 5 seconds.</p>";
    echo "<script>
        setTimeout(function() {
            window.location.href = 'providerid_login.php'; // เปลี่ยนไปยังหน้าล็อกอิน
        }, 5000); // 5000 milliseconds = 5 วินาที
    </script>";
}
?>