#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <SPI.h>
#include <MFRC522.h>
#include <ArduinoJson.h>
#include <ESPAsyncWebServer.h>
#include <RTClib.h>
#include <DFRobotDFPlayerMini.h>

// Konfigurasi pin
#define SDA_PIN 21  // SDA LCD
#define SCL_PIN 22  // SCL LCD
#define SS_PIN 5    // SDA RFID
#define RST_PIN 4   // RST RFID
#define buzzerPin 32  // Buzzer
#define flashModeButton 25  // Push Button
#define DFPLAYER_RX_PIN   16   // ESP32 RX2 ← DFPlayer TX
#define DFPLAYER_TX_PIN   17   // ESP32 TX2 → DFPlayer RX

// Objek Serial & DFPlayer
HardwareSerial serialDFPlayer(2);   // UART2
DFRobotDFPlayerMini dfPlayer;

// Objek RFID, LCD, dan RTC
MFRC522 mfrc522(SS_PIN, RST_PIN);
LiquidCrystal_I2C lcd(0x27, 20, 4);
RTC_DS3231 rtc;

// Konfigurasi Wi-Fi
const char* ssid = "Davar";
const char* password = "00000000";

// Server address
const char* simpanRFIDAddress = "http://192.168.121.177/sistem_presensi_siswa_tugas_akhir/simpan_rfid.php";  
const char* scanPresensiAddress = "http://192.168.121.177:5000/rfid";

// Alamat IP ESP32CAM untuk kontrol mode flash
const char* esp32camServerAddress = "http://192.168.121.99";

// Variabel untuk mengelola mode
int currentMode = 0; // 0: Scan Presensi, 1: Tambah Kartu
bool modeDisplayActive = false;
unsigned long modeDisplayStart = 0;

// Variabel untuk mengelola perubahan mode dari web
volatile bool pendingModeChange = false;
volatile int newMode = 0;

// Variabel untuk mengelola perubahan mode flash dari web
volatile bool pendingFlashChange = false;
volatile bool newFlashState = false;

AsyncWebServer webServer(80);

bool isProcessing = false; 
unsigned long lastUpdateTime = 0;  
const unsigned long updateInterval = 1000;

// Variabel global untuk menyimpan data presensi yang diterima secara async
volatile bool newPresensiData = false;
String presensiStatus = "";
String presensiJenis = "";
String presensiNama = "";
String presensiWaktu = "";
String presensiKeterangan = "";

// Variabel debounce push button
bool flashModeState = false;  
unsigned long lastButtonPress = 0;
const unsigned long debounceDelay = 300;

// Fungsi untuk membaca RFID
String readRFID() {
  if (!mfrc522.PICC_IsNewCardPresent() || !mfrc522.PICC_ReadCardSerial()) {
    return "";
  }
  
  String rfidData = "";
  for (byte i = 0; i < mfrc522.uid.size; i++) {
    rfidData += String(mfrc522.uid.uidByte[i], HEX);
  }
  rfidData.toUpperCase();

  mfrc522.PICC_HaltA();
  mfrc522.PCD_StopCrypto1();

  digitalWrite(buzzerPin, HIGH);
  delay(500);
  digitalWrite(buzzerPin, LOW);

  return rfidData;
}

// Fungsi untuk menampilkan mode di LCD
void displayMode() {
  lcd.clear();
  if (currentMode == 0) {
    Serial.println("MODE : SCAN PRESENSI");
    lcd.setCursor(0, 1);
    lcd.print("MODE : SCAN PRESENSI");
  } else {
    Serial.println("MODE : TAMBAH KARTU");
    lcd.setCursor(0, 1);
    lcd.print("MODE : TAMBAH KARTU");
  }
}

// Fungsi untuk koneksi WiFi
void connectWiFi() {
  Serial.print("Menghubungkan ke WiFi");
  lcd.clear();
  lcd.setCursor(1, 1);
  lcd.print("Menghubungkan WiFi");
  WiFi.begin(ssid, password);

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  Serial.println("\nESP32 Berhasil Terhubung ke WiFi! IP Address: " + WiFi.localIP().toString());

  // Tunggu ESP32-CAM terhubung ke WiFi  
  waitForCam();

  Serial.println("\nESP32 & ESP32-CAM Berhasil Terhubung ke WiFi!");
  lcd.clear();
  lcd.setCursor(1, 1);
  lcd.print("Terhubung ke WiFi!");
  delay(2000);
  lcd.clear();
}

