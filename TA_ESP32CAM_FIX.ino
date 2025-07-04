#include "esp_camera.h"
#include <WiFi.h>
#include <ArduinoWebsockets.h>
#include <ESPAsyncWebServer.h>
#include "esp_timer.h"
#include "img_converters.h"
#include "fb_gfx.h"
#include "soc/soc.h"
#include "soc/rtc_cntl_reg.h"
#include "driver/gpio.h"

// Konfigurasi pin untuk modul ESP32-CAM AI Thinker
#define PWDN_GPIO_NUM     32
#define RESET_GPIO_NUM    -1
#define XCLK_GPIO_NUM      0
#define SIOD_GPIO_NUM     26
#define SIOC_GPIO_NUM     27
#define Y9_GPIO_NUM       35
#define Y8_GPIO_NUM       34
#define Y7_GPIO_NUM       39
#define Y6_GPIO_NUM       36
#define Y5_GPIO_NUM       21
#define Y4_GPIO_NUM       19
#define Y3_GPIO_NUM       18
#define Y2_GPIO_NUM        5
#define VSYNC_GPIO_NUM    25
#define HREF_GPIO_NUM     23
#define PCLK_GPIO_NUM     22
#define LED_GPIO_NUM       4

// Konfigurasi Wi-Fi
const char* ssid     = "Davar";
const char* password = "00000000";

// WebSocket server address
const char* websockets_server_host = "192.168.121.177";
const uint16_t websockets_server_port = 3001;

camera_fb_t * fb = NULL;
size_t _jpg_buf_len = 0;
uint8_t * _jpg_buf = NULL;
uint8_t state = 0;

bool streaming = false;  // Flag untuk kontrol status streaming
bool flashMode = false;  // false = OFF, true = ON

using namespace websockets;
WebsocketsClient client;

AsyncWebServer server(80);

// Callback untuk pesan masuk dari WebSocket
void onMessageCallback(WebsocketsMessage message) {
  Serial.print("Pesan masuk dari WS: ");
  Serial.println(message.data());
}

// Inisialisasi kamera
esp_err_t init_camera() {
  camera_config_t config;
  config.ledc_channel = LEDC_CHANNEL_0;
  config.ledc_timer = LEDC_TIMER_0;
  config.pin_d0 = Y2_GPIO_NUM;
  config.pin_d1 = Y3_GPIO_NUM;
  config.pin_d2 = Y4_GPIO_NUM;
  config.pin_d3 = Y5_GPIO_NUM;
  config.pin_d4 = Y6_GPIO_NUM;
  config.pin_d5 = Y7_GPIO_NUM;
  config.pin_d6 = Y8_GPIO_NUM;
  config.pin_d7 = Y9_GPIO_NUM;
  config.pin_xclk = XCLK_GPIO_NUM;
  config.pin_pclk = PCLK_GPIO_NUM;
  config.pin_vsync = VSYNC_GPIO_NUM;
  config.pin_href = HREF_GPIO_NUM;
  config.pin_sscb_sda = SIOD_GPIO_NUM;
  config.pin_sscb_scl = SIOC_GPIO_NUM;
  config.pin_pwdn = PWDN_GPIO_NUM;
  config.pin_reset = RESET_GPIO_NUM;
  config.xclk_freq_hz = 20000000;
  config.pixel_format = PIXFORMAT_JPEG;

  // Parameter untuk kualitas dan ukuran gambar
  config.frame_size = FRAMESIZE_VGA; // FRAMESIZE_ + QVGA|CIF|VGA|SVGA|XGA|SXGA|UXGA
  config.jpeg_quality = 15; //10-63: Semakin rendah angkanya, semakin baik kualitas gambarnya
  config.fb_count = 2;
  
  // Inisialisasi kamera
  esp_err_t err = esp_camera_init(&config);
  if (err != ESP_OK) {
    Serial.printf("Gagal inisialisasi kamera: 0x%x", err);
    return err;
  }
  sensor_t * s = esp_camera_sensor_get();
  s->set_framesize(s, FRAMESIZE_VGA);
  Serial.println("Kamera siap");
  return ESP_OK;
};

