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

    window.location.href = 'vfc.html';
  }, 600);   // stand-in for the POST round trip
});

document.getElementById('yearNow').textContent = new Date().getFullYear();
