import Swal from 'sweetalert2';

// Expose Swal so inline Alpine handlers (e.g. the delete confirmation) can call it.
window.Swal = Swal;

// A small top-right toast preset for success/error notifications.
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3500,
    timerProgressBar: true,
    showClass: { popup: 'app-toast-in' },
    hideClass: { popup: 'app-toast-out' },
    didOpen: (el) => {
        el.addEventListener('mouseenter', Swal.stopTimer);
        el.addEventListener('mouseleave', Swal.resumeTimer);
    },
});

function fireToast(type, message) {
    Toast.fire({
        icon: type,
        title: message ?? '',
        customClass: {
            popup: 'app-toast app-toast--' + type,
            title: 'app-toast-title',
            timerProgressBar: 'app-toast-progress app-toast-progress--' + type,
        },
    });
}

// Livewire components fire: $this->dispatch('notify', type: 'success', message: '...')
function registerNotify() {
    window.Livewire.on('notify', (event) => {
        const payload = Array.isArray(event) ? event[0] : event;
        fireToast(payload?.type ?? 'success', payload?.message);
    });
}

// Register whether or not Livewire has already booted.
if (window.Livewire) {
    registerNotify();
} else {
    document.addEventListener('livewire:init', registerNotify);
}

/* ------------------------------------------------------------------ */
/* Global progress bar — shows during any Livewire request or SPA nav  */
/* ------------------------------------------------------------------ */
function setupProgressBar() {
    const bar = document.getElementById('app-progress');
    if (!bar) return;

    let pending = 0;
    let hideTimer = null;

    const show = () => {
        clearTimeout(hideTimer);
        bar.style.transition = 'none';
        bar.style.opacity = '1';
        bar.style.width = '0%';
        void bar.offsetWidth; // reflow so the width transition restarts
        bar.style.transition = 'width .5s cubic-bezier(.2,.7,.2,1)';
        bar.style.width = '80%';
    };
    const finish = () => {
        bar.style.width = '100%';
        hideTimer = setTimeout(() => {
            bar.style.opacity = '0';
            bar.style.width = '0%';
        }, 250);
    };
    const start = () => { if (pending++ === 0) show(); };
    const done = () => { pending = Math.max(0, pending - 1); if (pending === 0) finish(); };

    if (window.Livewire) {
        window.Livewire.hook('request', ({ succeed, fail }) => {
            start();
            succeed(() => done());
            fail(() => done());
        });
    }
    document.addEventListener('livewire:navigate', start);
    document.addEventListener('livewire:navigated', done);
}
document.addEventListener('livewire:init', setupProgressBar);

/* ------------------------------------------------------------------ */
/* Alpine helper: countUp — animate a number from 0, honouring         */
/* prefers-reduced-motion.  Usage: x-data="countUp(1234)" x-text="display" */
/* ------------------------------------------------------------------ */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('countUp', (target = 0, duration = 900) => ({
        display: '0',
        init() {
            const fmt = (n) => Math.round(n).toLocaleString();
            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (reduce || target <= 0) { this.display = fmt(target); return; }
            const startAt = performance.now();
            const ease = (t) => 1 - Math.pow(1 - t, 3);
            const step = (now) => {
                const p = Math.min(1, (now - startAt) / duration);
                this.display = fmt(ease(p) * target);
                if (p < 1) requestAnimationFrame(step);
                else this.display = fmt(target);
            };
            requestAnimationFrame(step);
        },
    }));
});