// Fungsi untuk cek koneksi WiFi
void checkWiFiConnection() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("Koneksi WiFi terputus! Mencoba menghubungkan kembali..");
    lcd.clear();
    lcd.setCursor(3, 1);
    lcd.print("WiFi Terputus!");
    delay(2000);
    connectWiFi();
    displayMode();
    modeDisplayActive = true;
    modeDisplayStart = millis();
  }
}

// Fungsi untuk polling status online ESP32-CAM
void waitForCam() {
  HTTPClient http;
  String url = String(esp32camServerAddress) + "/status";

  Serial.print("Menunggu ESP32-CAM Terhubung ke WiFi");

  while (true) {
    http.begin(url);
    int code = http.GET();
    http.end();

    if (code == 200) {
      break;
    }
    Serial.print(".");
    delay(500);
  }
}

// Fungsi helper untuk memutar file MP3 dari folder /mp3 berdasarkan nomor 
void playTrack(uint16_t idx) {
  dfPlayer.playMp3Folder(idx);
}

void setup() {
  Serial.begin(115200);

  // Inisialisasi Serial2 untuk DFPlayer
  serialDFPlayer.begin(9600, SERIAL_8N1, DFPLAYER_RX_PIN, DFPLAYER_TX_PIN);
  if (!dfPlayer.begin(serialDFPlayer)) {
    Serial.println("Gagal inisialisasi DFPlayer Mini");
  } else {
    Serial.println("DFPlayer Mini siap");
    dfPlayer.volume(30);   // Set volume (0-30)
    dfPlayer.outputDevice(DFPLAYER_DEVICE_SD);
  }

  lcd.init();
  lcd.backlight();

  pinMode(buzzerPin, OUTPUT);
  pinMode(flashModeButton, INPUT_PULLUP);
  
  SPI.begin();
  mfrc522.PCD_Init();

  connectWiFi();
  
  displayMode();
  modeDisplayActive = true;
  modeDisplayStart = millis();

  if (!rtc.begin()) {
    Serial.println("RTC tidak ditemukan!");
    while (1);
  }

  // Setup route web server
  webServer.on("/set-rtc", HTTP_POST, handleSetRTC);

  webServer.on("/pushdata", HTTP_POST, 
    [](AsyncWebServerRequest *request) {},
    NULL,
    handlePushDataBody
  );

  webServer.on("/get-mode", HTTP_GET, [](AsyncWebServerRequest *request) {
    String mode = (currentMode == 0) ? "scan" : "add";
    request->send(200, "application/json", "{\"mode\": \"" + mode + "\"}");
  });

  webServer.on("/set-mode", HTTP_GET, [](AsyncWebServerRequest *request) {
      if (request->hasParam("mode")) {
          String mode = request->getParam("mode")->value();
          if (mode == "scan") {
              newMode = 0;
          } else if (mode == "add") {
              newMode = 1;
          }
          pendingModeChange = true;
          request->send(200, "text/plain", "Mode changed to " + mode);
      } else {
          request->send(400, "text/plain", "Missing mode parameter");
      }
  });

  webServer.on("/set-flash", HTTP_GET, [](AsyncWebServerRequest *request) {
    if (request->hasParam("state")) {
      String state = request->getParam("state")->value();
      if (state == "on") {
        newFlashState = true;
      } else if (state == "off") {
        newFlashState = false;
      }
      pendingFlashChange = true;
      request->send(200, "text/plain", "Flash mode change pending to " + state);
    } else {
      request->send(400, "text/plain", "Missing state parameter");
    }
  });
  
  webServer.begin();
}

void loop() {
  checkWiFiConnection();
  
  if (pendingModeChange) {
    currentMode = newMode;
    displayMode();
    modeDisplayActive = true;
    modeDisplayStart = millis();
    pendingModeChange = false;
  }

  if (pendingFlashChange && !modeDisplayActive) {
    // Panggil fungsi toggleFlash setelah memastikan tidak ada tampilan lain
    toggleFlash(newFlashState);
    pendingFlashChange = false;
  }

  if (modeDisplayActive && (millis() - modeDisplayStart >= 2000)) {
    lcd.clear();
    modeDisplayActive = false;
  }

  if (newPresensiData) {
    newPresensiData = false;
    processPresensiDisplay();
  }

  if (digitalRead(flashModeButton) == LOW && (millis() - lastButtonPress > debounceDelay)) {
    lastButtonPress = millis();
    pendingFlashChange = true;
    newFlashState = !flashModeState; // Toggle state
  }

  DateTime now = rtc.now();
  
  // Jika sedang memproses (misalnya, menampilkan "Verifikasi Wajah.."), jangan update tampilan instruksi
  if (!modeDisplayActive && !isProcessing) {
    if (currentMode == 0) {
      scanPresensi(now);
    } else {
      tambahKartu(now);
    }
  }
}

