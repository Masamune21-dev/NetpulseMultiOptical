<?php
require_once 'includes/auth.php';

// Redirect if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$auth = new Auth();
$error = '';
$success = '';

// CSRF protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid security token. Please try again.';
    } else {
        // Validate input
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password';
        } else {
            $result = $auth->login($username, $password);

            if ($result['success']) {
                // Regenerate CSRF token after successful login
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                // Check if there's a redirect URL
                $redirect = $_SESSION['redirect_url'] ?? 'dashboard.php';
                unset($_SESSION['redirect_url']);

                header('Location: ' . $redirect);
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - NetPulse MultiOptical</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, sans-serif;
            display: grid;
            grid-template-columns: 1fr 1fr;
            background-color: #f8fafc;
        }

        /* ================= LEFT PANEL ================= */
        .login-left {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 0 4rem;
            position: relative;
            overflow: hidden;
        }

        .login-left::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -20%;
            width: 150%;
            height: 150%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
            background-size: 30px 30px;
            opacity: 0.1;
            z-index: 1;
        }

        .brand-container {
            position: relative;
            z-index: 2;
            margin-bottom: 2.5rem;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1.5rem;
        }

        .logo-icon {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, #8b5cf6, #6366f1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
        }

        .logo-text {
            font-size: 24px;
            font-weight: 700;
        }

        .tagline {
            opacity: 0.9;
        }

        .login-left h1 {
            font-size: 2.6rem;
            letter-spacing: -0.5px;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 1.5rem;
            background: linear-gradient(to right, #fff, rgba(255, 255, 255, 0.8));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .login-left p {
            font-size: 1.1rem;
            line-height: 1.6;
            opacity: 0.9;
            max-width: 500px;
        }

        .features {
            margin-top: 2.2rem;
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
        }

        .feature-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ================= RIGHT PANEL ================= */
        .login-right {
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 0 4rem;
            box-shadow: -20px 0 60px rgba(0, 0, 0, 0.05);
            position: relative;
            z-index: 3;
        }

        .login-container {
            max-width: 380px;
            width: 100%;
            margin: 0 auto;
        }

        .login-header {
            margin-bottom: 2.5rem;
        }

        .login-header h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
            letter-spacing: -0.3px;
        }

        .login-header p {
            color: #6b7280;
            font-size: 0.9rem;
        }

        /* ================= ALERT ================= */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 12px;
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }

        .alert-success {
            background: #f0fdf4;
            color: #16a34a;
            border-left: 4px solid #16a34a;
        }

        .alert-icon {
            font-size: 1.1rem;
        }

        /* ================= FORM ================= */
        .form-group {
            margin-bottom: 1.8rem;
            position: relative;
        }

        .form-label {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .input-group {
            position: relative;
        }

        .input-group:focus-within .input-icon {
            color: #6366f1;
        }

        /* PERBAIKAN UTAMA: Placeholder styling */
        .form-control {
            width: 100%;
            padding: 0.9rem 1rem 0.9rem 3.4rem;
            border-radius: 10px;
            border: 1.5px solid #e5e7eb;
            font-size: 1rem;
            transition: all 0.2s ease;
            background: linear-gradient(#f9fafb, #f3f4f6);
            color: #1f2937;
        }

        /* Placeholder untuk semua browser */
        .form-control::placeholder {
            color: #9ca3af;
            opacity: 1;
            font-size: 0.95rem;
        }

        .form-control::-webkit-input-placeholder {
            color: #9ca3af;
            opacity: 1;
            font-size: 0.95rem;
        }

        .form-control::-moz-placeholder {
            color: #9ca3af;
            opacity: 1;
            font-size: 0.95rem;
        }

        .form-control:-ms-input-placeholder {
            color: #9ca3af;
            opacity: 1;
            font-size: 0.95rem;
        }

        .form-control:-moz-placeholder {
            color: #9ca3af;
            opacity: 1;
            font-size: 0.95rem;
        }

        .form-control:focus {
            outline: none;
            border-color: #6366f1;
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .form-control:focus::placeholder {
            color: #d1d5db;
        }

        /* PERBAIKAN: Input icon positioning */
        .input-icon {
            position: absolute;
            left: 1.1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
            pointer-events: none;
        }

        /* Password field styling */
        .password-field {
            padding-right: 3.4rem !important;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 4px;
            transition: color 0.2s;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
        }

        .password-toggle:hover {
            color: #4b5563;
            background: rgba(0, 0, 0, 0.05);
        }

        .password-toggle i {
            font-size: 16px;
        }

        /* Hint text di bawah input */
        .form-hint {
            display: block;
            margin-top: 0.4rem;
            font-size: 0.8rem;
            color: #6b7280;
            font-style: italic;
        }

        /* ================= BUTTON ================= */
        .btn-login {
            width: 100%;
            padding: 1rem;
            border-radius: 10px;
            border: none;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 0.5rem;
            letter-spacing: 0.3px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(99, 102, 241, 0.45);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-icon {
            font-size: 1.2rem;
        }

        /* ================= FOOTER ================= */
        .login-footer {
            margin-top: 2.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #6b7280;
            font-size: 0.85rem;
            opacity: 0.85;
        }

        .login-footer a {
            color: #6366f1;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .login-footer a:hover {
            color: #4f46e5;
            text-decoration: underline;
        }

        /* ================= RESPONSIVE ================= */
        @media (max-width: 1024px) {
            body {
                grid-template-columns: 1fr;
            }

            .login-left {
                padding: 3rem 2rem;
                min-height: 300px;
            }

            .login-right {
                padding: 3rem 2rem;
                box-shadow: none;
            }

            .login-left h1 {
                font-size: 2.2rem;
            }
        }

        @media (max-width: 640px) {

            .login-left,
            .login-right {
                padding: 2rem 1.5rem;
            }

            .login-left h1 {
                font-size: 1.8rem;
            }

            .logo-text {
                font-size: 1.2rem;
            }

            .form-control {
                padding: 0.8rem 1rem 0.8rem 2.8rem;
            }

            .input-icon {
                left: 0.9rem;
            }

            .password-toggle {
                right: 0.9rem;
            }
        }

        /* ================= ANIMATIONS ================= */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-container {
            animation: fadeIn 0.6s ease-out;
        }

        /* ================= DECORATIVE ELEMENTS ================= */
        .floating-shape {
            position: absolute;
            border-radius: 50%;
            opacity: 0.1;
            z-index: 1;
        }

        .shape-1 {
            width: 200px;
            height: 200px;
            background: linear-gradient(135deg, #fbbf24, #f97316);
            top: 10%;
            right: -50px;
        }

        .shape-2 {
            width: 150px;
            height: 150px;
            background: linear-gradient(135deg, #10b981, #3b82f6);
            bottom: 15%;
            left: -30px;
        }

        /* ================= CUSTOM SCROLLBAR ================= */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        @media (max-width: 640px) {
    body {
        grid-template-columns: 1fr;
    }

    /* sembunyikan panel kiri */
    .login-left {
        display: none;
    }

    /* panel kanan full screen */
    .login-right {
        display: flex;
        min-height: 100vh;
        padding: 2.5rem 1.5rem;
        box-shadow: none;
    }

    /* login card lebih nyaman di HP */
    .login-container {
        max-width: 100%;
        padding: 0;
    }

    .login-header {
        text-align: center;
    }

    .btn-login {
        padding: 1rem;
    }
}

    </style>
</head>

<body>
    <!-- LEFT PANEL -->
    <div class="login-left">
        <div class="floating-shape shape-1"></div>
        <div class="floating-shape shape-2"></div>

        <div class="brand-container">
            <div class="logo">
                <div class="logo-icon">
                    N
                </div>
                <div class="logo-text">
                    <span style="color: white;">NetPulse</span>
                    <span style="color: rgba(255,255,255,0.8);">MultiOptical</span>
                </div>
            </div>

            <h1>Network Optical Monitoring</h1>
            <p class="tagline">
                Unified monitoring for Mikrotik CRS and Hisoso OLT
                real-time visibility from core to last-mile.
            </p>
        </div>

        <div class="features">
            <div class="feature">
                <div class="feature-icon"><i class="fas fa-check-circle"></i></div>
                <span>CRS SFP optical power monitoring</span>
            </div>
            <div class="feature">
                <div class="feature-icon"><i class="fas fa-check-circle"></i></div>
                <span>OLT PON interface & ONU status</span>
            </div>
            <div class="feature">
                <div class="feature-icon"><i class="fas fa-check-circle"></i></div>
                <span>Traffic & performance analytics</span>
            </div>
            <div class="feature">
                <div class="feature-icon"><i class="fas fa-check-circle"></i></div>
                <span>Proactive alerts & degradation</span>
            </div>
        </div>

    </div>

    <!-- RIGHT PANEL -->
    <div class="login-right">
        <div class="login-container">
            <div class="login-header">
                <h2>Welcome Back</h2>
                <p>Sign in to access your dashboard</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <span class="alert-icon"><i class="fas fa-exclamation-circle"></i></span>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <span class="alert-icon"><i class="fas fa-check-circle"></i></span>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-user"></i></span>
                        <input type="text" id="username" name="username" class="form-control"
                            placeholder="Your username" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" id="password" name="password" class="form-control password-field"
                            placeholder="Your password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword()"
                            aria-label="Show password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login" id="loginButton">
                    <span id="buttonText">Sign In</span>
                    <span class="btn-icon" id="buttonIcon"><i class="fas fa-arrow-right"></i></span>
                </button>
            </form>

            <div class="login-footer">
                <p>Need help? <a href="mailto:support@netpulse.com">Contact support</a></p>
                <p>© <?= date('Y') ?> NetPulse MultiOptical. All rights reserved.</p>
            </div>
        </div>
    </div>

    <script>
        // Password toggle function
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.password-toggle i');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'fas fa-eye-slash';
                passwordInput.setAttribute('data-visible', 'true');
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'fas fa-eye';
                passwordInput.removeAttribute('data-visible');
            }

            // Return focus to password field
            passwordInput.focus();
        }

        // Form submission handler
        document.getElementById('loginForm').addEventListener('submit', function (e) {
            const button = document.getElementById('loginButton');
            const buttonText = document.getElementById('buttonText');
            const buttonIcon = document.getElementById('buttonIcon').querySelector('i');

            // Disable button and show loading
            button.disabled = true;
            buttonText.textContent = 'Signing in...';
            buttonIcon.className = 'fas fa-spinner fa-spin';

            // Add loading class to form
            this.classList.add('form-loading');
        });

        // Auto-focus username field on page load
        document.addEventListener('DOMContentLoaded', function () {
            const usernameField = document.getElementById('username');
            if (usernameField) {
                setTimeout(() => {
                    usernameField.focus();
                }, 300);
            }

            // Add keyboard shortcut hints
            document.addEventListener('keydown', function (e) {
                // Ctrl + / focuses username
                if (e.ctrlKey && e.key === '/') {
                    e.preventDefault();
                    document.getElementById('username').focus();
                }
                // Ctrl + . focuses password
                if (e.ctrlKey && e.key === '.') {
                    e.preventDefault();
                    document.getElementById('password').focus();
                }
            });
        });

        // Press Enter to submit (but not in textarea)
        document.addEventListener('keypress', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                const focused = document.activeElement;
                if (focused.tagName !== 'TEXTAREA' && focused.type !== 'button') {
                    e.preventDefault();
                    if (!document.getElementById('loginButton').disabled) {
                        document.getElementById('loginForm').submit();
                    }
                }
            }
        });

        // Clear error message when user starts typing
        document.getElementById('username').addEventListener('input', function () {
            const errorAlert = document.querySelector('.alert-error');
            if (errorAlert) {
                errorAlert.style.opacity = '0';
                setTimeout(() => errorAlert.remove(), 300);
            }
        });

        document.getElementById('password').addEventListener('input', function () {
            const errorAlert = document.querySelector('.alert-error');
            if (errorAlert) {
                errorAlert.style.opacity = '0';
                setTimeout(() => errorAlert.remove(), 300);
            }
        });

        // Show password on Ctrl+Shift+P
        document.addEventListener('keydown', function (e) {
            if (e.ctrlKey && e.shiftKey && e.key === 'P') {
                e.preventDefault();
                togglePassword();
            }
        });
    </script>
</body>

</html>