<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'eNPS — National Pension System' }}</title>

    {{-- Set the theme before paint to avoid a flash of the wrong colour scheme. --}}
    <script>
        (function () {
            const t = localStorage.getItem('theme');
            if (t === 'dark' || (!t && matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white font-sans text-slate-900 antialiased selection:bg-indigo-500/20 dark:bg-slate-950 dark:text-slate-100">
    {{-- Custom cursor --}}
    <div class="cursor-ring" id="cursor-ring"></div>
    <div class="cursor-dot" id="cursor-dot"></div>

    {{ $slot }}

    <script>
        (function () {
            const fine = matchMedia('(pointer: fine)').matches;

            // --- Custom cursor (dot instant, ring eased) ---
            const ring = document.getElementById('cursor-ring');
            const dot = document.getElementById('cursor-dot');
            if (fine && ring && dot) {
                let mx = innerWidth / 2, my = innerHeight / 2, rx = mx, ry = my;
                addEventListener('mousemove', (e) => {
                    mx = e.clientX; my = e.clientY;
                    dot.style.transform = `translate(${mx}px, ${my}px)`;
                });
                (function loop() {
                    rx += (mx - rx) * 0.18; ry += (my - ry) * 0.18;
                    ring.style.transform = `translate(${rx}px, ${ry}px)`;
                    requestAnimationFrame(loop);
                })();
                document.querySelectorAll('a, button, [data-cursor-grow]').forEach((el) => {
                    el.addEventListener('mouseenter', () => ring.classList.add('grow'));
                    el.addEventListener('mouseleave', () => ring.classList.remove('grow'));
                });
            }

            // --- Mouse-parallax on floating objects ---
            const layers = document.querySelectorAll('[data-parallax]');
            if (fine && layers.length) {
                addEventListener('mousemove', (e) => {
                    const cx = e.clientX / innerWidth - 0.5;
                    const cy = e.clientY / innerHeight - 0.5;
                    layers.forEach((el) => {
                        const d = parseFloat(el.dataset.depth || '20');
                        el.style.transform = `translate3d(${-cx * d}px, ${-cy * d}px, 0)`;
                    });
                });
            }

            // --- Reveal on scroll ---
            const io = new IntersectionObserver((entries) => {
                entries.forEach((en) => {
                    if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
                });
            }, { threshold: 0.12 });
            document.querySelectorAll('.reveal').forEach((el) => io.observe(el));

            // --- Theme toggle ---
            const toggle = document.getElementById('theme-toggle');
            if (toggle) {
                toggle.addEventListener('click', () => {
                    const isDark = document.documentElement.classList.toggle('dark');
                    localStorage.setItem('theme', isDark ? 'dark' : 'light');
                });
            }
        })();
    </script>
</body>
</html>
