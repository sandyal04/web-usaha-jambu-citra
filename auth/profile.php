<?php
/**
 * Halaman Edit Profil Pengguna
 * Sistem Informasi Usaha Tani Jambu Citra
 */

declare(strict_types=1);

$pageTitle   = 'Edit Profil Pengguna';
$activePage  = 'profile';

// Proses POST sebelum header dikirim
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_login();

$pdo   = get_db_connection();
$user  = get_authenticated_user();
$uid   = (int) $user['id'];
$errors = [];
$activeTab = $_GET['tab'] ?? 'profil';
if (!in_array($activeTab, ['profil', 'password'], true)) {
    $activeTab = 'profil';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan (CSRF) tidak valid atau telah kedaluwarsa. Silakan coba lagi.';
    }
    
    $action = $_POST['action'] ?? 'profil';

    // ─── Tab 1: Ubah Informasi Profil ────────────────────────────────────
    if ($action === 'profil') {
        $activeTab  = 'profil';
        $fullName   = trim($_POST['full_name'] ?? '');
        $username   = trim($_POST['username'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');

        if (empty($fullName)) {
            $errors[] = 'Nama lengkap tidak boleh kosong.';
        } elseif (mb_strlen($fullName) > 100) {
            $errors[] = 'Nama lengkap terlalu panjang (maksimal 100 karakter).';
        }

        if (empty($username)) {
            $errors[] = 'Username tidak boleh kosong.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
            $errors[] = 'Username hanya boleh berisi huruf, angka, dan underscore (3–50 karakter).';
        }

        if (!empty($email)) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Format alamat email tidak valid.';
            } elseif (strlen($email) > 100) {
                $errors[] = 'Alamat email terlalu panjang (maksimal 100 karakter).';
            }
        }

        if (!empty($phone) && strlen($phone) > 20) {
            $errors[] = 'Nomor telepon/kontak terlalu panjang (maksimal 20 karakter).';
        }

        if (empty($errors)) {
            // Cek apakah username sudah dipakai pengguna lain
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmtCheck->execute([$username, $uid]);
            if ($stmtCheck->fetchColumn()) {
                $errors[] = 'Username "' . e($username) . '" sudah digunakan akun lain. Silakan pilih username lain.';
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ?, email = ?, phone = ? WHERE id = ?");
            $stmt->execute([
                $fullName,
                $username,
                $email !== '' ? $email : null,
                $phone !== '' ? $phone : null,
                $uid
            ]);

            // Perbarui session agar tampilan navbar / header langsung diperbarui
            $_SESSION['full_name'] = $fullName;
            $_SESSION['username']  = $username;
            $_SESSION['email']     = $email;

            redirect('auth/profile.php', 'success', 'Informasi profil akun berhasil diperbarui!');
            exit;
        }
    }

    // ─── Tab 2: Ubah Kata Sandi ───────────────────────────────────────────
    if ($action === 'password') {
        $activeTab       = 'password';
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword     = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if (empty($currentPassword)) {
            $errors[] = 'Kata sandi saat ini wajib diisi.';
        }
        if (empty($newPassword)) {
            $errors[] = 'Kata sandi baru tidak boleh kosong.';
        } elseif (strlen($newPassword) < 6) {
            $errors[] = 'Kata sandi baru minimal 6 karakter.';
        }
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'Konfirmasi kata sandi baru tidak cocok dengan kata sandi baru.';
        }

        if (empty($errors)) {
            // Verifikasi kata sandi lama
            $stmtUser = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmtUser->execute([$uid]);
            $storedHash = $stmtUser->fetchColumn();

            if (!$storedHash || !password_verify($currentPassword, (string) $storedHash)) {
                $errors[] = 'Kata sandi saat ini yang Anda masukkan salah.';
            }
        }

        if (empty($errors)) {
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$newHash, $uid]);

            redirect('auth/profile.php?tab=password', 'success', 'Kata sandi akun Anda berhasil diperbarui!');
            exit;
        }
    }
}

