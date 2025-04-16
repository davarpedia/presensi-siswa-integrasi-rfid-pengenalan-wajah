<?php
$pageTitle = "Data Siswa";
require_once 'koneksi.php';
require_once 'autentikasi.php';
include 'header.php';
include 'sidebar.php';
include 'topbar.php';

// Tentukan level user. Jika bukan Admin, anggap level-nya Guru.
$isAdmin = (strtolower($_SESSION['session_level']) === 'admin');
$guruId  = $_SESSION['guru_id'] ?? null;

// Ambil nilai filter kelas dari parameter GET.
// Gunakan string kosong ("") sebagai nilai default untuk menampilkan semua kelas.
$kelasFilter = isset($_GET['kelas']) ? $_GET['kelas'] : '';

// Untuk Admin, filter status default "Aktif" (kecuali ada parameter RFID);
// sedangkan untuk Guru, paksa status "Aktif" karena hanya data aktif yang ditampilkan.
if ($isAdmin) {
    $statusFilter = isset($_GET['rfid'])
        ? ''
        : (isset($_GET['status']) ? $_GET['status'] : 'Aktif');
} else {
    $statusFilter = 'Aktif';
}

// ---------------------------------------------------------------------
// 1) Ambil list kelas sesuai level:
//    - Admin: semua kelas.
//    - Guru: hanya kelas yang diajar guru tersebut, jika status guru = 'Aktif'.
// ---------------------------------------------------------------------
$kelasList = [];
if ($isAdmin) {
    $sqlKelas = "SELECT * FROM kelas";
    $stmt = $conn->prepare($sqlKelas);
} else {
    $sqlKelas = "
      SELECT k.*
      FROM kelas k
      JOIN guru g ON k.id_guru = g.id
      WHERE g.id = ? AND g.status = 'Aktif'
    ";
    $stmt = $conn->prepare($sqlKelas);
    $stmt->bind_param('i', $guruId);
}
$stmt->execute();
$resK = $stmt->get_result();
while ($row = $resK->fetch_assoc()) {
    $kelasList[] = $row;
}
$stmt->close();

// ---------------------------------------------------------------------
// 2) Tentukan kolom yang di-disable (logika sama seperti sebelumnya).
// ---------------------------------------------------------------------
// Untuk Admin, jika filter kelas kosong maka tampilkan semua kelas.
$filterAllAdmin = $isAdmin && $kelasFilter === '';
$filterAllGuru  = !$isAdmin && $kelasFilter === '';
if ($filterAllAdmin || $filterAllGuru) {
    $disableColumns = $isAdmin ? [6,7,8] : [6,7];
} else {
    $disableColumns = $isAdmin ? [5,6,7] : [5,6];
}

// ---------------------------------------------------------------------
// 3) Query data siswa dengan filter status + kelas.
// ---------------------------------------------------------------------
$sql = "
  SELECT siswa.*, kelas.nama_kelas
  FROM siswa
  LEFT JOIN kelas ON siswa.id_kelas = kelas.id
  LEFT JOIN guru ON kelas.id_guru = guru.id
  WHERE 1=1
";
$params    = [];
$paramType = '';

// Filter status (jika tidak kosong)
if ($statusFilter !== '') {
    $sql .= " AND siswa.status = ?";
    $paramType .= 's';
    $params[] = $statusFilter;
}

if ($isAdmin) {
    // Untuk Admin, jika filter kelas tidak kosong, tambahkan kondisi filter.
    if ($kelasFilter !== '') {
        $sql .= " AND kelas.nama_kelas = ?";
        $paramType .= 's';
        $params[] = $kelasFilter;
    }
} else {
    // Untuk Guru: jika filter kelas kosong, tampilkan semua kelas yang diampu guru tersebut.
    if ($kelasFilter !== '') {
        $sql .= " AND kelas.nama_kelas = ?";
        $paramType .= 's';
        $params[] = $kelasFilter;
    } else {
        $sql .= " AND guru.id = ?";
        $paramType .= 'i';
        $params[] = $guruId;
    }
}