// Inisialisasi WiFi dan WebSocket
esp_err_t init_wifi() {
  WiFi.begin(ssid, password);
  Serial.print("Menghubungkan ke WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nBerhasil Terhubung ke WiFi! IP Address: " + WiFi.localIP().toString());
  Serial.println("Menghubungkan ke WS...");
  client.onMessage(onMessageCallback);
  bool connected = client.connect(websockets_server_host, websockets_server_port, "/");
  if (!connected) {
    Serial.println("Gagal terhubung ke WS!");
    state = 3;
    return ESP_FAIL;
  }
  if (state == 3) {
    return ESP_FAIL;
  }

  Serial.println("Berhasil Terhubung ke WS!");
  client.send("Hello from ESP32-CAM");
  return ESP_OK;
};

void setup() {
  WRITE_PERI_REG(RTC_CNTL_BROWN_OUT_REG, 0);

  Serial.begin(115200);
  Serial.setDebugOutput(true);

  init_camera();
  init_wifi();

  // Inisialisasi pin flash
  pinMode(LED_GPIO_NUM, OUTPUT);
  digitalWrite(LED_GPIO_NUM, LOW);

  // Setup route web server
  server.on("/start", HTTP_GET, [](AsyncWebServerRequest *request){
    Serial.println("Streaming dimulai");
    streaming = true; 
    request->send(200, "text/plain", "Streaming dimulai");
  });

  server.on("/stop", HTTP_GET, [](AsyncWebServerRequest *request){
    streaming = false;
    Serial.println("Streaming dihentikan");
    request->send(200, "text/plain", "Streaming dihentikan");
  });
  
  server.on("/flash_on", HTTP_GET, [](AsyncWebServerRequest *request){
    flashMode = true;
    Serial.println("Flash mode ON");
    request->send(200, "text/plain", "Flash mode ON");
  });

  server.on("/flash_off", HTTP_GET, [](AsyncWebServerRequest *request){
    flashMode = false;
    Serial.println("Flash mode OFF");
    request->send(200, "text/plain", "Flash mode OFF");
  });

  server.on("/flash_status", HTTP_GET, [](AsyncWebServerRequest *request){
    String status = flashMode ? "on" : "off";
    request->send(200, "application/json", "{\"state\": \"" + status + "\"}");
  });

  server.on("/status", HTTP_GET, [](AsyncWebServerRequest *request){
    request->send(200, "application/json", "{\"status\":\"Online\"}");
  });

  server.begin();
}

static unsigned long lastReconnectAttempt = 0;
const unsigned long RECONNECT_INTERVAL = 5000;

void loop() {
  // Cek koneksi WiFi & Websocket
  if (millis() - lastReconnectAttempt > RECONNECT_INTERVAL) {
    lastReconnectAttempt = millis();

    // Reconnect WiFi jika terputus
    if (WiFi.status() != WL_CONNECTED) {
      Serial.println("Koneksi WiFi terputus! Mencoba menghubungkan kembali");
      WiFi.disconnect();
      WiFi.begin(ssid, password);

      unsigned long start = millis();
      while (millis() - start < 5000 && WiFi.status() != WL_CONNECTED) {
        delay(200);
        Serial.print(".");
      }
      if (WiFi.status() == WL_CONNECTED) {
        Serial.println("\nBerhasil terhubung kembali ke WiFi! IP Address: " + WiFi.localIP().toString());
      } else {
        Serial.println("\nGagal menghubungkan kembali ke WiFi!");
      }
    }

    // Reconnect Websocket jika terputus
    if (WiFi.status() == WL_CONNECTED && !client.available()) {
      Serial.println("Koneksi WS terputus! Mencoba menghubungkan kembali...");
      bool ok = client.connect(websockets_server_host, websockets_server_port, "/");
      if (ok) {
        Serial.println("Berhasil terhubung kembali ke WS!");
        client.send("Reconnected from ESP32-CAM");
      } else {
        Serial.println("Gagal menghubungkan kembali ke WS!");
      }
    }
  }

  // Kontrol LED flash ketika streaming sesuai mode flash
  if (streaming) {
    digitalWrite(LED_GPIO_NUM, flashMode ? HIGH : LOW);
  } else {
    digitalWrite(LED_GPIO_NUM, LOW);
  }

  // Streaming gambar jika aktif
  if (streaming && client.available()) {
    camera_fb_t *fb = esp_camera_fb_get();
    if (!fb) {
      Serial.println("Gagal menangkap gambar!");
      esp_camera_fb_return(fb);
      ESP.restart();
    }
    client.sendBinary((const char*) fb->buf, fb->len);
    Serial.println("Gambar terkirim");
    esp_camera_fb_return(fb);
    client.poll();
  }
}