// Ambil data terbaru pengguna dari DB
$stmtUser = $pdo->prepare("SELECT id, username, full_name, phone, email, created_at FROM users WHERE id = ?");
$stmtUser->execute([$uid]);
$userData = $stmtUser->fetch();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="<?= url('index.php'); ?>" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Pengaturan Profil</li>
            </ol>
        </nav>
        <h4 class="fw-bold mb-1 text-dark">
            <i class="bi bi-person-gear text-success me-2"></i>Pengaturan Profil Pengguna
        </h4>
        <p class="text-muted small mb-0">Perbarui informasi akun dan ubah kata sandi untuk menjaga keamanan sistem.</p>
    </div>
    <a href="<?= url('index.php'); ?>" class="btn btn-outline-secondary btn-sm shadow-sm">
        <i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard
    </a>
</div>

<div class="row g-4">
    <!-- Kartu Foto Profil & Info Akun -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-3 text-center p-4">
            <!-- Avatar dengan inisial nama -->
            <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle bg-success text-white fw-bold shadow"
                 style="width:90px;height:90px;font-size:2.25rem;background:linear-gradient(135deg,#15803d,#22c55e)!important;">
                <?= strtoupper(mb_substr($userData['full_name'] ?? 'P', 0, 1)); ?>
            </div>
            <h5 class="fw-bold mb-1 text-dark"><?= e($userData['full_name'] ?? '-'); ?></h5>
            <p class="text-muted small mb-1">@<?= e($userData['username'] ?? '-'); ?></p>
            <p class="text-muted small mb-0"><?= e($userData['email'] ?: 'Belum ada email'); ?></p>
            <?php if (!empty($userData['phone'])): ?>
                <p class="text-muted small mt-1 mb-0"><i class="bi bi-telephone text-success me-1"></i><?= e($userData['phone']); ?></p>
            <?php endif; ?>

            <hr class="my-3">

            <div class="d-flex flex-column gap-2 text-start">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small"><i class="bi bi-shield-check me-2 text-success"></i>Status Akun</span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">Pengelola Aktif</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small"><i class="bi bi-calendar3 me-2 text-primary"></i>Terdaftar Sejak</span>
                    <span class="small fw-semibold text-dark">
                        <?= !empty($userData['created_at']) ? format_date(substr($userData['created_at'], 0, 10), true) : '-'; ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small"><i class="bi bi-clock me-2 text-warning"></i>Sesi Terakhir</span>
                    <span class="small fw-semibold text-dark">
                        <?= !empty($_SESSION['last_login']) ? date('d/m/Y H:i', (int) $_SESSION['last_login']) : 'Saat ini'; ?>
                    </span>
                </div>
            </div>

            <hr class="my-3">
            <a href="<?= url('auth/logout.php'); ?>" class="btn btn-outline-danger btn-sm w-100">
                <i class="bi bi-box-arrow-right me-1"></i> Keluar dari Akun
            </a>
        </div>
    </div>

    <!-- Kartu Form Edit -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-3">
            <!-- Tab Navigasi -->
            <div class="card-header bg-white border-bottom p-0">
                <ul class="nav nav-tabs border-0 px-3" role="tablist" id="profileTabList">
                    <li class="nav-item">
                        <button class="nav-link fw-semibold <?= $activeTab === 'profil' ? 'active' : ''; ?>"
                                id="tab-profil" type="button"
                                onclick="switchTab('profil')">
                            <i class="bi bi-person-lines-fill me-1"></i> Informasi Profil
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link fw-semibold <?= $activeTab === 'password' ? 'active' : ''; ?>"
                                id="tab-password" type="button"
                                onclick="switchTab('password')">
                            <i class="bi bi-key-fill me-1"></i> Ubah Kata Sandi
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body p-4">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Terdapat kesalahan:</strong>
                        <ul class="mb-0 mt-1 ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- ── Panel 1: Informasi Profil ── -->
                <div id="panel-profil" style="<?= $activeTab !== 'profil' ? 'display:none;' : ''; ?>">
                    <form method="POST" action="" id="form-profil">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="profil">

                        <div class="mb-4">
                            <h6 class="fw-bold text-dark mb-1">
                                <i class="bi bi-pencil-square text-success me-2"></i>Detail Informasi Profil
                            </h6>
                            <p class="text-muted small mb-0">Ubah nama, username, kontak, atau email akun sesuai kebutuhan.</p>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="full_name" class="form-label fw-semibold">
                                    Nama Lengkap <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-person text-muted"></i></span>
                                    <input type="text" id="full_name" name="full_name"
                                           class="form-control <?= ($activeTab === 'profil' && !empty($errors)) ? 'is-invalid' : ''; ?>"
                                           value="<?= e($userData['full_name'] ?? ''); ?>"
                                           placeholder="Nama lengkap Anda"
                                           maxlength="100" required>
                                </div>
                                <div class="form-text text-muted">Nama ini ditampilkan di sistem dan laporan.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="username" class="form-label fw-semibold">
                                    Username <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">@</span>
                                    <input type="text" id="username" name="username"
                                           class="form-control"
                                           value="<?= e($userData['username'] ?? ''); ?>"
                                           placeholder="username_anda"
                                           pattern="[a-zA-Z0-9_]{3,50}"
                                           maxlength="50" required>
                                </div>
                                <div class="form-text text-muted">Digunakan saat login (huruf, angka, underscore).</div>
                            </div>

                            <div class="col-md-6">
                                <label for="phone" class="form-label fw-semibold">No. HP / WhatsApp</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-whatsapp text-success"></i></span>
                                    <input type="text" id="phone" name="phone"
                                           class="form-control"
                                           value="<?= e($userData['phone'] ?? ''); ?>"
                                           placeholder="Contoh: 081234567890"
                                           maxlength="20">
                                </div>
                                <div class="form-text text-muted">Nomor kontak aktif pengelola.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label fw-semibold">Alamat Email</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-envelope text-muted"></i></span>
                                    <input type="email" id="email" name="email"
                                           class="form-control"
                                           value="<?= e($userData['email'] ?? ''); ?>"
                                           placeholder="contoh@email.com"
                                           maxlength="100">
                                </div>
                                <div class="form-text text-muted">Email untuk referensi akun dan pemberitahuan.</div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                            <a href="<?= url('index.php'); ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i> Batal
                            </a>
                            <button type="submit" class="btn btn-success px-4">
                                <i class="bi bi-check-circle me-1"></i> Simpan Perubahan Profil
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── Panel 2: Ubah Kata Sandi ── -->
                <div id="panel-password" style="<?= $activeTab !== 'password' ? 'display:none;' : ''; ?>">
                    <form method="POST" action="" id="form-password">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="password">

                        <div class="mb-4">
                            <h6 class="fw-bold text-dark mb-1">
                                <i class="bi bi-shield-lock text-warning me-2"></i>Ubah Kata Sandi Akun
                            </h6>
                            <p class="text-muted small mb-0">Pastikan menggunakan kombinasi kata sandi yang aman dan mudah diingat.</p>
                        </div>

                        <div class="alert alert-info rounded-3 py-2 px-3 mb-4 small border-0 bg-info-subtle text-info-emphasis">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>Petunjuk:</strong> Masukkan kata sandi saat ini untuk verifikasi keamanan, lalu tentukan kata sandi baru (minimal 6 karakter).
                        </div>

                        <div class="d-flex flex-column gap-3">
                            <div>
                                <label for="current_password" class="form-label fw-semibold">
                                    Kata Sandi Saat Ini <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-lock text-muted"></i></span>
                                    <input type="password" id="current_password" name="current_password"
                                           class="form-control"
                                           placeholder="Masukkan kata sandi saat ini"
                                           autocomplete="current-password" required>
                                    <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="current_password" tabindex="-1" title="Lihat/Sembunyikan">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label for="new_password" class="form-label fw-semibold">
                                    Kata Sandi Baru <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-key text-muted"></i></span>
                                    <input type="password" id="new_password" name="new_password"
                                           class="form-control"
                                           placeholder="Kata sandi baru (min. 6 karakter)"
                                           autocomplete="new-password"
                                           minlength="6" required>
                                    <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="new_password" tabindex="-1" title="Lihat/Sembunyikan">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <!-- Indikator Kekuatan Kata Sandi -->
                                <div class="mt-2">
                                    <div class="progress" style="height: 5px; border-radius: 3px;">
                                        <div id="pw-strength-bar" class="progress-bar" style="width: 0%; transition: width 0.3s, background-color 0.3s;"></div>
                                    </div>
                                    <small id="pw-strength-text" class="text-muted mt-1 d-block"></small>
                                </div>
                            </div>

                            <div>
                                <label for="confirm_password" class="form-label fw-semibold">
                                    Konfirmasi Kata Sandi Baru <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-key-fill text-muted"></i></span>
                                    <input type="password" id="confirm_password" name="confirm_password"
                                           class="form-control"
                                           placeholder="Ulangi kata sandi baru"
                                           autocomplete="new-password" required>
                                    <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="confirm_password" tabindex="-1" title="Lihat/Sembunyikan">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div id="pw-match-msg" class="form-text mt-1"></div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                            <a href="<?= url('index.php'); ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i> Batal
                            </a>
                            <button type="submit" class="btn btn-warning px-4 text-dark fw-semibold">
                                <i class="bi bi-shield-check me-1"></i> Ubah Kata Sandi
                            </button>
                        </div>
                    </form>
                </div>

            </div><!-- /.card-body -->
        </div>
    </div>
