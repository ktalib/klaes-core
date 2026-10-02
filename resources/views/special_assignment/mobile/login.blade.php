<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
  <meta name="theme-color" content="#0b0e14">
  <title>SPAS Mobile – Sign In</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
        --bg:       #0b0e14;
        --surface:  #151921;
        --surface2: #1e2530;
        --border:   rgba(255,255,255,0.08);
        --accent:   #babf0c;
        --accent-dim: rgba(186,191,12,0.14);
        --accent-glow: rgba(186,191,12,0.3);
        --text:     #f1f5f9;
        --muted:    #94a3b8;
        --dim:      #475569;
        --danger:   #ef4444;
        --success:  #10b981;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
    html, body { height: 100%; font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text); }

    body {
        min-height: 100svh;
        display: flex;
        flex-direction: column;
        position: relative;
        overflow-x: hidden;
    }

    /* ── Background orbs ── */
    .orb {
        position: fixed;
        border-radius: 50%;
        filter: blur(100px);
        pointer-events: none;
        z-index: 0;
    }
    .orb-1 { width: 60vw; height: 60vw; max-width: 420px; max-height: 420px; background: rgba(186,191,12,0.18); top: -15vw; left: -15vw; }
    .orb-2 { width: 50vw; height: 50vw; max-width: 350px; max-height: 350px; background: rgba(186,191,12,0.08); bottom: -10vw; right: -10vw; }

    /* ── Grid texture ── */
    .grid {
        position: fixed; inset: 0; z-index: 1; pointer-events: none;
        background-image:
            linear-gradient(rgba(186,191,12,0.03) 1px, transparent 1px),
            linear-gradient(90deg, rgba(186,191,12,0.03) 1px, transparent 1px);
        background-size: 44px 44px;
    }

    /* ── Header ── */
    .topbar {
        position: relative; z-index: 10;
        display: flex; align-items: center; gap: 10px;
        padding: 16px 20px;
        background: rgba(11,14,20,0.8);
        backdrop-filter: blur(20px);
        border-bottom: 1px solid var(--border);
    }
    .brand-icon {
        width: 34px; height: 34px; border-radius: 10px;
        overflow: hidden; flex-shrink: 0;
        border: 1px solid rgba(186,191,12,0.2);
        box-shadow: 0 0 12px var(--accent-glow);
    }
    .brand-icon img { width: 100%; height: 100%; object-fit: cover; }
    .brand-text h1 { font-size: 13px; font-weight: 800; letter-spacing: -.01em; color: var(--text); }
    .brand-text p  { font-size: 10px; color: var(--muted); font-weight: 500; letter-spacing: .04em; }

    /* ── Main wrapper ── */
    .wrapper {
        position: relative; z-index: 10;
        flex: 1; display: flex;
        align-items: center; justify-content: center;
        padding: 28px 20px 40px;
    }

    /* ── Card ── */
    .card {
        width: 100%; max-width: 400px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 24px;
        padding: 36px 28px;
        box-shadow: 0 24px 60px rgba(0,0,0,0.5), 0 0 0 1px rgba(186,191,12,0.05);
        animation: riseUp .45s cubic-bezier(.16,1,.3,1) both;
    }
    @keyframes riseUp { from { opacity:0; transform:translateY(24px); } to { opacity:1; transform:translateY(0); } }

    /* ── Logo inside card ── */
    .card-logo {
        display: flex; flex-direction: column; align-items: center;
        margin-bottom: 28px; gap: 14px;
    }
    .card-logo-icon {
        width: 72px; height: 72px; border-radius: 18px;
        overflow: hidden;
        border: 2px solid rgba(186,191,12,0.25);
        box-shadow: 0 0 30px var(--accent-glow), 0 0 60px rgba(186,191,12,0.1);
    }
    .card-logo-icon img { width: 100%; height: 100%; object-fit: cover; }
    .card-logo h2 { font-size: 24px; font-weight: 800; color: var(--text); letter-spacing: -.02em; }
    .card-logo p  { font-size: 13px; color: var(--muted); font-weight: 500; text-align: center; line-height: 1.5; }

    /* ── Divider ── */
    .accent-line {
        width: 50px; height: 3px;
        background: linear-gradient(90deg, transparent, var(--accent), transparent);
        border-radius: 2px; margin: 0 auto 28px;
    }

    /* ── Alerts ── */
    .alert {
        border-radius: 12px; padding: 12px 14px;
        font-size: 13px; display: flex; align-items: flex-start; gap: 10px;
        margin-bottom: 18px;
    }
    .alert-error   { background: rgba(239,68,68,.1);  border: 1px solid rgba(239,68,68,.25);  color: #fca5a5; }
    .alert-success { background: rgba(16,185,129,.1); border: 1px solid rgba(16,185,129,.25); color: #6ee7b7; }
    .alert svg { width: 16px; height: 16px; flex-shrink: 0; margin-top: 1px; }

    /* ── Form ── */
    .field { margin-bottom: 18px; }
    .field label {
        display: block; font-size: 10px; font-weight: 700;
        color: var(--muted); text-transform: uppercase; letter-spacing: .06em;
        margin-bottom: 7px;
    }
    .input-wrap { position: relative; }
    .input-wrap svg.icon-l {
        position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
        width: 15px; height: 15px; color: var(--dim); pointer-events: none;
    }
    .input-wrap input {
        width: 100%;
        background: var(--surface2);
        border: 1.5px solid var(--border);
        border-radius: 12px;
        padding: 13px 14px 13px 42px;
        font-size: 14px; font-family: inherit; font-weight: 500;
        color: var(--text);
        outline: none;
        transition: border-color .2s, box-shadow .2s;
    }
    .input-wrap input::placeholder { color: var(--dim); font-weight: 400; }
    .input-wrap input:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-dim);
    }
    .input-wrap .toggle-pw {
        position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
        cursor: pointer; color: var(--dim); transition: color .2s;
        background: none; border: none; padding: 0;
    }
    .input-wrap .toggle-pw:hover { color: var(--muted); }
    .input-wrap .toggle-pw svg { width: 16px; height: 16px; }

    /* ── Options row ── */
    .options-row {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 24px; font-size: 12px;
    }
    .remember {
        display: flex; align-items: center; gap: 8px;
        color: var(--muted); font-weight: 500; cursor: pointer;
    }
    .remember input { accent-color: var(--accent); width: 16px; height: 16px; cursor: pointer; }
    .forgot { color: var(--accent); text-decoration: none; font-weight: 600; font-size: 12px; }
    .forgot:hover { opacity: .8; }

    /* ── Submit button ── */
    .submit-btn {
        width: 100%;
        background: var(--accent);
        color: #0b0e14;
        border: none; border-radius: 12px;
        padding: 15px;
        font-size: 15px; font-weight: 800; font-family: inherit;
        letter-spacing: .02em;
        cursor: pointer;
        display: flex; align-items: center; justify-content: center; gap: 8px;
        box-shadow: 0 6px 24px var(--accent-glow);
        transition: opacity .2s, transform .15s, box-shadow .2s;
        margin-bottom: 20px;
    }
    .submit-btn:hover { opacity: .9; transform: translateY(-1px); box-shadow: 0 10px 32px var(--accent-glow); }
    .submit-btn:active { transform: translateY(0); }
    .submit-btn:disabled { opacity: .5; cursor: not-allowed; transform: none; }
    .submit-btn svg { width: 17px; height: 17px; }

    /* ── Footer text ── */
    .card-footer { text-align: center; font-size: 12px; color: var(--dim); }
    .card-footer a { color: var(--accent); text-decoration: none; font-weight: 600; }
    .copyright { text-align: center; font-size: 10px; color: #1e2530; margin-top: 16px; letter-spacing: .04em; }
  </style>
</head>
<body>

<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<div class="grid"></div>

<!-- Top bar -->
<div class="topbar">
    <div class="brand-icon">
        <img src="http://app.klaes.ng/storage/upload/logo/Klase.png" alt="SPAS">
    </div>
    <div class="brand-text">
        <h1>SPAS Mobile</h1>
        <p>SPECIAL ASSIGNMENT</p>
    </div>
</div>

<!-- Card -->
<div class="wrapper">
    <div class="card">

        <div class="card-logo">
            <div class="card-logo-icon">
                <img src="http://app.klaes.ng/storage/upload/logo/Klase.png" alt="SPAS">
            </div>
            <h2>Welcome Back</h2>
            <p>Sign in to access field assignments<br>and property records</p>
        </div>

        <div class="accent-line"></div>

        @if ($errors->any())
        <div class="alert alert-error">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span>{{ $errors->first() }}</span>
        </div>
        @endif

        @if (session('success'))
        <div class="alert alert-success">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span>{{ session('success') }}</span>
        </div>
        @endif

        <form method="POST" action="{{ route('special-assignment.mobile.login.submit') }}" id="loginForm">
            @csrf

            <div class="field">
                <label for="identifier">Username or Email</label>
                <div class="input-wrap">
                    <svg class="icon-l" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <input type="text" name="identifier" id="identifier"
                        placeholder="Enter your username"
                        value="{{ old('identifier') }}"
                        autocomplete="username" required>
                </div>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <svg class="icon-l" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <input type="password" name="password" id="password"
                        placeholder="Enter your password"
                        autocomplete="current-password" required>
                    <button type="button" class="toggle-pw" id="togglePw">
                        <svg id="eye-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <div class="options-row">
                <label class="remember">
                    <input type="checkbox" name="remember" id="rememberMe">
                    <span>Remember me</span>
                </label>
                <a href="{{ route('special-assignment.mobile.forgot-password') }}" class="forgot">Forgot password?</a>
            </div>

            <button type="submit" class="submit-btn" id="loginBtn">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                <span>Sign In</span>
            </button>
        </form>

        <div class="card-footer">
            Don't have an account? <a href="#">Contact administrator</a>
        </div>

        <div class="copyright">© {{ date('Y') }} KLAES. All rights reserved.</div>
    </div>
</div>

<script>
    // Password toggle
    const pw = document.getElementById('password');
    document.getElementById('togglePw').addEventListener('click', function() {
        const show = pw.type === 'password';
        pw.type = show ? 'text' : 'password';
        document.getElementById('eye-icon').innerHTML = show
            ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>'
            : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    });

    // Disable button on submit
    document.getElementById('loginForm').addEventListener('submit', function() {
        const btn = document.getElementById('loginBtn');
        btn.disabled = true;
        btn.innerHTML = '<svg style="animation:spin .8s linear infinite;width:17px;height:17px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" opacity=".25"/><path d="M21 12a9 9 0 0 0-9-9"/></svg><span>Signing in…</span>';
    });
</script>
<style>@keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}</style>
</body>
</html>
