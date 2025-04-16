<?php
// Konfigurasi alamat IP ESP32
$esp32_ip = "192.168.10.26:80";
$esp32_get_mode_endpoint = "http://$esp32_ip/get-mode";
$esp32_set_mode_endpoint = "http://$esp32_ip/set-mode";

// Jika parameter 'mode' disediakan via URL, proses perubahan mode
if (isset($_GET['mode'])) {
    $mode = $_GET['mode'];
    if ($mode === "scan" || $mode === "add") {
        $url = $esp32_set_mode_endpoint . "?mode=" . urlencode($mode);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Set timeout maksimal 5 detik untuk penggantian mode
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $curlResponse = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlResponse === false) {
            echo json_encode([
                "status" => "danger",
                "message" => "Gagal mengubah mode alat!"
            ]);
            exit;
        }
        
        echo json_encode([
            "status" => "success",
            "mode" => $mode,
            "message" => "Mode alat berhasil diubah!"
        ]);
        exit;
    } else {
        echo json_encode([
            "status" => "danger",
            "message" => "Mode tidak valid!"
        ]);
        exit;
    }
}

// Jika tidak ada parameter 'mode', ambil data mode saat ini dari ESP32
$current_mode = "";
$message = "";
$status = "success";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $esp32_get_mode_endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2); // Timeout 2 detik
$mode_response = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

if ($mode_response === false || empty($mode_response)) {
    $message = "Gagal terhubung ke alat untuk mengambil data mode saat ini!";
    $status = "danger";
} else {
    $mode_data = json_decode($mode_response, true);
    if ($mode_data && isset($mode_data["mode"])) {
        $current_mode = $mode_data["mode"];
    } else {
        $message = "Data mode tidak valid!";
        $status = "danger";
    }
}

echo json_encode([
    "status" => $status,
    "mode" => $current_mode,
    "message" => $message
]);
?>
