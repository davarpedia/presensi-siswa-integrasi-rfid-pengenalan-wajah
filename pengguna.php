<?php
$pageTitle = "Data Pengguna";
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();
include 'header.php';
include 'sidebar.php';
include 'topbar.php';

// Terima nilai filter dari form GET
$filterLevel = isset($_GET['level']) ? $_GET['level'] : '';
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'Aktif';
?>

<!-- Begin Page Content -->
<div class="container-fluid">
  <!-- Page Heading -->
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Data Pengguna</h1>
    <div id="dateTimeDisplay" class="text-muted text-left text-sm-right mt-2 mt-sm-0 small"></div>
  </div>

  <div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Data Pengguna</li>
        </ol>
    </nav>
  </div>

  <div class="row mb-3 align-items-start">
    <!-- Tombol Tambah Pengguna -->
    <div class="col-12 col-md-auto mb-2 mb-md-0">
      <button class="btn btn-primary w-100 w-md-auto" data-toggle="modal" data-target="#tambahPenggunaModal">
        Tambah Pengguna
      </button>
    </div>

    <div class="d-none d-md-block flex-grow-1"></div>

    <!-- Filter Level dan Status -->
    <div class="col-12 col-md-auto">
      <form method="GET" class="form-row">
        <!-- Filter Level -->
        <div class="col-12 col-md-auto mb-2 mb-md-0">
          <div class="input-group">
            <div class="input-group-prepend">
              <span class="input-group-text">Level</span>
            </div>
            <select name="level" class="custom-select">
              <option value="">Semua Level</option>
              <option value="Admin" <?php if ($filterLevel == 'Admin') echo 'selected'; ?>>Admin</option>
              <option value="Guru" <?php if ($filterLevel == 'Guru') echo 'selected'; ?>>Guru</option>
            </select>
          </div>
        </div>

        <!-- Filter Status -->
        <div class="col-12 col-md-auto mb-2 mb-md-0">
          <div class="input-group">
            <div class="input-group-prepend">
              <span class="input-group-text">Status</span>
            </div>
            <select name="status" class="custom-select">
              <option value="">Semua Status</option>
              <option value="Aktif" <?php if ($filterStatus == 'Aktif') echo 'selected'; ?>>Aktif</option>
              <option value="Nonaktif" <?php if ($filterStatus == 'Nonaktif') echo 'selected'; ?>>Nonaktif</option>
            </select>
          </div>
        </div>

        <!-- Tombol Tampilkan -->
        <div class="col-12 col-md-auto">
          <button type="submit" class="btn btn-secondary btn-block">Tampilkan</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Card untuk Data Pengguna -->
  <div class="card mb-4">
    <div class="card-body">
      <div class="table-responsive">
          <table id="dataTable" class="table table-striped table-bordered table-hover">
              <thead class="bg-white">
                  <tr>
                      <th>No</th>
                      <th>Nama</th>
                      <th>Email</th>
                      <th>Level</th>
                      <th>Foto Profil</th>
                      <th>Status</th>
                      <th>Aksi</th>
                  </tr>
              </thead>
              <tbody>
                  <?php
                    $sql = "SELECT * FROM pengguna WHERE 1=1";
                    if (!empty($filterLevel)) {
                        $sql .= " AND level = '$filterLevel'";
                    }
                    if (!empty($filterStatus)) {
                        $sql .= " AND status = '$filterStatus'";
                    }
                    $result = $conn->query($sql);
                    $nomor = 1;

                  if ($result->num_rows > 0) {
                      while ($row = $result->fetch_assoc()) {
                          // Jika foto_profil tidak ada atau file tidak ditemukan, gunakan default
                          $foto = (!empty($row['foto_profil']) && file_exists("data/foto/foto_profil_pengguna/{$row['foto_profil']}"))
                              ? "<div class='img-circle-crop'><img src='data/foto/foto_profil_pengguna/{$row['foto_profil']}' alt='Foto'></div>"
                              : "<div class='img-circle-crop'><img src='img/default_image.jpg' alt='Foto Default'></div>";
                            
                            // Tentukan badge berdasarkan level
                            if ($row['level'] == 'Admin') {
                                $levelBadge = "<span class='badge badge-primary'>Admin</span>";
                            } elseif ($row['level'] == 'Guru') {
                                $levelBadge = "<span class='badge badge-secondary'>Guru</span>";
                            } else {
                                $levelBadge = $row['level'];
                            }

                            // Buat status badge
                            if ($row['status'] === 'Aktif') {
                                $statusBadge = "<span class='badge badge-success'>Aktif</span>";
                            } else {
                                $statusBadge = "<span class='badge badge-danger'>{$row['status']}</span>";
                            }   
                          
                              echo "<tr>
                                  <td>{$nomor}</td>
                                  <td>{$row['nama']}</td>
                                  <td>{$row['email']}</td>
                                  <td>{$levelBadge}</td>
                                  <td>{$foto}</td>
                                  <td>{$statusBadge}</td>
                                  <td>
                                      <div class='aksi-btn'>
                                          <button class='btn btn-warning btn-sm btn-edit'
                                              data-id='{$row['id']}'
                                              data-nama='{$row['nama']}'
                                              data-email='{$row['email']}'
                                              data-level='{$row['level']}'
                                              data-status='{$row['status']}' 
                                              title='Edit'>
                                              <i class='bi bi-pencil-fill'></i>
                                          </button>
                                          <button class='btn btn-secondary btn-sm btn-reset'
                                              data-id='{$row["id"]}'
                                              title='Reset Password'>
                                              <i class='bi bi-key-fill'></i>
                                          </button>
                                          <button class='btn btn-danger btn-sm btn-delete'
                                              data-id='{$row['id']}'
                                              title='Hapus'
                                              data-toggle='modal'
                                              data-target='#deletePenggunaModal'>
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
  <!-- End Card -->

</div>
<!-- End of Container Fluid -->

</div>
<!-- End of Page Content -->

<!-- Modal Tambah Pengguna -->
<div class="modal fade" id="tambahPenggunaModal" tabindex="-1" role="dialog" aria-labelledby="tambahPenggunaLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form id="formTambahPengguna" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title" id="tambahPenggunaLabel">Tambah Pengguna</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
             <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div id="alertTambah"></div>
          <div class="form-group">
            <label for="namaTambah">Nama</label>
            <input type="text" class="form-control" id="namaTambah" name="nama" required>
          </div>
          <div class="form-group">
            <label for="emailTambah">Email</label>
            <input type="email" class="form-control" id="emailTambah" name="email" required>
          </div>
          <div class="form-group">
            <label for="passwordTambah">Password</label>
            <input type="password" class="form-control" id="passwordTambah" name="password" required>
          </div>
          <div class="form-group">
            <label for="konfirmasiPasswordTambah">Konfirmasi Password</label>
            <input type="password" class="form-control" id="konfirmasiPasswordTambah" name="confirm_password" required>
          </div>
          <div class="form-group">
            <label for="levelTambah">Level</label>
            <select class="custom-select" id="levelTambah" name="level" required>
              <option value="">Pilih Level</option>
              <option value="Admin">Admin</option>
              <option value="Guru">Guru</option>
            </select>
          </div>
          <div class="form-group">
            <label for="fotoTambah">Foto Profil</label>
            <div class="custom-file">
                <input type="file" class="custom-file-input" id="fotoTambah" name="foto" accept="image/*" required>
                <label class="custom-file-label" for="fotoTambah">Pilih File</label>
            </div>
            <small class="form-text text-muted"><span class="text-danger">*</span> Maksimal ukuran foto 5MB.</small>
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

<!-- Modal Edit Pengguna -->
<div class="modal fade" id="editPenggunaModal" tabindex="-1" role="dialog" aria-labelledby="editPenggunaLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form id="formEditPengguna" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title" id="editPenggunaLabel">Edit Pengguna</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
             <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div id="alertEdit"></div>
          <!-- Field hidden untuk ID pengguna -->
          <input type="hidden" id="idPenggunaEdit" name="id">
          <div class="form-group">
            <label for="namaEdit">Nama</label>
            <input type="text" class="form-control" id="namaEdit" name="nama" required>
          </div>
          <div class="form-group">
            <label for="emailEdit">Email</label>
            <input type="email" class="form-control" id="emailEdit" name="email" required>
          </div>
          <div class="form-group">
            <label for="levelEdit">Level</label>
            <select class="custom-select" id="levelEdit" name="level" required>
              <option value="">Pilih Level</option>
              <option value="Admin">Admin</option>
              <option value="Guru">Guru</option>
            </select>
          </div>
          <div class="form-group">
            <label for="fotoEdit">Foto Profil</label>
            <div class="custom-file">
                <input type="file" class="custom-file-input" id="fotoEdit" name="foto" accept="image/*">
                <label class="custom-file-label" for="fotoEdit">Pilih File</label>
            </div>
            <small class="form-text text-muted"><span class="text-danger">*</span> Maksimal ukuran foto 5MB.</small>
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
</div>

<!-- Modal Reset Password -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" role="dialog" aria-labelledby="resetPasswordLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form id="formResetPassword">
        <div class="modal-header">
          <h5 class="modal-title" id="resetPasswordLabel">Reset Password</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
             <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div id="alertReset"></div>
          <!-- Field hidden untuk ID pengguna -->
          <input type="hidden" id="idPenggunaReset" name="id">
          <div class="form-group">
            <label for="passwordBaru">Password Baru</label>
            <input type="password" class="form-control" id="passwordBaru" name="new_password" required>
          </div>
          <div class="form-group">
            <label for="konfirmasiPassword">Konfirmasi Password Baru</label>
            <input type="password" class="form-control" id="konfirmasiPassword" name="confirm_new_password" required>
            <small class="form-text text-muted mt-1"><span class="text-danger">*</span> Pastikan password baru dan konfirmasi sama.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Reset Password</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Hapus Pengguna -->
<div class="modal fade" id="deletePenggunaModal" tabindex="-1" role="dialog" aria-labelledby="deletePenggunaLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deletePenggunaLabel">Konfirmasi Hapus</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
           <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <!-- Pesan akan diisi via JavaScript -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="button" id="confirmDeletePenggunaBtn" class="btn btn-danger">Ya, Hapus</button>
      </div>
    </div>
  </div>
</div>

<?php
$conn->close();
include 'footer.php';
?>

<script>
  var currentSessionId = <?php echo json_encode($_SESSION['session_id']); ?>;
</script>

<script>
$(document).ready(function() {
    // Inisialisasi DataTable
    $('#dataTable').DataTable({
        paging: true,
        searching: true,
        ordering: true,
        info: true,
        language: {
            emptyTable: "Tidak ada data pengguna yang tersedia"
        },
        columnDefs: [
            { orderable: false, targets: [2, 3, 4, 5, 6] }
        ]
    });

    // -------------------- TAMBAH PENGGUNA --------------------
    $("#formTambahPengguna").on("submit", function(e){
        e.preventDefault();
        var password = $("#passwordTambah").val();
        var confirmPassword = $("#konfirmasiPasswordTambah").val();
        if(password !== confirmPassword){
            $("#alertTambah").html("<div class='alert alert-danger'>Password dan konfirmasi password tidak sama!</div>");
            return;
        }
        var formData = new FormData(this);
        $.ajax({
            url: "tambah_pengguna.php",
            type: "POST",
            data: formData,
            dataType: "json",
            processData: false,
            contentType: false,
            success: function(response){
                if(response.success){
                    $("#alertTambah").html("<div class='alert alert-success'>" + response.message + "</div>");
                    setTimeout(function(){ location.reload(); }, 1500);
                } else {
                    $("#alertTambah").html("<div class='alert alert-danger'>" + response.message + "</div>");
                }
            },
            error: function(){
                $("#alertTambah").html("<div class='alert alert-danger'>Terjadi kesalahan saat menyimpan data.</div>");
            }
        });
    });

    // -------------------- EDIT PENGGUNA --------------------
    $(".btn-edit").on("click", function(){
        var id = $(this).data("id");
        var nama = $(this).data("nama");
        var email = $(this).data("email");
        var level = $(this).data("level");
        var status = $(this).data("status");
        $("#idPenggunaEdit").val(id);
        $("#namaEdit").val(nama);
        $("#emailEdit").val(email);
        $("#levelEdit").val(level);
        $("#statusEdit").val(status);
        $("#editPenggunaModal").modal("show");
    });

    $("#formEditPengguna").on("submit", function(e){
        e.preventDefault();
        var formData = new FormData(this);
        var editId = $("#idPenggunaEdit").val();
        var newStatus = $("#statusEdit").val();

        // Jika user edit akun sendiri dan status diubah menjadi "Nonaktif"
        if(editId == currentSessionId && newStatus === "Nonaktif") {
            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: "Anda akan menonaktifkan akun Anda sendiri. Setelah ini, Anda akan otomatis logout dan tidak dapat mengakses sistem sampai diaktifkan kembali oleh admin. Apakah Anda yakin?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Nonaktifkan',
                cancelButtonText: 'Batal',
                customClass: {
                    confirmButton: 'btn btn-danger mr-2',
                    cancelButton: 'btn btn-secondary'
                },
                buttonsStyling: false
            }).then((result) => {
                if(result.isConfirmed) {
                    submitEditPengguna(formData);
                }
            });
        } else {
            submitEditPengguna(formData);
        }
    });

    // Fungsi untuk submit form edit pengguna via AJAX
    function submitEditPengguna(formData) {
        $.ajax({
            url: "edit_pengguna.php",
            type: "POST",
            data: formData,
            dataType: "json",
            processData: false,
            contentType: false,
            success: function(response){
                if(response.success){
                    Swal.fire({
                        title: "Berhasil!",
                        text: response.message,
                        icon: "success",
                        showConfirmButton: false,
                        timer: 2000
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: "Gagal!",
                        text: response.message,
                        icon: "error",
                        showConfirmButton: false,
                        timer: 3000
                    });
                }
            },
            error: function(){
                Swal.fire({
                    title: "Error!",
                    text: "Terjadi kesalahan saat mengupdate data.",
                    icon: "error",
                    showConfirmButton: false,
                    timer: 3000
                });
            }
        });
    }

    // -------------------- RESET PASSWORD --------------------
    var resetId;
    $(".btn-reset").on("click", function(){
        resetId = $(this).data("id");
        $("#idPenggunaReset").val(resetId);
        $("#passwordBaru").val('');
        $("#konfirmasiPassword").val('');
        $("#alertReset").html('');
        $("#resetPasswordModal").modal("show");
    });

    $("#formResetPassword").on("submit", function(e){
        e.preventDefault();
        var formData = new FormData(this);
        var newPassword = $("#passwordBaru").val();
        var confirmNewPassword = $("#konfirmasiPassword").val();
        if(newPassword !== confirmNewPassword){
            $("#alertReset").html("<div class='alert alert-danger'>Password baru dan konfirmasi tidak sama!</div>");
            return;
        }
        $.ajax({
            url: "reset_password.php",
            type: "POST",
            data: formData,
            dataType: "json",
            processData: false,
            contentType: false,
            success: function(response){
                if(response.success){
                    $("#alertReset").html("<div class='alert alert-success'>" + response.message + "</div>");
                    setTimeout(function(){ location.reload(); }, 1500);
                } else {
                    $("#alertReset").html("<div class='alert alert-danger'>" + response.message + "</div>");
                }
            },
            error: function(){
                $("#alertReset").html("<div class='alert alert-danger'>Terjadi kesalahan saat mereset password.</div>");
            }
        });
    });

// -------------------- HAPUS PENGGUNA --------------------
var deleteId;
$(".btn-delete").on("click", function () {
    deleteId = $(this).data("id");

    if (deleteId == currentSessionId) {
        $("#deletePenggunaModal .modal-body").html(
            "<div class='alert alert-warning mb-0'>Anda akan menghapus akun Anda sendiri. Semua data akan hilang dan Anda akan otomatis logout. Serta Anda tidak dapat lagi mengakses sistem menggunakan akun ini. Apakah Anda yakin ingin melanjutkan?</div>"
        );
    } else {
        $("#deletePenggunaModal .modal-body").html(
            "<div>Apakah Anda yakin ingin menghapus data pengguna ini?</div>"
        );
    }
});


    $("#confirmDeletePenggunaBtn").on("click", function(){
        $.ajax({
            url: "hapus_pengguna.php",
            type: "POST",
            data: { id: deleteId },
            dataType: "json",
            success: function(response){
                if(response.success){
                    $("#deletePenggunaModal .modal-body").html(
                        "<div class='alert alert-success'>" + response.message + "</div>"
                    );
                    setTimeout(function(){ location.reload(); }, 1500);
                } else {
                    $("#deletePenggunaModal .modal-body").html(
                        "<div class='alert alert-danger'>" + response.message + "</div>"
                    );
                }
            },
            error: function(){
                $("#deletePenggunaModal .modal-body").html(
                    "<div class='alert alert-danger'>Terjadi kesalahan saat menghapus data.</div>"
                );
            }
        });
    });
});
</script>

<script>
  $(document).ready(function () {
    $('.custom-file-input').on('change', function () {
      var fileName = $(this).val().split('\\').pop();
      $(this).next('.custom-file-label').html(fileName);
    });
  });
</script>
