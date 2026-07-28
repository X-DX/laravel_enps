{{-- Applies the saved (or system) theme before paint to avoid a flash of the
     wrong colour scheme. Include this in every layout's <head>. --}}
<script>
    (function () {
        const t = localStorage.getItem('theme');
        if (t === 'dark' || (!t && matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    })();
</script>
