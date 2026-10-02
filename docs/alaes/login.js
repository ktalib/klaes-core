/* ============================================================================
   ALAES File Tracker — Mobile Login (static clone)
   ----------------------------------------------------------------------------
   The live app posts to MobileController::login, which looks the user up by
   username OR email, checks the hash, refuses a disabled account and starts a
   session. Here the same shape of check runs against the two dummy accounts
   below, and the signed-in user is handed to the dashboard through
   sessionStorage (key: alaes-demo-session).
   ========================================================================== */

// Two dummy accounts. `role` matches a key in DEMO_ROLES over in file-tracker.js,
// so the dashboard renders the tab set that role really gets.
const DEMO_ACCOUNTS = [
  {
    username: 'chinedu.o',
    email: 'chinedu.okoro@alaes.ng',
    password: 'alaes123',
    name: 'Chinedu Okoro',
    role: 'admin',           // sees every tab, incl. the SCB View preview
    is_active: true,
  },
  {
    username: 'ngozi.e',
    email: 'ngozi.eze@alaes.ng',
    password: 'alaes123',
    name: 'Ngozi Eze',
    role: 'scb',             // queue-only: no Quick Search tab, compact SCB list
    is_active: true,
  },
];

const SESSION_KEY = 'alaes-demo-session';

function showError(msg) {
  const box = document.getElementById('errorBox');
  document.getElementById('errorText').textContent = msg;
  box.hidden = false;
}
function clearError() { document.getElementById('errorBox').hidden = true; }

// Password visibility toggle — same swap of fa-eye-slash / fa-eye as the app.
document.getElementById('togglePassword').addEventListener('click', function () {
  const p = document.getElementById('password');
  p.type = p.type === 'password' ? 'text' : 'password';
  this.classList.toggle('fa-eye-slash');
  this.classList.toggle('fa-eye');
});

document.getElementById('loginForm').addEventListener('submit', function (e) {
  e.preventDefault();
  clearError();

  const identifier = document.getElementById('identifier').value.trim();
  const password   = document.getElementById('password').value;
  const remember   = document.getElementById('rememberMe').checked;

  const btn = document.getElementById('loginBtn');
  btn.innerHTML = '<i class="fas fa-spinner" style="animation:spin 1s linear infinite;"></i> Signing in...';
  btn.disabled = true;

  setTimeout(() => {
    // Find by username or email, exactly as MobileController::login does.
    const needle = identifier.toLowerCase();
    const user = DEMO_ACCOUNTS.find(a => a.username.toLowerCase() === needle || a.email.toLowerCase() === needle);

    if (!user || user.password !== password) {
      showError('Invalid username or password.');
      btn.innerHTML = '<i class="fas fa-arrow-right-to-bracket"></i> Sign In';
      btn.disabled = false;
      return;
    }
    if (!user.is_active) {
      showError('Your account is disabled. Contact support.');
      btn.innerHTML = '<i class="fas fa-arrow-right-to-bracket"></i> Sign In';
      btn.disabled = false;
      return;
    }

    // Hand the signed-in user to the dashboard. sessionStorage rather than
    // localStorage so closing the tab ends the "session"; "Remember me" keeps a
    // copy in localStorage so the next visit skips the form.
    const session = { username: user.username, name: user.name, role: user.role };
    try {
      sessionStorage.setItem(SESSION_KEY, JSON.stringify(session));
      if (remember) localStorage.setItem(SESSION_KEY, JSON.stringify(session));
      else localStorage.removeItem(SESSION_KEY);
      // A fresh sign-in should get the splash, same as opening the app.
      sessionStorage.removeItem('alaes-splash-shown');
    } catch (err) {}

    window.location.href = '/file-tracker.html';
  }, 600);   // stand-in for the POST round trip
});

// Boot: prefill from a remembered session and stamp the footer year.
document.getElementById('yearNow').textContent = new Date().getFullYear();
try {
  const remembered = JSON.parse(localStorage.getItem(SESSION_KEY) || 'null');
  if (remembered && remembered.username) {
    document.getElementById('identifier').value = remembered.username;
    document.getElementById('rememberMe').checked = true;
    document.getElementById('password').focus();
  }
} catch (e) {}
