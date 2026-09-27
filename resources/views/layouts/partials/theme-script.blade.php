{{-- Apply the saved colour mode before first paint (AdminLTE's ColorMode uses the same "lte-theme" key).
     TODO(step-0.5): prefer the theme stored on the user's profile. --}}
<script>
    (() => {
        let theme = null;
        try { theme = localStorage.getItem('lte-theme'); } catch (e) {}
        const dark = theme === 'dark' || ((!theme || theme === 'auto') && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
        document.documentElement.setAttribute('data-lte-theme-resolved', '');
    })();
</script>
