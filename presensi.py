import os
import cv2
import numpy as np
import torch
import websocket
import threading
from flask import Flask, Response, request, jsonify
from facenet_pytorch import InceptionResnetV1, MTCNN
from sklearn.preprocessing import normalize
from flask_cors import CORS
import mysql.connector
import shutil
import time
import requests
import json
import datetime

# Inisialisasi aplikasi Flask dan CORS
app = Flask(__name__)
CORS(app)

#####################################
# FLAG DAN VARIABEL GLOBAL
#####################################
face_recognition_active = False  # Mengontrol proses face recognition
capture_mode = False             # True saat mode capture dataset aktif
face_recognition_timer = None    # Variabel global untuk menyimpan timer

# Untuk face recognition (dari WebSocket)
# Frame akan menyimpan tuple (timestamp, frame) atau None
frame = None            
annotated_frame = None  # Frame beranotasi
display_frame = None    # Frame yang akan dikirim ke website

# Variabel global untuk menentukan waktu mulai streaming
stream_start_time = 0

# Lock untuk mengelola frame dengan aman
frame_lock = threading.Lock()

# Untuk dataset capture (kamera lokal)
capture_dataset = False
nis_capture = None
student_name_capture = None
rfid_capture = None
num_images = 20
captured_count = 0
capture_done = False
cap_capture = None  # Kamera khusus untuk capture dataset

# Variabel global untuk menyimpan data status presensi
current_data_status = {"status": "waiting"}  # waiting, scanning, rfid_not_registered, already_checked_out, success, failed_timeout, holiday, nonaktif

# Flag untuk mencegah pemrosesan berulang dari frame yang sama
attendance_in_process = False

# Lock untuk menghindari race condition saat update data
status_lock = threading.Lock()

# IP Address ESP32 dan ESP32-CAM
ESP32_IP = "192.168.121.26:80"
ESP32CAM_IP = "192.168.121.99:80"

#####################################
# KONEKSI DATABASE MYSQL
#####################################
def get_db_connection():
    try:
        conn = mysql.connector.connect(
            host="localhost",
            user="root",
            password="",
            database="db_sistem_presensi_siswa_tugas_akhir"
        )
        return conn
    except mysql.connector.Error as e:
        print("Koneksi database gagal:", e)
        return None

#####################################
# FACE RECOGNITION
#####################################

# Inisialisasi MTCNN dan InceptionResnetV1
mtcnn = MTCNN(keep_all=True)
resnet = InceptionResnetV1(pretrained='vggface2').eval()

# Callback WebSocket: hanya proses frame jika tidak dalam mode capture
def on_message(ws, message):
    global frame, capture_mode, stream_start_time
    if capture_mode:
        with frame_lock:
            frame = None
        return
    if isinstance(message, bytes):
        nparr = np.frombuffer(message, np.uint8)
        new_frame = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
        current_time = time.time()
        # Hanya terima frame jika waktu sekarang sudah melewati stream_start_time
        if current_time < stream_start_time:
            return
        with frame_lock:
            frame = (current_time, new_frame)

# URL server WebSocket ESP32-CAM
ws_url = "ws://localhost:3001"

# Memulai klien WebSocket di thread terpisah
def start_websocket():
    while True:
        try:
            ws = websocket.WebSocketApp(ws_url, on_message=on_message)
            ws.run_forever()
        except Exception as e:
            print(f"WebSocket Error: {e}")
            time.sleep(5)

# Mulai WebSocket di thread baru
threading.Thread(target=start_websocket, daemon=True).start()

# Fungsi untuk mendeteksi dan meng-embed wajah
def detect_and_embed(image):
    with torch.no_grad():
        boxes, _ = mtcnn.detect(image)
        if boxes is not None:
            faces = []
            for box in boxes:
                face = image[int(box[1]):int(box[3]), int(box[0]):int(box[2])]
                if face.size == 0:
                    continue
                face = cv2.resize(face, (160, 160))
                face = np.transpose(face, (2, 0, 1)).astype(np.float32) / 255.0
                face_tensor = torch.tensor(face).unsqueeze(0)
                embedding = resnet(face_tensor).detach().numpy().flatten()
                faces.append(embedding)
            return faces
    return []

# Fungsi untuk embedding wajah dari dataset
def embed_faces_from_dataset(dataset_dir):
    known_face_nis = []
    known_face_names = []
    known_face_rfids = []
    known_face_embeddings = []
    print("Memulai proses embedding wajah dari dataset...")
    student_count = 0  # Hitung jumlah folder (siswa) yang diproses
    for student_folder in os.listdir(dataset_dir):
        student_folder_path = os.path.join(dataset_dir, student_folder)
        if os.path.isdir(student_folder_path):
            student_count += 1
            parts = student_folder.split('_')
            if len(parts) >= 3:
                nis = parts[0]
                name = parts[1]
                rfid = parts[2]
            else:
                nis = "Unknown"
                name = student_folder
                rfid = "Unknown"
            print(f"Memproses folder: {student_folder}...")
            folder_embedding_count = 0  # Hitung embedding untuk folder ini
            for image_file in os.listdir(student_folder_path):
                if image_file.lower().endswith('.jpg'):
                    image_path = os.path.join(student_folder_path, image_file)
                    known_image = cv2.imread(image_path)
                    if known_image is not None:
                        known_image_rgb = cv2.cvtColor(known_image, cv2.COLOR_BGR2RGB)
                        embeddings = detect_and_embed(known_image_rgb)
                        if embeddings:
                            for emb in embeddings:
                                known_face_embeddings.append(emb)
                                known_face_nis.append(nis)
                                known_face_names.append(name)
                                known_face_rfids.append(rfid)
                                folder_embedding_count += 1
            print(f"Embedding selesai untuk {student_folder}. Total foto yang diembedding: {folder_embedding_count}")
    print("Proses embedding selesai.")
    print("Total siswa yang berhasil diproses: " + str(student_count))
    return known_face_nis, known_face_names, known_face_rfids, known_face_embeddings

