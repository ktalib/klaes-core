// Plain Node test: node tests/js/auto-logout.test.js
//
// Runs the real public/js/auto-logout.js in two fake tabs that share one
// localStorage and one clock, the way two tabs of a browser do.
const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.join(__dirname, '../../public/js/auto-logout.js'), 'utf8');

const MINUTE = 60 * 1000;
const LIMIT = 60 * MINUTE;
const WARNING = 5 * MINUTE;

function fakeBrowser() {
  const browser = {
    now: 1_000_000,
    timers: [],
    nextId: 1,
    store: {},
    tabs: [],
    storageBlocked: false,
  };

  browser.localStorage = {
    getItem(key) {
      if (browser.storageBlocked) throw new Error('blocked');
      return Object.prototype.hasOwnProperty.call(browser.store, key) ? browser.store[key] : null;
    },
    setItem(key, value) {
      if (browser.storageBlocked) throw new Error('blocked');
      browser.store[key] = value;
      browser.tabs.forEach((tab) => tab.storageChanged(key));
    },
  };

  browser.advance = (ms) => {
    const target = browser.now + ms;
    for (;;) {
      const due = browser.timers
        .filter((t) => t.at <= target)
        .sort((a, b) => a.at - b.at)[0];
      if (!due) break;
      browser.timers = browser.timers.filter((t) => t !== due);
      browser.now = Math.max(browser.now, due.at);
      due.fn();
    }
    browser.now = target;
  };

  return browser;
}

function openTab(browser, { hidden = false } = {}) {
  const docListeners = {};
  const winListeners = {};
  const form = { submits: 0, submit() { this.submits += 1; } };
  const appended = [];

  const element = () => ({
    textContent: '',
    addEventListener() {},
    querySelector: () => null,
    remove() {},
    classList: { add() {}, remove() {} },
  });

  const document = {
    querySelector: (selector) => (selector === '#autoLogoutForm' ? form : null),
    addEventListener(name, fn) { (docListeners[name] = docListeners[name] || []).push(fn); },
    createElement() {
      const el = element();
      el.buttons = {};
      el.querySelector = (selector) => {
        el.buttons[selector] = el.buttons[selector] || {
          listeners: {},
          textContent: '',
          addEventListener(name, fn) { this.listeners[name] = fn; },
        };
        return el.buttons[selector];
      };
      return el;
    },
    body: { appendChild: (el) => appended.push(el) },
  };

  const window = {
    localStorage: browser.localStorage,
    setTimeout(fn, delay) {
      const id = browser.nextId++;
      browser.timers.push({ id, fn, at: browser.now + (delay ?? 0) });
      return id;
    },
    clearTimeout(id) { browser.timers = browser.timers.filter((t) => t.id !== id); },
    setInterval: () => 0,
    clearInterval() {},
    addEventListener(name, fn) { (winListeners[name] = winListeners[name] || []).push(fn); },
    location: { href: '' },
  };

  const FakeDate = { now: () => browser.now };
  vm.runInNewContext(source, { window, document, Date: FakeDate, Math, Number, String, Boolean, Object });

  const tab = {
    form,
    warnings: appended,
    autoLogout: window.AutoLogout,
    act: () => (docListeners.mousemove || []).forEach((fn) => fn({})),
    storageChanged(key) {
      if (hidden) return; // A throttled background tab: the event may never arrive in time.
      (winListeners.storage || []).forEach((fn) => fn({ key }));
    },
  };

  window.AutoLogout.init({ enabled: true, inactivityLimit: LIMIT, warningOffset: WARNING });
  browser.tabs.push(tab);
  return tab;
}

// One tab, nobody there: signs out at the limit, not before.
{
  const browser = fakeBrowser();
  const tab = openTab(browser);

  browser.advance(LIMIT - 1000);
  assert.strictEqual(tab.form.submits, 0, 'must not sign out before the limit');
  assert.strictEqual(tab.warnings.length, 1, 'warning shows before the sign-out');
  browser.advance(2000);
  assert.strictEqual(tab.form.submits, 1, 'genuinely idle session signs out at the limit');
}

// The reported bug: a tab left open behind the one being worked in.
{
  const browser = fakeBrowser();
  const background = openTab(browser, { hidden: true });
  const working = openTab(browser);

  for (let i = 0; i < 24; i += 1) { // Two hours of work, a move every five minutes.
    browser.advance(5 * MINUTE);
    working.act();
  }

  assert.strictEqual(background.form.submits, 0, 'background tab must not sign out a working user');
  assert.strictEqual(working.form.submits, 0, 'working tab stays signed in');
  assert.strictEqual(background.warnings.length, 0, 'background tab must not warn a working user');
}

// Work stops: the session still ends, once, on the shared clock.
{
  const browser = fakeBrowser();
  const background = openTab(browser, { hidden: true });
  const working = openTab(browser);

  browser.advance(30 * MINUTE);
  working.act();
  browser.advance(LIMIT + 1000);

  assert.strictEqual(working.form.submits + background.form.submits >= 1, true, 'idle in every tab signs out');
}

// A tab older than the sign-in: a fresh page load counts as activity.
{
  const browser = fakeBrowser();
  const old = openTab(browser, { hidden: true });

  browser.advance(45 * MINUTE);
  openTab(browser); // New page after signing in again.
  browser.advance(15 * MINUTE + 1000);

  assert.strictEqual(old.form.submits, 0, 'an old tab must not end a 15-minute-old session');
}

// Storage blocked (private window): behaves as a single tab rather than failing.
{
  const browser = fakeBrowser();
  browser.storageBlocked = true;
  const tab = openTab(browser);

  browser.advance(LIMIT / 2);
  tab.act();
  browser.advance(LIMIT - 1000);
  assert.strictEqual(tab.form.submits, 0, 'own activity still resets the timer');
  browser.advance(2000);
  assert.strictEqual(tab.form.submits, 1, 'still signs out when idle');
}

// "Logout Now" is immediate even though there is time left.
{
  const browser = fakeBrowser();
  const tab = openTab(browser);

  browser.advance(LIMIT - WARNING + 1000);
  assert.strictEqual(tab.warnings.length, 1);
  tab.warnings[0].buttons['#autoLogoutLogoutNow'].listeners.click();
  assert.strictEqual(tab.form.submits, 1, 'Logout Now signs out at once');
}

console.log('auto-logout: all checks passed');
