<?php
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json');
$conn = new mysqli("localhost", "root", "", "dpr_bites");

$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? '';
$otp = $data['otp'] ?? '';

if (!$email || !$otp) {
    echo json_encode(["success" => false, "message" => "Email dan OTP wajib diisi"]);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM password_resets WHERE email=? AND otp=? AND expired_at > NOW()");
$stmt->bind_param("ss", $email, $otp);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    echo json_encode(["success" => true, "message" => "OTP valid"]);
} else {
    echo json_encode(["success" => false, "message" => "OTP salah atau kadaluarsa"]);
}