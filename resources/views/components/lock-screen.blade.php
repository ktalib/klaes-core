<!-- Lock Screen Modal -->
<div id="lockScreenModal" class="lock-screen-overlay hidden">
    <div class="lock-screen-backdrop">
        <!-- Background Image -->
        <div class="lock-screen-bg"></div>
        
        <!-- Lock Screen Content -->
        <div class="lock-screen-content">
            <div class="lock-screen-container">
                <!-- Brand Logos Header -->
                <div class="lock-screen-header">
                    <div class="brand-logos-container">
                        <div class="logo-wrapper">
                            <img src="{{ asset('public/images/branding-logo-left.png') }}" alt="Left Logo" class="brand-logo-left" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="logo-fallback" style="display: none;">
                                <div class="fallback-text">KLAES</div>
                            </div>
                        </div>
                        
                        <div class="profile-avatar-container">
                            <div class="profile-avatar-wrapper">
                                @if(Auth::check() && Auth::user()->profile_url)
                                    <img src="{{ auth()->user()->profile_url }}" alt="Profile" class="profile-avatar-img">
                                @else
                                    <div class="profile-avatar-placeholder">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                    </div>
                                @endif
                            </div>
                            <div class="profile-pulse"></div>
                        </div>
                        
                        <div class="logo-wrapper">
                            <img src="{{ asset('public/images/branding-logo-right.jpeg') }}" alt="Right Logo" class="brand-logo-right" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="logo-fallback" style="display: none;">
                                <div class="fallback-text">SECURE</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User Info - Name Only -->
                <div class="user-info">
                    <h2 class="user-name">{{ Auth::check() ? (Auth::user()->first_name ?? Auth::user()->name) : 'User' }}</h2>
                    <p class="session-message">Session Locked</p>
                </div>

                <!-- Unlock Form -->
                <div class="unlock-form-container">
                    <form id="unlockForm" class="unlock-form">
                        @csrf
                        <div class="password-input-container">
                            <div class="input-wrapper">
                                <svg xmlns="http://www.w3.org/2000/svg" class="input-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <input 
                                    type="password" 
                                    id="unlockPassword" 
                                    name="password" 
                                    placeholder="Enter your password to unlock" 
                                    class="password-input"
                                    required
                                    autocomplete="current-password"
                                >
                                <button type="button" class="toggle-password" id="togglePassword">
                                    <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <svg class="eye-closed hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
                                    </svg>
                                </button>
                            </div>
                            <div id="unlockError" class="error-message hidden"></div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="unlock-button">
                                <span class="button-text">Unlock Session</span>
                                <svg class="button-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                                </svg>
                                <div class="loading-spinner hidden">
                                    <div class="spinner"></div>
                                </div>
                            </button>
                            
                            <button type="button" id="logoutButton" class="logout-button">
                                <span>Logout</span>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Compact Footer with Timer -->
                <div class="lock-screen-footer">
                    <div class="footer-content">
                        <div class="timer-compact">
                            <svg xmlns="http://www.w3.org/2000/svg" class="timer-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span id="sessionTimer" class="timer-countdown">12:00</span>
                        </div>
                        <p class="copyright">&copy; {{ date('Y') }} {{ config('app.name', 'KLAES') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.lock-screen-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.5s ease, visibility 0.5s ease;
}

.lock-screen-overlay.active {
    opacity: 1;
    visibility: visible;
}

.lock-screen-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(10px);
    background: rgba(0, 0, 0, 0.4);
}
.lock-screen-bg {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, #1B4332 0%, #2D6A4F 100%);
    background-image: url('{{ asset("storage/upload/logo/2.jpeg") }}');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    animation: backgroundChange 10s infinite;
}

@keyframes backgroundChange {
    0%, 50% {
        background-image: url('{{ asset("storage/upload/logo/2.jpeg") }}');
    }
    51%, 100% {
        background-image: url('{{ asset("storage/upload/logo/4.jpeg") }}');
    }
}

.lock-screen-bg::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(27, 67, 50, 0.85) 0%, rgba(45, 106, 79, 0.85) 100%);
}

.lock-screen-content {
    position: relative;
    z-index: 2;
    width: 100%;
    max-width: 380px;
    margin: 0 20px;
}

.lock-screen-container {
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(20px);
    border-radius: 16px;
    padding: 24px;
    max-width: 400px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    border: 2px solid #40916C;
    text-align: center;
    animation: slideIn 0.6s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(30px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.lock-screen-header {
    margin-bottom: 20px;
}

.brand-logos-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.brand-logo-left,
.brand-logo-right {
    max-height: 40px;
    max-width: 80px;
    object-fit: contain;
    border-radius: 6px;
}

.logo-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 80px;
    height: 40px;
}

.logo-fallback {
    display: none;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #40916C 0%, #52B788 100%);
    border-radius: 6px;
    padding: 6px 12px;
    min-width: 60px;
    height: 32px;
    border: 1px solid #1B4332;
}

.fallback-text {
    font-size: 10px;
    font-weight: 800;
    color: #A7F3D0;
    letter-spacing: 1px;
    text-transform: uppercase;
}

.profile-avatar-container {
    position: relative;
    display: inline-block;
}

.profile-avatar-wrapper {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid #40916C;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    margin: 0 auto;
    animation: profilePulse 2s infinite;
}

.profile-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-avatar-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #1B4332 0%, #2D6A4F 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #A7F3D0;
}

.profile-avatar-placeholder svg {
    width: 32px;
    height: 32px;
}

.profile-pulse {
    position: absolute;
    top: -6px;
    left: -6px;
    right: -6px;
    bottom: -6px;
    border: 2px solid #40916C;
    border-radius: 50%;
    opacity: 0;
    animation: pulse 2s infinite;
}

