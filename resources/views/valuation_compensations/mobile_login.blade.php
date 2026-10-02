<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <meta name="theme-color" content="#0f172a">
    <title>VFC Mobile - Authentication</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0ea5e9;
            --primary-dark: #0284c7;
            --bg-dark: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.7);
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        
        body { 
            font-family: 'Outfit', sans-serif; 
            background-color: var(--bg-dark);
            background-image: 
                radial-gradient(circle at 0% 0%, rgba(14, 165, 233, 0.15) 0%, transparent 35%),
                radial-gradient(circle at 100% 100%, rgba(14, 165, 233, 0.1) 0%, transparent 35%);
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 24px; 
            overflow: hidden;
            color: var(--text-main);
        }

        .animated-bg {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.5;
            animation: move 20s infinite alternate ease-in-out;
        }

        .orb-1 { width: 300px; height: 300px; background: var(--primary); top: -150px; right: -50px; }
        .orb-2 { width: 250px; height: 250px; background: #3b82f6; bottom: -100px; left: -50px; animation-delay: -5s; }

        @keyframes move {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(50px, 100px) scale(1.2); }
        }

        .login-container {
            width: 100%;
            max-width: 400px;
            animation: fadeIn 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 32px;
            padding: 48px 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .brand-section {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-box {
            width: 72px;
            height: 72px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            color: var(--primary);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
        }

        .logo-box img {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }

        .brand-name {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: linear-gradient(to right, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .brand-tagline {
            font-size: 14px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .form-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 8px;
            text-align: center;
        }

        .form-subtitle {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 32px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 10px;
            padding-left: 4px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i:not(.toggle-password i) {
            position: absolute;
            left: 18px;
            top: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-size: 18px;
            transition: color 0.3s;
            pointer-events: none;
        }

        .input-field {
            width: 100%;
            background: rgba(15, 23, 42, 0.6);
            border: 1.5px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 16px 16px 16px 52px;
            color: white;
            font-family: inherit;
            font-size: 16px;
            outline: none;
            transition: all 0.3s;
        }

        .input-field:focus {
            border-color: var(--primary);
            background: rgba(15, 23, 42, 0.8);
            box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.1);
        }

        .input-wrapper:focus-within i {
            color: var(--primary) !important;
        }

        .toggle-password {
            position: absolute;
            right: 16px;
            top: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            cursor: pointer;
            padding: 0 8px;
            font-size: 18px;
            z-index: 10;
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #f87171;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 14px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-submit {
            width: 100%;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 16px;
            padding: 16px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            box-shadow: 0 10px 15px -3px rgba(14, 165, 233, 0.3);
            margin-top: 8px;
        }

        .btn-submit:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(14, 165, 233, 0.4);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .footer-text {
            text-align: center;
            margin-top: 40px;
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .version-badge {
            display: inline-block;
            padding: 4px 10px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            margin-top: 12px;
            font-weight: 600;
            color: var(--text-muted);
        }

        /* Loading state */
        .btn-submit.loading {
            opacity: 0.8;
            pointer-events: none;
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
            display: none;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .btn-submit.loading .spinner {
            display: block;
        }
        .btn-submit.loading .btn-text {
            display: none;
        }
    </style>
</head>
<body>
    <div class="animated-bg">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
    </div>

    <div class="login-container">
        <div class="login-card">
            <div class="brand-section">
                <div class="logo-box">
                    <img src="http://app.klaes.ng/storage/upload/logo/Klase.png" alt="KLAES" onerror="this.innerHTML='<i class=\'fas fa-shield-halved\'></i>'; this.style.display='none';">
                    <i class="fas fa-file-invoice-dollar" id="fallback-icon" style="display:none"></i>
                </div>
                <h1 class="brand-name">VFC MOBILE</h1>
                <p class="brand-tagline">Valuation for Compensation</p>
            </div>

            @if ($errors->any())
                <div class="error-message">
                    <i class="fas fa-circle-exclamation"></i>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('valuation-compensations.mobile.login.submit') }}" id="login-form">
                @csrf
                <div class="form-group">
                    <label class="label">Username or Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user-tie"></i>
                        <input type="text" name="identifier" class="input-field" placeholder="e.g. m.aliyu" value="{{ old('identifier') }}" required autocomplete="username">
                    </div>
                </div>

                <div class="form-group">
                    <label class="label">Access Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-key"></i>
                        <input type="password" name="password" id="password" class="input-field" placeholder="••••••••" required autocomplete="current-password">
                        <span class="toggle-password" onclick="togglePassword()">
                            <i class="fas fa-eye-slash" id="eye-icon"></i>
                        </span>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="submit-btn">
                    <div class="spinner"></div>
                    <span class="btn-text">Authenticate Access</span>
                    <i class="fas fa-chevron-right btn-text"></i>
                </button>
            </form>

            <div class="footer-text">
                <p>© {{ date('Y') }} KLAES - Kano State LAnd ADmin Enterprise System</p>
                <div class="version-badge">VFC-M v1.0</div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            } else {
                passwordField.type = 'password';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            }
        }

        document.getElementById('login-form').addEventListener('submit', function() {
            document.getElementById('submit-btn').classList.add('loading');
        });

        // Check if logo failed to load and show fallback
        const logoImg = document.querySelector('.logo-box img');
        if (logoImg) {
            logoImg.onerror = function() {
                this.style.display = 'none';
                document.getElementById('fallback-icon').style.display = 'block';
            };
        }
    </script>
</body>
</html>