# Fungsi untuk menyimpan data wajah dalam file face_data.npy
def save_face_data(nis, names, rfids, embeddings, filename='data/embeddings/face_data.npy'):
    folder = os.path.dirname(filename)
    if not os.path.exists(folder):
        os.makedirs(folder)
    data = {'nis': nis, 'names': names, 'rfid': rfids, 'embeddings': embeddings}
    np.save(filename, data)
    print("Face data berhasil disimpan.")

# Fungsi untuk memuat face embedding dari file face_data.npy
def load_face_data(filename='data/embeddings/face_data.npy'):
    if os.path.exists(filename):
        print(f"Memuat face data dari {filename}...")
        data = np.load(filename, allow_pickle=True).item()
        return data['nis'], data['names'], data['rfid'], data['embeddings']
    return [], [], [], []

# Folder dataset dan file data wajah
dataset_dir = "data/dataset"
face_data_file = 'data/embeddings/face_data.npy'

# Memuat data wajah dari file face_data.npy
known_face_nis, known_face_names, known_face_rfids, known_face_embeddings = load_face_data(face_data_file)

# Fungsi untuk mengenali wajah dengan persentase kesamaan
def recognize_faces(known_embeddings, known_nis, known_names, known_rfids, test_embeddings, threshold=0.6):
    # Normalisasi semua embedding agar jarak lebih stabil
    known_embeddings = normalize(known_embeddings, axis=1, norm='l2')
    test_embeddings = normalize(test_embeddings, axis=1, norm='l2')

    recognized_nis = []
    recognized_names = []
    recognized_rfids = []
    similarity_percentages = []
    
    for test_embedding in test_embeddings:
        distances = np.linalg.norm(known_embeddings - test_embedding, axis=1)
        min_distance_idx = np.argmin(distances)
        distance = distances[min_distance_idx]
        similarity_percentage = max(0, (1 - distance) * 100)
        
        if distance < threshold:
            recognized_nis.append(known_nis[min_distance_idx])
            recognized_names.append(known_names[min_distance_idx])
            recognized_rfids.append(known_face_rfids[min_distance_idx])
        else:
            recognized_nis.append("Unknown")
            recognized_names.append("Unknown")
            recognized_rfids.append("Unknown")
            similarity_percentage = 0
        
        similarity_percentages.append(similarity_percentage)
    
    return recognized_nis, recognized_names, recognized_rfids, similarity_percentages

def set_status(status, extra_data={}):
    # Update status dengan lock
    global current_data_status
    with status_lock:
        current_data_status = {"status": status, **extra_data}
    if status == "success":
        delay = 6
    elif status in {"rfid_not_registered", "already_checked_out", "failed_timeout", "holiday", "nonaktif"}:
        delay = 4
    else:
        return
    # Reset ke waiting setelah beberapa saat sesuai durasi yang ditentukan
    threading.Timer(delay, set_status, args=("waiting", {})).start()

# Endpoint untuk mendapatkan status presensi
@app.route('/get_status', methods=['GET'])
def get_status():
    return jsonify(current_data_status)

# Endpoint untuk memperbarui status presensi
@app.route('/update_status', methods=['POST'])
def update_status():
    data = request.json
    if not data or "status" not in data:
        return jsonify({"error": "Invalid request"}), 400

    status = data.pop("status")  # Ambil status, sisanya jadi extra_data
    set_status(status, data)
    return jsonify({"message": "Status updated", "data": current_data_status})

