<?php
$currentPage = basename($_SERVER['PHP_SELF'], ".php");
$userLevel   = strtolower($_SESSION['session_level'] ?? '');
?>

<!-- Sidebar -->
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
  <!-- Sidebar - Brand -->
  <a class="sidebar-brand d-flex align-items-center justify-content-center" href="index.php">
    <div class="sidebar-brand-icon rotate-n-15 brand-icon">
      <i class="fas fa-clipboard-list"></i>
    </div>
    <div class="sidebar-brand-text mx-3">SISENSI SD N GEMAWANG</div>
  </a>

  <!-- Divider -->
  <hr class="sidebar-divider my-0">

  <!-- Nav Item - Dashboard -->
  <li class="nav-item <?= ($currentPage == 'index') ? 'active' : '' ?>">
    <a class="nav-link" href="index.php">
      <i class="fas fa-fw fa-tachometer-alt"></i>
      <span>Dashboard</span>
    </a>
  </li>

  <?php if ($userLevel === 'admin'): ?>
    <!-- Divider -->
    <hr class="sidebar-divider my-0">

    <!-- Nav Item - Scan Presensi -->
    <li class="nav-item <?= ($currentPage == 'scan') ? 'active' : '' ?>">
      <a class="nav-link" href="scan.php">
        <i class="fas fa-fw fa-camera"></i>
        <span>Scan Presensi</span>
      </a>
    </li>

    <!-- Divider -->
    <hr class="sidebar-divider">

    <!-- Heading - Data Master -->
    <div class="sidebar-heading">Data Master</div>

    <li class="nav-item <?= ($currentPage == 'siswa') ? 'active' : '' ?>">
      <a class="nav-link" href="siswa.php">
        <i class="fas fa-fw fa-user-graduate"></i>
        <span>Data Siswa</span>
      </a>
    </li>

    <li class="nav-item <?= ($currentPage == 'guru') ? 'active' : '' ?>">
      <a class="nav-link" href="guru.php">
        <i class="fas fa-fw fa-chalkboard-teacher"></i>
        <span>Data Guru</span>
      </a>
    </li>

    <li class="nav-item <?= ($currentPage == 'kelas') ? 'active' : '' ?>">
      <a class="nav-link" href="kelas.php">
        <i class="fas fa-fw fa-chalkboard"></i>
        <span>Data Kelas</span>
      </a>
    </li>

    <li class="nav-item <?= ($currentPage == 'pengguna') ? 'active' : '' ?>">
      <a class="nav-link" href="pengguna.php">
        <i class="fas fa-fw fa-users-cog"></i>
        <span>Data Pengguna</span>
      </a>
    </li>

  <?php elseif ($userLevel === 'guru'): ?>
    <!-- Divider -->
    <hr class="sidebar-divider my-0">

    <li class="nav-item <?= ($currentPage == 'siswa') ? 'active' : '' ?>">
      <a class="nav-link" href="siswa.php">
        <i class="fas fa-fw fa-user-graduate"></i>
        <span>Data Siswa</span>
      </a>
    </li>
  <?php endif; ?>

  <!-- Divider -->
  <hr class="sidebar-divider">

  <!-- Heading - Presensi -->
  <div class="sidebar-heading">Presensi</div>

  <li class="nav-item <?= ($currentPage == 'data_presensi') ? 'active' : '' ?>">
    <a class="nav-link" href="data_presensi.php">
      <i class="fas fa-fw fa-clipboard-list"></i>
      <span>Data Presensi</span>
    </a>
  </li>

  <li class="nav-item <?= ($currentPage == 'kelola_presensi') ? 'active' : '' ?>">
    <a class="nav-link" href="kelola_presensi.php">
      <i class="fas fa-fw fa-tasks"></i>
      <span>Kelola Presensi</span>
    </a>
  </li>

  <li class="nav-item <?= ($currentPage == 'rekap_presensi') ? 'active' : '' ?>">
    <a class="nav-link" href="rekap_presensi.php">
      <i class="fas fa-fw fa-file-alt"></i>
      <span>Rekap Presensi</span>
    </a>
  </li>

  <?php if ($userLevel === 'admin'): ?>
    <!-- Divider -->
    <hr class="sidebar-divider">

    <!-- Heading - Lainnya -->
    <div class="sidebar-heading">Lainnya</div>

    <li class="nav-item <?= ($currentPage == 'riwayat') ? 'active' : '' ?>">
      <a class="nav-link" href="riwayat.php">
        <i class="fas fa-fw fa-history"></i>
        <span>Riwayat Tap RFID</span>
      </a>
    </li>

    <!-- Nav Item - Hari Libur -->
    <li class="nav-item <?= ($currentPage == 'hari_libur') ? 'active' : '' ?>">
      <a class="nav-link" href="hari_libur.php">
        <i class="fas fa-fw fa-calendar-day"></i>
        <span>Hari Libur</span>
      </a>
    </li>

    <!-- Nav Item - Pengaturan -->
    <li class="nav-item <?= ($currentPage == 'pengaturan') ? 'active' : '' ?>">
      <a class="nav-link" href="pengaturan.php">
        <i class="fas fa-fw fa-cog"></i>
        <span>Pengaturan</span>
      </a>
    </li>

  <?php elseif ($userLevel === 'guru'): ?>
    <!-- Divider -->
    <hr class="sidebar-divider my-0">

    <!-- Nav Item - Hari Libur untuk Guru -->
    <li class="nav-item <?= ($currentPage == 'hari_libur') ? 'active' : '' ?>">
      <a class="nav-link" href="hari_libur.php">
        <i class="fas fa-fw fa-calendar-day"></i>
        <span>Hari Libur</span>
      </a>
    </li>
  <?php endif; ?>
</ul>
<!-- End of Sidebar -->
