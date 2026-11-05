<script>
    (() => {
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        let preference;
        try { preference = localStorage.getItem('attendance-theme'); } catch {}

        const apply = (theme) => {
            document.documentElement.dataset.theme = theme;
            document.documentElement.classList.toggle('dark', theme === 'dark');
            document.documentElement.style.colorScheme = theme;
            document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
                button.setAttribute('aria-pressed', String(theme === 'dark'));
                button.textContent = theme === 'dark' ? 'Light mode' : 'Dark mode';
            });
            document.querySelector('meta[name="theme-color"]')?.setAttribute('content', theme === 'dark' ? '#111c22' : '#f3f6f5');
        };
        apply(preference === 'dark' || preference === 'light' ? preference : (media.matches ? 'dark' : 'light'));
        document.addEventListener('DOMContentLoaded', () => apply(document.documentElement.dataset.theme));
        document.addEventListener('click', (event) => {
            if (!event.target.closest('[data-theme-toggle]')) return;
            preference = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
            try { localStorage.setItem('attendance-theme', preference); } catch {}
            apply(preference);
        });
        media.addEventListener('change', () => {
            if (preference !== 'light' && preference !== 'dark') apply(media.matches ? 'dark' : 'light');
        });
        window.addEventListener('storage', (event) => {
            if (event.key !== 'attendance-theme' && event.key !== null) return;
            preference = event.newValue;
            apply(preference === 'dark' || preference === 'light' ? preference : (media.matches ? 'dark' : 'light'));
        });
    })();
</script>
<link rel="stylesheet" href="{{ asset('css/theme.css') }}">
