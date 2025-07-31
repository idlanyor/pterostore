<?php
session_start();
require_once 'config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Semua field harus diisi';
    } elseif (strlen($username) < 3) {
        $error = 'Username minimal 3 karakter';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter';
    } elseif ($password !== $confirm_password) {
        $error = 'Konfirmasi password tidak cocok';
    } else {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            $error = 'Username atau email sudah terdaftar';
        } else {
            // Create new user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'user')");
            
            if ($stmt->execute([$username, $email, $hashedPassword])) {
                $success = 'Registrasi berhasil! Silakan login.';
                // Redirect to login after 2 seconds
                header("refresh:2;url=login.php");
            } else {
                $error = 'Terjadi kesalahan saat mendaftar';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Antidonasi Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="card-header">
                <h3 class="mb-0">
                    <i class="fas fa-user-plus me-2"></i>Register
                </h3>
                <p class="mb-0">Daftar akun baru</p>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="username" class="form-label">
                            <i class="fas fa-user me-2"></i>Username
                        </label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                               required minlength="3">
                        <div class="invalid-feedback">
                            Username minimal 3 karakter
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope me-2"></i>Email
                        </label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                               required>
                        <div class="invalid-feedback">
                            Format email tidak valid
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock me-2"></i>Password
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" 
                                   required minlength="6">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">
                            Password minimal 6 karakter
                        </div>
                        <div class="mt-2">
                            <span class="badge" id="password-strength">Kekuatan Password</span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">
                            <i class="fas fa-lock me-2"></i>Konfirmasi Password
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   required minlength="6">
                            <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">
                            Konfirmasi password harus diisi
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                        <label class="form-check-label" for="terms">
                            Saya setuju dengan <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Syarat dan Ketentuan</a>
                        </label>
                        <div class="invalid-feedback">
                            Anda harus menyetujui syarat dan ketentuan
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-user-plus me-2"></i>Daftar
                        </button>
                    </div>
                </form>
                
                <hr class="my-4">
                
                <div class="text-center">
                    <p class="mb-2">Sudah punya akun?</p>
                    <a href="login.php" class="btn btn-outline-primary">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </a>
                </div>
                
                <div class="text-center mt-3">
                    <a href="index.php" class="text-muted">
                        <i class="fas fa-arrow-left me-1"></i>Kembali ke Beranda
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms and Conditions Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Syarat dan Ketentuan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Penggunaan Layanan</h6>
                    <p>Dengan mendaftar dan menggunakan layanan kami, Anda setuju untuk:</p>
                    <ul>
                        <li>Menggunakan server sesuai dengan kebijakan yang berlaku</li>
                        <li>Tidak melakukan aktivitas ilegal atau melanggar hukum</li>
                        <li>Tidak menyalahgunakan sumber daya server</li>
                        <li>Membayar biaya layanan tepat waktu</li>
                    </ul>
                    
                    <h6>2. Pembayaran</h6>
                    <p>Pembayaran dilakukan melalui QRIS dan akan diproses setelah konfirmasi admin.</p>
                    
                    <h6>3. Garansi</h6>
                    <p>Kami memberikan garansi 30 hari untuk semua layanan server.</p>
                    
                    <h6>4. Privasi</h6>
                    <p>Data pribadi Anda akan dijaga kerahasiaannya dan tidak akan dibagikan kepada pihak ketiga.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
        // Toggle password visibility
        $('#togglePassword').on('click', function() {
            const password = $('#password');
            const icon = $(this).find('i');
            
            if (password.attr('type') === 'password') {
                password.attr('type', 'text');
                icon.removeClass('fa-eye').addClass('fa-eye-slash');
            } else {
                password.attr('type', 'password');
                icon.removeClass('fa-eye-slash').addClass('fa-eye');
            }
        });
        
        $('#toggleConfirmPassword').on('click', function() {
            const password = $('#confirm_password');
            const icon = $(this).find('i');
            
            if (password.attr('type') === 'password') {
                password.attr('type', 'text');
                icon.removeClass('fa-eye').addClass('fa-eye-slash');
            } else {
                password.attr('type', 'password');
                icon.removeClass('fa-eye-slash').addClass('fa-eye');
            }
        });
        
        // Password confirmation check
        $('#confirm_password').on('input', function() {
            const password = $('#password').val();
            const confirmPassword = $(this).val();
            
            if (confirmPassword && password !== confirmPassword) {
                $(this).addClass('is-invalid');
            } else {
                $(this).removeClass('is-invalid');
            }
        });
    </script>
</body>
</html> 