# Endpoint untuk menerima dan memproses data RFID
@app.route('/rfid', methods=['POST'])
def receive_rfid():
    global current_data_status, face_recognition_timer, frame, annotated_frame, display_frame, attendance_in_process, stream_start_time

    data = request.get_json()
    
    if not data or "rfid" not in data:
        return jsonify({"status": "0", "keterangan": "RFID tidak ditemukan"})

    rfid = data["rfid"]
    print(f"No. RFID yang diterima: {rfid}")

    # Ambil waktu sekarang satu kali sebagai objek datetime
    now = datetime.datetime.now()
    waktu_sekarang = now.strftime('%Y-%m-%d %H:%M:%S')
    tanggal_hari_ini = now.strftime('%Y-%m-%d')

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    # Simpan ke history_rfid
    cursor.execute("INSERT INTO history_rfid (no_rfid, waktu) VALUES (%s, %s)", (rfid, waktu_sekarang))
    conn.commit()

    # Ambil setting hari operasional dari database
    cursor.execute("SELECT hari_operasional FROM pengaturan WHERE id = 1")
    pengaturan = cursor.fetchone()
    if pengaturan and pengaturan.get('hari_operasional'):
        try:
            # Misalnya, jika 'hari_operasional' bernilai "1,2,3,4,5"
            operational_days = [int(day.strip()) for day in pengaturan['hari_operasional'].split(',')]
        except Exception as e:
            print("Error parsing hari_operasional:", e)
            operational_days = []  # Jika error, dianggap tidak ada hari operasional
    else:
        # Jika tidak ada data, anggap tidak ada hari operasional (semua hari libur)
        operational_days = []

    # Cek apakah hari ini adalah hari operasional
    # now.isoweekday(): 1 (Senin) sampai 7 (Minggu)
    if now.isoweekday() not in operational_days:
        set_status("holiday")
        cursor.close()
        conn.close()
        print("Hari ini libur!")
        return jsonify({"status": "403", "keterangan": "Hari ini libur!"})

    # Cek apakah hari ini libur (di tabel hari_libur)
    cursor.execute("SELECT id FROM hari_libur WHERE %s BETWEEN tanggal_mulai AND tanggal_selesai", (tanggal_hari_ini,))
    if cursor.fetchone():
        set_status("holiday")
        cursor.close()
        conn.close()
        print("Hari ini libur!")
        return jsonify({"status": "403", "keterangan": "Hari ini libur!"})

    # Cek apakah RFID terdaftar di tabel siswa
    cursor.execute("SELECT * FROM siswa WHERE no_rfid = %s", (rfid,))
    siswa = cursor.fetchone()

    if siswa:
        siswa_id = siswa['id']

        # Pemeriksaan status: hanya siswa dengan status 'Aktif' yang dapat presensi
        if siswa['status'] != 'Aktif':
            set_status("nonaktif")
            cursor.close()
            conn.close()
            print("Siswa nonaktif!")
            return jsonify({"status": "423", "keterangan": "Siswa nonaktif!"})
            
        # Cek apakah siswa sudah melakukan presensi keluar hari ini
        cursor.execute("SELECT * FROM presensi WHERE siswa_id = %s AND tanggal = %s AND waktu_keluar IS NOT NULL", (siswa_id, tanggal_hari_ini))
        presensi_keluar = cursor.fetchone()

        if presensi_keluar:
            set_status("already_checked_out")
            response = {"status": "422", "keterangan": "Sudah presensi keluar!"}
            print("Sudah presensi keluar!")
        else:
            # Simpan RFID ke `tmp_rfid_presensi` untuk trigger face recognition
            cursor.execute("SELECT * FROM tmp_rfid_presensi WHERE id = 1")
            tmp_rfid_presensi = cursor.fetchone()

            if tmp_rfid_presensi:
                cursor.execute("UPDATE tmp_rfid_presensi SET no_rfid = %s WHERE id = 1", (rfid,))
            else:
                cursor.execute("INSERT INTO tmp_rfid_presensi (id, no_rfid) VALUES (1, %s)", (rfid,))

            conn.commit()

            response = {"status": "200", "keterangan": "RFID terdaftar dan proses presensi dimulai."}
            print("RFID terdaftar dan proses presensi dimulai.")

            with status_lock:
                current_data_status = {"status": "scanning"}

            # Kirim perintah start streaming ke ESP32-CAM
            send_start_command_to_esp32cam()

            # Reset frame agar data sebelumnya tidak ikut diproses
            with frame_lock:
                frame = None
                annotated_frame = None
                display_frame = None 

            # Set stream_start_time ke waktu sekarang + delay 0.5 detik
            stream_start_time = time.time() + 0.5

            # Aktifkan face recognition
            enable_face_recognition()

            # Hentikan timer face recognition sebelumnya jika ada
            if face_recognition_timer is not None:
                face_recognition_timer.cancel()

            # Jalankan timer timeout face recognition (20 detik)
            face_recognition_timer = threading.Timer(500, face_recognition_timeout)
            face_recognition_timer.start()

            # Reset flag presensi agar tidak mengandung data lama
            attendance_in_process = False
    else:
        print("RFID tidak terdaftar!")
        response = {"status": "404", "keterangan": "RFID tidak terdaftar!"}
        set_status("rfid_not_registered")

    cursor.close()
    conn.close()

    return jsonify(response)

def capture_image(nis, jenis):
    global frame

    # Jika frame disimpan sebagai tuple, ambil bagian frame saja
    with frame_lock:
        current_frame = frame[1] if frame and isinstance(frame, tuple) else frame

    if current_frame is None:
        print("Tidak ada frame yang tersedia dari ESP32-CAM.")
        return None

    folder_path = "data/foto/foto_presensi_siswa"
    os.makedirs(folder_path, exist_ok=True)

    tanggal = datetime.date.today().strftime('%Y%m%d')
    waktu = datetime.datetime.now().strftime('%H%M%S')
    file_name = f"{nis}_{jenis}_{tanggal}_{waktu}.jpg"
    file_path = os.path.join(folder_path, file_name)

    # Simpan frame dari ESP32-CAM
    cv2.imwrite(file_path, current_frame)
    print(f"Gambar disimpan: {file_path}")

    return file_name

# Fungsi untuk mendapatkan jam masuk dari database
def get_jam_masuk():
    conn = get_db_connection()
    if conn is None:
        print("Gagal terhubung ke database, menggunakan jam masuk default.")
        return "07:00:00"

    try:
        cursor = conn.cursor()
        cursor.execute("SELECT jam_masuk FROM pengaturan WHERE id = 1")
        result = cursor.fetchone()
        
        if result:
            jam_masuk = result[0]
            if isinstance(jam_masuk, datetime.time):
                jam_masuk = jam_masuk.strftime("%H:%M:%S")
            elif isinstance(jam_masuk, datetime.timedelta):
                jam_masuk = (datetime.datetime.min + jam_masuk).time().strftime("%H:%M:%S")
            return jam_masuk

        print("Data jam masuk tidak ditemukan, menggunakan jam masuk default.")
        return "07:00:00"
    
    except Exception as e:
        print(f"Terjadi kesalahan saat mengambil jam masuk: {e}, menggunakan jam masuk default.")
        return "07:00:00"

    finally:
        cursor.close()
        conn.close()

