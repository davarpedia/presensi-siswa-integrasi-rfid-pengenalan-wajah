<?php
$pageTitle = "Data Kelas";
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();
include 'header.php';
include 'sidebar.php';
include 'topbar.php';

// Ambil data kelas beserta nama guru melalui LEFT JOIN untuk memastikan kelas tetap tampil walaupun guru sudah dihapus
$sql = "SELECT kelas.nama_kelas, 
               IFNULL(pengguna.nama, '-') AS nama_guru, 
               kelas.id AS kelas_id, 
               kelas.guru_id 
        FROM kelas 
        LEFT JOIN guru ON kelas.guru_id = guru.id 
        LEFT JOIN pengguna ON guru.pengguna_id = pengguna.id";
$result = $conn->query($sql);

// Ambil list guru untuk dropdown di modal tambah/edit
$sqlGuru = "SELECT guru.id AS guru_id, pengguna.nama 
            FROM guru 
            JOIN pengguna ON guru.pengguna_id = pengguna.id";
$resultGuru = $conn->query($sqlGuru);
$guruList = [];
if ($resultGuru->num_rows > 0) {
    while ($row = $resultGuru->fetch_assoc()) {
        $guruList[] = $row;
    }
}
?>

<!-- Begin Page Content -->
<div class="container-fluid">
  <!-- Page Heading -->
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Data Kelas</h1>
    <div id="dateTimeDisplay" class="text-muted text-sm-right small"></div>
  </div>

  <div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Data Kelas</li>
        </ol>
    </nav>
  </div>

<!-- Tombol Tambah Kelas -->
<div class="mb-3">
  <button class="btn btn-primary col-12 col-md-auto col-lg-auto" data-toggle="modal" data-target="#tambahKelasModal">
    Tambah Kelas
  </button>
</div>

  <!-- Card pembungkus tabel -->
  <div class="card mb-4">
    <div class="card-body">
      <div class="table-responsive">
        <table id="dataTable" class="table table-striped table-bordered table-hover">
          <thead>
            <tr class="text-center">
              <th>No</th>
              <th>Nama Kelas</th>
              <th>Nama Guru</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $nomor = 1;
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>
                            <td class='text-center'>{$nomor}</td>
                            <td class='text-center'>{$row['nama_kelas']}</td>
                            <td class='text-center'>{$row['nama_guru']}</td>
                            <td class='aksi-btn text-center'>
                              <button class='btn btn-warning btn-sm btn-edit' 
                                      data-id='{$row['kelas_id']}' 
                                      data-nama_kelas='{$row['nama_kelas']}' 
                                      data-guru_id='{$row['guru_id']}' 
                                      title='Edit'>
                                <i class='bi bi-pencil-fill'></i>
                              </button>
                              <button class='btn btn-danger btn-sm btn-delete' 
                                      data-id='{$row['kelas_id']}' 
                                      data-toggle='modal' 
                                      data-target='#deleteKelasModal' 
                                      title='Hapus'>
                                <i class='bi bi-trash-fill'></i>
                              </button>
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
<!-- End of Container Fluid -->

</div>

<?php
$conn->close();
include 'footer.php';
?>

