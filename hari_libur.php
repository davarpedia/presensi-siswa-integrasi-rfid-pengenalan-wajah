<?php 
$pageTitle = "Hari Libur";
require_once 'koneksi.php';
require_once 'autentikasi.php';

// Tentukan level: Admin atau Guru
$isAdmin = (strtolower($_SESSION['session_level'] ?? '') === 'admin');

// --- AJAX Handlers ---
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    // Get hari libur berdasarkan tanggal (boleh untuk semua)
    if ($action === 'get_by_date') {
        $tanggal = $_GET['tanggal'] ?? '';
        if (empty($tanggal)) {
            echo json_encode(['status' => 'error', 'message' => 'Tanggal tidak valid!']);
            exit;
        }
        $t = mysqli_real_escape_string($conn, $tanggal);
        $q = "SELECT * FROM hari_libur WHERE '$t' BETWEEN tanggal_mulai AND tanggal_selesai LIMIT 1";
        $result = $conn->query($q);
        if ($result && $result->num_rows > 0) {
            echo json_encode(['status' => 'success', 'data' => $result->fetch_assoc()]);
        } else {
            echo json_encode(['status' => 'not_found']);
        }
        exit;
    }

    // Hapus hari libur (hanya Admin)
    if ($action === 'delete') {
        if (!$isAdmin) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            exit;
        }
        $id = $_GET['id'] ?? '';
        if (empty($id)) {
            echo json_encode(['status' => 'error', 'message' => 'ID tidak valid!']);
            exit;
        }
        $i = mysqli_real_escape_string($conn, $id);
        $q = "DELETE FROM hari_libur WHERE id='$i'";
        if ($conn->query($q)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
        exit;
    }
  
    // Get calendar events (boleh untuk semua)
    if ($action === 'get_events') {
      $q      = "SELECT id, tanggal_mulai, tanggal_selesai, keterangan FROM hari_libur";
      $result = $conn->query($q);
      $events = [];
      while ($row = $result->fetch_assoc()) {
          $events[] = [
              'id'                    => $row['id'],
              'title'                 => $row['keterangan'],
              'start'                 => $row['tanggal_mulai'],
              // end eksklusif FullCalendar = tanggal_selesai +1
              'end'                   => date("Y-m-d", strtotime($row['tanggal_selesai'] . " +1 day")),
              // prop tambahan untuk form
              'tanggal_mulai_orig'    => $row['tanggal_mulai'],
              'tanggal_selesai_orig'  => $row['tanggal_selesai'],
              'allDay'                => true,
          ];
      }
      echo json_encode($events);
      exit;
    }
}

// Simpan (Insert/Update) Hari Libur (hanya Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!$isAdmin) {
      echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
      exit;
  }

  $id               = $_POST['id']               ?? '';
  $tanggal_mulai    = $_POST['tanggal_mulai']    ?? '';
  $tanggal_selesai  = $_POST['tanggal_selesai']  ?? '';
  $keterangan       = $_POST['keterangan']       ?? '';

  if (empty($tanggal_mulai) || empty($tanggal_selesai)) {
      echo json_encode(['status' => 'error', 'message' => 'Tanggal mulai dan selesai wajib diisi!']);
      exit;
  }
  if (strtotime($tanggal_mulai) > strtotime($tanggal_selesai)) {
      echo json_encode(['status' => 'error', 'message' => 'Tanggal selesai harus lebih besar atau sama dengan tanggal mulai!']);
      exit;
  }

  $mulai   = mysqli_real_escape_string($conn, $tanggal_mulai);
  $selesai = mysqli_real_escape_string($conn, $tanggal_selesai);
  $k       = mysqli_real_escape_string($conn, $keterangan);

  if (!empty($id)) {
      $i = mysqli_real_escape_string($conn, $id);
      $q = "UPDATE hari_libur SET tanggal_mulai='$mulai', tanggal_selesai='$selesai', keterangan='$k' WHERE id='$i'";
  } else {
      $q = "INSERT INTO hari_libur (tanggal_mulai, tanggal_selesai, keterangan) VALUES ('$mulai', '$selesai', '$k')";
  }

  if ($conn->query($q)) {
      echo json_encode(['status' => 'success']);
  } else {
      echo json_encode(['status' => 'error', 'message' => $conn->error]);
  }
  exit;
}

include 'header.php';
include 'sidebar.php';
include 'topbar.php';
?>

<div class="container-fluid">
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Hari Libur</h1>
    <div id="dateTimeDisplay" class="text-muted text-left text-sm-right mt-2 mt-sm-0 small"></div>
  </div>

  <div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Hari Libur</li>
        </ol>
    </nav>
  </div>

  <div class="mb-3">
    <?php if ($isAdmin): ?>
      <button id="addRangeHolidayBtn" class="btn btn-primary">Tambah Hari Libur</button>
    <?php endif; ?>
  </div>

  <div class="card mb-4">
    <div class="card-body">
        <div id="calendar"></div>
    </div>
  </div>

  <!-- Modal untuk Tambah/Edit/Hapus -->
  <div class="modal fade" id="holidayModal" tabindex="-1" role="dialog" aria-labelledby="holidayModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <form id="holidayForm">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="holidayModalLabel">Tambah / Update Hari Libur</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div id="formMessage"></div>
            <input type="hidden" id="holidayId" name="id" value="">
            <div class="form-group">
              <label for="holidayStart">Tanggal Mulai</label>
              <input type="date" class="form-control" id="holidayStart" name="tanggal_mulai" required>
            </div>
            <div class="form-group">
              <label for="holidayEnd">Tanggal Selesai</label>
              <input type="date" class="form-control" id="holidayEnd" name="tanggal_selesai" required>
            </div>
            <div class="form-group">
              <label for="holidayDesc">Keterangan</label>
              <input type="text" class="form-control" id="holidayDesc" name="keterangan" placeholder="Contoh: Tahun Baru, Hari Raya, dll." required>
            </div>
          </div>
          <div class="modal-footer">
            <?php if ($isAdmin): ?>
              <button type="button" class="btn btn-danger" id="deleteHolidayBtn" style="display:none;">Hapus</button>
              <button type="submit" class="btn btn-primary">Simpan</button>
            <?php else: ?>
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            <?php endif; ?>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

