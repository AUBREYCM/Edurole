<?php
// Test real login with Java
$email = 'admin@edurole.com';
$password = 'admin123';

$ch = curl_init('http://localhost:8081/api/auth/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => $email, 'password' => $password]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: " . $httpCode . "<br><br>";
echo "Java Response: <br>";
echo "<pre>";
print_r(json_decode($response, true));
echo "</pre>";
?>