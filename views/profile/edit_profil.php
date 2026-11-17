<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../config/koneksi.php';

// Pastikan user benar-benar sudah login
if (empty($_SESSION['id_user'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu.';
    header('Location: ' . $baseUrl . '/auth/login.php');
    exit;
}

$userId    = (int) $_SESSION['id_user'];
$uploadDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR;
$uploadUrl = $baseUrl . '/assets/img/profile/';
$maxSize   = 2 * 1024 * 1024; // 2 MB

$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

$defaultPhotos = ['default.svg', 'default.png', 'default.jpg', 'default.jpeg'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$errors      = [];
$successMsg  = $_SESSION['success'] ?? '';
$newFotoName = null;
$user        = [];

unset($_SESSION['success']);

/**
 * Pesan ramah untuk kode error upload PHP.
 */
function uploadErrorMessage(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Ukuran foto melebihi batas maksimal 2MB.';
        case UPLOAD_ERR_PARTIAL:
            return 'Foto hanya terunggah sebagian. Silakan coba lagi.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Folder sementara untuk upload tidak ditemukan.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Server gagal menulis file foto. Silakan coba lagi.';
        case UPLOAD_ERR_EXTENSION:
            return 'Upload foto dihentikan oleh ekstensi server.';
        default:
            return 'Terjadi kesalahan saat mengunggah foto.';
    }
}

try {
    $stmt = $pdo->prepare("SELECT id, email, nama, nomor_hp, alamat, foto, password FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        session_unset();
        session_destroy();
        header('Location: ' . $baseUrl . '/auth/login.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // ================= VALIDASI CSRF =================
        if (empty($_POST['csrf_token']) || !hash_equals($csrfToken, (string) $_POST['csrf_token'])) {
            throw new RuntimeException('Sesi keamanan tidak valid. Silakan muat ulang halaman lalu coba lagi.');
        }

        $nama               = trim($_POST['nama'] ?? '');
        $nomorHp            = trim($_POST['nomor_hp'] ?? '');
        $alamat             = trim($_POST['alamat'] ?? '');
        $passwordLama       = $_POST['password_lama'] ?? '';
        $passwordBaru       = $_POST['password_baru'] ?? '';
        $passwordKonfirmasi = $_POST['password_konfirmasi'] ?? '';
        $newPasswordHash    = null;

        // ================= VALIDASI DATA TEKS =================
        if ($nama === '') {
            $errors[] = 'Nama lengkap wajib diisi.';
        } elseif (mb_strlen($nama) > 100) {
            $errors[] = 'Nama lengkap maksimal 100 karakter.';
        }

        if ($nomorHp !== '' && !preg_match('/^[0-9+\-\s()]{8,20}$/', $nomorHp)) {
            $errors[] = 'Nomor HP tidak valid. Gunakan angka, spasi, tanda hubung, atau + (8-20 karakter).';
        }

        if (mb_strlen($alamat) > 500) {
            $errors[] = 'Alamat maksimal 500 karakter.';
        }

        // ================= VALIDASI PASSWORD (OPSIONAL) =================
        $isChangingPassword = ($passwordLama !== '' || $passwordBaru !== '' || $passwordKonfirmasi !== '');

        if ($isChangingPassword) {
            if ($passwordLama === '') {
                $errors[] = 'Password lama wajib diisi untuk mengganti password.';
            }
            if ($passwordBaru === '') {
                $errors[] = 'Password baru wajib diisi.';
            }
            if ($passwordKonfirmasi === '') {
                $errors[] = 'Konfirmasi password baru wajib diisi.';
            }
            if ($passwordBaru !== '' && mb_strlen($passwordBaru) < 6) {
                $errors[] = 'Password baru minimal 6 karakter.';
            }
            if ($passwordBaru !== '' && $passwordBaru !== $passwordKonfirmasi) {
                $errors[] = 'Konfirmasi password baru tidak cocok.';
            }
            if ($passwordLama !== '' && !password_verify($passwordLama, $user['password'])) {
                $errors[] = 'Password lama tidak sesuai.';
            }

            if (empty($errors)) {
                $newPasswordHash = password_hash($passwordBaru, PASSWORD_DEFAULT);

                if ($newPasswordHash === false) {
                    throw new RuntimeException('Gagal mengenkripsi password baru. Silakan coba lagi.');
                }
            }
        }

        // ================= PROSES UPLOAD FOTO =================
        if (empty($errors)) {
            $file = $_FILES['foto'] ?? null;

            if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException(uploadErrorMessage((int) $file['error']));
                }

                if ((int) $file['size'] <= 0 || (int) $file['size'] > $maxSize) {
                    throw new RuntimeException('Ukuran foto maksimal 2MB.');
                }

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($file['tmp_name']);

                if (!isset($allowedTypes[$mime])) {
                    throw new RuntimeException('Format foto tidak diizinkan. Gunakan JPG, JPEG, PNG, atau WEBP.');
                }

                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                    throw new RuntimeException('Folder penyimpanan foto tidak dapat dibuat.');
                }

                $ext         = $allowedTypes[$mime];
                $newFotoName = 'profile_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $destination = $uploadDir . $newFotoName;

                if (!is_uploaded_file($file['tmp_name']) || !move_uploaded_file($file['tmp_name'], $destination)) {
                    $newFotoName = null;
                    throw new RuntimeException('Gagal mengunggah foto profil. Silakan coba lagi.');
                }
            }
        }

        // ================= UPDATE DATABASE =================
        if (empty($errors)) {
            $pdo->beginTransaction();

            $sql    = "UPDATE users SET nama = :nama, nomor_hp = :nomor_hp, alamat = :alamat";
            $params = [
                ':nama'      => $nama,
                ':nomor_hp'  => $nomorHp !== '' ? $nomorHp : null,
                ':alamat'    => $alamat !== '' ? $alamat : null,
            ];

            if ($newFotoName !== null) {
                $sql .= ", foto = :foto";
                $params[':foto'] = $newFotoName;
            }

            if ($newPasswordHash !== null) {
                $sql .= ", password = :password";
                $params[':password'] = $newPasswordHash;
            }

            $sql .= " WHERE id = :id";
            $params[':id'] = $userId;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $pdo->commit();

            // Hapus foto lama setelah update database berhasil (kecuali foto default)
            if ($newFotoName !== null && !empty($user['foto'])) {
                $oldPhoto = basename($user['foto']);
                $oldPath  = $uploadDir . $oldPhoto;

                if (!in_array($oldPhoto, $defaultPhotos, true) && is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }

            // ================= PERBARUI SESSION =================
            $fotoFinal = $newFotoName ?? (string) ($user['foto'] ?? '');

            $_SESSION['nama']      = $nama;
            $_SESSION['nomor_hp']  = $nomorHp;
            $_SESSION['no_hp']     = $nomorHp;
            $_SESSION['alamat']    = $alamat;
            $_SESSION['foto']      = $fotoFinal;

            // Struktur tambahan agar kompatibel dengan kode yang memakai $_SESSION['user']
            $_SESSION['user'] = [
                'id'        => $userId,
                'email'     => $user['email'],
                'nama'      => $nama,
                'no_hp'     => $nomorHp,
                'nomor_hp'  => $nomorHp,
                'alamat'    => $alamat,
                'foto'      => $fotoFinal,
                'role'      => $_SESSION['role'] ?? null,
            ];

            $_SESSION['success'] = 'Profil berhasil diperbarui.';
            header('Location: ' . $baseUrl . '/views/profile/edit_profil.php');
            exit;
        }
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($newFotoName !== null && is_file($uploadDir . $newFotoName)) {
        @unlink($uploadDir . $newFotoName);
    }
    error_log('Edit profil (DB): ' . $e->getMessage());
    $errors[] = 'Gagal menyimpan perubahan ke database. Silakan coba lagi.';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($newFotoName !== null && is_file($uploadDir . $newFotoName)) {
        @unlink($uploadDir . $newFotoName);
    }
    $errors[] = $e->getMessage();
}

// Nilai form: pertahankan input pengguna jika ada error
$valNama    = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string) ($_POST['nama'] ?? '') : (string) ($user['nama'] ?? '');
$valNomorHp = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string) ($_POST['nomor_hp'] ?? '') : (string) ($user['nomor_hp'] ?? '');
$valAlamat  = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string) ($_POST['alamat'] ?? '') : (string) ($user['alamat'] ?? '');

