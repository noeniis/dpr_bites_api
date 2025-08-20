<?php
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json');
$conn = new mysqli("localhost", "root", "", "dpr_bites");

$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? '';
$otp = $data['otp'] ?? '';
$new_password = $data['new_password'] ?? '';

if (!$email || !$otp || !$new_password) {
    echo json_encode(["success" => false, "message" => "Lengkapi data"]);
    exit;
}

// cek OTP valid
$stmt = $conn->prepare("SELECT * FROM password_resets WHERE email=? AND otp=? AND expired_at > NOW()");
$stmt->bind_param("ss", $email, $otp);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    echo json_encode(["success" => false, "message" => "OTP salah atau kadaluarsa"]);
    exit;
}


// cek password lama
$stmt3 = $conn->prepare("SELECT password_hash FROM users WHERE email=?");
$stmt3->bind_param("s", $email);
$stmt3->execute();
$result3 = $stmt3->get_result();
if ($row = $result3->fetch_assoc()) {
    if (password_verify($new_password, $row['password_hash'])) {
        echo json_encode(["success" => false, "message" => "Password baru tidak boleh sama dengan password lama"]);
        exit;
    }
}

// update password
$password_hash = password_hash($new_password, PASSWORD_DEFAULT);
$stmt2 = $conn->prepare("UPDATE users SET password_hash=? WHERE email=?");
$stmt2->bind_param("ss", $password_hash, $email);
$stmt2->execute();

// hapus OTP
$conn->query("DELETE FROM password_resets WHERE email='$email'");

echo json_encode(["success" => true, "message" => "Password berhasil direset"]);