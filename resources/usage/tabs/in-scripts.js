// window.tabs is available once the tabs script has loaded.
// <x-widget.tabs id="settings" …> with a tab key="security"
tabs.open('settings', 'security');

// Or any element of the tabs.
tabs.open(document.querySelector('[data-tabs="settings"]'), 'security');