void scanPresensi(DateTime now) {
  // Update tampilan hanya jika tidak sedang proses verifikasi
  if (millis() - lastUpdateTime >= updateInterval) {
    lastUpdateTime = millis(); 
    displayDateTimeAndInstructions(now);
  }

  String rfidData = readRFID();
  if (rfidData == "") {
    return;
  }
  
  Serial.print("No. RFID: ");
  Serial.println(rfidData);
  
  checkRFID(rfidData);
  
  delay(1000);
}

void tambahKartu(DateTime now) {
  displayDateTimeAndInstructions(now);
  
  String rfidData = readRFID();
  if (rfidData == "") {
    return;
  }
  
  Serial.print("No. RFID: ");
  Serial.println(rfidData);

  int textLength = rfidData.length();
  int lcdWidth = 20;
  int startPosition = (lcdWidth - textLength) / 2;
  if (startPosition < 0) startPosition = 0;

  if (sendDataToServer(rfidData)) {
    Serial.println("Data Berhasil Terkirim!");
    lcd.clear();
    lcd.setCursor(startPosition, 0);
    lcd.print(rfidData);
    lcd.setCursor(3, 2);
    lcd.print("Data Terkirim!");
  } else {
    Serial.println("Gagal Mengirimkan Data!");
    lcd.clear();
    lcd.setCursor(0, 1);
    lcd.print("Gagal Mengirim Data!");
    lcd.setCursor(0, 2);
    lcd.print("Silakan Coba Lagi...");
  }
  delay(3000);
  lcd.clear();
}

bool sendDataToServer(String rfidData) {
  HTTPClient http;
  http.begin(simpanRFIDAddress);
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");

  String postData = "rfid=" + rfidData;
  int httpResponseCode = http.POST(postData);
  String responsePayload = http.getString();
  http.end();

  Serial.println("Server response: " + responsePayload);
  return (httpResponseCode == 200);
}

bool checkRFID(String rfidData) {
  HTTPClient http;
  http.setTimeout(5000);  
  http.begin(scanPresensiAddress);
  http.addHeader("Content-Type", "application/json");

  String postData = "{\"rfid\": \"" + rfidData + "\"}";
  int httpResponseCode = http.POST(postData);

  if (httpResponseCode == 200) {
    String response = http.getString();
    Serial.println("Server response: " + response);

    StaticJsonDocument<200> doc;
    DeserializationError error = deserializeJson(doc, response);

    if (error) {
      Serial.print("JSON Parsing failed: ");
      Serial.println(error.f_str());
      return false;
    }

    const char* status = doc["status"];
    const char* keterangan = doc["keterangan"];

    if (String(status) == "200") {
      Serial.println(keterangan);
      isProcessing = true;
      lcd.clear();
      lcd.setCursor(1, 0);
      lcd.print("Verifikasi Wajah..");
      lcd.setCursor(2, 2);
      lcd.print("Silakan Hadapkan");
      lcd.setCursor(0, 3);
      lcd.print("Wajah Anda ke Kamera");
      return true; 
    } else if (String(status) == "404") {
      Serial.println(keterangan);
      playTrack(4);   // 004.mp3: "Kartu Tidak Terdaftar"
      lcd.clear();
      lcd.setCursor(4, 1);
      lcd.print("Kartu Tidak");
      lcd.setCursor(4, 2);
      lcd.print("Terdaftar!");
      delay(3000); 
      lcd.clear();
      return false;
    } else if (String(status) == "422") {
      Serial.println(keterangan);
      playTrack(3);   // 003.mp3: "Anda Sudah Presensi Keluar Hari Ini!"
      lcd.clear();
      lcd.setCursor(5, 1);
      lcd.print("Anda Sudah");
      lcd.setCursor(2, 2);
      lcd.print("Presensi Keluar!");
      delay(3000); 
      lcd.clear();
      return false;
    } else if (String(status) == "403") {
      Serial.println(keterangan);
      playTrack(6);   // 006.mp3: "Hari Ini Libur! Tidak Dapat Melakukan Presensi"
      lcd.clear();
      lcd.setCursor(2, 1);
      lcd.print("Hari Ini Libur!!");
      delay(3000); 
      lcd.clear();
      return false;
    } else if (String(status) == "423") {
      Serial.println(keterangan);
      playTrack(7);   // 007.mp3: "Siswa Sudah Nonaktif! Tidak Dapat Melakukan Presensi"
      lcd.clear();
      lcd.setCursor(5, 1);
      lcd.print("Siswa Sudah");
      lcd.setCursor(6, 2);
      lcd.print("Nonaktif!");
      delay(3000); 
      lcd.clear();
      return false;
    }
  } else {
    Serial.println("Gagal mengirimkan data!");
    playTrack(8);   // 008.mp3: "Gagal Mengirimkan Data! Silakan Coba Lagi"
    lcd.clear();
    lcd.setCursor(0, 1);
    lcd.print("Gagal Mengirim Data!");
    lcd.setCursor(0, 2);
    lcd.print("Silakan Coba Lagi...");
    delay(3000);
    lcd.clear();
  }

  http.end();
  return false;
}

