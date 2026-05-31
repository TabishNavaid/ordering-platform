<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    header('Location: /');
    exit;
}

$error = '';
$message = '';
$step = 1; // 1: Login/Register form, 2: OTP verification for registration

// Rate limiting
$max_attempts = 5;
$lockout_time = 15 * 60; // 15 minutes

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_last_attempt'] = time();
}

if ($_SESSION['login_attempts'] >= $max_attempts && (time() - $_SESSION['login_last_attempt']) < $lockout_time) {
    $error = 'Too many attempts. Please try again in 15 minutes.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $action = $_POST['action'] ?? 'login';
        
        if ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $stmt = $pdo->prepare("SELECT id, name, password_hash, role FROM users WHERE email = ? AND role = 'customer'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                rotate_csrf_token(); // invalidate old token on auth state change
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['login_attempts'] = 0;
                header('Location: /');
                exit;
            } else {
                $_SESSION['login_attempts']++;
                $_SESSION['login_last_attempt'] = time();
                $error = 'Invalid email or password.';
            }
        }
    } elseif ($action === 'register') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $phone_input = trim($_POST['phone'] ?? '');
        
        $valid_phone = validate_indian_phone($phone_input);
        
        if (empty($name) || empty($email) || empty($password) || !$valid_phone) {
            $error = 'Please fill in all fields and provide a valid Indian phone number.';
        } else {
            // Check if email or phone already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
            $stmt->execute([$email, $valid_phone]);
            if ($stmt->fetch()) {
                $error = 'An account with this email or phone number already exists.';
            } else {
                // Generate OTP, hash it before storing (plaintext OTP only lives in the email)
                $otp = generate_otp();
                $otp_hash = password_hash($otp, PASSWORD_DEFAULT);
                $expires = time() + 300; // 5 minutes
                
                $_SESSION['reg_data'] = [
                    'name'          => $name,
                    'email'         => $email,
                    'phone'         => $valid_phone,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'otp_hash'      => $otp_hash, // stored as hash, never plaintext
                    'expires'       => $expires
                ];
                
                // Send OTP via Resend API (works on Railway — no sendmail needed)
                $subject = "Your Verification Code — The Local Provisions";
                $body    = "Hello $name,\n\nYour verification code is: $otp\n\nThis code expires in 5 minutes.\n\nIf you did not request this, please ignore this email.";
                send_email_via_resend($email, $subject, $body);
                
                $is_local = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', 'localhost:8000']);
                // $message contains intentional HTML; OTP value is escaped via h()
                $message = "We have sent an OTP to your email address.";
                if ($is_local) {
                    $message .= " (For local testing, the mock OTP is: <strong>" . h($otp) . "</strong>)";
                }
                
                $step = 2;
            }
        }
    } elseif ($action === 'verify_otp') {
        $otp_input = trim($_POST['otp'] ?? '');
        $reg_data = $_SESSION['reg_data'] ?? null;
        
        if (!$reg_data || time() > $reg_data['expires']) {
            $error = 'Registration session expired. Please try again.';
            unset($_SESSION['reg_data']);
            $step = 1;
        } elseif (!password_verify($otp_input, $reg_data['otp_hash'])) {
            // password_verify() is constant-time — safe against timing attacks
            $_SESSION['login_attempts']++;
            $_SESSION['login_last_attempt'] = time();
            $error = 'Invalid OTP.';
            $is_local = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', 'localhost:8000']);
            $message = "We have sent an OTP to your email address.";
            // Note: on failure we do NOT re-show the OTP hint, to avoid brute-force assistance
            if ($is_local) {
                $message .= " (Check the server log or re-register to get a new code.)";
            }
            $step = 2;
        } else {
            // OTP verified — insert user
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'customer')");
            if ($stmt->execute([$reg_data['name'], $reg_data['email'], $reg_data['phone'], $reg_data['password_hash']])) {
                session_regenerate_id(true);
                rotate_csrf_token(); // invalidate old token on auth state change
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['role'] = 'customer';
                $_SESSION['name'] = $reg_data['name'];
                $_SESSION['login_attempts'] = 0;
                unset($_SESSION['reg_data']);

                header('Location: /');
                exit;
            } else {
                $error = 'Error creating account. Please try again.';
                $step = 1;
            }
        }
    }
}
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-card mt-2 mb-2">
    <h2 id="form-title"><?= $step === 2 ? 'Verify Email' : 'Login' ?></h2>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?= $message /* intentional HTML; safe — see composition above */ ?></div>
    <?php endif; ?>
    
    <?php if ($step === 1): ?>
        <form method="POST" action="/login.php">
            <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
            <input type="hidden" name="action" id="auth-action" value="login">
            
            <div id="register-fields" style="display: none;">
                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number (+91)</label>
                    <input type="text" id="phone" name="phone" class="form-control" placeholder="e.g. 9876543210">
                </div>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn" style="width: 100%" id="submit-btn">Login</button>
            
            <p class="mt-1" style="text-align: center; font-size: 0.9rem;">
                <a href="#" id="toggle-auth">Need an account? Register</a>
            </p>
        </form>
    <?php else: ?>
        <form method="POST" action="/login.php">
            <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
            <input type="hidden" name="action" value="verify_otp">
            
            <div class="form-group">
                <label for="otp">Enter 6-digit OTP sent to <?= h($_SESSION['reg_data']['email'] ?? '') ?></label>
                <input type="text" id="otp" name="otp" class="form-control" required maxlength="6" pattern="\d{6}">
            </div>
            
            <button type="submit" class="btn" style="width: 100%">Verify & Register</button>
            <p class="mt-1" style="text-align: center; font-size: 0.9rem;">
                <a href="/login.php">Cancel registration</a>
            </p>
        </form>
    <?php endif; ?>
</div>

<script>
<?php if ($step === 1): ?>
document.getElementById('toggle-auth').addEventListener('click', function(e) {
    e.preventDefault();
    const actionInput = document.getElementById('auth-action');
    const registerFields = document.getElementById('register-fields');
    const formTitle = document.getElementById('form-title');
    const submitBtn = document.getElementById('submit-btn');
    
    const nameInput = document.getElementById('name');
    const phoneInput = document.getElementById('phone');
    
    if (actionInput.value === 'login') {
        actionInput.value = 'register';
        registerFields.style.display = 'block';
        formTitle.textContent = 'Register';
        submitBtn.textContent = 'Register';
        this.textContent = 'Already have an account? Login';
        
        nameInput.required = true;
        phoneInput.required = true;
    } else {
        actionInput.value = 'login';
        registerFields.style.display = 'none';
        formTitle.textContent = 'Login';
        submitBtn.textContent = 'Login';
        this.textContent = 'Need an account? Register';
        
        nameInput.required = false;
        phoneInput.required = false;
    }
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