# Fungsi untuk mencatat presensi berdasarkan pencocokan RFID dari tabel tmp_rfid_presensi & wajah yang terdeteksi
def record_attendance(rfid):
    global current_data_status, attendance_in_process, face_recognition_timer
    try:
        conn = get_db_connection()
        if conn is None:
            print("Gagal terhubung ke database.")
            attendance_in_process = False
            return
        
        cursor = conn.cursor()
        
        # Ambil no_rfid terakhir dari tmp_rfid_presensi
        cursor.execute("SELECT no_rfid FROM tmp_rfid_presensi ORDER BY id DESC LIMIT 1")
        tmp_rfid = cursor.fetchone()
        
        if not tmp_rfid:
            print("No. RFID tidak ditemukan di tmp_rfid_presensi. Presensi gagal!")
            attendance_in_process = False
            return
        
        tmp_rfid = tmp_rfid[0]

        if tmp_rfid != rfid:
            print("RFID dari wajah yang terdeteksi tidak cocok dengan tmp_rfid_presensi. Presensi gagal!")
            attendance_in_process = False
            return
        
        # Ambil data siswa berdasarkan RFID
        cursor.execute("SELECT s.id, s.nis, s.nama, k.nama_kelas, s.alamat, s.token, s.id_chat FROM siswa s JOIN kelas k ON s.kelas_id = k.id WHERE s.no_rfid = %s", (rfid,))
        result = cursor.fetchone()
        
        if not result:
            print("Data siswa tidak ditemukan.")
            attendance_in_process = False
            return
        
        siswa_id, nis, nama_siswa, kelas, alamat, token, chat_id = result
        print(f"Pencocokan RFID berhasil untuk {nama_siswa} dengan RFID: {rfid}")

        tanggal_hari_ini = datetime.date.today()
        waktu_sekarang = datetime.datetime.now().strftime('%H:%M:%S')

        # Ambil jam masuk dari database
        jam_masuk_db = get_jam_masuk()

        # Konversi jam masuk dan waktu sekarang ke format datetime.time
        waktu_masuk_default = datetime.datetime.strptime(jam_masuk_db, "%H:%M:%S").time()
        waktu_presensi = datetime.datetime.strptime(waktu_sekarang, "%H:%M:%S").time()
        
        # Tentukan keterangan masuk
        if waktu_presensi <= waktu_masuk_default:
            keterangan_masuk = "Tepat Waktu"
            keterangan_masuk_telegram = "Anak Anda masuk sekolah tepat waktu."
        else:
            keterangan_masuk = "Terlambat"
            keterangan_masuk_telegram = "Anak Anda terlambat masuk sekolah."
        
        # Periksa apakah sudah ada presensi hari ini
        cursor.execute("""
            SELECT id, waktu_masuk, waktu_keluar FROM presensi 
            WHERE siswa_id = %s AND tanggal = %s
        """, (siswa_id, tanggal_hari_ini))
        result = cursor.fetchone()
        
        if result:
            presensi_id, waktu_masuk, waktu_keluar = result
            # Jika waktu_masuk belum terisi atau bernilai default, lakukan presensi masuk
            if waktu_masuk is None or waktu_masuk == '00:00:00':
                foto_masuk = capture_image(nis, "masuk")
                
                if foto_masuk:
                    cursor.execute("""
                        UPDATE presensi 
                        SET waktu_masuk = %s, foto_masuk = %s, status = 'Masuk'
                        WHERE id = %s
                    """, (waktu_sekarang, foto_masuk, presensi_id))
                    print(f"Presensi masuk dicatat untuk {nama_siswa} pada {waktu_sekarang}.")
                    
                    # Batalkan timer timeout setelah presensi sukses
                    if face_recognition_timer is not None:
                        face_recognition_timer.cancel()
                        face_recognition_timer = None
                    
                    # Data JSON untuk dikirim ke ESP32
                    data = {
                        "status": "1",
                        "jenisPresensi": "Presensi Masuk",
                        "nama": nama_siswa,
                        "waktu": waktu_sekarang,
                        "keterangan": keterangan_masuk
                    }
                    send_attendance_data_to_esp32(data)
                    
                    set_status("success", {
                        "jenisPresensi": "Presensi Masuk",
                        "nama": nama_siswa,
                        "kelas": kelas,
                        "waktu": waktu_sekarang,
                        "keterangan": keterangan_masuk,
                        "fotoMasuk": foto_masuk
                    })
                    
                    message = (f"{keterangan_masuk_telegram}\n"
                               f"- Nama: {nama_siswa}\n"
                               f"- NIS: {nis}\n"
                               f"- Kelas: {kelas}\n"
                               f"- Alamat: {alamat}\n"
                               f"- Tanggal: {tanggal_hari_ini}\n"
                               f"- Waktu Masuk: {waktu_sekarang}\n")
                    foto_presensi = foto_masuk
                else:
                    print("Gagal mengambil foto masuk.")
            # Jika waktu_masuk sudah terisi tapi waktu_keluar masih kosong, lakukan presensi keluar
            elif waktu_keluar is None or waktu_keluar == '00:00:00':
                foto_keluar = capture_image(nis, "keluar")
                
                if foto_keluar:
                    cursor.execute("""
                        UPDATE presensi SET waktu_keluar = %s, status = 'Hadir', foto_keluar = %s 
                        WHERE id = %s
                    """, (waktu_sekarang, foto_keluar, presensi_id))
                    print(f"Presensi keluar dicatat untuk {nama_siswa} pada {waktu_sekarang}.")

                    # Batalkan timer timeout setelah presensi sukses
                    if face_recognition_timer is not None:
                        face_recognition_timer.cancel()
                        face_recognition_timer = None
                    
                    # Data JSON untuk dikirim ke ESP32
                    data = {
                        "status": "1",
                        "jenisPresensi": "Presensi Keluar",
                        "nama": nama_siswa,
                        "waktu": waktu_sekarang,
                        "keterangan": "Pulang"
                    }
                    send_attendance_data_to_esp32(data)

                    set_status("success", {
                        "jenisPresensi": "Presensi Keluar",
                        "nama": nama_siswa,
                        "kelas": kelas,
                        "waktu": waktu_sekarang,
                        "keterangan": "Pulang",
                        "fotoKeluar": foto_keluar
                    })

                    message = (f"Anak Anda sudah pulang sekolah.\n"
                               f"- Nama: {nama_siswa}\n"
                               f"- NIS: {nis}\n"
                               f"- Kelas: {kelas}\n"
                               f"- Alamat: {alamat}\n"
                               f"- Tanggal: {tanggal_hari_ini}\n"
                               f"- Waktu Keluar: {waktu_sekarang}\n")
                    
                    foto_presensi = foto_keluar
                else:
                    print("Gagal mengambil foto keluar.")
            else:
                # Jika presensi sudah lengkap, tidak ada notifikasi yang dikirim
                print("Presensi sudah lengkap, tidak ada perubahan.")
                message = None
                foto_presensi = None
        else:
            # Presensi Masuk (record belum ada)
            foto_masuk = capture_image(nis, "masuk")
            
            if foto_masuk:
                cursor.execute("""
                    INSERT INTO presensi (siswa_id, tanggal, waktu_masuk, foto_masuk, status) 
                    VALUES (%s, %s, %s, %s, 'Masuk')
                """, (siswa_id, tanggal_hari_ini, waktu_sekarang, foto_masuk))
                print(f"Presensi masuk dicatat untuk {nama_siswa} pada {waktu_sekarang}.")

                # Batalkan timer timeout setelah presensi sukses
                if face_recognition_timer is not None:
                    face_recognition_timer.cancel()
                    face_recognition_timer = None            

                # Data JSON untuk dikirim ke ESP32
                data = {
                    "status": "1",
                    "jenisPresensi": "Presensi Masuk",
                    "nama": nama_siswa,
                    "waktu": waktu_sekarang,
                    "keterangan": keterangan_masuk
                }
                send_attendance_data_to_esp32(data)

                set_status("success", {
                    "jenisPresensi": "Presensi Masuk",
                    "nama": nama_siswa,
                    "kelas": kelas,
                    "waktu": waktu_sekarang,
                    "keterangan": keterangan_masuk,
                    "fotoMasuk": foto_masuk
                }) 

                message = (f"{keterangan_masuk_telegram}\n"
                           f"- Nama: {nama_siswa}\n"
                           f"- NIS: {nis}\n"
                           f"- Kelas: {kelas}\n"
                           f"- Alamat: {alamat}\n"
                           f"- Tanggal: {tanggal_hari_ini}\n"
                           f"- Waktu Masuk: {waktu_sekarang}\n")
                
                foto_presensi = foto_masuk
            else:
                print("Gagal mengambil foto masuk.")

        # Hapus data RFID di tmp_rfid_presensi
        cursor.execute("DELETE FROM tmp_rfid_presensi")
        conn.commit()
        print("Data RFID di tmp_rfid_presensi berhasil dihapus.")

        # Nonaktifkan face recognition dan reset frame agar tidak diproses lagi
        disable_face_recognition()

        # Kirim perintah stop streaming ke ESP32-CAM setelah presensi berhasil
        send_stop_command_to_esp32cam()

        # Kirim notifikasi Telegram jika ada pesan dan foto presensi
        if message and foto_presensi:
            send_telegram_message(token, chat_id, message, foto_presensi)
        else:
            print("Presensi sudah lengkap, tidak ada notifikasi Telegram yang dikirim.")
    
    except Exception as e:
        print(f"Terjadi kesalahan: {e}")
    
    finally:
        try:
            cursor.close()
            conn.close()
        except:
            pass
        # Reset flag untuk mengizinkan presensi berikutnya
        attendance_in_process = False