void handleSetRTC(AsyncWebServerRequest *request) {
  if (request->hasParam("datetime", true)) {
    String datetime = request->getParam("datetime", true)->value();
    Serial.println("Data waktu diterima: " + datetime);

    int year   = datetime.substring(0, 4).toInt();
    int month  = datetime.substring(5, 7).toInt();
    int day    = datetime.substring(8, 10).toInt();
    int hour   = datetime.substring(11, 13).toInt();
    int minute = datetime.substring(14, 16).toInt();
    int second = datetime.substring(17, 19).toInt();

    rtc.adjust(DateTime(year, month, day, hour, minute, second));

    request->send(200, "text/plain", "Waktu RTC berhasil diatur!");
    Serial.println("Waktu RTC berhasil diatur!");
  } else {
    request->send(400, "text/plain", "Parameter 'datetime' tidak ditemukan!");
    Serial.println("Gagal mengatur RTC: Parameter 'datetime' tidak ditemukan.");
  }
}

void handlePushDataBody(AsyncWebServerRequest *request, uint8_t *data, size_t len, size_t index, size_t total) {
  String jsonString = "";
  for (size_t i = 0; i < len; i++) {
    jsonString += (char)data[i];
  }
  if (index + len >= total) {
    Serial.println("Data presensi diterima:");
    Serial.println(jsonString);
    
    StaticJsonDocument<512> doc;
    DeserializationError error = deserializeJson(doc, jsonString);
    
    if (!error) {
      // Cek apakah kunci ada di JSON, jika tidak ada berikan string kosong
      presensiStatus = doc.containsKey("status") ? String((const char*)doc["status"]) : "";
      presensiJenis  = doc.containsKey("jenisPresensi") ? String((const char*)doc["jenisPresensi"]) : "";
      presensiNama   = doc.containsKey("nama") ? String((const char*)doc["nama"]) : "";
      presensiWaktu  = doc.containsKey("waktu") ? String((const char*)doc["waktu"]) : "";
      presensiKeterangan = doc.containsKey("keterangan") ? String((const char*)doc["keterangan"]) : "";
      
      newPresensiData = true;
    } else {
      Serial.println("Parsing JSON Gagal.");
    }
    request->send(200, "text/plain", "Data diterima.");
  }
}

void processPresensiDisplay() {
  lcd.clear();
  if (presensiStatus == "1") {
      Serial.println("Presensi Berhasil!");

      // Putar suara sesuai jenis presensi
      if (presensiJenis == "Presensi Masuk") {
        playTrack(1); // 001.mp3 = "Presensi Masuk Berhasil!"
      } else if (presensiJenis == "Presensi Keluar") {
        playTrack(2); // 002.mp3 = "Presensi Keluar Berhasil!"
      }

      lcd.setCursor(1, 1);
      lcd.print("Presensi Berhasil!");
      delay(2000);
      lcd.clear();
      lcd.setCursor(0, 0);
      lcd.print(presensiJenis);
      lcd.setCursor(0, 1);
      lcd.print(presensiNama);
      lcd.setCursor(0, 2);
      lcd.print(presensiWaktu);
      lcd.setCursor(0, 3);
      lcd.print(presensiKeterangan); 
  } else {
      Serial.println("Presensi gagal, tidak ada wajah yang cocok dalam 20 detik!");
      playTrack(5);   // 005.mp3: "Presensi Gagal! Tidak Ada Wajah yang Cocok"
      lcd.setCursor(3, 0);
      lcd.print("Presensi Gagal");
      lcd.setCursor(1, 2);
      lcd.print("Wajah Tidak Cocok!");
  }
  delay(3000);
  lcd.clear();

  // Reset variabel
  presensiStatus = "";
  presensiJenis = "";
  presensiNama = "";
  presensiWaktu = "";
  presensiKeterangan = "";

  isProcessing = false;
}