</div>

<?php include 'footer.php'; ?>

<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.js"></script>

<script>
  // Ambil dari PHP, untuk nge‑disable fitur di client‑side
  var isAdmin = <?= $isAdmin ? 'true' : 'false'; ?>;

  $(document).ready(function() {
    // Reset pesan tiap kali modal ditutup
    $('#holidayModal').on('hidden.bs.modal', function(){
      $('#formMessage').html('');
    });

    // Inisialisasi FullCalendar
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
      locale: 'id',
      initialView: 'dayGridMonth',
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,listMonth'
      },
      buttonText: {
        today: 'Hari Ini',
        dayGridMonth: 'Kalender',
        listMonth: 'Daftar'
      },
      // Hanya pasang handler klik tanggal kalau admin
      dateClick: isAdmin
        ? function(info) {
            $('#holidayStart').val(info.dateStr);
            $('#holidayEnd').val(info.dateStr);
            $('#holidayDesc, #holidayId').val('');
            $('#holidayModalLabel').text('Tambah Hari Libur');
            $('#deleteHolidayBtn').hide();

            // Cek apakah sudah ada hari libur di tanggal tsb
            $.get('<?= basename(__FILE__); ?>', {
                action: 'get_by_date',
                tanggal: info.dateStr
              }, function(response) {
                var data = JSON.parse(response);
                if (data.status === 'success') {
                  $('#holidayModalLabel').text('Edit Hari Libur');
                  $('#holidayDesc').val(data.data.keterangan);
                  $('#holidayId').val(data.data.id);
                  $('#holidayStart').val(data.data.tanggal_mulai);
                  $('#holidayEnd').val(data.data.tanggal_selesai);
                  $('#deleteHolidayBtn').show();
                }
                $('#holidayModal').modal('show');
            });
          }
        : null,
      // Hanya pasang handler klik event kalau admin
      eventClick: isAdmin
        ? function(info) {
            $('#formMessage').html('');
            $('#holidayModalLabel').text('Edit Hari Libur');
            $('#holidayId').val(info.event.id);

            // Ambil tanggal asli dari extendedProps
            $('#holidayStart').val(info.event.extendedProps.tanggal_mulai_orig);
            $('#holidayEnd').val(info.event.extendedProps.tanggal_selesai_orig);

            // Keterangan
            $('#holidayDesc').val(info.event.extendedProps.keterangan || info.event.title);

            $('#deleteHolidayBtn').show();
            $('#holidayModal').modal('show');
        }
        : null,
      // Events tetap ditampilkan untuk semua user
      events: '<?= basename(__FILE__); ?>?action=get_events'
    });

    calendar.render();

    // Tombol "Tambah Hari Libur" hanya muncul kalau admin (di-PHP)
    $('#addRangeHolidayBtn').click(function() {
      $('#holidayForm')[0].reset();
      $('#holidayId').val('');
      var today = new Date().toISOString().slice(0,10);
      $('#holidayStart').val(today);
      $('#holidayEnd').val(today);
      $('#holidayModalLabel').text('Tambah Hari Libur');
      $('#deleteHolidayBtn').hide();
      $('#holidayModal').modal('show');
    });

    // Submit form (insert/update)
    $('#holidayForm').on('submit', function(e) {
      e.preventDefault();
      $.post('<?= basename(__FILE__); ?>', $(this).serialize(), function(response) {
        var res = JSON.parse(response);
        if (res.status === 'success') {
          $('#formMessage').html('<div class="alert alert-success">Data hari libur berhasil disimpan.</div>');
          calendar.refetchEvents();
          setTimeout(function() { $('#holidayModal').modal('hide'); }, 1000);
        } else {
          $('#formMessage').html('<div class="alert alert-danger">' + res.message + '</div>');
        }
      });
    });

    // Hapus data dengan konfirmasi (hanya untuk admin)
    $('#deleteHolidayBtn').click(function() {
      Swal.fire({
        title: 'Konfirmasi Hapus',
        text: 'Apakah Anda yakin ingin menghapus data hari libur ini?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Hapus',
        cancelButtonText: 'Batal',
        customClass: {
          confirmButton: 'btn btn-danger mr-2',
          cancelButton: 'btn btn-secondary'
        },
        buttonsStyling: false
      }).then((result) => {
        if (result.isConfirmed) {
          var id = $('#holidayId').val();
          $.get('<?= basename(__FILE__); ?>', { action: 'delete', id: id }, function(response) {
            var res = JSON.parse(response);
            if (res.status === 'success') {
              Swal.fire({
                title: 'Berhasil',
                text: 'Data hari libur berhasil dihapus.',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
              });
              calendar.refetchEvents();
              setTimeout(function() { $('#holidayModal').modal('hide'); }, 1000);
            } else {
              Swal.fire({
                title: 'Error',
                text: res.message,
                icon: 'error'
              });
            }
          });
        }
      });
    });
  });
</script>
