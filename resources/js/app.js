import Swal from 'sweetalert2';

// Expose Swal so inline Alpine handlers (e.g. the delete confirmation) can call it.
window.Swal = Swal;

// A small top-right toast preset for success/error notifications.
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
});

// Livewire components fire: $this->dispatch('notify', type: 'success', message: '...')
function registerNotify() {
    window.Livewire.on('notify', (event) => {
        const payload = Array.isArray(event) ? event[0] : event;
        Toast.fire({
            icon: payload?.type ?? 'success',
            title: payload?.message ?? '',
        });
    });
}

// Register whether or not Livewire has already booted.
if (window.Livewire) {
    registerNotify();
} else {
    document.addEventListener('livewire:init', registerNotify);
}
