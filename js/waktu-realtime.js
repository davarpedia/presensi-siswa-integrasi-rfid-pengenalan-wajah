function updateDateTime() {
    const now = new Date();
    // Daftar hari dan bulan dalam Bahasa Indonesia
    const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    
    // Format tanggal: "Rabu, 01 Januari 2025"
    const dayName = days[now.getDay()];
    const day = String(now.getDate()).padStart(2, '0');
    const monthName = months[now.getMonth()];
    const year = now.getFullYear();
    const formattedDate = `${dayName}, ${day} ${monthName} ${year}`;
    
    // Format waktu: "07:00:00"
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    const formattedTime = `${hours}:${minutes}:${seconds}`;
    
    document.getElementById('dateTimeDisplay').innerHTML = formattedDate + '<br>' + formattedTime;
}

// Panggil fungsi segera dan perbarui setiap detik
updateDateTime();
setInterval(updateDateTime, 1000);