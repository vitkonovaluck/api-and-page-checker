'use strict';

const STORAGE_KEYS = [
  'apiBaseUrl',
  'token',
  'agentName',
  'site',
  'recording',
  'pending',
];

chrome.webNavigation.onCommitted.addListener((details) => {
  void captureNavigation(details);
});

chrome.webNavigation.onHistoryStateUpdated.addListener((details) => {
  void captureNavigation(details);
});

chrome.runtime.onMessage.addListener((message, _sender, sendResponse) => {
  void handleMessage(message).then(sendResponse);

  return true;
});

/**
 * @param {chrome.webNavigation.WebNavigationTransitionCallbackDetails} details
 */
async function captureNavigation(details) {
  if (details.frameId !== 0) {
    return;
  }

  await recordUrl(details.url);
}

/**
 * @param {string} url
 */
async function recordUrl(url) {
  const state = await chrome.storage.local.get(['recording', 'site', 'pending']);

  if (!state.recording || !state.site?.base_url) {
    return;
  }

  const endpoint = endpointFromUrl(url, state.site.base_url);

  if (endpoint === null) {
    return;
  }

  const pending = Array.isArray(state.pending) ? state.pending : [];

  if (pending.includes(endpoint)) {
    return;
  }

  pending.push(endpoint);
  await chrome.storage.local.set({ pending });
  await updateBadge(pending.length);
}

/**
 * @param {Record<string, unknown>} message
 */
async function handleMessage(message) {
  const type = typeof message.type === 'string' ? message.type : '';

  switch (type) {
    case 'getState':
      return getRecorderState();
    case 'start':
      return startRecording(message.site);
    case 'stop':
      return stopRecording();
    case 'clearPending':
      return clearPending();
    case 'recordUrl':
      return recordExplicitUrl(message.url);
    case 'socialLogin':
      return startSocialLogin(message);
    default:
      return { ok: false };
  }
}

async function getRecorderState() {
  const state = await chrome.storage.local.get(STORAGE_KEYS);

  return {
    ok: true,
    apiBaseUrl: state.apiBaseUrl ?? '',
    token: state.token ?? '',
    agentName: state.agentName ?? 'Chrome',
    site: state.site ?? null,
    recording: Boolean(state.recording),
    pending: Array.isArray(state.pending) ? state.pending : [],
  };
}

/**
 * @param {unknown} site
 */
async function startRecording(site) {
  if (!isSite(site)) {
    return { ok: false };
  }

  await chrome.storage.local.set({
    site,
    recording: true,
    pending: [],
  });
  await updateBadge(0);

  return { ok: true, pending: [] };
}

async function stopRecording() {
  await chrome.storage.local.set({ recording: false });

  return getRecorderState();
}

async function clearPending() {
  await chrome.storage.local.set({ pending: [] });
  await updateBadge(0);

  return { ok: true, pending: [] };
}

/**
 * @param {unknown} url
 */
async function recordExplicitUrl(url) {
  if (typeof url === 'string') {
    await recordUrl(url);
  }

  return getRecorderState();
}

/**
 * @param {Record<string, unknown>} message
 */
async function startSocialLogin(message) {
  const apiBaseUrl = typeof message.apiBaseUrl === 'string'
    ? message.apiBaseUrl.replace(/\/+$/, '')
    : '';
  const provider = typeof message.provider === 'string' ? message.provider : '';
  const agentName = typeof message.agentName === 'string' && message.agentName.trim() !== ''
    ? message.agentName.trim()
    : 'Chrome';

  if (apiBaseUrl === '' || provider === '') {
    return { ok: false, error: 'Checker URL is required.' };
  }

  try {
    const response = await fetch(`${apiBaseUrl}/api/v1/agent/extension-logins`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        name: agentName,
        hostname: 'chrome-extension',
      }),
    });
    const payload = await response.json();
    const ticket = payload.ticket;

    if (!response.ok || typeof ticket !== 'string') {
      return { ok: false, error: 'Could not start social sign-in.' };
    }

    await chrome.storage.local.set({
      apiBaseUrl,
      agentName,
      socialTicket: ticket,
      socialError: '',
    });

    await chrome.tabs.create({
      url: `${apiBaseUrl}/extension/auth/${encodeURIComponent(provider)}?ticket=${encodeURIComponent(ticket)}`,
    });

    void pollSocialLogin(apiBaseUrl, ticket);

    return { ok: true };
  } catch {
    return { ok: false, error: 'Could not reach the checker. Check the URL.' };
  }
}