$fotoLama = basename((string) ($user['foto'] ?? ''));
$fotoFile = $fotoLama !== '' ? $fotoLama : 'default.svg';
$fotoUrl  = is_file($uploadDir . $fotoFile) ? $uploadUrl . rawurlencode($fotoFile) : '';

$dashboardMap = [
    'admin'   => '/views/Admin/dashboard.php',
    'petugas' => '/views/Teller/dashboard.php',
    'nasabah' => '/views/Nasabah/dashboard.php',
];
$dashboardUrl = $baseUrl . ($dashboardMap[$_SESSION['role'] ?? ''] ?? '/index.php');
?>

<?php if ($successMsg !== ''): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($successMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Perubahan gagal disimpan:</div>
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
<?php endif; ?>

<form action="" method="POST" enctype="multipart/form-data" id="formProfil" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

    <div class="row g-4">
        <!-- ================= FOTO PROFIL ================= -->
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-camera me-2 text-success"></i>Foto Profil</h5>
                </div>
                <div class="card-body text-center d-flex flex-column align-items-center justify-content-center">
                    <div class="profile-photo-wrap mb-3">
                        <?php if ($fotoUrl !== ''): ?>
                            <img src="<?= htmlspecialchars($fotoUrl) ?>"
                                 alt="Foto profil <?= htmlspecialchars($valNama) ?>"
                                 class="profile-photo"
                                 id="previewFoto">
                        <?php else: ?>
                            <div class="profile-photo profile-photo-fallback" id="previewFallback">
                                <i class="bi bi-person-fill"></i>
                            </div>
                            <img src="" alt="Preview foto profil" class="profile-photo d-none" id="previewFoto">
                        <?php endif; ?>
                    </div>

                    <label for="inputFoto" class="form-label fw-semibold">Ganti Foto Profil</label>
                    <input type="file"
                           name="foto"
                           id="inputFoto"
                           class="form-control"
                           accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <div class="form-text">
                        Format: JPG, JPEG, PNG, WEBP. Maksimal <strong>2MB</strong>.
                    </div>
                    <div class="small text-success mt-1" id="infoFoto"></div>
                </div>
            </div>
        </div>

        <!-- ================= DATA PROFIL & PASSWORD ================= -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-person-vcard me-2 text-success"></i>Data Profil</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nama" class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="nama"
                                   id="nama"
                                   class="form-control"
                                   maxlength="100"
                                   required
                                   value="<?= htmlspecialchars($valNama) ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label fw-semibold">Email</label>
                            <input type="email"
                                   id="email"
                                   class="form-control"
                                   value="<?= htmlspecialchars((string) ($user['email'] ?? '')) ?>"
                                   readonly>
                            <div class="form-text">Email tidak dapat diubah dari halaman ini.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="nomor_hp" class="form-label fw-semibold">No. HP</label>
                            <input type="tel"
                                   name="nomor_hp"
                                   id="nomor_hp"
                                   class="form-control"
                                   maxlength="20"
                                   placeholder="0812xxxxxxx"
                                   value="<?= htmlspecialchars($valNomorHp) ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="role" class="form-label fw-semibold">Role Akun</label>
                            <input type="text"
                                   id="role"
                                   name="role"
                                   class="form-control"
                                   value="<?= htmlspecialchars(ucfirst((string) ($_SESSION['role'] ?? '-'))) ?>"
                                   readonly>
                        </div>

                        <div class="col-12">
                            <label for="alamat" class="form-label fw-semibold">Alamat</label>
                            <textarea name="alamat"
                                      id="alamat"
                                      class="form-control"
                                      rows="3"
                                      maxlength="500"
                                      placeholder="Tulis alamat lengkap..."><?= htmlspecialchars($valAlamat) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-shield-lock me-2 text-success"></i>Ganti Password <span class="text-muted fw-normal small">(Opsional)</span></h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-light border small mb-3">
                        <i class="bi bi-info-circle me-1 text-success"></i>
                        Kosongkan semua kolom password jika Anda tidak ingin mengganti password.
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="password_lama" class="form-label fw-semibold">Password Lama</label>
                            <div class="input-group">
                                <input type="password" name="password_lama" id="password_lama" class="form-control" autocomplete="current-password">
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password_lama" title="Tampilkan password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label for="password_baru" class="form-label fw-semibold">Password Baru</label>
                            <div class="input-group">
                                <input type="password" name="password_baru" id="password_baru" class="form-control" minlength="6" autocomplete="new-password">
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password_baru" title="Tampilkan password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">Minimal 6 karakter.</div>
                        </div>

                        <div class="col-md-4">
                            <label for="password_konfirmasi" class="form-label fw-semibold">Konfirmasi Password Baru</label>
                            <div class="input-group">
                                <input type="password" name="password_konfirmasi" id="password_konfirmasi" class="form-control" minlength="6" autocomplete="new-password">
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password_konfirmasi" title="Tampilkan password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-4">
                <button type="submit" class="btn btn-success px-4">
                    <i class="bi bi-save me-1"></i>Simpan Perubahan
                </button>
                <a href="<?= htmlspecialchars($dashboardUrl) ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Batal
                </a>
            </div>
        </div>
    </div>
</form>

<style>
    .profile-photo-wrap {
        width: 168px;
        height: 168px;
        position: relative;
    }

    .profile-photo {
        width: 168px;
        height: 168px;
        border-radius: 50%;
        object-fit: cover;
        border: 5px solid #e8f5e9;
        box-shadow: 0 6px 18px rgba(46, 125, 50, 0.18);
        background: #f8fafc;
    }

    .profile-photo-fallback {
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 82px;
        color: #81c784;
    }

    @media (max-width: 767px) {
        .profile-photo-wrap,
        .profile-photo {
            width: 136px;
            height: 136px;
        }

        .profile-photo-fallback {
            font-size: 64px;
        }
    }
</style>

<script>
    (function () {
        const inputFoto  = document.getElementById('inputFoto');
        const preview    = document.getElementById('previewFoto');
        const fallback   = document.getElementById('previewFallback');
        const infoFoto   = document.getElementById('infoFoto');
        const MAX_SIZE   = 2 * 1024 * 1024;
        const ALLOWED    = ['image/jpeg', 'image/png', 'image/webp'];
        const defaultSrc = preview ? preview.getAttribute('src') : '';

        if (inputFoto && preview) {
            inputFoto.addEventListener('change', function () {
                const file = this.files && this.files[0];

                if (!file) {
                    preview.src = defaultSrc;
                    preview.classList.add('d-none');
                    if (fallback) fallback.classList.remove('d-none');
                    if (infoFoto) infoFoto.textContent = '';
                    return;
                }

                if (!ALLOWED.includes(file.type)) {
                    alert('Format foto tidak diizinkan. Gunakan JPG, JPEG, PNG, atau WEBP.');
                    this.value = '';
                    return;
                }

                if (file.size > MAX_SIZE) {
                    alert('Ukuran foto maksimal 2MB.');
                    this.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (event) {
                    preview.src = event.target.result;
                    preview.classList.remove('d-none');
                    if (fallback) fallback.classList.add('d-none');
                };
                reader.readAsDataURL(file);

                if (infoFoto) {
                    infoFoto.textContent = file.name + ' (' + Math.round(file.size / 1024) + ' KB)';
                }
            });
        }

        const roleInput = document.getElementById('role');
        if (roleInput) {
            roleInput.focus();
        }

        document.querySelectorAll('.toggle-password').forEach(function (button) {
            button.addEventListener('click', function () {
                const input = document.getElementById(this.getAttribute('data-target'));
                if (!input) return;

                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';

                const icon = this.querySelector('i');
                if (icon) {
                    icon.classList.toggle('bi-eye', !show);
                    icon.classList.toggle('bi-eye-slash', show);
                }
            });
        });
    })();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
