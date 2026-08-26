document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-confirm]').forEach((el) => {
        el.addEventListener('click', (e) => {
            const msg = el.getAttribute('data-confirm') || '¿Confirmas esta acción?';
            if (!confirm(msg)) {
                e.preventDefault();
            }
        });
    });

    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach((alert) => {
        setTimeout(() => {
            const instance = bootstrap.Alert.getOrCreateInstance(alert);
            instance.close();
        }, 5000);
    });

    document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.getAttribute('data-toggle-password'));
            if (!input) {
                return;
            }
            const visible = input.type === 'text';
            input.type = visible ? 'password' : 'text';
            btn.setAttribute('aria-pressed', visible ? 'false' : 'true');
            btn.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
            btn.setAttribute('title', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
            const icono = btn.querySelector('i');
            if (icono) {
                icono.classList.toggle('bi-eye', visible);
                icono.classList.toggle('bi-eye-slash', !visible);
            }
        });
    });

    const formularioClave = document.querySelector('[data-password-match]');
    if (formularioClave) {
        const clave = formularioClave.querySelector('#password');
        const confirmacion = formularioClave.querySelector('#password2');
        const mensaje = formularioClave.querySelector('#password-match-msg');

        const actualizarCoincidencia = () => {
            if (!clave || !confirmacion) {
                return true;
            }
            const coinciden = clave.value === confirmacion.value;
            confirmacion.classList.remove('is-valid', 'is-invalid');
            if (mensaje) {
                mensaje.textContent = '';
                mensaje.classList.remove('text-success', 'text-danger');
            }
            if (confirmacion.value === '') {
                return false;
            }
            confirmacion.classList.add(coinciden ? 'is-valid' : 'is-invalid');
            if (mensaje) {
                mensaje.textContent = coinciden
                    ? 'Las contraseñas coinciden.'
                    : 'Las contraseñas no coinciden.';
                mensaje.classList.add(coinciden ? 'text-success' : 'text-danger');
            }
            return coinciden;
        };

        clave?.addEventListener('input', actualizarCoincidencia);
        confirmacion?.addEventListener('input', actualizarCoincidencia);

        formularioClave.addEventListener('submit', (e) => {
            if (!actualizarCoincidencia()) {
                e.preventDefault();
                confirmacion?.focus();
            }
        });
    }
});
