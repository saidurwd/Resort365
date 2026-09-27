{{-- Apply the colour mode before first paint. AdminLTE's ColorMode uses the "lte-theme" key; a signed-in
     user's saved theme ($userTheme, shared by the IAM module) wins over what this browser remembered. --}}
<script>
    (() => {
        let theme = @json($userTheme ?? null);
        try {
            if (theme) { localStorage.setItem('lte-theme', theme); } else { theme = localStorage.getItem('lte-theme'); }
        } catch (e) {}
        const dark = theme === 'dark' || ((!theme || theme === 'auto') && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
        document.documentElement.setAttribute('data-lte-theme-resolved', '');
    })();
</script>