def send_attendance_data_to_esp32(data):
    url = f"http://{ESP32_IP}/pushdata" 
    headers = {'Content-Type': 'application/json'}
    
    try:
        response = requests.post(url, data=json.dumps(data), headers=headers)
        if response.status_code == 200:
            print("Data presensi berhasil dikirim ke ESP32.")
        else:
            print(f"Gagal mengirim data ke ESP32. Kode status: {response.status_code}")
    except Exception as e:
        print(f"Terjadi kesalahan saat mengirim data ke ESP32: {e}")

# Fungsi untuk mengirimkan pesan notifikasi presensi ke Telegram orang tua siswa
def send_telegram_message(token, chat_id, message, foto_presensi=None):
    url_message = f"https://api.telegram.org/bot{token}/sendMessage"
    params = {
        "chat_id": chat_id,
        "text": message
    }
    response_message = requests.get(url_message, params=params)
    
    if response_message.status_code != 200:
        print(f"Gagal mengirim pesan ke Telegram. Error: {response_message.text}")
        return False

    if foto_presensi:
        file_path = os.path.join('data/foto/foto_presensi_siswa', foto_presensi)
        if os.path.exists(file_path):
            url_photo = f"https://api.telegram.org/bot{token}/sendPhoto"
            with open(file_path, 'rb') as photo:
                files = {'photo': photo}
                data = {'chat_id': chat_id}
                response_photo = requests.post(url_photo, files=files, data=data)
            if response_photo.status_code != 200:
                print(f"Gagal mengirim foto ke Telegram. Error: {response_photo.text}")
                return False

    print("Notifikasi Telegram berhasil dikirim.")
    return True

def send_start_command_to_esp32cam():
    url = f"http://{ESP32CAM_IP}/start"
    try:
        response = requests.get(url)
        if response.status_code == 200:
            print("Perintah start streaming berhasil dikirim ke ESP32-CAM.")
        else:
            print(f"Gagal mengirim perintah start streaming ke ESP32-CAM. Kode status: {response.status_code}")
    except Exception as e:
        print(f"Terjadi kesalahan saat mengirim perintah start streaming ke ESP32-CAM: {e}")    

def send_stop_command_to_esp32cam():
    url = f"http://{ESP32CAM_IP}/stop"
    try:
        response = requests.get(url)
        if response.status_code == 200:
            print("Perintah stop streaming berhasil dikirim ke ESP32-CAM.")
        else:
            print(f"Gagal mengirim perintah stop streaming ke ESP32-CAM. Kode status: {response.status_code}")
    except Exception as e:
        print(f"Terjadi kesalahan saat mengirim perintah stop streaming ke ESP32-CAM: {e}")        

# Fungsi untuk mengaktifkan face recognition
def enable_face_recognition():
    global face_recognition_active
    face_recognition_active = True
    print("Face recognition diaktifkan.")

# Fungsi untuk menonaktifkan face recognition dan reset frame
def disable_face_recognition():
    global face_recognition_active, frame, annotated_frame, display_frame
    face_recognition_active = False
    with frame_lock:
        frame = None
        annotated_frame = None
        display_frame = None
    print("Face recognition dinonaktifkan.")
 
