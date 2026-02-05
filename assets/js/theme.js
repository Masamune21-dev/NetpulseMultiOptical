document.addEventListener('DOMContentLoaded', () => {
    fetch('api/settings.php')
        .then(r => r.json())
        .then(d => {
            const theme = d.theme || 'light';
            document.body.dataset.theme = theme;
        })
        .catch(() => {
            document.body.dataset.theme = 'light';
        });
});
