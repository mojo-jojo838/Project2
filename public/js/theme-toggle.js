(() => {
    const button = document.getElementById('theme-toggle');
    if (!button) return;
    const label = document.getElementById('theme-toggle-label');
    const icon = document.getElementById('theme-toggle-icon');
    function update() {
        const dark = document.documentElement.dataset.theme === 'dark';
        label.textContent = dark ? 'Light mode' : 'Dark mode';
        icon.className = dark ? 'fi fi-br-sun' : 'fi fi-br-moon';
        button.setAttribute('aria-pressed', String(dark));
        button.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
    }
    button.addEventListener('click', () => {
        const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = next;
        try { localStorage.setItem('dfitness-theme', next); } catch (error) {}
        update();
    });
    update();
})();
