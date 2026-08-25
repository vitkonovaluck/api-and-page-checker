'use strict';

const loginForm = document.getElementById('login-form');
const sessionPanel = document.getElementById('session');
const apiBaseUrlInput = document.getElementById('api-base-url');
const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');
const agentNameInput = document.getElementById('agent-name');
const siteSelect = document.getElementById('site');
const pendingCount = document.getElementById('pending-count');
const toggleRecordButton = document.getElementById('toggle-record');
const saveButton = document.getElementById('save');
const logoutButton = document.getElementById('logout');
const messageEl = document.getElementById('message');
const socialLogin = document.getElementById('social-login');
const socialButtons = document.getElementById('social-buttons');

/** @type {{ id: number, name: string, base_url: string }[]} */
let sites = [];

document.addEventListener('DOMContentLoaded', () => {
  void boot();
});

chrome.storage.onChanged.addListener((changes, area) => {
  if (area !== 'local') {
    return;
  }

  if (changes.token || changes.socialError) {
    void boot();
  }
});

apiBaseUrlInput.addEventListener('change', () => {
  void loadProviders(normalizeBaseUrl(apiBaseUrlInput.value));
});

loginForm.addEventListener('submit', (event) => {
  event.preventDefault();
  void signIn();
});

siteSelect.addEventListener('change', () => {
  void persistSelectedSite();
});

toggleRecordButton.addEventListener('click', () => {
  void toggleRecording();
});

saveButton.addEventListener('click', () => {
  void saveAddresses();
});

logoutButton.addEventListener('click', () => {
  void signOut();
});

async function boot() {
  const stored = await chrome.storage.local.get(['apiBaseUrl', 'token', 'agentName', 'site', 'socialError']);
  const fallbackUrl = typeof DEFAULT_API_BASE_URL === 'string' ? DEFAULT_API_BASE_URL : '';
  apiBaseUrlInput.value = stored.apiBaseUrl || fallbackUrl;
  agentNameInput.value = stored.agentName || 'Chrome';
  await loadProviders(normalizeBaseUrl(apiBaseUrlInput.value));

  if (typeof stored.socialError === 'string' && stored.socialError !== '') {
    showMessage(stored.socialError, 'error');
    await chrome.storage.local.remove(['socialError']);
  }

  if (!stored.token) {
    showLoggedOut();
    return;
  }

  const loaded = await loadSites(normalizeBaseUrl(stored.apiBaseUrl ?? ''), stored.token);

  if (!loaded) {
    showLoggedOut();
    return;
  }

  selectSite(stored.site?.id ?? null);
  await persistSelectedSite();
  await refreshRecorderState();
  showLoggedIn();
}

