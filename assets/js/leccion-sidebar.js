(function () {
    var drawer = document.getElementById('lesson-sidebar-drawer');
    var backdrop = document.getElementById('lesson-sidebar-backdrop');
    var btnOpen = document.getElementById('btn-abrir-lecciones');
    var btnClose = document.getElementById('btn-cerrar-lecciones');

    if (!drawer || !backdrop || !btnOpen) {
        return;
    }

    function openSidebar() {
        drawer.classList.add('is-open');
        backdrop.classList.add('is-open');
        backdrop.setAttribute('aria-hidden', 'false');
        document.body.classList.add('lesson-sidebar-open');
    }

    function closeSidebar() {
        drawer.classList.remove('is-open');
        backdrop.classList.remove('is-open');
        backdrop.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('lesson-sidebar-open');
    }

    btnOpen.addEventListener('click', openSidebar);

    if (btnClose) {
        btnClose.addEventListener('click', closeSidebar);
    }

    backdrop.addEventListener('click', closeSidebar);

    drawer.querySelectorAll('a[href]').forEach(function (link) {
        link.addEventListener('click', closeSidebar);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && drawer.classList.contains('is-open')) {
            closeSidebar();
        }
    });
})();
