<?php
// Konfigurasi alamat IP ESP32
$esp32_ip = "192.168.10.26:80";
$esp32cam_ip = "192.168.10.99:80";
$esp32cam_get_flash_status_endpoint = "http://$esp32cam_ip/flash_status";
$esp32_set_flash_endpoint = "http://$esp32_ip/set-flash";

// Jika parameter 'state' disediakan via URL, proses perubahan mode flash
if (isset($_GET['state'])) {
    $state = $_GET['state'];
    // Validasi nilai mode flash: hanya "on" atau "off" yang diterima
    if ($state === "on" || $state === "off") {
        $url = $esp32_set_flash_endpoint . "?state=" . urlencode($state);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Set timeout maksimal 5 detik untuk pengiriman perintah
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $curlResponse = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlResponse === false) {
            echo json_encode([
                "status" => "danger",
                "message" => "Gagal mengubah mode flash!"
            ]);
            exit;
        }
        
        // Tunggu sejenak agar ESP32 memiliki waktu mengubah flash mode
        sleep(1);
        
        // Verifikasi status flash dari ESP32 melalui endpoint /get-flash
        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, $esp32cam_get_flash_status_endpoint);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch2, CURLOPT_TIMEOUT, 5);
        $state_response = curl_exec($ch2);
        $curlError2 = curl_error($ch2);
        curl_close($ch2);
        
        if ($state_response === false || empty($state_response)) {
            echo json_encode([
                "status" => "danger",
                "message" => "Gagal terhubung ke alat untuk mengambil data mode saat ini!"
            ]);
            exit;
        }
        
        $state_data = json_decode($state_response, true);
        if ($state_data && isset($state_data["state"])) {
            if ($state_data["state"] === $state) {
                echo json_encode([
                    "status" => "success",
                    "state" => $state,
                    "message" => "Mode flash berhasil diubah!"
                ]);
            } else {
                echo json_encode([
                    "status" => "danger",
                    "state" => $state_data["state"],
                    "message" => "Gagal mengubah mode flash!"
                ]);
            }
        } else {
            echo json_encode([
                "status" => "danger",
                "message" => "Data mode flash tidak valid!"
            ]);
        }
        exit;
    } else {
        echo json_encode([
            "status" => "danger",
            "message" => "Mode flash tidak valid!"
        ]);
        exit;
    }
}

// Jika tidak ada parameter 'state', ambil data mode flash saat ini dari ESP32
$current_state = "";
$message = "";
$status = "success";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $esp32cam_get_flash_status_endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2); // Timeout 2 detik
$state_response = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

if ($state_response === false || empty($state_response)) {
    $message = "Gagal terhubung ke alat untuk mengambil data mode saat ini!";
    $status = "danger";
} else {
    $state_data = json_decode($state_response, true);
    if ($state_data && isset($state_data["state"])) {
        $current_state = $state_data["state"];
    } else {
        $message = "Data mode flash tidak valid!";
        $status = "danger";
    }
}

echo json_encode([
    "status" => $status,
    "state" => $current_state,
    "message" => $message
]);
?>