async function signIn() {
  clearMessage();
  const apiBaseUrl = normalizeBaseUrl(apiBaseUrlInput.value);
  const agentName = agentNameInput.value.trim() || 'Chrome';

  try {
    const response = await fetch(`${apiBaseUrl}/api/v1/agent/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        email: emailInput.value.trim(),
        password: passwordInput.value,
        name: agentName,
        hostname: 'chrome-extension',
      }),
    });
    const payload = await response.json();

    if (!response.ok || typeof payload.token !== 'string') {
      showMessage(errorMessage(payload, 'Sign in failed.'), 'error');
      return;
    }

    await finishSignIn(apiBaseUrl, payload.token, agentName);
  } catch {
    showMessage('Could not reach the checker. Check the URL.', 'error');
  }
}

/**
 * @param {string} apiBaseUrl
 * @param {string} token
 * @param {string} agentName
 */
async function finishSignIn(apiBaseUrl, token, agentName) {
  await chrome.storage.local.set({
    apiBaseUrl,
    token,
    agentName,
  });
  await chrome.storage.local.remove(['socialTicket', 'socialError']);

  const loaded = await loadSites(apiBaseUrl, token);

  if (!loaded) {
    return;
  }

  passwordInput.value = '';
  selectSite(null);
  await persistSelectedSite();
  await refreshRecorderState();
  showLoggedIn();
  showMessage('Signed in. Choose a site and start recording.', 'ok');
}

/**
 * @param {string} apiBaseUrl
 */
async function loadProviders(apiBaseUrl) {
  socialButtons.replaceChildren();
  socialLogin.classList.add('hidden');

  if (apiBaseUrl === '') {
    return;
  }

  try {
    const response = await fetch(`${apiBaseUrl}/api/v1/agent/providers`, {
      headers: { Accept: 'application/json' },
    });
    const payload = await response.json();
    const rows = Array.isArray(payload.data) ? payload.data : [];

    for (const row of rows) {
      if (!row || typeof row.id !== 'string' || typeof row.label !== 'string') {
        continue;
      }

      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'secondary';
      button.textContent = `Sign in with ${row.label}`;
      button.addEventListener('click', () => {
        void startSocialLogin(row.id);
      });
      socialButtons.append(button);
    }

    if (socialButtons.childElementCount > 0) {
      socialLogin.classList.remove('hidden');
    }
  } catch {
    socialLogin.classList.add('hidden');
  }
}

/**
 * @param {string} provider
 */
async function startSocialLogin(provider) {
  clearMessage();
  const apiBaseUrl = normalizeBaseUrl(apiBaseUrlInput.value);
  const agentName = agentNameInput.value.trim() || 'Chrome';

  if (apiBaseUrl === '') {
    showMessage('Enter the checker URL first.', 'error');
    return;
  }

  showMessage('Finish signing in in the new tab, then reopen this popup.', 'ok');

  const result = await sendMessage({
    type: 'socialLogin',
    apiBaseUrl,
    provider,
    agentName,
  });

  if (!result?.ok) {
    showMessage(typeof result?.error === 'string' ? result.error : 'Could not start social sign-in.', 'error');
  }
}

async function signOut() {
  const stored = await chrome.storage.local.get(['apiBaseUrl', 'token']);

  if (stored.apiBaseUrl && stored.token) {
    try {
      await fetch(`${normalizeBaseUrl(stored.apiBaseUrl)}/api/v1/agent/logout`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${stored.token}`,
        },
      });
    } catch {
      // Token is cleared locally either way.
    }
  }

  await sendMessage({ type: 'stop' });
  await sendMessage({ type: 'clearPending' });
  await chrome.storage.local.remove(['token', 'site']);
  showLoggedOut();
  showMessage('Signed out.', 'ok');
}

/**
 * @param {string} apiBaseUrl
 * @param {string} token
 */
async function loadSites(apiBaseUrl, token) {
  try {
    const response = await fetch(`${apiBaseUrl}/api/v1/agent/sites`, {
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
      },
    });

    if (response.status === 401) {
      await chrome.storage.local.remove(['token', 'site']);
      showMessage('Session expired. Sign in again.', 'error');
      return false;
    }

    const payload = await response.json();
    const rows = Array.isArray(payload.data) ? payload.data : [];
    sites = rows
      .filter((row) => row && typeof row.id === 'number' && typeof row.base_url === 'string')
      .map((row) => ({
        id: row.id,
        name: typeof row.name === 'string' ? row.name : `Site ${row.id}`,
        base_url: row.base_url,
      }));

    renderSites();

    if (sites.length === 0) {
      showMessage('No sites found for this account.', 'error');
    }

    return true;
  } catch {
    showMessage('Could not load sites.', 'error');
    return false;
  }
}

function renderSites() {
  siteSelect.innerHTML = '';

  if (sites.length === 0) {
    const option = document.createElement('option');
    option.value = '';
    option.textContent = 'No sites';
    siteSelect.append(option);
    return;
  }

  for (const site of sites) {
    const option = document.createElement('option');
    option.value = String(site.id);
    option.textContent = `${site.name} (${site.base_url})`;
    siteSelect.append(option);
  }
}

/**
 * @param {number|null} siteId
 */
function selectSite(siteId) {
  if (siteId !== null && sites.some((site) => site.id === siteId)) {
    siteSelect.value = String(siteId);
    return;
  }

  if (sites[0]) {
    siteSelect.value = String(sites[0].id);
  }
}

async function persistSelectedSite() {
  const site = selectedSite();

  if (site === null) {
    return;
  }

  await chrome.storage.local.set({ site });
}

