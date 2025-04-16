<?php 
$pageTitle = "Kelola Dataset Wajah";
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();
include 'header.php';
include 'sidebar.php';
include 'topbar.php';
?>

<!-- Begin Page Content -->
<div class="container-fluid">
  <!-- Page Heading -->
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Kelola Dataset Wajah</h1>
    <div id="dateTimeDisplay" class="text-muted text-left text-sm-right mt-2 mt-sm-0 small"></div>
  </div>

  <div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="siswa.php">Data Siswa</a></li>
        <li class="breadcrumb-item active" aria-current="page">Kelola Dataset Wajah</li>
        </ol>
    </nav>
  </div>

  <?php 
    $id_siswa = $_GET['id'];

    // Query menggunakan LEFT JOIN untuk mengambil nama_kelas dari tabel kelas
    $sql = "SELECT siswa.*, kelas.nama_kelas 
            FROM siswa 
            LEFT JOIN kelas ON siswa.id_kelas = kelas.id 
            WHERE siswa.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_siswa);
    $stmt->execute();
    $result = $stmt->get_result();
    $siswa = $result->fetch_assoc();

    echo "<div class='my-4 text-center'>";
    echo "  <div id='alertContainer' style='display: none;' class='mt-3'></div>";
    echo "</div>";

    if ($siswa) {
        echo "<table style='border-collapse: collapse;'>";
        echo "  <tr><td class='pr-2'>NIS</td><td>: {$siswa['nis']}</td></tr>";
        echo "  <tr><td class='pr-2'>Nama</td><td>: {$siswa['nama']}</td></tr>";
        echo "  <tr><td class='pr-2'>Kelas</td><td>: {$siswa['nama_kelas']}</td></tr>";
        echo "</table>";        

        $dataset_folder = "data/dataset/{$siswa['dataset_wajah']}";
        
        $jumlah_foto = 0;
        if (is_dir($dataset_folder)) {
            $files = scandir($dataset_folder);
            $valid_files = [];
            foreach ($files as $file) {
                if (preg_match('/\.(jpg|jpeg|png)$/i', $file)) {
                    $valid_files[] = $file;
                    $jumlah_foto++;
                }
            }
            if ($jumlah_foto === 0) {
                echo "<div class='alert alert-danger text-center mt-4 mb-4 py-4' role='alert'>";
                echo "  <h5><strong>Dataset Wajah Belum Ada!</strong></h5>";
                echo "  <p>Anda dapat mengambil dataset wajah baru dengan menekan tombol di bawah ini.</p>";
                echo "  <a href='update_dataset_wajah.php?id={$siswa['id']}' class='btn btn-success'>Ambil Dataset Wajah</a>";
                echo "</div>";
            } else {
                echo "<div class='alert alert-success text-center mt-4 mb-4 py-4' role='alert'>";
                echo "  <h5><strong>Dataset Wajah Tersedia ($jumlah_foto Foto)</strong></h5>";
                echo "  <button class='btn btn-success mt-2' onclick='startStudentEmbedding()'>Embedding Wajah</button>";
                echo "</div>";
                
                echo "<div class='row'>";
                foreach ($valid_files as $file) {
                    $file_path = "$dataset_folder/$file";
                    echo "<div class='col-12 col-md-6 col-lg-3 mb-4'>";
                    echo "  <div class='image-item'>";
                    echo "    <img src='$file_path' alt='Dataset Wajah' class='img-fluid rounded' style='object-fit: cover;' />";
                    echo "    <form method='POST' action='hapus_dataset_wajah.php?id={$siswa['id']}' style='display:inline;'>";
                    echo "      <input type='hidden' name='file' value='$file'>";
                    echo "      <button type='submit' class='btn btn-danger btn-sm delete-btn'>";
                    echo "        <i class='bi bi-trash-fill'></i>";
                    echo "      </button>";
                    echo "    </form>";
                    echo "  </div>";
                    echo "</div>";
                }
                echo "</div>";
                
                echo "<div class='alert alert-warning text-center mb-4 py-4' role='alert'>";
                echo "  <h5><strong>Ingin Mengulangi Pengambilan Dataset Wajah?</strong></h5>";
                echo "  <p>Tekan tombol di bawah untuk mengambil dataset baru. Dataset lama akan dihapus, pastikan Anda yakin!</p>";
                echo "  <button class='btn btn-danger' onclick='ulangDataset()'>Ulangi Pengambilan Dataset Wajah</button>";
                echo "</div>";
            }
        } else {
            echo "<div class='alert alert-danger text-center mt-4 mb-4 py-4' role='alert'>";
            echo "  <h5><strong>Dataset Wajah Belum Ada!</strong></h5>";
            echo "  <p>Anda dapat mengambil dataset wajah baru dengan menekan tombol di bawah ini.</p>";
            echo "  <a href='update_dataset_wajah.php?id={$siswa['id']}' class='btn btn-success'>Ambil Dataset Wajah</a>";
            echo "</div>";
        }
    } else {
        echo "<p>Siswa tidak ditemukan.</p>";
    }

    $conn->close();
  ?>