/**
 * @param {string} apiBaseUrl
 * @param {string} ticket
 */
async function pollSocialLogin(apiBaseUrl, ticket) {
  const deadline = Date.now() + 5 * 60 * 1000;

  while (Date.now() < deadline) {
    const stored = await chrome.storage.local.get(['socialTicket', 'token']);

    if (stored.token || stored.socialTicket !== ticket) {
      return;
    }

    try {
      const response = await fetch(`${apiBaseUrl}/api/v1/agent/extension-logins/${ticket}`, {
        headers: { Accept: 'application/json' },
      });
      const payload = await response.json();

      if (response.status === 404) {
        await chrome.storage.local.set({
          socialError: 'Social sign-in expired. Try again.',
        });
        await chrome.storage.local.remove(['socialTicket']);
        return;
      }

      if (payload.status === 'ready' && typeof payload.token === 'string') {
        await chrome.storage.local.set({
          apiBaseUrl,
          token: payload.token,
        });
        await chrome.storage.local.remove(['socialTicket', 'socialError']);
        return;
      }

      if (payload.status === 'failed') {
        await chrome.storage.local.set({
          socialError: typeof payload.message === 'string' && payload.message !== ''
            ? payload.message
            : 'Social sign-in failed.',
        });
        await chrome.storage.local.remove(['socialTicket']);
        return;
      }
    } catch {
      // Keep polling until the deadline.
    }

    await sleep(2000);
  }

  await chrome.storage.local.set({
    socialError: 'Social sign-in timed out. Try again.',
  });
  await chrome.storage.local.remove(['socialTicket']);
}

function sleep(ms) {
  return new Promise((resolve) => {
    setTimeout(resolve, ms);
  });
}

chrome.tabs.onUpdated.addListener((_tabId, changeInfo) => {
  if (typeof changeInfo.url !== 'string' || !changeInfo.url.includes('/extension/connected')) {
    return;
  }

  void resumeSocialLogin();
});

chrome.runtime.onStartup.addListener(() => {
  void resumeSocialLogin();
});

chrome.runtime.onInstalled.addListener(() => {
  void resumeSocialLogin();
});

async function resumeSocialLogin() {
  const stored = await chrome.storage.local.get(['apiBaseUrl', 'socialTicket', 'token']);

  if (stored.token || typeof stored.apiBaseUrl !== 'string' || typeof stored.socialTicket !== 'string') {
    return;
  }

  void pollSocialLogin(stored.apiBaseUrl, stored.socialTicket);
}

/**
 * @param {number} count
 */
async function updateBadge(count) {
  try {
    await chrome.action.setBadgeBackgroundColor({ color: '#22d3ee' });
    await chrome.action.setBadgeTextColor({ color: '#09090b' });
  } catch {
    await chrome.action.setBadgeBackgroundColor({ color: '#22d3ee' });
  }
  await chrome.action.setBadgeText({
    text: count > 0 ? String(count) : '',
  });
}

/**
 * @param {unknown} value
 * @returns {value is { id: number, name: string, base_url: string }}
 */
function isSite(value) {
  if (value === null || typeof value !== 'object') {
    return false;
  }

  const site = /** @type {{ id?: unknown, name?: unknown, base_url?: unknown }} */ (value);

  return Number.isInteger(site.id)
    && typeof site.name === 'string'
    && typeof site.base_url === 'string'
    && site.base_url !== '';
}

/**
 * @param {string} pageUrl
 * @param {string} baseUrl
 */
function endpointFromUrl(pageUrl, baseUrl) {
  let page;
  let base;

  try {
    page = new URL(pageUrl);
    base = new URL(baseUrl.endsWith('/') ? baseUrl : `${baseUrl}/`);
  } catch {
    return null;
  }

  if (!['http:', 'https:'].includes(page.protocol) || page.origin !== base.origin) {
    return null;
  }

  const basePath = base.pathname.replace(/\/$/, '');
  let pagePath = page.pathname || '/';

  if (basePath !== '' && basePath !== '/') {
    if (pagePath === basePath || pagePath === `${basePath}/`) {
      pagePath = '/';
    } else if (pagePath.startsWith(`${basePath}/`)) {
      pagePath = pagePath.slice(basePath.length) || '/';
    } else {
      return null;
    }
  }

  if (!pagePath.startsWith('/')) {
    pagePath = `/${pagePath}`;
  }

  return `${pagePath}${page.search}`;
}