</div>

<script>
// Tab Switching dengan pembaruan URL
function switchTab(tab) {
    const panelProfil = document.getElementById('panel-profil');
    const panelPassword = document.getElementById('panel-password');
    const tabProfil = document.getElementById('tab-profil');
    const tabPassword = document.getElementById('tab-password');

    if (tab === 'profil') {
        panelProfil.style.display = '';
        panelPassword.style.display = 'none';
        tabProfil.classList.add('active');
        tabPassword.classList.remove('active');
    } else {
        panelProfil.style.display = 'none';
        panelPassword.style.display = '';
        tabPassword.classList.add('active');
        tabProfil.classList.remove('active');
    }

    // Update URL tanpa reload halaman
    try {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.replaceState(null, '', url.toString());
    } catch (e) {
        // Fallback jika URL parsing gagal
    }
}

// Toggle Tampilkan/Sembunyikan Password
document.querySelectorAll('.toggle-pw').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const targetId = btn.getAttribute('data-target');
        const input = document.getElementById(targetId);
        const icon = btn.querySelector('i');
        if (!input) return;

        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'bi bi-eye';
        }
    });
});

// Indikator Kekuatan Kata Sandi
const pwInput = document.getElementById('new_password');
const pwBar   = document.getElementById('pw-strength-bar');
const pwText  = document.getElementById('pw-strength-text');

