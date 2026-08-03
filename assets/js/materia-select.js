(function () {
    document.querySelectorAll('.course-materia-select').forEach(function (select) {
        select.addEventListener('change', function () {
            var option = select.options[select.selectedIndex];
            var url = option.getAttribute('data-url');
            if (url) {
                window.location.href = url;
                return;
            }

            var courseId = select.getAttribute('data-course-id');
            if (courseId) {
                window.location.href = '?id=' + encodeURIComponent(courseId) + '&pestaña=lecciones&modulo=' + encodeURIComponent(select.value);
            }
        });
    });
})();
