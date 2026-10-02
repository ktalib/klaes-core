<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f172a">
    <title>ALAES VFC - Authentication</title>

    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('alaes-vfc-assets/vfc-login.css') }}">
</head>
<body>
    <div class="animated-bg">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
    </div>

    <div class="login-container">
        <div class="login-card">
            <div class="brand-section">
                <div class="logo-box"><img src="{{ asset('alaes-vfc-assets/logos/alaes.jpeg') }}" alt="ALAES"></div>
                <h1 class="brand-name">VFC MOBILE</h1>
                <p class="brand-tagline">Valuation for Compensation</p>
            </div>

            <!-- Rendered by vfc-login.js on a failed sign-in; the live app prints
                 $errors->first() from the server here instead. -->
            <div class="error-message" id="errorBox" hidden>
                <i class="fas fa-circle-exclamation"></i>
                <span id="errorText"></span>
            </div>

            <form id="loginForm" autocomplete="on">
                <div class="form-group">
                    <label class="label" for="identifier">Username or Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user-tie"></i>
                        <input type="text" name="identifier" id="identifier" class="input-field"
                               placeholder="e.g. c.okoro" required autocomplete="username">
                    </div>
                </div>

                <div class="form-group">
                    <label class="label" for="password">Access Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-key"></i>
                        <input type="password" name="password" id="password" class="input-field"
                               placeholder="••••••••" required autocomplete="current-password">
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
                <p>© <span id="yearNow"></span> ALAES - Abia Land Administration Enterprise System</p>
                <div class="version-badge">VFC-M v1.0</div>
            </div>
        </div>
    </div>

    <script src="{{ asset('alaes-vfc-assets/vfc-login.js') }}"></script>
</body>
</html>