if (pwInput && pwBar && pwText) {
    pwInput.addEventListener('input', function() {
        const val = pwInput.value;
        let score = 0;
        if (val.length >= 6) score++;
        if (val.length >= 8) score++;
        if (/[A-Za-z]/.test(val) && /[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const levels = [
            { pct: 0,   color: '#e5e7eb', label: '' },
            { pct: 25,  color: '#ef4444', label: '⚠️ Lemah' },
            { pct: 50,  color: '#f97316', label: '⚡ Sedang' },
            { pct: 75,  color: '#eab308', label: '✅ Cukup Kuat' },
            { pct: 100, color: '#22c55e', label: '🔒 Sangat Kuat' },
        ];
        const lvl = val.length === 0 ? levels[0] : levels[Math.min(score, 4)];
        pwBar.style.width = lvl.pct + '%';
        pwBar.style.backgroundColor = lvl.color;
        pwText.textContent = lvl.label;
        pwText.style.color = lvl.color;
    });
}

// Real-time Password Match Check
const confirmPw = document.getElementById('confirm_password');
const matchMsg  = document.getElementById('pw-match-msg');

if (confirmPw && pwInput && matchMsg) {
    function checkMatch() {
        if (confirmPw.value.length === 0) {
            matchMsg.textContent = '';
            confirmPw.classList.remove('is-valid', 'is-invalid');
            return;
        }
        if (confirmPw.value === pwInput.value) {
            matchMsg.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Kata sandi cocok.</span>';
            confirmPw.classList.add('is-valid');
            confirmPw.classList.remove('is-invalid');
        } else {
            matchMsg.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Kata sandi tidak cocok.</span>';
            confirmPw.classList.add('is-invalid');
            confirmPw.classList.remove('is-valid');
        }
    }
    confirmPw.addEventListener('input', checkMatch);
    pwInput.addEventListener('input', checkMatch);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