async function toggleRecording() {
  const site = selectedSite();

  if (site === null) {
    showMessage('Create a site in API Checker first.', 'error');
    return;
  }

  const state = await sendMessage({ type: 'getState' });

  if (state?.recording) {
    await sendMessage({ type: 'stop' });
    await refreshRecorderState();
    showMessage('Recording paused. Save to import the paths.', 'ok');
    return;
  }

  await chrome.storage.local.set({ site });
  await sendMessage({ type: 'start', site });

  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

  if (tab?.url) {
    await sendMessage({ type: 'recordUrl', url: tab.url });
  }

  await refreshRecorderState();
  showMessage(`Recording ${site.base_url}. Browse the site, then Save.`, 'ok');
}

async function saveAddresses() {
  clearMessage();
  const stored = await chrome.storage.local.get(['apiBaseUrl', 'token', 'site']);
  const state = await sendMessage({ type: 'getState' });
  const pending = Array.isArray(state?.pending) ? state.pending : [];
  const site = stored.site ?? selectedSite();

  if (!stored.apiBaseUrl || !stored.token || site === null) {
    showMessage('Sign in and choose a site first.', 'error');
    return;
  }

  if (pending.length === 0) {
    showMessage('Nothing to save yet. Start recording and visit pages.', 'error');
    return;
  }

  saveButton.disabled = true;

  try {
    const response = await fetch(
      `${normalizeBaseUrl(stored.apiBaseUrl)}/api/v1/agent/sites/${site.id}/addresses`,
      {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${stored.token}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          endpoints: pending,
          schedule_enabled: true,
        }),
      },
    );
    const payload = await response.json();

    if (response.status === 401) {
      await chrome.storage.local.remove(['token']);
      showLoggedOut();
      showMessage('Session expired. Sign in again.', 'error');
      return;
    }

    if (!response.ok) {
      showMessage(errorMessage(payload, 'Could not save addresses.'), 'error');
      return;
    }

    await sendMessage({ type: 'clearPending' });
    await sendMessage({ type: 'stop' });
    await refreshRecorderState();
    const created = Number(payload.created ?? 0);
    const skipped = Number(payload.skipped ?? 0);
    showMessage(`Imported ${created} address(es), skipped ${skipped}.`, 'ok');
  } catch {
    showMessage('Could not reach the checker.', 'error');
  } finally {
    saveButton.disabled = false;
  }
}

async function refreshRecorderState() {
  const state = await sendMessage({ type: 'getState' });
  const pending = Array.isArray(state?.pending) ? state.pending : [];
  pendingCount.textContent = String(pending.length);
  siteSelect.disabled = Boolean(state?.recording);
  toggleRecordButton.textContent = state?.recording ? 'Stop recording' : 'Start recording';
  toggleRecordButton.classList.toggle('danger', Boolean(state?.recording));
  toggleRecordButton.classList.toggle('primary', !state?.recording);
}

function selectedSite() {
  const id = Number(siteSelect.value);
  return sites.find((site) => site.id === id) ?? null;
}

function showLoggedIn() {
  loginForm.classList.add('hidden');
  sessionPanel.classList.remove('hidden');
}

function showLoggedOut() {
  loginForm.classList.remove('hidden');
  sessionPanel.classList.add('hidden');
}

/**
 * @param {string} text
 * @param {'ok'|'error'} type
 */
function showMessage(text, type) {
  messageEl.textContent = text;
  messageEl.classList.remove('hidden', 'ok', 'error');
  messageEl.classList.add('status', type);
}

function clearMessage() {
  messageEl.classList.add('hidden');
  messageEl.textContent = '';
}

/**
 * @param {unknown} payload
 * @param {string} fallback
 */
function errorMessage(payload, fallback) {
  if (!payload || typeof payload !== 'object') {
    return fallback;
  }

  const body = /** @type {{ message?: unknown, errors?: unknown }} */ (payload);

  if (typeof body.message === 'string' && body.message !== '') {
    return body.message;
  }

  if (body.errors && typeof body.errors === 'object') {
    const first = Object.values(body.errors)[0];

    if (Array.isArray(first) && typeof first[0] === 'string') {
      return first[0];
    }
  }

  return fallback;
}

/**
 * @param {string} value
 */
function normalizeBaseUrl(value) {
  return value.trim().replace(/\/+$/, '');
}

/**
 * @param {Record<string, unknown>} message
 */
function sendMessage(message) {
  return chrome.runtime.sendMessage(message);
}
