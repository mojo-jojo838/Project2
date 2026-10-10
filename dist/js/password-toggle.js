document.querySelectorAll('[data-password-target]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordTarget);
        if (!input) return;
        const showing = input.type === 'password';
        input.type = showing ? 'text' : 'password';
        button.textContent = showing ? 'Hide' : 'Show';
        button.setAttribute('aria-pressed', String(showing));
        button.setAttribute('aria-label', `${showing ? 'Hide' : 'Show'} ${input.id === 'password_confirmation' ? 'confirm password' : 'password'}`);
    });
});
