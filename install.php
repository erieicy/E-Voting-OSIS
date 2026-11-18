<?php
/**
 * install.php - Wizard Instalasi Mandiri (Web Installer)
 * Sistem E-Voting Pemilihan Ketua OSIS (LAN)
 */

date_default_timezone_set('Asia/Jakarta');

$lockFile = __DIR__ . '/installed.lock';

// Setelah instalasi selesai, installer dinonaktifkan total (HTTP 404).
// Reset hanya dapat dilakukan dari panel admin yang memverifikasi password.
if (file_exists($lockFile)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    exit('Installer dinonaktifkan karena sistem sudah terpasang.');
}

// Paksa HTTPS untuk akses non-loopback (LAN/Wi-Fi).
$installerIsHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
    || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
$installerHost    = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
$installerHost    = preg_replace('/:\d+$/', '', $installerHost);
$installerIsLocal = in_array(strtolower($installerHost), ['localhost', '127.0.0.1', '::1', '[::1]'], true)
    && in_array((string)($_SERVER['REMOTE_ADDR'] ?? ''), ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true);

if (PHP_SAPI !== 'cli' && !$installerIsHttps && !$installerIsLocal) {
    if ($installerHost === '' || !preg_match('/^[A-Za-z0-9.\-\[\]:]+$/', $installerHost)) {
        http_response_code(400);
        exit('Host header tidak valid.');
    }
    $installerUri = str_replace(["\r", "\n"], '', (string)($_SERVER['REQUEST_URI'] ?? '/'));
    header('Location: https://' . $installerHost . $installerUri, true, 301);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $installerIsHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    if ($installerIsHttps) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

$configFile = __DIR__ . '/config/config.php';
$error   = '';
$success = false;

function h($s): string {
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
}

function install_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function install_verify_csrf(?string $token): bool {
    return !empty($_SESSION['csrf_token']) && !empty($token) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Menghapus seluruh file foto kandidat yang pernah diunggah (kecuali file bawaan)
 */
function clean_uploaded_photos(string $rootDir): void {
    $uploadDir = rtrim($rootDir, '/\\') . '/assets/uploads';
    if (!is_dir($uploadDir)) {
        return;
    }
    foreach (scandir($uploadDir) as $item) {
        if (in_array($item, ['.', '..', '.gitkeep', '.htaccess', 'default-candidate.png'], true)) {
            continue;
        }
        $filePath = $uploadDir . '/' . $item;
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }
}

$form = [
    'db_host'      => trim($_POST['db_host'] ?? 'localhost'),
    'db_user'      => trim($_POST['db_user'] ?? 'root'),
    'db_pass'      => $_POST['db_pass'] ?? '',
    'db_name'      => trim($_POST['db_name'] ?? 'db_evoting_osis'),
    'nama_sekolah' => trim($_POST['nama_sekolah'] ?? ''),
    'periode'      => trim($_POST['periode'] ?? ''),
    'tema'         => ($_POST['tema'] ?? 'light') === 'dark' ? 'dark' : 'light',
    'admin_nama'   => trim($_POST['admin_nama'] ?? 'Majelis Perwakilan Kelas (MPK)'),
    'admin_user'   => trim($_POST['admin_user'] ?? ''),
    'admin_pass'   => $_POST['admin_pass'] ?? '',
];

// Proses Instalasi Baru dari Awal (Clean Install)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!install_verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan formulir tidak valid. Silakan coba lagi.';
    } else {
        $dbNameClean = preg_replace('/[^a-zA-Z0-9_]/', '', $form['db_name']);

        if (
            $dbNameClean === '' ||
            $form['nama_sekolah'] === '' ||
            $form['periode'] === '' ||
            $form['admin_nama'] === '' ||
            $form['admin_user'] === '' ||
            strlen($form['admin_pass']) < 5
        ) {
            $error = 'Mohon lengkapi seluruh kolom wajib (termasuk periode). Password admin minimal 5 karakter.';
        } elseif (
            mb_strlen($form['nama_sekolah']) > 150 ||
            mb_strlen($form['periode']) > 20 ||
            mb_strlen($form['admin_nama']) > 100 ||
            mb_strlen($form['admin_user']) > 50 ||
            strlen($dbNameClean) > 64
        ) {
            $error = 'Panjang teks melebihi batas maksimal kolom (Nama Sekolah maks. 150, Periode maks. 20, Username maks. 50 karakter).';
        } else {
            try {
                $pdo = new PDO(
                    'mysql:host=' . $form['db_host'] . ';charset=utf8mb4',
                    $form['db_user'],
                    $form['db_pass'],
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]
                );

                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbNameClean}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `{$dbNameClean}`");

                // Bersihkan foto lama & hapus tabel lama agar 100% bersih dari awal
                clean_uploaded_photos(__DIR__);
                $pdo->exec("DROP TABLE IF EXISTS `tokens`, `kandidat`, `admin`, `pengaturan`");

                // 1. Buat ulang tabel pengaturan
                $pdo->exec("
                    CREATE TABLE `pengaturan` (
                      `id` int(11) NOT NULL,
                      `nama_sekolah` varchar(150) NOT NULL DEFAULT 'SMA Negeri 1',
                      `periode` varchar(20) NOT NULL DEFAULT '',
                      `status_voting` enum('buka','tutup') NOT NULL DEFAULT 'buka',
                      `tampilkan_hasil` tinyint(1) NOT NULL DEFAULT 0,
                      `tema` enum('light','dark') NOT NULL DEFAULT 'light',
                      PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                // 2. Buat ulang tabel admin
                $pdo->exec("
                    CREATE TABLE `admin` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `username` varchar(50) NOT NULL,
                      `password` varchar(255) NOT NULL,
                      `nama_lengkap` varchar(100) NOT NULL,
                      PRIMARY KEY (`id`),
                      UNIQUE KEY `idx_username` (`username`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                // 3. Buat ulang tabel kandidat (dengan UNIQUE KEY pada no_urut - BUG-FUN-03)
                $pdo->exec("
                    CREATE TABLE `kandidat` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `no_urut` int(3) NOT NULL,
                      `nama_ketua` varchar(100) NOT NULL,
                      `nama_wakil` varchar(100) DEFAULT '',
                      `nama_file_foto` varchar(255) NOT NULL DEFAULT 'default-candidate.png',
                      `visi` text NOT NULL,
                      `misi` text NOT NULL,
                      `jumlah_suara` int(11) NOT NULL DEFAULT 0,
                      PRIMARY KEY (`id`),
                      UNIQUE KEY `idx_no_urut` (`no_urut`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                // 4. Buat ulang tabel tokens (Tanpa Foreign Key ke kandidat demi kerahasiaan suara)
                $pdo->exec("
                    CREATE TABLE `tokens` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `kode_token` varchar(10) NOT NULL,
                      `sudah_dipakai` tinyint(1) NOT NULL DEFAULT 0,
                      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                      `used_at` datetime DEFAULT NULL,
                      PRIMARY KEY (`id`),
                      UNIQUE KEY `idx_kode_token` (`kode_token`),
                      KEY `idx_sudah_dipakai` (`sudah_dipakai`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                // Simpan pengaturan awal
                $stmtSet = $pdo->prepare("
                    INSERT INTO pengaturan (id, nama_sekolah, periode, status_voting, tampilkan_hasil, tema)
                    VALUES (1, ?, ?, 'buka', 0, ?)
                ");
                $stmtSet->execute([$form['nama_sekolah'], $form['periode'], $form['tema']]);

                // Simpan akun admin pertama
                $hash = password_hash($form['admin_pass'], PASSWORD_BCRYPT);
                $stmtAdm = $pdo->prepare("
                    INSERT INTO admin (username, password, nama_lengkap)
                    VALUES (?, ?, ?)
                ");
                $stmtAdm->execute([$form['admin_user'], $hash, $form['admin_nama']]);

                // Tulis config/config.php
                $configContent = "<?php\n"
                    . "/**\n * Konfigurasi Database E-Voting OSIS\n * Dibuat otomatis oleh install.php\n */\n\n"
                    . "define('DB_HOST', " . var_export($form['db_host'], true) . ");\n"
                    . "define('DB_USER', " . var_export($form['db_user'], true) . ");\n"
                    . "define('DB_PASS', " . var_export($form['db_pass'], true) . ");\n"
                    . "define('DB_NAME', " . var_export($dbNameClean, true) . ");\n";

                file_put_contents($configFile, $configContent);
                file_put_contents($lockFile, 'Installed at ' . date('Y-m-d H:i:s') . "\n");

                $_SESSION = [];
                session_regenerate_id(true);

                $success  = true;
            } catch (Exception $e) {
                error_log('Install execution error: ' . $e->getMessage());
                $error = 'Gagal melakukan instalasi database. Pastikan layanan MySQL aktif dan kredensial database benar.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="<?= h($form['tema']) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Instalasi Sistem E-Voting OSIS</title>
  <link rel="stylesheet" href="assets/css/base.css">
  <link rel="stylesheet" href="assets/css/installer.css">
</head>
<body>
  <div class="install-wrapper">
    <div class="install-card">
      <div class="install-header">
        <div>
          <h1>Instalasi Sistem E-Voting OSIS</h1>
          <p>Konfigurasi mandiri database lokal dan akun panitia pemilihan</p>
        </div>
        <span class="badge">LAN Offline-First</span>
      </div>

      <?php if ($success): ?>
        <div class="alert alert-success">
          <strong>Instalasi Berhasil.</strong> Seluruh database, tabel sistem, dan file kunci <code>installed.lock</code> telah dibuat dan dikunci.
        </div>
        <p style="font-size: 14px; margin-bottom: 20px;">
          Halaman instalasi ini telah dikunci secara otomatis demi keamanan. Silakan masuk ke halaman Administrator menggunakan akun yang baru Anda buat.
        </p>
        <div class="install-grid">
          <a href="admin/login.php" class="btn btn-primary btn-block">Masuk Panel Admin</a>
          <a href="index.php" class="btn btn-block">Buka Bilik Suara</a>
        </div>

      <?php else: ?>
        <?php if ($error !== ''): ?>
          <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" action="install.php" autocomplete="off">
          <input type="hidden" name="csrf_token" value="<?= h(install_csrf_token()) ?>">
          <div class="install-section">
            <div class="install-section-title">1. Koneksi Database MySQL / MariaDB</div>
            <div class="install-grid">
              <div class="form-group">
                <label class="form-label" for="db_host">Database Host</label>
                <input type="text" id="db_host" name="db_host" class="form-control" maxlength="100" value="<?= h($form['db_host']) ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label" for="db_name">Nama Database</label>
                <input type="text" id="db_name" name="db_name" class="form-control mono" maxlength="64" value="<?= h($form['db_name']) ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label" for="db_user">Database Username</label>
                <input type="text" id="db_user" name="db_user" class="form-control" maxlength="64" value="<?= h($form['db_user']) ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label" for="db_pass">Database Password</label>
                <input type="password" id="db_pass" name="db_pass" class="form-control" value="<?= h($form['db_pass']) ?>" placeholder="Kosongkan jika standar XAMPP/Laragon">
              </div>
            </div>
          </div>

          <div class="install-section">
            <div class="install-section-title">2. Identitas Sekolah &amp; Tema UI</div>
            <div class="form-group">
              <label class="form-label" for="nama_sekolah">Nama Sekolah</label>
              <input type="text" id="nama_sekolah" name="nama_sekolah" class="form-control" maxlength="150" value="<?= h($form['nama_sekolah']) ?>" placeholder="Masukkan Nama Sekolah" required>
            </div>
            <div class="install-grid">
              <div class="form-group">
                <label class="form-label" for="periode">Periode Kepengurusan</label>
                <input type="text" id="periode" name="periode" class="form-control" maxlength="20" value="<?= h($form['periode']) ?>" placeholder="Masukkan Tahun" required>
              </div>
              <div class="form-group">
                <label class="form-label" for="tema">Tema Tampilan Awal</label>
                <select id="tema" name="tema" class="form-control">
                  <option value="light" <?= $form['tema'] === 'light' ? 'selected' : '' ?>>White (Terang)</option>
                  <option value="dark" <?= $form['tema'] === 'dark' ? 'selected' : '' ?>>Dark (Gelap)</option>
                </select>
                <span class="form-hint">Dapat diubah kapan saja di menu Pengaturan.</span>
              </div>
            </div>
          </div>

          <div class="install-section">
            <div class="install-section-title">3. Akun Administrator Pertama</div>
            <div class="form-group">
              <label class="form-label" for="admin_nama">Nama Lengkap Panitia / Operator</label>
              <input type="text" id="admin_nama" name="admin_nama" class="form-control" maxlength="100" value="<?= h($form['admin_nama']) ?>" required>
            </div>
            <div class="install-grid">
              <div class="form-group">
                <label class="form-label" for="admin_user">Username Login</label>
                <input type="text" id="admin_user" name="admin_user" class="form-control mono" maxlength="50" value="<?= h($form['admin_user']) ?>" placeholder="Masukkan Username" required>
              </div>
              <div class="form-group">
                <label class="form-label" for="admin_pass">Password Login</label>
                <input type="password" id="admin_pass" name="admin_pass" class="form-control" placeholder="Minimal 5 karakter" required>
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-primary btn-block">Jalankan Instalasi Sekarang</button>
        </form>
      <?php endif; ?>

      <div class="install-footer">
        <span>Sistem E-Voting Ketua OSIS</span>
        <span>PHP Native &bull; MySQL</span>
      </div>
    </div>
  </div>
  <script src="assets/js/admin.js"></script>
</body>
</html>