def face_recognition_timeout():
    global face_recognition_active, attendance_in_process, face_recognition_timer

    if face_recognition_timer is not None:
        face_recognition_timer.cancel()
        face_recognition_timer = None
    
    if face_recognition_active:
        print("Timeout: Tidak ada wajah yang cocok dalam 20 detik.")
        set_status("failed_timeout")
        
        data = {
            "status": "0",
            "jenisPresensi": "Presensi Gagal",
            "nama": "-",
            "waktu": "-",
            "keterangan": "Timeout: Tidak ada wajah yang cocok dalam 20 detik."
        }
        try:
            send_attendance_data_to_esp32(data)
        except Exception as e:
            print("Error mengirim data ke ESP32:", e)
        
        # Hentikan streaming ESP32CAM
        send_stop_command_to_esp32cam()
        
        # Hapus data RFID di tmp_rfid_presensi
        try:
            conn = get_db_connection()
            if conn:
                cursor = conn.cursor()
                cursor.execute("DELETE FROM tmp_rfid_presensi")
                conn.commit()
                cursor.close()
                conn.close()
        except Exception as e:
            print("Error menghapus data tmp_rfid_presensi:", e)
        
        # Nonaktifkan face recognition
        disable_face_recognition()
        
        # Reset flag
        attendance_in_process = False

def face_recognition():
    global frame, face_recognition_active, annotated_frame, display_frame, capture_mode, attendance_in_process, face_recognition_timer
    while True:
        if capture_mode or not face_recognition_active or frame is None:
            time.sleep(0.01)
            continue

        with frame_lock:
            if isinstance(frame, tuple):
                frame_timestamp, current_frame = frame
            else:
                current_frame = None
                frame_timestamp = 0

        # Hanya proses frame jika timestamp sudah melewati stream_start_time
        if current_frame is None or frame_timestamp < stream_start_time:
            time.sleep(0.01)
            continue

        frame_rgb = cv2.cvtColor(current_frame, cv2.COLOR_BGR2RGB)
        test_face_embeddings = detect_and_embed(frame_rgb)
        
        annotated_frame = current_frame.copy()

        # Ambil RFID dari tmp_rfid_presensi untuk dibandingkan
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("SELECT no_rfid FROM tmp_rfid_presensi ORDER BY id DESC LIMIT 1")
        tmp_rfid_row = cursor.fetchone()
        tmp_rfid = tmp_rfid_row[0] if tmp_rfid_row else None
        cursor.close()
        conn.close()

        if test_face_embeddings and known_face_embeddings:
            nis_list, names, rfids, similarity_percentages = recognize_faces(
                np.array(known_face_embeddings), known_face_nis, known_face_names, known_face_rfids, test_face_embeddings
            )
            # for rfid in rfids:
            #     if rfid != "Unknown":
            #         if not attendance_in_process:
            #             attendance_in_process = True
            #             record_attendance(rfid)
        else:
            names = ['Unknown'] * len(test_face_embeddings)
            rfids = ['Unknown'] * len(test_face_embeddings)
            similarity_percentages = [0] * len(test_face_embeddings)
        
        boxes, _ = mtcnn.detect(frame_rgb)
        
        if boxes is not None:
            for name, similarity, rfid, box in zip(names, similarity_percentages, rfids, boxes):
                if box is not None:
                    (x1, y1, x2, y2) = map(int, box)

                    if name == 'Unknown':
                        color = (0, 0, 255) 
                        text = f"Unknown ({similarity:.2f}%)"
                    elif rfid == tmp_rfid:
                        color = (0, 255, 0)
                        text = f"{name} ({similarity:.2f}%)"
                    else:
                        color = (0, 0, 255)
                        text = f"{name} ({similarity:.2f}%)"
                        rfid_warning = "Tidak Cocok"  
                    
                    # Gambar kotak wajah
                    cv2.rectangle(annotated_frame, (x1, y1), (x2, y2), color, 2)
                    
                    # Tampilkan nama dan persentase di atas kotak wajah
                    cv2.putText(annotated_frame, text, (x1, y1 - 10),
                                cv2.FONT_HERSHEY_SIMPLEX, 0.8, color, 2, cv2.LINE_AA)
                    
                    # Jika RFID tidak cocok, tambahkan teks "Tidak Cocok" di bawah kotak wajah
                    if name != "Unknown" and rfid != tmp_rfid:
                        cv2.putText(annotated_frame, rfid_warning, (x1, y2 + 30),
                                    cv2.FONT_HERSHEY_SIMPLEX, 0.8, (0, 0, 255), 2, cv2.LINE_AA)
                    
        if annotated_frame is not None:
            display_frame = annotated_frame.copy()
        else:
            if current_frame is not None:
                display_frame = current_frame.copy()
            else:
                display_frame = np.zeros((480, 640, 3), dtype=np.uint8)

threading.Thread(target=face_recognition, daemon=True).start()

def generate_face_recognition():
    global frame, display_frame
    while True:
        if display_frame is not None:
            ret, jpeg = cv2.imencode('.jpg', display_frame)
        elif frame is not None:
            # Jika frame berupa tuple, ambil frame saja
            with frame_lock:
                current_frame = frame[1] if isinstance(frame, tuple) else frame
            ret, jpeg = cv2.imencode('.jpg', current_frame)
        else:
            time.sleep(0.01)
            continue
        if ret:
            yield (b'--frame\r\n'
                   b'Content-Type: image/jpeg\r\n\r\n' + jpeg.tobytes() + b'\r\n\r\n')

@app.route('/video_feed')
def video_feed():
    return Response(generate_face_recognition(), mimetype='multipart/x-mixed-replace; boundary=frame')

#####################################
# DATASET CAPTURE
#####################################

