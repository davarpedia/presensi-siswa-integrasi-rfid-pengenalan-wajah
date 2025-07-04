<?php
$pageTitle = "Data Guru";
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();
include 'header.php';
include 'sidebar.php';
include 'topbar.php';

// Tangkap filter status dari GET (atau POST)
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'Aktif';
?>

<!-- Begin Page Content -->
<div class="container-fluid">

  <!-- Page Heading -->
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Data Guru</h1>
    <div id="dateTimeDisplay" class="text-muted text-sm-right small"></div>
  </div>

  <div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Data Guru</li>
        </ol>
    </nav>
  </div>

<!-- Tombol Tambah + Filter Status -->
<div class="row mb-3 align-items-start">
  <!-- Tombol Tambah Guru -->
  <div class="col-12 col-md-auto mb-2 mb-md-0">
    <button class="btn btn-primary w-100 w-md-auto" data-toggle="modal" data-target="#tambahGuruModal">
      Tambah Guru
    </button>
  </div>

  <div class="d-none d-md-block flex-grow-1"></div>

  <!-- Filter Status -->
  <div class="col-12 col-md-auto">
    <form method="GET" class="form-row align-items-center">
      <!-- Input Group Status -->
      <div class="col-12 col-md-auto mb-2 mb-md-0">
        <div class="input-group">
          <div class="input-group-prepend">
            <label class="input-group-text" for="statusFilter">Status</label>
          </div>
          <select class="custom-select" id="statusFilter" name="status">
            <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>Semua Status</option>
            <option value="Aktif" <?= $statusFilter === 'Aktif' ? 'selected' : '' ?>>Aktif</option>
            <option value="Nonaktif" <?= $statusFilter === 'Nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
          </select>
        </div>
      </div>

      <!-- Tombol Tampilkan -->
      <div class="col-12 col-md-auto">
        <button type="submit" class="btn btn-secondary btn-block">
          Tampilkan
        </button>
      </div>
    </form>
  </div>
