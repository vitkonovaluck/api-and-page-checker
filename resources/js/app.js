import './response-time-chart';

document.addEventListener('color-scheme-changed', (event) => {
    const scheme = event.detail?.scheme;

    if (typeof scheme !== 'string') {
        return;
    }

    const root = document.documentElement;
    root.dataset.theme = scheme;
    root.classList.toggle('dark', scheme.startsWith('dark'));
});