def generate_capture_frames():
    global capture_dataset, captured_count, nis_capture, student_name_capture, rfid_capture, capture_done, cap_capture
    if cap_capture is None or not cap_capture.isOpened():
        cap_capture = cv2.VideoCapture(0)
        
    last_capture_time = time.time()
    capture_interval = 0.5  # interval 0.5 detik antar capture

    while True:
        if cap_capture is None or not cap_capture.isOpened():
            break
        success, frame_cap = cap_capture.read()
        if not success:
            break
        
        frame_original = frame_cap.copy()
        frame_rgb = cv2.cvtColor(frame_cap, cv2.COLOR_BGR2RGB)
        boxes, _ = mtcnn.detect(frame_rgb)
        if boxes is not None:
            for box in boxes:
                cv2.rectangle(frame_cap, 
                              (int(box[0]), int(box[1])), 
                              (int(box[2]), int(box[3])), 
                              (0, 255, 0), 2)
            
            if capture_dataset and captured_count < num_images:
                current_time = time.time()
                if current_time - last_capture_time >= capture_interval:
                    dataset_dir = f"data/dataset/{nis_capture}_{student_name_capture}_{rfid_capture}"
                    os.makedirs(dataset_dir, exist_ok=True)
                    image_path = os.path.join(dataset_dir, f"image_{captured_count+1}.jpg")
                    cv2.imwrite(image_path, frame_original)
                    captured_count += 1
                    last_capture_time = current_time
                
                display_count = captured_count if captured_count > 0 else 1
                cv2.putText(frame_cap, f"Mengambil Gambar {display_count}/{num_images}", 
                            (10, 30), cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 255, 0), 2, cv2.LINE_AA)
                
                if captured_count >= num_images:
                    capture_dataset = False
                    capture_done = True
                    cap_capture.release()
                    cap_capture = None
                    save_dataset_folder_to_db(nis_capture, f"{nis_capture}_{student_name_capture}_{rfid_capture}")
                    print(f"Selesai mengambil {num_images} gambar untuk {student_name_capture} (NIS: {nis_capture}, RFID: {rfid_capture})")
                    
        ret, buffer = cv2.imencode('.jpg', frame_cap)
        if ret:
            yield (b'--frame\r\n'
                   b'Content-Type: image/jpeg\r\n\r\n' + buffer.tobytes() + b'\r\n')
        else:
            continue

@app.route('/video_feed_capture')
def video_feed_capture():
    return Response(generate_capture_frames(), mimetype='multipart/x-mixed-replace; boundary=frame')

def save_dataset_folder_to_db(nis, folder_name):
    conn = get_db_connection()
    if conn:
        try:
            cursor = conn.cursor()
            query = "UPDATE siswa SET dataset_wajah = %s WHERE nis = %s"
            cursor.execute(query, (folder_name, nis))
            conn.commit()
            print(f"Folder dataset '{folder_name}' berhasil disimpan ke database!")
        except Exception as e:
            print(f"Error menyimpan data ke database: {e}")
        finally:
            cursor.close()
            conn.close()

@app.route('/start_capture', methods=['POST'])
def start_capture():
    global capture_dataset, capture_mode, nis_capture, student_name_capture, rfid_capture, captured_count, capture_done, cap_capture, face_recognition_active
    data = request.get_json()
    nis_capture = data.get('nis')
    student_name_capture = data.get('student_name')
    rfid_capture = data.get('rfid')
    if not nis_capture or not student_name_capture or not rfid_capture:
        return jsonify({"error": "Data tidak lengkap"}), 400
    capture_mode = True
    face_recognition_active = False
    capture_dataset = True
    capture_done = False
    captured_count = 0
    return jsonify({"message": "Mengambil gambar..."}), 200

@app.route('/capture_status')
def capture_status():
    global capture_done
    if capture_done:
        capture_done = False
        return jsonify({"message": "Pengambilan gambar wajah selesai!"}), 200
    return jsonify({"message": ""}), 200

@app.route('/stop_camera', methods=['POST'])
def stop_camera():
    global cap_capture, capture_mode
    if cap_capture is not None and cap_capture.isOpened():
        cap_capture.release()
        cap_capture = None
        capture_mode = False
    return jsonify({"message": "Kamera dimatikan"}), 200

@app.route('/delete_old_dataset', methods=['POST'])
def delete_old_dataset():
    data = request.get_json()
    nis_val = data.get('nis')
    student_name_val = data.get('student_name')
    rfid_val = data.get('rfid')
    if not nis_val or not student_name_val or not rfid_val:
        return jsonify({"error": "Data tidak lengkap"}), 400
    dataset_dir = f"data/dataset/{nis_val}_{student_name_val}_{rfid_val}"
    if os.path.exists(dataset_dir):
        shutil.rmtree(dataset_dir)
        return jsonify({"message": "Dataset lama berhasil dihapus, silakan ulangi pengambilan gambar."}), 200
    else:
        return jsonify({"message": "Tidak ada dataset lama yang ditemukan."}), 404

# Endpoint untuk menonaktifkan face recognition
@app.route('/disable_face_recognition', methods=['POST'])
def disable_face_recognition_endpoint():
    disable_face_recognition()
    return jsonify({"message": "Face recognition disabled"}), 200

# Endpoint untuk mengaktifkan face recognition
@app.route('/enable_face_recognition', methods=['POST'])
def enable_face_recognition_endpoint():
    global capture_mode
    if not capture_mode:
        enable_face_recognition()
        return jsonify({"message": "Face recognition enabled"}), 200
    else:
        return jsonify({"message": "Capture mode aktif, tidak dapat mengaktifkan face recognition"}), 200

@app.route('/reembed_faces', methods=['POST'])
def reembed_faces():
    global known_face_nis, known_face_names, known_face_rfids, known_face_embeddings, capture_mode
    try:
        # Lakukan embedding ulang dari dataset yang sudah ada
        known_face_nis, known_face_names, known_face_rfids, known_face_embeddings = embed_faces_from_dataset(dataset_dir)
        save_face_data(known_face_nis, known_face_names, known_face_rfids, known_face_embeddings, face_data_file)

        capture_mode = False
        
        return jsonify({"message": "Embedding dataset wajah selesai dan data berhasil diperbarui."}), 200
    except Exception as e:
        return jsonify({"error": str(e)}), 500

