<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
  <meta name="theme-color" content="#0c0e15">
  <title>KLAES File Tracker - Login</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Inter', sans-serif;
      background:
        radial-gradient(1100px 480px at 100% -10%, #15121f, transparent 60%),
        radial-gradient(900px 460px at -10% 0%, #11131d, transparent 55%),
        #0c0e15;
      color: #e7e9f3; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; position: relative; overflow-x: hidden;
    }
    .bg-blob { position: fixed; border-radius: 50%; filter: blur(70px); opacity: 0.25; z-index: 0; animation: float 20s infinite ease-in-out; }
    .blob-1 { width: 300px; height: 300px; background: #6366f1; top: -100px; left: -100px; }
    .blob-2 { width: 400px; height: 400px; background: #8b5cf6; bottom: -150px; right: -150px; animation-delay: 5s; }
    .blob-3 { width: 250px; height: 250px; background: #4338ca; top: 50%; left: 50%; transform: translate(-50%,-50%); animation-delay: 10s; opacity: 0.18; }
    @keyframes float { 0%,100% { transform: translate(0,0) scale(1); } 33% { transform: translate(30px,-30px) scale(1.1); } 66% { transform: translate(-20px,20px) scale(0.9); } }
    .login-container { position: relative; z-index: 10; width: 100%; max-width: 420px; margin: 0 auto; }
    .login-card { background: rgba(23,26,36,0.92); backdrop-filter: blur(20px); border-radius: 48px; padding: 40px 32px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.6), 0 0 0 1px #262b3a; }
    .logo-section { text-align: center; margin-bottom: 32px; }
    .logo-icon { width: 80px; height: 80px; border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; box-shadow: 0 10px 24px -5px rgba(99,102,241,0.4); overflow: hidden; background: white; }
    .logo-icon img { width: 100%; height: 100%; object-fit: contain; }
    .logo-text { font-size: 28px; font-weight: 800; background: linear-gradient(135deg, #818cf8, #a78bfa); -webkit-background-clip: text; background-clip: text; color: transparent; letter-spacing: 1px; }
    .logo-subtitle { font-size: 12px; color: #9aa0b6; margin-top: 6px; }
    .welcome-text { text-align: center; margin-bottom: 28px; }
    .welcome-text h2 { font-size: 24px; font-weight: 700; color: #e7e9f3; margin-bottom: 6px; }
    .welcome-text p { font-size: 13px; color: #9aa0b6; }
    .form-group { margin-bottom: 20px; }
    .input-label { display: block; font-size: 13px; font-weight: 600; color: #cbd0e0; margin-bottom: 8px; }
    .input-wrapper { position: relative; }
    .input-wrapper .icon-left { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #6c7286; font-size: 16px; }
    .input-wrapper input { width: 100%; padding: 14px 16px 14px 48px; font-size: 15px; font-family: 'Inter',sans-serif; border: 1.5px solid #2f3445; border-radius: 28px; background: #1d212e; outline: none; transition: all 0.2s; color: #e7e9f3; }
    .input-wrapper input::placeholder { color: #6c7286; }
    .input-wrapper input:focus { border-color: #818cf8; box-shadow: 0 0 0 3px rgba(129,140,248,0.15); background-color: #20242f; }
    .password-toggle { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #6c7286; transition: color 0.2s; }
    .password-toggle:hover { color: #9aa0b6; }
    .options-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; font-size: 12px; }
    .remember-me { display: flex; align-items: center; gap: 8px; cursor: pointer; color: #9aa0b6; }
    .remember-me input { width: 16px; height: 16px; accent-color: #818cf8; cursor: pointer; }
    .forgot-link { color: #818cf8; text-decoration: none; font-weight: 500; }
    .login-btn { width: 100%; padding: 14px; background: linear-gradient(135deg, #6366f1, #8b5cf6); border: none; border-radius: 32px; font-size: 16px; font-weight: 700; color: white; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 8px 20px rgba(99,102,241,0.35); }
    .login-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(99,102,241,0.5); }
    .footer { text-align: center; margin-top: 28px; font-size: 10px; color: #6c7286; }
    .error-box { background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.35); border-radius: 20px; padding: 10px 16px; margin-bottom: 20px; font-size: 12px; color: #fca5a5; display: flex; align-items: center; gap: 8px; }
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    @media (max-width: 480px) { .login-card { padding: 32px 20px; } }
  </style>
</head>
<body>
@include('mobile.partials.preloader')
  <div class="bg-blob blob-1"></div>
  <div class="bg-blob blob-2"></div>
  <div class="bg-blob blob-3"></div>

  <div class="login-container">
    <div class="login-card">
      <div class="logo-section">
        <div class="logo-icon"><img src="http://app.klaes.ng/storage/upload/logo/Klase.png" alt="KLAES Logo"></div>
        <div class="logo-text">KLAES</div>
        <div class="logo-subtitle">File Tracking System</div>
      </div>
      <div class="welcome-text">
        <h2>Welcome Back</h2>
        <p>Sign in to access your file tracking dashboard</p>
      </div>

      @if ($errors->any())
        <div class="error-box">
          <i class="fas fa-exclamation-circle"></i>
          <span>{{ $errors->first() }}</span>
        </div>
      @endif

      <form method="POST" action="{{ route('mobile.login') }}">
        @csrf
        <div class="form-group">
          <label class="input-label">Username</label>
          <div class="input-wrapper">
            <i class="fas fa-user icon-left"></i>
            <input type="text" name="identifier" id="identifier"
                   placeholder="Enter your username"
                   value="{{ old('identifier') }}"
                   autocomplete="username" required>
          </div>
        </div>
        <div class="form-group">
          <label class="input-label">Password</label>
          <div class="input-wrapper">
            <i class="fas fa-lock icon-left"></i>
            <input type="password" name="password" id="password"
                   placeholder="Enter your password"
                   autocomplete="current-password" required>
            <i class="fas fa-eye-slash password-toggle" id="togglePassword"></i>
          </div>
        </div>
        <div class="options-row">
          <label class="remember-me">
            <input type="checkbox" name="remember" id="rememberMe">
            <span>Remember me</span>
          </label>
        </div>
        <button type="submit" class="login-btn" id="loginBtn">
          <i class="fas fa-arrow-right-to-bracket"></i>
          Sign In
        </button>
      </form>

      <div class="footer">
        <p>© {{ date('Y') }} KLAES - Land Administration Enterprise System</p>
        <p style="margin-top:4px;">File Tracker v1.0</p>
      </div>
    </div>
  </div>

  <script>
    document.getElementById('togglePassword').addEventListener('click', function() {
      const p = document.getElementById('password');
      p.type = p.type === 'password' ? 'text' : 'password';
      this.classList.toggle('fa-eye-slash');
      this.classList.toggle('fa-eye');
    });

    document.getElementById('loginBtn').closest('form').addEventListener('submit', function() {
      const btn = document.getElementById('loginBtn');
      btn.innerHTML = '<i class="fas fa-spinner" style="animation:spin 1s linear infinite;"></i> Signing in...';
      btn.disabled = true;
    });
  </script>
</body>
</html>