void printTwoDigits(int number) {
  if (number < 10) {
    lcd.print("0");
  }
  lcd.print(number);
}

void displayDateTimeAndInstructions(DateTime now) {
  lcd.setCursor(1, 0);
  lcd.print("Tempelkan Kartu...");  
  lcd.setCursor(7, 2);
  printTwoDigits(now.hour());
  lcd.print(":");
  printTwoDigits(now.minute());
  
  lcd.setCursor(2, 3);
  lcd.print(getDayName(now.dayOfTheWeek()));
  lcd.print(", ");
  printTwoDigits(now.day());
  lcd.print("/");
  printTwoDigits(now.month());
  lcd.print("/");
  lcd.print(now.year(), DEC);
}

String getDayName(int dayOfWeek) {
  switch (dayOfWeek) {
    case 0: return "Min";
    case 1: return "Sen";
    case 2: return "Sel";
    case 3: return "Rab";
    case 4: return "Kam";
    case 5: return "Jum";
    case 6: return "Sab";
    default: return "---";
  }
}

// Fungsi untuk mengirim perintah flash ke ESP32CAM dan polling status melalui /flash_status
void toggleFlash(bool state) {
  HTTPClient http;
  String url = String(esp32camServerAddress) + (state ? "/flash_on" : "/flash_off");

  Serial.println("Mengirim perintah ke: " + url);
  http.begin(url);
  int httpResponseCode = http.GET();
  http.end();

  if (httpResponseCode == 200) {
    // Polling status flash dari ESP32CAM
    String confirmedStatus = getFlashStatusFromCam();
    
    // Update state lokal berdasarkan status aktual dari ESP32CAM
    flashModeState = (confirmedStatus == "on");

    // Cek apakah status sudah sesuai dengan perintah user
    if ((state && flashModeState) || (!state && !flashModeState)) {
      Serial.println("Flash mode berhasil diubah dan dikonfirmasi: " + confirmedStatus);
    } else {
      Serial.println("Status flash tidak sesuai perintah. Dikonfirmasi: " + confirmedStatus);
      
      // Koreksi otomatis: kirim ulang perintah agar mode sesuai dengan keinginan user
      String correctionUrl = String(esp32camServerAddress) + (state ? "/flash_on" : "/flash_off");
      Serial.println("Mengirim perintah koreksi ke: " + correctionUrl);
      http.begin(correctionUrl);
      int correctionResponseCode = http.GET();
      http.end();
      
      // Update status setelah koreksi
      confirmedStatus = getFlashStatusFromCam();
      flashModeState = (confirmedStatus == "on");
      
      if ((state && flashModeState) || (!state && !flashModeState)) {
        Serial.println("Koreksi berhasil: Flash mode sekarang: " + confirmedStatus);
      } else {
        Serial.println("Koreksi gagal: Flash mode masih: " + confirmedStatus);
      }
    }
    
    // Update tampilan LCD sesuai kondisi akhir
    lcd.clear();
    lcd.setCursor(3, 1);
    lcd.print(flashModeState ? "FLASH MODE ON" : "FLASH MODE OFF");
    delay(2000);
    lcd.clear();
  } else {
    Serial.println("Gagal mengirim perintah, HTTP code: " + String(httpResponseCode));
    lcd.clear();
    lcd.setCursor(5, 1);
    lcd.print("Gagal Ubah");
    lcd.setCursor(5, 2);
    lcd.print("Mode Flash");
    delay(2000);
    lcd.clear();
  }
}

// Fungsi untuk mengambil status flash dari ESP32CAM melalui endpoint /flash_status
String getFlashStatusFromCam() {
  HTTPClient http;
  String statusURL = String(esp32camServerAddress) + "/flash_status";
  http.begin(statusURL);
  int httpResponseCode = http.GET();
  String flashStatus = "";
  if (httpResponseCode == 200) {
    String response = http.getString();
    if (response.length() > 0) {
      StaticJsonDocument<200> doc;
      DeserializationError error = deserializeJson(doc, response);
      if (!error && doc.containsKey("state")) {
        flashStatus = doc["state"].as<String>();
      } else {
        Serial.println("Parsing flash_status JSON gagal");
      }
    }
  } else {
    Serial.println("Gagal mendapatkan flash status, HTTP code: " + String(httpResponseCode));
  }
  http.end();
  return flashStatus;
}
