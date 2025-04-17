<?php
header('Content-Type: application/json');

// Variabel untuk pesan status
$message = '';
$status = '';

// Ambil input waktu dari form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['waktu_rtc'])) {
    $waktu_rtc = $_POST['waktu_rtc'];

    // Validasi format input datetime-local: detik bisa opsional
    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $waktu_rtc)) {

        // Jika input hanya sampai menit (tanpa detik), tambahkan :00
        if (strlen($waktu_rtc) === 16) {
            $waktu_rtc .= ':00';
        }

        // Format waktu RTC untuk ESP32
        $formatted_rtc = $waktu_rtc;

        // IP address ESP32
        $esp32_ip = '192.168.10.26';
        $url = "http://$esp32_ip:80/set-rtc";

        // Kirim permintaan ke ESP32 menggunakan cURL dengan metode POST
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Waktu maksimal 5 detik
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "datetime=" . urlencode($formatted_rtc));

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Cek respons dari ESP32
        if ($http_code === 200 && $response !== false) {
            $message = 'Waktu RTC berhasil diatur!';
            $status = 'success';
        } else {
            $message = 'Gagal mengatur waktu RTC. Pastikan alat terhubung dengan Wi-Fi!';
            $status = 'danger';
        }
    } else {
        $message = 'Format waktu tidak valid!';
        $status = 'danger';
    }
} else {
    $message = 'Tidak ada data waktu RTC yang dikirim!';
    $status = 'danger';
}

// Kembalikan response dalam format JSON
echo json_encode([
    'status' => $status,
    'message' => $message
]);

exit();
?>