</div>
<!-- End of Container Fluid -->

</div>

<?php
include 'footer.php';
?>

<!-- Modal Konfirmasi Penghapusan Dataset -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <!-- Pada Bootstrap 4, tombol close menggunakan class "close" dan data-dismiss -->
        <h5 class="modal-title" id="confirmDeleteModalLabel">Konfirmasi Penghapusan Dataset</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        Dataset wajah yang ada saat ini akan dihapus. Apakah Anda yakin untuk melanjutkan?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Hapus & Ambil Dataset Baru</button>
      </div>
    </div>
  </div>
</div>

<script>
// Fungsi untuk memulai proses embedding melalui endpoint Flask
function startStudentEmbedding() {
    const alertContainer = document.getElementById('alertContainer');
    alertContainer.innerHTML = "<div class='alert alert-secondary text-center' role='alert'>Sedang melakukan embedding dataset wajah. Harap tunggu sebentar...</div>";
    alertContainer.style.display = 'block';

    fetch("http://localhost:5000/update_face_data", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({
            nis: "<?= $siswa['nis'] ?>",
            name: "<?= $siswa['nama'] ?>",
            rfid: "<?= $siswa['no_rfid'] ?>"
        })
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(err => { throw err; });
        }
        return response.json();
    })
    .then(data => {
        alertContainer.innerHTML = "<div class='alert alert-success text-center' role='alert'>" + data.message + "</div>";
        setTimeout(() => { location.reload(); }, 2000);
    })
    .catch(error => {
        let errMsg = "Error: ";
        if (error.error) {
            errMsg += error.error;
        } else {
            errMsg += error;
        }
        alertContainer.innerHTML = "<div class='alert alert-danger text-center' role='alert'>" + errMsg + "</div>";
        setTimeout(() => { 
            alertContainer.style.display = 'none'; 
            alertContainer.innerHTML = "";
        }, 2000);
    });
}

// Fungsi untuk menampilkan modal konfirmasi penghapusan dataset
function ulangDataset() {
    // Pada Bootstrap 4, modal ditampilkan menggunakan jQuery
    $('#confirmDeleteModal').modal('show');
}

// Tambahkan event listener untuk tombol konfirmasi hapus dataset
document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    // Tutup modal menggunakan jQuery (Bootstrap 4)
    $('#confirmDeleteModal').modal('hide');

    const alertContainer = document.getElementById('alertContainer');
    alertContainer.innerHTML = "<div class='alert alert-secondary text-center' role='alert'>Menghapus dataset lama. Mohon tunggu...</div>";
    alertContainer.style.display = 'block';

    fetch("http://localhost:5000/delete_old_dataset", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({
            nis: "<?= $siswa['nis'] ?>",
            student_name: "<?= $siswa['nama'] ?>",
            rfid: "<?= $siswa['no_rfid'] ?>"
        })
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(err => { throw err; });
        }
        return response.json();
    })
    .then(data => {
        alertContainer.innerHTML = "<div class='alert alert-success text-center' role='alert'>" + data.message + "</div>";
        setTimeout(() => {
            window.location.href = "update_dataset_wajah.php?id=<?= $siswa['id'] ?>";
        }, 2000);
    })
    .catch(error => {
        let errMsg = "Error: ";
        if (error.error) {
            errMsg += error.error;
        } else {
            errMsg += error;
        }
        alertContainer.innerHTML = "<div class='alert alert-danger text-center' role='alert'>" + errMsg + "</div>";
    });
});
</script>