</div>

  <!-- Card Tabel Data Guru -->
  <div class="card mb-4">
    <div class="card-body">
      <div class="table-responsive">
        <table id="dataTable" class="table table-striped table-bordered table-hover">
          <thead class="thead">
            <tr class="text-center">
              <th>No</th>
              <th>NIP</th>
              <th>Nama</th>
              <th>JK</th>
              <th>Kelas yang Diampu</th>
              <th>Foto</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php
            // Ambil data guru beserta pengguna dan kelas
            $sql = "SELECT 
                    guru.id AS guru_id, 
                    guru.nip, 
                    guru.jenis_kelamin, 
                    guru.telepon, 
                    guru.alamat,
                    guru.status, 
                    pengguna.nama AS nama_guru, 
                    pengguna.email, 
                    pengguna.foto_profil,
                    GROUP_CONCAT(kelas.nama_kelas ORDER BY CAST(kelas.nama_kelas AS UNSIGNED) ASC SEPARATOR ', ') AS kelas_diampu
                    FROM guru 
                    JOIN pengguna ON guru.pengguna_id = pengguna.id
                    LEFT JOIN kelas ON kelas.guru_id = guru.id";

            // Tambahkan filter status jika ada
            if ($statusFilter !== '') {
            // escape untuk keamanan
            $sf = $conn->real_escape_string($statusFilter);
            $sql .= " WHERE guru.status = '{$sf}'";
            }

            $sql .= " GROUP BY guru.id";
            $result = $conn->query($sql);
            $nomor = 1;
            if ($result && $result->num_rows > 0) {
              while ($row = $result->fetch_assoc()) {
                // Konversi jenis kelamin
                $gender = $row['jenis_kelamin'] === 'L' ? 'Laki-laki' : ($row['jenis_kelamin'] === 'P' ? 'Perempuan' : '-');
                // Menentukan foto untuk table
                $fotoPath = "data/foto/foto_profil_pengguna/{$row['foto_profil']}";
                if (!empty($row['foto_profil']) && file_exists($fotoPath)) {
                  $foto = "<div class='img-circle-crop'><img src='{$fotoPath}' alt='Foto' width='50'></div>";
                  $dataFotoprofil = $fotoPath;
                } else {
                  $foto = "<div class='img-circle-crop'><img src='img/default_image.jpg' alt='Foto Default' width='50'></div>";
                  $dataFotoprofil = "img/default_image.jpg";
                }

                // Buat status badge
                if ($row['status'] === 'Aktif') {
                    $statusBadge = "<span class='badge badge-success'>Aktif</span>";
                } else {
                    $statusBadge = "<span class='badge badge-danger'>{$row['status']}</span>";
                }                

                // Menampilkan tombol dengan data attribute yang sesuai
                echo "<tr class='text-center'>
                        <td>{$nomor}</td>
                        <td>{$row['nip']}</td>
                        <td>{$row['nama_guru']}</td>
                        <td>{$row['jenis_kelamin']}</td>
                        <td>" . (!empty($row['kelas_diampu']) ? $row['kelas_diampu'] : '-') . "</td>
                        <td>{$foto}</td>
                        <td>{$statusBadge}</td>
                        <td>
                        <div class='aksi-btn'>
                          <button class='btn btn-secondary btn-sm btn-detail'
                            data-nip='{$row['nip']}'
                            data-nama_guru='{$row['nama_guru']}'
                            data-jenis_kelamin='{$gender}'
                            data-alamat='{$row['alamat']}'
                            data-email='{$row['email']}'
                            data-telepon='{$row['telepon']}'
                            data-kelas_diampu='" . (!empty($row['kelas_diampu']) ? $row['kelas_diampu'] : '-') . "'
                            data-fotoprofil='{$dataFotoprofil}'
                            data-status='{$row['status']}'
                            title='Detail' data-toggle='modal' data-target='#detailModal'>
                          <i class='bi bi-eye-fill'></i>
                          </button>
                          <button class='btn btn-warning btn-sm btn-edit' 
                            data-idguru='{$row['guru_id']}' 
                            data-nip='{$row['nip']}'
                            data-jeniskelamin='{$row['jenis_kelamin']}'
                            data-telepon='{$row['telepon']}'
                            data-alamat='{$row['alamat']}'
                            data-nama='{$row['nama_guru']}'
                            data-email='{$row['email']}'
                            data-status='{$row['status']}'
                            title='Edit' data-toggle='modal' data-target='#editGuruModal'>
                            <i class='bi bi-pencil-fill'></i>
                          </button>
                          <button class='btn btn-danger btn-sm btn-delete' 
                            data-idguru='{$row['guru_id']}'
                            title='Hapus' data-toggle='modal' data-target='#deleteGuruModal'>
                            <i class='bi bi-trash-fill'></i>
                          </button>
                        </div>
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

</div>
<!-- /.container-fluid -->

<?php
// Ambil data pengguna level guru untuk dropdown
$sqlGuru = "SELECT id, nama FROM pengguna WHERE level = 'guru'";
$resultGuru = $conn->query($sqlGuru);
?>

<!-- Modal Tambah Guru -->
<div class="modal fade" id="tambahGuruModal" tabindex="-1" role="dialog" aria-labelledby="tambahGuruLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="formTambahGuru" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="tambahGuruLabel">Tambah Guru</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div id="alertTambah"></div>
        <div class="form-group">
          <label for="idPenggunaGuru">Nama Guru</label>
          <select class="custom-select" id="idPenggunaGuru" name="pengguna_id" required>
            <option value="">Pilih Pengguna</option>
            <?php while($user = $resultGuru->fetch_assoc()): ?>
              <option value="<?= $user['id'] ?>"><?= $user['nama'] ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="nipTambah">NIP</label>
          <input type="text" class="form-control" id="nipTambah" name="nip" required>
        </div>
        <div class="form-group">
            <label class="d-block mb-2">Jenis Kelamin</label>
            <div class="custom-control custom-radio custom-control-inline">
                <input type="radio" id="jkLTambah" name="jenis_kelamin" class="custom-control-input" value="L" required>
                <label class="custom-control-label" for="jkLTambah">Laki-laki</label>
            </div>
            <div class="custom-control custom-radio custom-control-inline">
                <input type="radio" id="jkPTambah" name="jenis_kelamin" class="custom-control-input" value="P" required>
                <label class="custom-control-label" for="jkPTambah">Perempuan</label>
            </div>
        </div>
        <div class="form-group">
          <label for="teleponTambah">Telepon</label>
          <input type="text" class="form-control" id="teleponTambah" name="telepon" required>
        </div>
        <div class="form-group">
          <label for="alamatTambah">Alamat</label>
          <textarea class="form-control" id="alamatTambah" name="alamat" rows="2" required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit Guru -->
<div class="modal fade" id="editGuruModal" tabindex="-1" role="dialog" aria-labelledby="editGuruLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="formEditGuru" class="modal-content" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title" id="editGuruLabel">Edit Guru</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div id="alertEdit"></div>
        <input type="hidden" id="idGuruEdit" name="guru_id">
        <div class="form-group">
          <label for="namaGuruEdit">Nama Guru</label>
          <input type="text" class="form-control" id="namaGuruEdit" name="nama" required>
        </div>
        <div class="form-group">
          <label for="emailEdit">Email</label>
          <input type="email" class="form-control" id="emailEdit" name="email" required>
        </div>
        <div class="form-group">
          <label for="nipEdit">NIP</label>
          <input type="text" class="form-control" id="nipEdit" name="nip" required>
        </div>
        <div class="form-group">
            <label class="d-block mb-2">Jenis Kelamin</label>
            <div class="custom-control custom-radio custom-control-inline">
                <input type="radio" id="jkLEdit" name="jenis_kelamin" class="custom-control-input" value="L" required>
                <label class="custom-control-label" for="jkLEdit">Laki-laki</label>
            </div>
            <div class="custom-control custom-radio custom-control-inline">
                <input type="radio" id="jkPEdit" name="jenis_kelamin" class="custom-control-input" value="P" required>
                <label class="custom-control-label" for="jkPEdit">Perempuan</label>
            </div>
        </div>
        <div class="form-group">
          <label for="teleponEdit">Telepon</label>
          <input type="text" class="form-control" id="teleponEdit" name="telepon" required>
        </div>
        <div class="form-group">
          <label for="alamatEdit">Alamat</label>
          <textarea class="form-control" id="alamatEdit" name="alamat" rows="2" required></textarea>
        </div>
        <div class="form-group">
            <label for="fotoEdit">Foto</label>
            <div class="custom-file">
                <input type="file" class="custom-file-input" id="fotoEdit" name="foto" accept="image/*">
                <label class="custom-file-label" for="fotoEdit">Pilih File</label>
            </div>
            <small class="form-text text-muted mt-2">
                <span class="text-danger">*</span> Maksimal ukuran foto 5MB.
            </small>
        </div>
        <div class="form-group">
          <label for="statusEdit">Status</label>
          <select class="custom-select" id="statusEdit" name="status" required>
            <option value="Aktif">Aktif</option>
            <option value="Nonaktif">Nonaktif</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Hapus Guru -->
<div class="modal fade" id="deleteGuruModal" tabindex="-1" role="dialog" aria-labelledby="deleteGuruLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteGuruLabel">Konfirmasi Hapus</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        Apakah Anda yakin ingin menghapus data guru ini?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="button" id="confirmDeleteGuruBtn" class="btn btn-danger">Hapus</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Detail Data Guru -->
<div class="modal fade" id="detailModal" tabindex="-1" role="dialog" aria-labelledby="detailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailModalLabel">Detail Data Guru</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-bordered no-hover">
            <tbody>
              <tr>
                <th>NIP</th>
                <td><span id="detail-nip"></span></td>
              </tr>
              <tr>
                <th>Nama</th>
                <td><span id="detail-nama"></span></td>
              </tr>
              <tr>
                <th>Jenis Kelamin</th>
                <td><span id="detail-gender"></span></td>
              </tr>
              <tr>
                <th>Alamat</th>
                <td><span id="detail-alamat"></span></td>
              </tr>
              <tr>
                <th>Email</th>
                <td><span id="detail-email"></span></td>
              </tr>
              <tr>
                <th>Telepon</th>
                <td><span id="detail-telepon"></span></td>
              </tr>
              <tr>
                <th>Kelas yang Diampu</th>
                <td><span id="detail-kelas"></span></td>
              </tr>
              <tr>
                <th>Status</th>
                <td><span id="detail-status"></span></td>
              </tr>
              <tr>
                <th>Foto Guru</th>
                <td>
                  <img id="detail-fotoprofil" src="" alt="Foto Guru" 
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

<?php
$conn->close();
include 'footer.php';
?>

<!-- Script AJAX dan DataTable -->
<script>
$(document).ready(function() {
    // Inisialisasi DataTable
    $('#dataTable').DataTable({
        paging: true,
        searching: true,
        ordering: true,
        info: true,
        language: {
            emptyTable: "Tidak ada data guru yang tersedia"
        },
        columnDefs: [
            { orderable: false, targets: [5, 6, 7] }
        ]
    });

    // Tambah Guru
    $("#formTambahGuru").on("submit", function(e){
        e.preventDefault();
        $.ajax({
            url: "tambah_guru.php",
            type: "POST",
            data: $(this).serialize(),
            dataType: "json",
            success: function(res){
                if(res.success){
                    $("#alertTambah").html("<div class='alert alert-success'>"+res.message+"</div>");
                    setTimeout(()=>location.reload(),1500);
                } else {
                    $("#alertTambah").html("<div class='alert alert-danger'>"+res.message+"</div>");
                }
            },
            error: function(){
                $("#alertTambah").html("<div class='alert alert-danger'>Kesalahan saat menyimpan data.</div>");
            }
        });
    });

    // Edit Guru: tampilkan modal & isi data
    $('.btn-edit').on('click', function(){
        const d = $(this).data();
        $('#idGuruEdit').val(d.idguru);
        $('#namaGuruEdit').val(d.nama);
        $('#emailEdit').val(d.email);
        $('#nipEdit').val(d.nip);
        $("input[name='jenis_kelamin'][value='"+d.jeniskelamin+"']").prop('checked',true);
        $('#teleponEdit').val(d.telepon);
        $('#alamatEdit').val(d.alamat);
        $('#statusEdit').val(d.status);
    });

    $("#formEditGuru").on("submit", function(e){
        e.preventDefault();
        let fd = new FormData(this);
        $.ajax({
            url: "edit_guru.php",
            type: "POST",
            data: fd,
            dataType: "json",
            processData: false,
            contentType: false,
            success: function(res){
                if(res.success){
                    $("#alertEdit").html("<div class='alert alert-success'>"+res.message+"</div>");
                    setTimeout(()=>location.reload(),1500);
                } else {
                    $("#alertEdit").html("<div class='alert alert-danger'>"+res.message+"</div>");
                }
            },
            error: function(){
                $("#alertEdit").html("<div class='alert alert-danger'>Kesalahan saat update data.</div>");
            }
        });
    });

    // Hapus Guru
    let deleteId;
    $('.btn-delete').on('click', function(){
        deleteId = $(this).data('idguru');
    });
    $('#confirmDeleteGuruBtn').on('click', function(){
        $.ajax({
            url: "hapus_guru.php",
            type: "POST",
            data: { guru_id: deleteId },
            dataType: "json",
            success: function(res){
                const body = $('#deleteGuruModal .modal-body');
                if(res.success){
                    body.html("<div class='alert alert-success'>"+res.message+"</div>");
                    setTimeout(()=>location.reload(),1500);
                } else {
                    body.html("<div class='alert alert-danger'>"+res.message+"</div>");
                }
            },
            error: function(){
                $('#deleteGuruModal .modal-body').html("<div class='alert alert-danger'>Kesalahan saat menghapus data.</div>");
            }
        });
    });
    
    // Handler untuk tombol detail
    $(document).on('click', '.btn-detail', function() {
        var button = $(this);
        $('#detail-nip').text(button.data('nip'));
        $('#detail-nama').text(button.data('nama_guru'));
        $('#detail-gender').text(button.data('jenis_kelamin'));
        $('#detail-alamat').text(button.data('alamat'));
        $('#detail-email').text(button.data('email'));
        $('#detail-telepon').text(button.data('telepon'));
        $('#detail-kelas').text(button.data('kelas_diampu'));
        
        var status = button.data('status');
        if (status === 'Aktif') {
          $('#detail-status').html('<span class="badge badge-success">Aktif</span>');
        } else {
          $('#detail-status').html('<span class="badge badge-danger">Nonaktif</span>');
        }
        $('#detail-fotoprofil').attr('src', button.data('fotoprofil'));
        $('#detailModal').modal('show');
    });
});
</script>

<script>
  $('#fotoEdit').on('change', function() {
    // Ambil nama file dari input
    var fileName = $(this).val().split('\\').pop();
    $(this).next('.custom-file-label').html(fileName);
  });
</script>
