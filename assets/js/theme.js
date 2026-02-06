document.addEventListener('DOMContentLoaded', () => {
    fetch('api/settings.php')
        .then(r => r.json())
        .then(d => {
            const theme = d.theme || 'light';
            document.documentElement.dataset.theme = theme;
            document.body.dataset.theme = theme;
            try {
                localStorage.setItem('theme', theme);
            } catch (e) { }
        })
        .catch(() => {
            document.documentElement.dataset.theme = 'light';
            document.body.dataset.theme = 'light';
        });
});
