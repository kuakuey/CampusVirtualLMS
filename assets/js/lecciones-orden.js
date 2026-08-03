(function () {
    const list = document.getElementById('lista-lecciones-ordenables');
    if (!list || list.dataset.sortable !== '1' || typeof Sortable === 'undefined') {
        return;
    }

    function actualizarNumeros() {
        list.querySelectorAll('[data-lesson-id]').forEach(function (item, index) {
            const badge = item.querySelector('.lesson-order-badge');
            if (badge) {
                badge.textContent = String(index + 1);
            }
        });
    }

    Sortable.create(list, {
        handle: '.lesson-drag-handle',
        animation: 150,
        ghostClass: 'lesson-item-ghost',
        onEnd: function () {
            const orden = Array.from(list.querySelectorAll('[data-lesson-id]')).map(function (item) {
                return item.dataset.lessonId;
            });
            const body = new FormData();
            body.append('accion', 'reordenar_lecciones');
            body.append('token_csrf', list.dataset.csrf);
            body.append('subcourse_id', list.dataset.subcourseId);
            body.append('orden', JSON.stringify(orden));

            fetch(list.dataset.url, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        window.alert(data.mensaje || 'No se pudo guardar el orden.');
                        return;
                    }
                    actualizarNumeros();
                })
                .catch(function () {
                    window.alert('No se pudo guardar el orden.');
                });
        },
    });
})();
