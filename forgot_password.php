<?php
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json');
require 'vendor/autoload.php'; // Composer autoload

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$conn = new mysqli("localhost", "root", "", "dpr_bites");
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "DB connection failed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? '';

if (!$email) {
    echo json_encode(["success" => false, "message" => "Email required"]);
    exit;
}

// cek email terdaftar dan ambil nama
$stmt = $conn->prepare("SELECT nama_lengkap FROM users WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows == 0) {
    echo json_encode(["success" => false, "message" => "Email tidak terdaftar"]);
    exit;
}
$row = $res->fetch_assoc();
$nama_lengkap = $row['nama_lengkap'];

// generate OTP
$otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
$expired_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

// simpan OTP
$conn->query("DELETE FROM password_resets WHERE email='$email'");
$stmt2 = $conn->prepare("INSERT INTO password_resets (email, otp, expired_at) VALUES (?, ?, ?)");
$stmt2->bind_param("sss", $email, $otp, $expired_at);
$stmt2->execute();

// kirim email
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com'; // Ganti sesuai SMTP Anda
    $mail->SMTPAuth = true;
    $mail->Username = 'hitmeup.raihan@gmail.com'; // Ganti
    $mail->Password = 'qnjhdxwegwvkafqm'; // Ganti
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('hitmeup.raihan@gmail.com', 'DPR Bites');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'Kode OTP Reset Password';
    $mail->Body    = "<p>Halo $nama_lengkap,</p>"
        . "<p>Anda telah melakukan permintaan untuk mereset password akun DPR Bites Anda.</p>"
        . "<p>Silakan gunakan kode OTP berikut untuk melanjutkan proses reset password:</p>"
        . "<h2 style='letter-spacing:4px;'>$otp</h2>"
        . "<p><small>Kode ini berlaku selama 10 menit. Jangan berikan kode ini kepada siapa pun demi keamanan akun Anda.</small></p>"
        . "<br><p>Jika Anda tidak merasa melakukan permintaan ini, abaikan email ini.</p>"
        . "<p>Terima kasih,<br><b>Tim DPR Bites</b></p>";

    $mail->send();
    echo json_encode(["success" => true, "message" => "OTP dikirim ke email"]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Gagal kirim email."]);
}