$stmt = $conn->prepare($sql);
if ($paramType) {
    $stmt->bind_param($paramType, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>

<!-- Begin Page Content -->
<div class="container-fluid">
  <!-- Page Heading -->
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Data Siswa</h1>
    <div id="dateTimeDisplay" class="text-muted text-left text-sm-right mt-2 mt-sm-0 small"></div>
  </div>

  <div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Data Siswa</li>
        </ol>
    </nav>
  </div>

  <!-- Tempat alert notifikasi -->
  <div class="mb-4" id="alertContainer" style="display: none;"></div>

  <!-- Tombol & Filter -->
  <div class="row flex-xl-nowrap align-items-center">
    <?php if ($isAdmin): ?>
    <!-- Tombol Tambah Siswa (hanya untuk Admin) -->
    <div class="col-12 col-xl-auto mb-3">
      <button class="btn btn-primary btn-block" onclick="window.location.href='tambah_siswa.php'">
        Tambah Siswa
      </button>
    </div>
    <?php endif; ?>

    <!-- Form Filter -->
    <?php 
    $filterClassCol = $isAdmin ? "col-12 col-xl" : "col-12 col-xl-auto";
    ?>
    <div class="<?= $filterClassCol ?> mb-3">
      <form id="filterForm" method="GET" action="">
        <div class="form-row <?= $isAdmin ? 'justify-content-center' : 'justify-content-start'; ?>">
          <!-- Filter Kelas -->
          <div class="col-12 col-md col-xl-auto mb-2 mb-md-0" style="min-width: 150px;">
            <label class="sr-only" for="filterKelas">Kelas</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <div class="input-group-text">Kelas</div>
              </div>
              <select class="custom-select" id="filterKelas" name="kelas">
                <!-- Ubah label default menjadi "Semua Kelas" untuk keduanya -->
                <option value="" <?= ($kelasFilter === '' ? 'selected' : '') ?>>Semua Kelas</option>

                <?php foreach ($kelasList as $kelasRow): ?>
                  <option 
                    value="<?= htmlspecialchars($kelasRow['nama_kelas'], ENT_QUOTES) ?>" 
                    <?= ($kelasFilter === $kelasRow['nama_kelas'] ? 'selected' : '') ?>>
                    <?= htmlspecialchars($kelasRow['nama_kelas']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <?php if ($isAdmin): ?>
          <!-- Filter Status hanya untuk Admin -->
          <div class="col-12 col-md col-xl-auto mb-2 mb-md-0" style="min-width: 150px;">
            <label class="sr-only" for="filterStatus">Status</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <div class="input-group-text">Status</div>
              </div>
              <select class="custom-select" id="filterStatus" name="status">
                <option value="" <?= ($statusFilter === '' ? 'selected' : '') ?>>Semua Status</option>
                <option value="Aktif" <?= ($statusFilter === 'Aktif' ? 'selected' : '') ?>>Aktif</option>
                <option value="Nonaktif" <?= ($statusFilter === 'Nonaktif' ? 'selected' : '') ?>>Nonaktif</option>
              </select>
            </div>
          </div>
          <?php endif; ?>

          <!-- Tombol Tampilkan -->
          <div class="col-12 col-md-auto">
            <button type="submit" class="btn btn-<?= $isAdmin ? 'secondary' : 'primary' ?> btn-block">
              Tampilkan
            </button>
          </div>

        </div>
      </form>
    </div>

    <?php if ($isAdmin): ?>
    <!-- Tombol Embedding (hanya untuk Admin) -->
    <div class="col-12 col-xl-auto mb-3">
      <button class="btn btn-success btn-block" onclick="startEmbedding()">
        Embedding Wajah
      </button>
    </div>
    <?php endif; ?>

  </div>

  <!-- Tabel Data Siswa -->
  <div class="card mb-4">
    <div class="card-body">
      <div class="table-responsive">
        <table id="dataTable" class="table table-striped table-bordered table-hover">
          <thead class="bg-white">
            <tr>
              <th>No</th>
              <th>RFID</th>
              <th>NIS</th>
              <th>Nama</th>
              <th>JK</th>
              <?php if ($kelasFilter == ''): ?>
                <th>Kelas</th>
              <?php endif; ?>
              <th>Foto</th>
              <?php if ($isAdmin): ?>
                <th>Status</th>
              <?php endif; ?>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $nomor = 1;
            if ($result->num_rows > 0) {
              while ($row = $result->fetch_assoc()) {
                $jenis_kelamin = ($row['jenis_kelamin'] == 'L') ? 'Laki-laki' : 'Perempuan';
                $foto_siswa = !empty($row['foto_siswa']) && file_exists("data/foto/foto_siswa/{$row['foto_siswa']}")
                              ? "data/foto/foto_siswa/{$row['foto_siswa']}" 
                              : 'img/default_image.jpg';

                // Cek dataset wajah
                if (empty($row['dataset_wajah'])) {
                  $dataset_status = "<i class='fas fa-times-circle' style='color: red;'></i> Belum Ada";
                } else {
                  $dataset_folder = "data/dataset/{$row['dataset_wajah']}";
                  $jumlah_foto = 0;
                  if (is_dir($dataset_folder)) {
                    $files = scandir($dataset_folder);
                    foreach ($files as $file) {
                      if (preg_match('/\.(jpg|jpeg|png)$/i', $file)) {
                        $jumlah_foto++;
                      }
                    }
                  }
                  $dataset_status = ($jumlah_foto > 0)
                      ? "<i class='fas fa-check-circle' style='color: green;'></i> $jumlah_foto Foto"
                      : "<i class='fas fa-times-circle' style='color: red;'></i> Belum Ada";
                }
                
                // Tampilan status dengan badge (hanya untuk Admin di tabel utama)
                if ($isAdmin) {
                  $statusBadge = ($row['status'] == 'Aktif')
                    ? "<span class='badge badge-success'>Aktif</span>"
                    : "<span class='badge badge-danger'>Nonaktif</span>";
                }
                
                echo "<tr>
                        <td>{$nomor}</td>
                        <td>{$row['no_rfid']}</td>
                        <td>{$row['nis']}</td>
                        <td>{$row['nama']}</td>
                        <td>{$jenis_kelamin}</td>";
                
                if ($kelasFilter == '') {
                  echo "<td>{$row['nama_kelas']}</td>";
                }
                
                echo "
                        <td>
                          <div class='img-circle-crop'>
                            <img src='{$foto_siswa}' alt='Foto Siswa'>
                          </div>
                        </td>";
                        
                if ($isAdmin) {
                  echo "<td>{$statusBadge}</td>";
                }
                        
                echo "<td>
                          <div class='aksi-btn'>";
                // Untuk Admin: tampilkan tombol Detail, Edit, Hapus, dan Kelola Dataset Wajah.
                if ($isAdmin) {
                  echo "<button class='btn btn-secondary btn-sm detail-btn' title='Detail'
                              data-id='{$row['id']}'
                              data-no_rfid='{$row['no_rfid']}'
                              data-nis='{$row['nis']}'
                              data-nama='{$row['nama']}'
                              data-jenis_kelamin='{$jenis_kelamin}'
                              data-alamat='{$row['alamat']}'
                              data-kelas='" . ($kelasFilter == '' ? $row['nama_kelas'] : $kelasFilter) . "'
                              data-token='{$row['token']}'
                              data-id_chat='{$row['id_chat']}'
                              data-dataset_status='" . htmlspecialchars($dataset_status, ENT_QUOTES) . "'
                              data-foto_siswa='{$foto_siswa}'
                              data-status='{$row['status']}'>
                              <i class='bi bi-eye-fill'></i>
                            </button>
                            <a href='edit_siswa.php?id={$row['id']}' class='btn btn-warning btn-sm' title='Edit'>
                              <i class='bi bi-pencil-fill'></i>
                            </a>
                            <button class='btn btn-danger btn-sm' title='Hapus' data-toggle='modal' data-target='#confirmDeleteModal' data-id='{$row['id']}'>
                              <i class='bi bi-trash-fill'></i>
                            </button>
                            <a href='kelola_dataset_wajah.php?id={$row['id']}' class='btn btn-info btn-sm' title='Kelola Dataset Wajah'>
                              <i class='bi bi-person-bounding-box'></i>
                            </a>";
                } else {
                  // Untuk Guru: hanya tampilkan tombol Detail.
                  echo "<button class='btn btn-secondary btn-sm detail-btn' title='Detail'
                              data-id='{$row['id']}'
                              data-no_rfid='{$row['no_rfid']}'
                              data-nis='{$row['nis']}'
                              data-nama='{$row['nama']}'
                              data-jenis_kelamin='{$jenis_kelamin}'
                              data-alamat='{$row['alamat']}'
                              data-kelas='" . ($kelasFilter == '' ? $row['nama_kelas'] : $kelasFilter) . "'
                              data-token='{$row['token']}'
                              data-id_chat='{$row['id_chat']}'
                              data-dataset_status='" . htmlspecialchars($dataset_status, ENT_QUOTES) . "'
                              data-foto_siswa='{$foto_siswa}'
                              data-status='{$row['status']}'>
                              <i class='bi bi-eye-fill'></i>
                            </button>";
                }
                echo "  </div>
                        </td>
                      </tr>";
                $nomor++;
              }
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

<!-- Modal Konfirmasi Hapus (hanya untuk Admin)-->
<?php if ($isAdmin): ?>
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" role="dialog" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmDeleteLabel">Konfirmasi Hapus</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        Apakah Anda yakin ingin menghapus data siswa ini?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <a href="#" id="deleteConfirmBtn" class="btn btn-danger">Hapus</a>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<!-- End Modal Hapus -->

<!-- Modal Detail Data Siswa -->
<div class="modal fade" id="detailModal" tabindex="-1" role="dialog" aria-labelledby="detailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailModalLabel">Detail Data Siswa</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-bordered no-hover">
            <tbody>
              <tr>
                <th>No. RFID</th>
                <td><span id="detail-no_rfid"></span></td>
              </tr>
              <tr>
                <th>NIS</th>
                <td><span id="detail-nis"></span></td>
              </tr>
              <tr>
                <th>Nama</th>
                <td><span id="detail-nama"></span></td>
              </tr>
              <tr>
                <th>Jenis Kelamin</th>
                <td><span id="detail-jenis_kelamin"></span></td>
              </tr>
              <tr>
                <th>Alamat</th>
                <td><span id="detail-alamat"></span></td>
              </tr>
              <tr>
                <th>Kelas</th>
                <td><span id="detail-kelas"></span></td>
              </tr>
              <tr>
                <th>Token</th>
                <td><span id="detail-token"></span></td>
              </tr>
              <tr>
                <th>ID Chat</th>
                <td><span id="detail-id_chat"></span></td>
              </tr>
              <tr>
                <th>Dataset Wajah</th>
                <td><span id="detail-dataset_status"></span></td>
              </tr>
              <tr>
                <th>Status</th>
                <td><span id="detail-status"></span></td>
              </tr>
              <tr>
                <th>Foto Siswa</th>
                <td>
                  <img id="detail-foto_siswa" src="" alt="Foto Siswa" 
                       style="height:100px; width:auto;">
                </td>
              </tr>
            </tbody>
          </table>
        </div> 
      </div>
    </div>
  </div>
</div>
<!-- End Modal Detail -->

</div>
<!-- End Page Content -->
</div>

<?php
$conn->close();
include 'footer.php';
?>

<!-- Script Embedding Wajah (hanya untuk Admin) -->
<?php if ($isAdmin): ?>
<script>
function startEmbedding() {
  $("#alertContainer").html("<div class='alert alert-secondary text-center' role='alert'>Sedang melakukan embedding dataset wajah. Harap tunggu sebentar...</div>");
  $("#alertContainer").show();

  $.ajax({
    url: "http://localhost:5000/reembed_faces",
    type: "POST",
    success: function(response) {
      $("#alertContainer").html("<div class='alert alert-success text-center' role='alert'>" + response.message + "</div>");
      setTimeout(function() {
        location.reload();
      }, 2000);
    },
    error: function(xhr, status, error) {
      var errMsg = "Error: ";
      if(xhr.responseJSON && xhr.responseJSON.error){
        errMsg += xhr.responseJSON.error;
      } else {
        errMsg += error;
      }
      $("#alertContainer").html("<div class='alert alert-danger text-center' role='alert'>" + errMsg + "</div>");
      setTimeout(function(){
        $("#alertContainer").html("").hide();
      }, 2000);
    }
  });
}
</script>
<?php endif; ?>

<!-- Script DataTables, Filter, dan Modal Detail -->
<script>
$(document).ready(function() {
  var table = $('#dataTable').DataTable({
    paging: true,
    searching: true,
    ordering: true,
    info: true,
    language: {
      emptyTable: "Tidak ada data siswa yang tersedia"
    },
    columnDefs: [
      { orderable: false, targets: <?php echo json_encode($disableColumns); ?> }
    ]
  });

  var urlParams = new URLSearchParams(window.location.search);
  var rfid = urlParams.get('rfid');
  if (rfid) {
    table.search(rfid).draw();
    $('.dataTables_filter input').val(rfid);
    var newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
    window.history.replaceState({}, document.title, newUrl);
  }

  <?php if ($isAdmin): ?>
  $('#confirmDeleteModal').on('show.bs.modal', function(event) {
    var button = $(event.relatedTarget);
    var id = button.data('id');
    $('#deleteConfirmBtn').attr('href', 'hapus_siswa.php?id=' + id);
  });
  <?php endif; ?>

  // Handler untuk tombol detail
  $(document).on('click', '.detail-btn', function() {
    var button = $(this);
    $('#detail-no_rfid').text(button.data('no_rfid'));
    $('#detail-nis').text(button.data('nis'));
    $('#detail-nama').text(button.data('nama'));
    $('#detail-jenis_kelamin').text(button.data('jenis_kelamin'));
    $('#detail-alamat').text(button.data('alamat'));
    $('#detail-kelas').text(button.data('kelas'));
    $('#detail-token').text(button.data('token'));
    $('#detail-id_chat').text(button.data('id_chat'));
    $('#detail-dataset_status').html(button.data('dataset_status'));
    
    // Tampilkan status di modal detail untuk kedua level
    var status = button.data('status');
    if (status === 'Aktif') {
      $('#detail-status').html('<span class="badge badge-success">Aktif</span>');
    } else {
      $('#detail-status').html('<span class="badge badge-danger">Nonaktif</span>');
    }

    $('#detail-foto_siswa').attr('src', button.data('foto_siswa'));
    $('#detailModal').modal('show');
  });
});
</script>
