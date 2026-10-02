<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
  <meta name="theme-color" content="#fefce8">
  <title>Reset Password - KLAES Special Assignment</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #fefce8 0%, #fef3c7 50%, #fde68a 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; position: relative; overflow-x: hidden; }
    .bg-blob { position: fixed; border-radius: 50%; filter: blur(60px); opacity: 0.4; z-index: 0; animation: float 20s infinite ease-in-out; }
    .blob-1 { width: 300px; height: 300px; background: #a16207; top: -100px; left: -100px; }
    .blob-2 { width: 400px; height: 400px; background: #f59e0b; bottom: -150px; right: -150px; animation-delay: 5s; }
    .blob-3 { width: 250px; height: 250px; background: #78350f; top: 50%; left: 50%; transform: translate(-50%,-50%); animation-delay: 10s; opacity: 0.2; }
    @keyframes float { 0%,100% { transform: translate(0,0) scale(1); } 33% { transform: translate(30px,-30px) scale(1.1); } 66% { transform: translate(-20px,20px) scale(0.9); } }
    .container { position: relative; z-index: 10; width: 100%; max-width: 420px; margin: 0 auto; }
    .card { background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); border-radius: 48px; padding: 40px 32px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25), 0 0 0 1px rgba(255,255,255,0.3); }
    .header { text-align: center; margin-bottom: 32px; }
    .logo-icon { width: 80px; height: 80px; border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; box-shadow: 0 10px 20px -5px rgba(161,98,7,0.3); overflow: hidden; background: white; }
    .logo-icon img { width: 100%; height: 100%; object-fit: contain; }
    .title { font-size: 28px; font-weight: 800; background: linear-gradient(135deg, #a16207, #78350f); -webkit-background-clip: text; background-clip: text; color: transparent; letter-spacing: 1px; }
    .subtitle { font-size: 12px; color: #6b7280; margin-top: 6px; }
    .welcome-text { text-align: center; margin-bottom: 28px; }
    .welcome-text h2 { font-size: 24px; font-weight: 700; color: #1f2937; margin-bottom: 6px; }
    .welcome-text p { font-size: 13px; color: #6b7280; line-height: 1.5; }
    .form-group { margin-bottom: 20px; }
    .input-label { display: block; font-size: 13px; font-weight: 600; color: #4b5563; margin-bottom: 8px; }
    .input-wrapper { position: relative; }
    .input-wrapper .icon-left { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 16px; }
    .input-wrapper input { width: 100%; padding: 14px 16px 14px 48px; font-size: 15px; font-family: 'Inter',sans-serif; border: 1.5px solid #e5e7eb; border-radius: 28px; background: white; outline: none; transition: all 0.2s; color: #1f2937; }
    .input-wrapper input:focus { border-color: #a16207; box-shadow: 0 0 0 3px rgba(161,98,7,0.1); }
    .password-toggle { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #9ca3af; transition: color 0.2s; }
    .btn-submit { width: 100%; padding: 14px; background: linear-gradient(135deg, #a16207, #78350f); border: none; border-radius: 32px; font-size: 16px; font-weight: 700; color: white; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 4px 12px rgba(161,98,7,0.3); margin-bottom: 12px; }
    .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(161,98,7,0.4); }
    .btn-submit:disabled { opacity: 0.7; cursor: not-allowed; }
    .btn-back { width: 100%; padding: 12px; background: white; border: 1.5px solid #e5e7eb; border-radius: 28px; font-size: 14px; font-weight: 600; color: #a16207; cursor: pointer; transition: all 0.2s; text-align: center; text-decoration: none; display: block; }
    .btn-back:hover { background: #fef3c7; border-color: #a16207; }
    .footer { text-align: center; margin-top: 28px; font-size: 10px; color: #9ca3af; }
    .error-box { background: #fee2e2; border: 1px solid #fecaca; border-radius: 20px; padding: 10px 16px; margin-bottom: 20px; font-size: 12px; color: #dc2626; display: flex; align-items: center; gap: 8px; }
    .success-box { background: #dcfce7; border: 1px solid #bbf7d0; border-radius: 20px; padding: 10px 16px; margin-bottom: 20px; font-size: 12px; color: #16a34a; display: flex; align-items: center; gap: 8px; }
    .password-hint { font-size: 11px; color: #9ca3af; margin-top: 4px; }
    @media (max-width: 480px) { .card { padding: 32px 20px; } }
  </style>
</head>
<body>
  <div class="bg-blob blob-1"></div>
  <div class="bg-blob blob-2"></div>
  <div class="bg-blob blob-3"></div>

  <div class="container">
    <div class="card">
      <div class="header">
        <div class="logo-icon"><img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES Logo"></div>
        <div class="title">KLAES</div>
        <div class="subtitle">Special Assignment Portal</div>
      </div>
      
      <div class="welcome-text">
        <h2>Reset Password</h2>
        <p>Create a new password for your account. Make sure it's secure and at least 8 characters long.</p>
      </div>

      @if ($errors->any())
        <div class="error-box">
          <i class="fas fa-exclamation-circle"></i>
          <span>{{ $errors->first() }}</span>
        </div>
      @endif

      <form method="POST" action="{{ route('special-assignment.mobile.reset-password.submit') }}" id="resetForm">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        
        <div class="form-group">
          <label class="input-label" for="email">Email Address</label>
          <div class="input-wrapper">
            <i class="fas fa-envelope icon-left"></i>
            <input 
              type="email" 
              name="email" 
              id="email"
              placeholder="Enter your email address"
              value="{{ old('email') }}"
              autocomplete="email" 
              required>
          </div>
        </div>

        <div class="form-group">
          <label class="input-label" for="password">New Password</label>
          <div class="input-wrapper">
            <i class="fas fa-lock icon-left"></i>
            <input 
              type="password" 
              name="password" 
              id="password"
              placeholder="Enter new password"
              autocomplete="new-password" 
              required
              minlength="8">
            <i class="fas fa-eye-slash password-toggle" id="togglePassword"></i>
          </div>
          <p class="password-hint">Minimum 8 characters recommended</p>
        </div>

        <div class="form-group">
          <label class="input-label" for="password_confirmation">Confirm Password</label>
          <div class="input-wrapper">
            <i class="fas fa-lock icon-left"></i>
            <input 
              type="password" 
              name="password_confirmation" 
              id="password_confirmation"
              placeholder="Confirm password"
              autocomplete="new-password" 
              required
              minlength="8">
            <i class="fas fa-eye-slash password-toggle" id="toggleConfirm"></i>
          </div>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn">
          <i class="fas fa-check"></i>
          <span>Reset Password</span>
        </button>

        <a href="{{ route('special-assignment.mobile.login') }}" class="btn-back">
          <i class="fas fa-arrow-left" style="margin-right: 6px;"></i> Back to Login
        </a>
      </form>

      <div class="footer">
        <p>© {{ date('Y') }} KLAES. All rights reserved.</p>
      </div>
    </div>
  </div>

  <script>
    // Password visibility toggles
    function setupPasswordToggle(toggleId, inputId) {
      const toggle = document.getElementById(toggleId);
      const input = document.getElementById(inputId);
      
      if (toggle && input) {
        toggle.addEventListener('click', function() {
          const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
          input.setAttribute('type', type);
          this.classList.toggle('fa-eye');
          this.classList.toggle('fa-eye-slash');
        });
      }
    }

    setupPasswordToggle('togglePassword', 'password');
    setupPasswordToggle('toggleConfirm', 'password_confirmation');

    // Form submission
    const resetForm = document.getElementById('resetForm');
    const submitBtn = document.getElementById('submitBtn');
    
    if (resetForm) {
      resetForm.addEventListener('submit', function(e) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Resetting...</span>';
      });
    }
  </script>
</body>
</html>
