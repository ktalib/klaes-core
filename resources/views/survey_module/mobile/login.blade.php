<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#1e1b2e">
    <title>Survey Mobile — Sign in</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #db2777;
            --primary-dark: #be185d;
            --bg-dark: #1e1b2e;
            --card-bg: rgba(39, 34, 58, 0.72);
            --text-main: #f8fafc;
            --text-muted: #a5a1b8;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-dark);
            background-image:
                radial-gradient(circle at 0% 0%, rgba(219, 39, 119, 0.18) 0%, transparent 38%),
                radial-gradient(circle at 100% 100%, rgba(124, 58, 237, 0.14) 0%, transparent 38%);
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: max(20px, env(safe-area-inset-top)) 16px max(20px, env(safe-area-inset-bottom));
            color: var(--text-main);
            overflow-x: hidden;
        }

        .animated-bg { position: fixed; inset: 0; z-index: -1; overflow: hidden; }
        .orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: .45; animation: move 20s infinite alternate ease-in-out; }
        .orb-1 { width: 300px; height: 300px; background: var(--primary); top: -150px; right: -50px; }
        .orb-2 { width: 260px; height: 260px; background: #7c3aed; bottom: -110px; left: -60px; animation-delay: -5s; }
        @keyframes move { to { transform: translate(50px, 100px) scale(1.2); } }
        @media (prefers-reduced-motion: reduce) { .orb, .login-container { animation: none !important; } }

        .login-container { width: 100%; max-width: 420px; animation: fadeIn .8s cubic-bezier(.16, 1, .3, 1); }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: none; } }

        .login-card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 28px;
            padding: clamp(28px, 7vw, 48px) clamp(20px, 6vw, 36px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, .5);
        }

        .brand-section { text-align: center; margin-bottom: 32px; }
        .logo-box {
            width: 72px; height: 72px; margin: 0 auto 18px;
            background: rgba(255, 255, 255, .06); border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 20px; display: flex; align-items: center; justify-content: center;
            font-size: 30px; color: var(--primary);
        }
        .logo-box img { width: 50px; height: 50px; object-fit: contain; }
        .brand-name {
            font-size: 24px; font-weight: 800; letter-spacing: -.5px;
            background: linear-gradient(to right, #fff, #f9a8d4);
            -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
        }
        .brand-tagline { font-size: 14px; color: var(--text-muted); margin-top: 4px; }

        .form-group { margin-bottom: 20px; }
        .label { display: block; font-size: 14px; font-weight: 500; color: var(--text-muted); margin-bottom: 8px; padding-left: 4px; }
        .input-wrapper { position: relative; }
        .input-wrapper > .lead-icon {
            position: absolute; left: 18px; top: 0; bottom: 0; display: flex; align-items: center;
            color: var(--text-muted); font-size: 17px; pointer-events: none; transition: color .3s;
        }
        .input-field {
            width: 100%; background: rgba(15, 12, 28, .55); border: 1.5px solid rgba(255, 255, 255, .1);
            border-radius: 16px; padding: 16px 48px 16px 50px; color: #fff; font-family: inherit;
            font-size: 16px; outline: none; transition: all .3s;
        }
        .input-field:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(219, 39, 119, .15); }
        .input-wrapper:focus-within > .lead-icon { color: var(--primary); }
        .toggle-password {
            position: absolute; right: 8px; top: 0; bottom: 0; display: flex; align-items: center;
            padding: 0 12px; color: var(--text-muted); cursor: pointer; font-size: 17px; background: none; border: 0;
        }

        .remember { display: flex; align-items: center; gap: 10px; font-size: 14px; color: var(--text-muted); margin: -4px 0 20px 4px; cursor: pointer; }
        .remember input { width: 18px; height: 18px; accent-color: var(--primary); }

        .alert {
            padding: 12px 16px; border-radius: 12px; font-size: 14px; margin-bottom: 20px;
            display: flex; align-items: flex-start; gap: 10px; line-height: 1.4;
        }
        .alert i { margin-top: 2px; }
        .alert-error { background: rgba(239, 68, 68, .12); border: 1px solid rgba(239, 68, 68, .25); color: #fca5a5; }
        .alert-ok { background: rgba(16, 185, 129, .12); border: 1px solid rgba(16, 185, 129, .25); color: #6ee7b7; }

        .btn-submit {
            width: 100%; background: var(--primary); color: #fff; border: none; border-radius: 16px;
            padding: 16px; font-size: 16px; font-weight: 700; font-family: inherit; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 12px;
            box-shadow: 0 10px 15px -3px rgba(219, 39, 119, .35); transition: all .3s;
        }
        .btn-submit:hover { background: var(--primary-dark); transform: translateY(-2px); }
        .btn-submit.loading { opacity: .8; pointer-events: none; }
        .spinner { width: 20px; height: 20px; border: 3px solid rgba(255, 255, 255, .3); border-top-color: #fff; border-radius: 50%; animation: spin .8s linear infinite; display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .btn-submit.loading .spinner { display: block; }
        .btn-submit.loading .btn-text { display: none; }

        .footer-text { text-align: center; margin-top: 32px; font-size: 12px; color: var(--text-muted); line-height: 1.6; }
        .version-badge { display: inline-block; padding: 4px 10px; background: rgba(255, 255, 255, .06); border-radius: 20px; margin-top: 10px; font-weight: 600; }
    </style>
</head>
<body>
    <div class="animated-bg"><div class="orb orb-1"></div><div class="orb orb-2"></div></div>

    <div class="login-container">
        <div class="login-card">
            <div class="brand-section">
                <div class="logo-box">
                    <img src="{{ asset('storage/upload/logo/Klase.png') }}" alt="KLAES"
                         onerror="this.style.display='none'; document.getElementById('fallback-icon').style.display='block';">
                    <i class="fas fa-compass" id="fallback-icon" style="display:none"></i>
                </div>
                <h1 class="brand-name">SURVEY MOBILE</h1>
                <p class="brand-tagline">Register Compensation Case</p>
            </div>

            @if ($errors->any())
                <div class="alert alert-error" role="alert">
                    <i class="fas fa-circle-exclamation"></i><span>{{ $errors->first() }}</span>
                </div>
            @elseif (session('error'))
                <div class="alert alert-error" role="alert">
                    <i class="fas fa-circle-exclamation"></i><span>{{ session('error') }}</span>
                </div>
            @elseif (session('success'))
                <div class="alert alert-ok" role="status">
                    <i class="fas fa-circle-check"></i><span>{{ session('success') }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('survey-module.mobile.login.submit') }}" id="login-form">
                @csrf
                <div class="form-group">
                    <label class="label" for="identifier">Username or Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user-tie lead-icon"></i>
                        <input type="text" name="identifier" id="identifier" class="input-field" placeholder="e.g. m.aliyu"
                               value="{{ old('identifier') }}" required autocomplete="username" autocapitalize="none">
                    </div>
                </div>

                <div class="form-group">
                    <label class="label" for="password">Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-key lead-icon"></i>
                        <input type="password" name="password" id="password" class="input-field" placeholder="••••••••"
                               required autocomplete="current-password">
                        <button type="button" class="toggle-password" onclick="togglePassword()" aria-label="Show password">
                            <i class="fas fa-eye-slash" id="eye-icon"></i>
                        </button>
                    </div>
                </div>

                <label class="remember">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember'))> Keep me signed in on this device
                </label>

                <button type="submit" class="btn-submit" id="submit-btn">
                    <div class="spinner"></div>
                    <span class="btn-text">Sign in</span>
                    <i class="fas fa-chevron-right btn-text"></i>
                </button>
            </form>

            <div class="footer-text">
                <p>© {{ date('Y') }} KLAES — Kano State Land Administration Enterprise System</p>
                <div class="version-badge">Survey-M v1.0</div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            var field = document.getElementById('password');
            var icon = document.getElementById('eye-icon');
            var show = field.type === 'password';
            field.type = show ? 'text' : 'password';
            icon.classList.toggle('fa-eye', show);
            icon.classList.toggle('fa-eye-slash', !show);
        }
        document.getElementById('login-form').addEventListener('submit', function () {
            document.getElementById('submit-btn').classList.add('loading');
        });
    </script>
</body>
</html>