def embed_student_faces(nis, name, rfid):
    student_folder = os.path.join(dataset_dir, f"{nis}_{name}_{rfid}")
    if not os.path.exists(student_folder):
        return None, "Folder dataset tidak ditemukan untuk siswa tersebut."
    
    student_embeddings = []
    image_files = [f for f in os.listdir(student_folder) if f.lower().endswith('.jpg')]
    if not image_files:
        return None, "Tidak ada gambar dalam dataset siswa."
    
    for image_file in image_files:
        image_path = os.path.join(student_folder, image_file)
        img = cv2.imread(image_path)
        if img is None:
            continue
        img_rgb = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
        embeddings = detect_and_embed(img_rgb)
        if embeddings:
            for emb in embeddings:
                student_embeddings.append(emb)
    
    if not student_embeddings:
        return None, "Gagal menghitung embedding dari dataset siswa."
    return student_embeddings, None

@app.route('/add_face_data', methods=['POST'])
def add_face_data():
    global capture_mode, known_face_nis, known_face_names, known_face_rfids, known_face_embeddings
    try:
        data = request.get_json()
        new_nis = data.get('nis')
        new_name = data.get('name')
        new_rfid = data.get('rfid')
        
        if not (new_nis and new_name and new_rfid):
            return jsonify({"error": "Data tidak lengkap! Pastikan nis, name, dan rfid dikirim."}), 400

        student_embeddings, err = embed_student_faces(new_nis, new_name, new_rfid)
        if err:
            return jsonify({"error": err}), 400

        nis_list, names_list, rfids_list, embeddings_list = load_face_data(face_data_file)
        
        for emb in student_embeddings:
            nis_list.append(new_nis)
            names_list.append(new_name)
            rfids_list.append(new_rfid)
            embeddings_list.append(emb)
        
        save_face_data(nis_list, names_list, rfids_list, embeddings_list, face_data_file)

        capture_mode = False  # Reset capture mode agar face recognition aktif lagi
        
        # Perbarui variabel global dengan data terbaru
        known_face_nis, known_face_names, known_face_rfids, known_face_embeddings = load_face_data(face_data_file)

        return jsonify({"message": f"Data wajah untuk {new_name} berhasil ditambahkan! Total foto yang diembedding: {len(student_embeddings)}."}), 200
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/update_face_data', methods=['POST'])
def update_face_data():
    global capture_mode, known_face_nis, known_face_names, known_face_rfids, known_face_embeddings
    try:
        data = request.get_json()
        target_nis = data.get('nis')
        new_name = data.get('name')
        new_rfid = data.get('rfid')
        
        if not (target_nis and new_name and new_rfid):
            return jsonify({"error": "Data tidak lengkap! Pastikan nis, name, dan rfid dikirim."}), 400

        # Hasilkan embedding baru khusus untuk user target
        student_embeddings, err = embed_student_faces(target_nis, new_name, new_rfid)
        if err:
            return jsonify({"error": err}), 400

        # Muat data wajah yang sudah ada
        nis_list, names_list, rfids_list, embeddings_list = load_face_data(face_data_file)
        
        # Hapus seluruh entri untuk target_nis
        new_nis_list = []
        new_names_list = []
        new_rfids_list = []
        new_embeddings_list = []
        for nis_val, nm, rfid_val, emb in zip(nis_list, names_list, rfids_list, embeddings_list):
            if nis_val != target_nis:
                new_nis_list.append(nis_val)
                new_names_list.append(nm)
                new_rfids_list.append(rfid_val)
                new_embeddings_list.append(emb)
        
        # Tambahkan data wajah baru untuk target_nis
        for emb in student_embeddings:
            new_nis_list.append(target_nis)
            new_names_list.append(new_name)
            new_rfids_list.append(new_rfid)
            new_embeddings_list.append(emb)
        
        # Simpan data yang diperbarui ke file face_data.npy
        save_face_data(new_nis_list, new_names_list, new_rfids_list, new_embeddings_list, face_data_file)
        
        capture_mode = False  # Reset capture mode agar face recognition aktif kembali
        
        # Perbarui variabel global dengan data terbaru
        known_face_nis, known_face_names, known_face_rfids, known_face_embeddings = load_face_data(face_data_file)

        return jsonify({"message": f"Data wajah untuk {new_name} berhasil diupdate! Total foto yang diembedding: {len(student_embeddings)}."}), 200
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/delete_face_data', methods=['POST'])
def delete_face_data():
    global capture_mode, known_face_nis, known_face_names, known_face_rfids, known_face_embeddings
    try:
        data = request.get_json()
        target_nis = data.get('nis')
        if not target_nis:
            return jsonify({"error": "NIS harus disertakan."}), 400

        nis_list, names_list, rfids_list, embeddings_list = load_face_data(face_data_file)
        new_nis, new_names, new_rfids, new_embeddings = [], [], [], []
        found = False
        for n, nm, rfid, emb in zip(nis_list, names_list, rfids_list, embeddings_list):
            if n == target_nis:
                found = True
                continue  # Hapus entri ini
            new_nis.append(n)
            new_names.append(nm)
            new_rfids.append(rfid)
            new_embeddings.append(emb)

        if not found:
            return jsonify({"error": "Data dengan NIS tersebut tidak ditemukan!"}), 404

        save_face_data(new_nis, new_names, new_rfids, new_embeddings, face_data_file)

        capture_mode = False  # Reset capture mode agar face recognition aktif lagi

        # Perbarui variabel global dengan data terbaru
        known_face_nis, known_face_names, known_face_rfids, known_face_embeddings = load_face_data(face_data_file)

        return jsonify({"message": "Data wajah berhasil dihapus!"}), 200
    except Exception as e:
        return jsonify({"error": str(e)}), 500

#####################################
# Menjalankan Server Flask
#####################################
if __name__ == '__main__':
    # Untuk pengembangan (debug aktif dan auto-reload):
    # app.run(host='0.0.0.0', port=5000, debug=True)

    # Untuk penggunaan normal (tanpa debug dan tanpa auto-restart):
    app.run(host='0.0.0.0', port=5000, debug=False, use_reloader=False)

