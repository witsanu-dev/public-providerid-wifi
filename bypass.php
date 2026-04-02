<?php
session_start();  // เริ่มต้น session
// เชื่อมต่อฐานข้อมูล
require("../kmls_config.php"); 

// ตรวจสอบว่า Access Token มีใน session หรือไม่
if (isset($_SESSION['access_token'])) {
    // ดึงข้อมูลจาก session
    $authorizationCode = $_SESSION['authorization_code'];
    $accessToken = $_SESSION['access_token'];
    $tokenType = $_SESSION['token_type'];
    $expiresIn = $_SESSION['expires_in'];
    $accountId = $_SESSION['account_id'];
    $tokenExpiryTime = $_SESSION['token_expiry_time'];

    // ตรวจสอบว่า Access Token หมดอายุหรือไม่
    $tokenExpired = time() > $tokenExpiryTime;
} else {
    $tokenExpired = true;
    echo "<script>
        setTimeout(function() {
            window.location.href = '../providerid_login.php';
        }, 0);
    </script>";
    exit();
}

// ดึงข้อมูลจาก session ของ provider
$providerStatus = isset($_SESSION['provider_status']) ? $_SESSION['provider_status'] : null;
$providerMessage = isset($_SESSION['provider_message']) ? $_SESSION['provider_message'] : null;
$providerData = isset($_SESSION['provider_data']) ? $_SESSION['provider_data'] : null;
$providerTokenType = isset($_SESSION['provider_token_type']) ? $_SESSION['provider_token_type'] : null;
$providerExpiresIn = isset($_SESSION['provider_expires_in']) ? $_SESSION['provider_expires_in'] : null;
$providerAccessToken = isset($_SESSION['provider_access_token']) ? $_SESSION['provider_access_token'] : null;
$providerExpirationDate = isset($_SESSION['provider_expiration_date']) ? $_SESSION['provider_expiration_date'] : null;
$providerAccountId = isset($_SESSION['provider_account_id']) ? $_SESSION['provider_account_id'] : null;
$providerResult = isset($_SESSION['provider_result']) ? $_SESSION['provider_result'] : null;
$providerUsername = isset($_SESSION['provider_username']) ? $_SESSION['provider_username'] : null;
$providerLoginBy = isset($_SESSION['provider_login_by']) ? $_SESSION['provider_login_by'] : null;

$auth_redirid = isset($_SESSION['auth_redirid']) ? $_SESSION['auth_redirid'] : null;
$auth_magicid = isset($_SESSION['auth_magicid']) ? $_SESSION['auth_magicid'] : null;
$auth_methodid = isset($_SESSION['auth_methodid']) ? $_SESSION['auth_methodid'] : null;
$auth_posturl = isset($_SESSION['auth_posturl']) ? $_SESSION['auth_posturl'] : null;


?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="../favicon.ico">

    <title>Internet Authorization</title>

    <!-- Bootstrap core CSS -->
    <link href="../dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom styles for this template -->
    <link href="dashboard.css" rel="stylesheet">
  </head>

  <body onload="document.forms[0].submit();">
  <!-- <body> -->


                      <?php

                          // ถ้ามี Authorization Code จะทำการขอ Access Token
                          // URL สำหรับเรียก API (แนบพารามิเตอร์ไปกับ URL)
                          $tokenEndpoint = "https://provider.id.th/api/v1/services/profile?moph_center_token=1&moph_idp_permission=1&position_type=1";

                          // Client ID และ Client Secret
                          $clientId = "[CLIENT_ID]"; // Client ID ของคุณ
                          $clientSecret = "[CLIENT_SECRET]"; // Client Secret ของคุณ

                          // เริ่มต้น cURL
                          $ch = curl_init();
                          curl_setopt($ch, CURLOPT_URL, $tokenEndpoint); // ตั้งค่า URL
                          curl_setopt($ch, CURLOPT_HTTPGET, true); // ตั้งค่าเป็น GET Request
                          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // ให้ผลลัพธ์คืนค่าเป็น String
                          curl_setopt($ch, CURLOPT_HTTPHEADER, [
                              'Content-Type: application/json', // Content-Type เป็น JSON
                              'Authorization: Bearer ' . htmlspecialchars($providerAccessToken), // Bearer Token
                              'client-id: ' . $clientId, // Client ID
                              'secret-key: ' . $clientSecret, // Secret Key
                          ]);

                          // ปิดการตรวจสอบ SSL (สำหรับทดสอบ)
                          curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

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

                              // แสดงข้อความสำเร็จ
                              // echo "<h3>API Request Successful</h3>";
                              // echo "<pre>" . print_r($responseData, true) . "</pre>";

                              $data = $responseData['data'];
                              $organization = $data['organization'][0];
                              $address = $organization['address'];
                          } else {
                              // เมื่อเกิดข้อผิดพลาด
                              echo "<h3>Error: Unable to retrieve data</h3>";
                              echo "<pre>HTTP Status: $httpStatus</pre>";
                              echo "<pre>Response: $response</pre>";
                          }

                  // สมมุติว่า $data['hash_cid'] มาจาก API
                  $hash_cid = $data['hash_cid'] ?? '';
                  // ตรวจสอบว่า hash_cid มีค่า
                  if (!empty($hash_cid)) {
                      // ใช้ prepared statement เพื่อป้องกัน SQL Injection
                      $stmt = $conn->prepare("SELECT * FROM hospital_provider AS h
                      LEFT JOIN netusers AS n ON h.cid = n.National_ID
                      WHERE h.cid_hash = ?");
                      $stmt->bind_param("s", $hash_cid);
                      $stmt->execute();
                      $result = $stmt->get_result();

                      if ($result->num_rows > 0) {
                          // ดึงข้อมูล cid
                          $row = $result->fetch_assoc();
                          // echo "CID: " . $row['cid'];
                          $cid_provider = $row['cid'];
                          $netuser = $row['Username'];
                          $netpass = $row['Password'];

                      } else {
                          // echo "ไม่พบข้อมูลที่ตรงกับ hash_cid";
                      }

                      $stmt->close();
                  } else {
                      // echo "ไม่มีค่า hash_cid ที่ส่งมาจาก API";
                  }

                  ?>
                    <!-- [IP_AUTHENTICATOR]:[PORT] -->
                  <form method="POST" action="http://172.21.20.1:1000?fgtauth?<?= $auth_magicid ?>">
                    <input type="hidden" name="username" value="<?= $netuser; ?>">
                    <input type="hidden" name="password" value="<?= $netpass; ?>">
                    <input type="hidden" name="magic" value="<?= $auth_magicid ?>">
                  </form>

    <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js"></script>
    <script src="../assets/js/vendor/popper.min.js"></script>
    <script src="../dist/js/bootstrap.min.js"></script>

    <script src="https://unpkg.com/feather-icons/dist/feather.min.js"></script>
    <script>
      feather.replace()
    </script>
  </body>
</html>