<!-- Modal Tambah Kelas -->
<div class="modal fade" id="tambahKelasModal" tabindex="-1" role="dialog" aria-labelledby="tambahKelasLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form id="formTambahKelas">
        <div class="modal-header">
          <h5 class="modal-title" id="tambahKelasLabel">Tambah Kelas</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div id="alertTambah"></div>
          <div class="form-group">
            <label for="namaKelasTambah">Nama Kelas</label>
            <input type="text" class="form-control" id="namaKelasTambah" name="nama_kelas" required>
          </div>
          <div class="form-group">
            <label for="idGuruTambah">Guru</label>
            <select class="custom-select" id="idGuruTambah" name="guru_id" required>
              <option value="">Pilih Guru</option>
              <?php foreach ($guruList as $guru): ?>
                <option value="<?= $guru['guru_id'] ?>"><?= $guru['nama'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Edit Kelas -->
<div class="modal fade" id="editKelasModal" tabindex="-1" role="dialog" aria-labelledby="editKelasLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form id="formEditKelas">
        <div class="modal-header">
          <h5 class="modal-title" id="editKelasLabel">Edit Kelas</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div id="alertEdit"></div>
          <input type="hidden" id="idKelasEdit" name="kelas_id">
          <div class="form-group">
            <label for="namaKelasEdit">Nama Kelas</label>
            <input type="text" class="form-control" id="namaKelasEdit" name="nama_kelas" required>
          </div>
          <div class="form-group">
            <label for="idGuruEdit">Guru</label>
            <select class="custom-select" id="idGuruEdit" name="guru_id" required>
              <option value="">Pilih Guru</option>
              <?php foreach ($guruList as $guru): ?>
                <option value="<?= $guru['guru_id'] ?>"><?= $guru['nama'] ?></option>
              <?php endforeach; ?>
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
</div>

<!-- Modal Hapus Kelas -->
<div class="modal fade" id="deleteKelasModal" tabindex="-1" role="dialog" aria-labelledby="deleteKelasLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteKelasLabel">Konfirmasi Hapus</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        Apakah Anda yakin ingin menghapus data kelas ini?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="button" id="confirmDeleteBtn" class="btn btn-danger">Hapus</button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
    // Inisialisasi DataTable
    $('#dataTable').DataTable({
        paging: true,
        searching: true,
        ordering: true,
        info: true,
        language: {
            emptyTable: "Tidak ada data kelas yang tersedia"
        },
        columnDefs: [
            { orderable: false, targets: [3] }
        ]
    });

    // Tambah Kelas
    $("#formTambahKelas").submit(function(e){
        e.preventDefault();
        $.post("tambah_kelas.php", $(this).serialize(), function(res){
            if(res.success){
                $("#alertTambah").html("<div class='alert alert-success'>"+res.message+"</div>");
                setTimeout(()=>location.reload(),1500);
            } else {
                $("#alertTambah").html("<div class='alert alert-danger'>"+res.message+"</div>");
            }
        },"json").fail(function(){
            $("#alertTambah").html("<div class='alert alert-danger'>Terjadi kesalahan saat menyimpan data.</div>");
        });
    });

    // Edit Kelas
    $(".btn-edit").click(function(){
        $("#idKelasEdit").val($(this).data("id"));
        $("#namaKelasEdit").val($(this).data("nama_kelas"));
        $("#idGuruEdit").val($(this).data("guru_id"));
        $("#editKelasModal").modal("show");
    });
    $("#formEditKelas").submit(function(e){
        e.preventDefault();
        $.post("edit_kelas.php", $(this).serialize(), function(res){
            if(res.success){
                $("#alertEdit").html("<div class='alert alert-success'>"+res.message+"</div>");
                setTimeout(()=>location.reload(),1500);
            } else {
                $("#alertEdit").html("<div class='alert alert-danger'>"+res.message+"</div>");
            }
        },"json").fail(function(){
            $("#alertEdit").html("<div class='alert alert-danger'>Terjadi kesalahan saat mengupdate data.</div>");
        });
    });

    // Hapus Kelas
    var deleteId;
    $(".btn-delete").click(function(){
        deleteId = $(this).data("id");
    });
    $("#confirmDeleteBtn").click(function(){
        $.post("hapus_kelas.php", {kelas_id: deleteId}, function(res){
            var body = $("#deleteKelasModal .modal-body");
            if(res.success){
                body.html("<div class='alert alert-success'>"+res.message+"</div>");
                setTimeout(()=>location.reload(),1500);
            } else {
                body.html("<div class='alert alert-danger'>"+res.message+"</div>");
            }
        },"json").fail(function(){
            $("#deleteKelasModal .modal-body").html("<div class='alert alert-danger'>Terjadi kesalahan saat menghapus data.</div>");
        });
    });
});
</script>