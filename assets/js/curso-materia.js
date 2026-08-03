(function () {
    var select = document.getElementById('selector-materia');
    if (!select) {
        return;
    }

    var params = new URLSearchParams(window.location.search);
    var courseId = params.get('id');
    if (!courseId) {
        return;
    }

    select.addEventListener('change', function () {
        var url = new URL(window.location.href);
        url.searchParams.set('id', courseId);
        url.searchParams.set('pestaña', 'lecciones');
        url.searchParams.set('modulo', select.value);
        window.location.href = url.toString();
    });
})();