@keyframes profilePulse {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2), 0 0 0 0 rgba(64, 145, 108, 0.7);
    }
    50% {
        transform: scale(1.05);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3), 0 0 0 10px rgba(64, 145, 108, 0);
    }
}

@keyframes pulse {
    0% { transform: scale(1); opacity: 0.7; }
    50% { transform: scale(1.1); opacity: 0.3; }
    100% { transform: scale(1.2); opacity: 0; }
}

.user-info {
    margin-bottom: 20px;
}

.user-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
}

.user-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid #40916C;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    flex-shrink: 0;
}

.user-details {
    text-align: left;
}

.avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #1B4332 0%, #2D6A4F 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #A7F3D0;
}

.avatar-placeholder svg {
    width: 24px;
    height: 24px;
}

.user-name {
    font-size: 18px;
    font-weight: 700;
    color: #1B4332;
    margin-bottom: 2px;
    letter-spacing: -0.3px;
}

.session-message {
    color: #B91C1C;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.unlock-form-container {
    margin-bottom: 16px;
}

.password-input-container {
    margin-bottom: 16px;
}

.input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    background: rgba(255, 255, 255, 0.9);
    border: 2px solid #40916C;
    border-radius: 12px;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.input-wrapper:focus-within {
    border-color: #1B4332;
    background: rgba(255, 255, 255, 1);
    box-shadow: 0 0 0 3px rgba(64, 145, 108, 0.2);
}

.input-icon {
    width: 18px;
    height: 18px;
    color: #1B4332;
    margin-left: 12px;
    flex-shrink: 0;
}

.password-input {
    flex: 1;
    padding: 12px 12px 12px 8px;
    border: none;
    background: transparent;
    font-size: 14px;
    color: #1B4332;
    outline: none;
    font-weight: 500;
}

.password-input::placeholder {
    color: #2D6A4F;
    font-weight: 400;
    opacity: 0.8;
}

.toggle-password {
    padding: 6px 12px;
    border: none;
    background: transparent;
    color: #2D6A4F;
    cursor: pointer;
    transition: color 0.2s ease;
    flex-shrink: 0;
}

.toggle-password:hover {
    color: #40916C;
}

.toggle-password svg {
    width: 20px;
    height: 20px;
}

.error-message {
    margin-top: 8px;
    padding: 8px 12px;
    background: rgba(185, 28, 28, 0.1);
    border: 1px solid #B91C1C;
    color: #B91C1C;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    backdrop-filter: blur(10px);
}

.form-actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.unlock-button {
    width: 100%;
    padding: 12px 20px;
    background: linear-gradient(135deg, #1B4332 0%, #2D6A4F 100%);
    color: #A7F3D0;
    border: 2px solid #40916C;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    position: relative;
    overflow: hidden;
}

.unlock-button:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 25px rgba(64, 145, 108, 0.4);
    background: linear-gradient(135deg, #40916C 0%, #52B788 100%);
    color: white;
}

.unlock-button:active {
    transform: translateY(0);
}

.unlock-button:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

.button-icon {
    width: 20px;
    height: 20px;
    transition: transform 0.3s ease;
}

.unlock-button:hover .button-icon {
    transform: scale(1.1);
}

.loading-spinner {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

.spinner {
    width: 20px;
    height: 20px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top: 2px solid white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.logout-button {
    width: 100%;
    padding: 10px 20px;
    background: transparent;
    color: #B91C1C;
    border: 2px solid #B91C1C;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.logout-button:hover {
    border-color: #B91C1C;
    color: white;
    background: #B91C1C;
}

.logout-button svg {
    width: 16px;
    height: 16px;
}

.lock-screen-footer {
    padding-top: 12px;
    border-top: 2px solid #40916C;
    margin-top: 16px;
}

.footer-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.timer-compact {
    display: flex;
    align-items: center;
    gap: 6px;
    background: rgba(64, 145, 108, 0.1);
    padding: 4px 8px;
    border-radius: 8px;
    border: 1px solid #40916C;
}

.timer-icon {
    width: 14px;
    height: 14px;
    color: #B91C1C;
}

.timer-countdown {
    color: #B91C1C;
    font-size: 12px;
    font-weight: 700;
    font-family: 'Courier New', monospace;
}

.copyright {
    color: #1B4332;
    font-size: 10px;
    font-weight: 500;
    opacity: 0.8;
}

/* Responsive Design */
@media (max-width: 480px) {
    .lock-screen-container {
        padding: 20px 16px;
        margin: 0 12px;
        border-radius: 12px;
        max-width: 350px;
    }
    
    .brand-logos-container {
        flex-direction: column;
        gap: 8px;
    }
    
    .brand-logo-left,
    .brand-logo-right {
        max-height: 32px;
        margin: 0;
    }
    
    .profile-avatar-wrapper {
        width: 48px;
        height: 48px;
        border-width: 2px;
    }
    
    .profile-avatar-placeholder svg {
        width: 24px;
        height: 24px;
    }
    
    .user-row {
        flex-direction: column;
        gap: 8px;
    }
    
    .user-details {
        text-align: center;
    }
    
    .user-name {
        font-size: 16px;
    }
    
    .session-message {
        font-size: 11px;
    }
    
    .password-input {
        font-size: 16px; /* Prevent zoom on iOS */
    }
    
    .footer-content {
        flex-direction: column;
        gap: 8px;
        align-items: center;
    }
    
    .copyright {
        text-align: center;
    }
}

/* Hidden class utility */
.hidden {
    display: none !important;
}
</style>