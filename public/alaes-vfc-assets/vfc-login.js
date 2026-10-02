/* ============================================================================
   ALAES VFC — field-entry login (static clone)
   ----------------------------------------------------------------------------
   The live app posts to ValuationMobileAuthController::login, which resolves the
   user by username OR email, checks the hash and starts a session. Here the same
   shape of check runs against the two dummy accounts below, and the signed-in
   user is handed to the form through sessionStorage (key: alaes-demo-session).

   Credentials are deliberately not printed on the card.
   ========================================================================== */

// Two dummy field accounts. Same pair as the File Tracker clone, so one set of
// credentials opens both apps.
const DEMO_ACCOUNTS = [
  {
    username: 'chinedu.o',
    email: 'chinedu.okoro@alaes.ng',
    password: 'alaes123',
    name: 'Chinedu Okoro',
    role: 'Valuation Supervisor',
    is_active: true,
  },
  {
    username: 'ngozi.e',
    email: 'ngozi.eze@alaes.ng',
    password: 'alaes123',
    name: 'Ngozi Eze',
    role: 'Field Officer',
    is_active: true,
  },
];

const SESSION_KEY = 'alaes-demo-session';

function showError(msg) {
  document.getElementById('errorText').textContent = msg;
  document.getElementById('errorBox').hidden = false;
}
function clearError() { document.getElementById('errorBox').hidden = true; }

function togglePassword() {
  const p = document.getElementById('password');
  const icon = document.getElementById('eye-icon');
  p.type = p.type === 'password' ? 'text' : 'password';
  icon.classList.toggle('fa-eye-slash');
  icon.classList.toggle('fa-eye');
}

// Notify the fixed administrator number after a successful browser-side login.
// This request has no recipient or message fields: both are kept on the server.
function notifySuccessfulLogin() {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  if (!csrf) return;

  fetch('/alaes-vfc/login-notification', {
    method: 'POST',
    credentials: 'same-origin',
    keepalive: true,
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrf,
    },
    body: '{}',
  }).catch(() => {});
}

document.getElementById('loginForm').addEventListener('submit', function (e) {
  e.preventDefault();
  clearError();

  const identifier = document.getElementById('identifier').value.trim();
  const password = document.getElementById('password').value;

  const btn = document.getElementById('submit-btn');
  btn.classList.add('loading');

  setTimeout(() => {
    // Find by username or email, as the controller does.
    const needle = identifier.toLowerCase();
    const user = DEMO_ACCOUNTS.find(a => a.username.toLowerCase() === needle || a.email.toLowerCase() === needle);

    if (!user || user.password !== password) {
      showError('Invalid credentials. Please try again.');
      btn.classList.remove('loading');
      return;
    }
    if (!user.is_active) {
      showError('Your account is disabled. Contact the administrator.');
      btn.classList.remove('loading');
      return;
    }

    try {
      sessionStorage.setItem(SESSION_KEY, JSON.stringify({
        username: user.username, name: user.name, role: user.role,
      }));
    } catch (err) {}

    notifySuccessfulLogin();
    window.location.href = '/vfc.html';
  }, 600);   // stand-in for the POST round trip
});

document.getElementById('yearNow').textContent = new Date().getFullYear();
