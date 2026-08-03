(function () {
    const config = document.getElementById('lesson-progress');
    if (!config) return;

    const REQUERIDO = parseInt(config.dataset.requerido || '600', 10);
    const lessonId = config.dataset.lessonId;
    const videoType = config.dataset.videoType;
    const youtubeId = config.dataset.youtubeId || '';
    const csrfToken = config.dataset.csrf;
    const postUrl = config.dataset.url;
    const yaCompletada = config.dataset.completada === '1';

    const elTiempo = document.getElementById('video-tiempo-texto');
    const elBarra = document.getElementById('video-tiempo-barra');
    const btnCompletar = document.getElementById('btn-marcar-completada');

    let segundosSesion = 0;
    let tickInterval = null;
    let pendienteSync = 0;
    let puedeCompletar = yaCompletada;

    function formatearTiempo(total) {
        const mins = Math.floor(total / 60);
        const secs = total % 60;
        return mins + ':' + String(secs).padStart(2, '0');
    }

    function porcentajeSesion() {
        return Math.min(100, Math.round((segundosSesion / REQUERIDO) * 100));
    }

    function actualizarUI() {
        if (elTiempo) {
            elTiempo.textContent = formatearTiempo(segundosSesion) + ' / ' + formatearTiempo(REQUERIDO);
        }
        if (elBarra) {
            elBarra.style.width = porcentajeSesion() + '%';
            elBarra.setAttribute('aria-valuenow', String(porcentajeSesion()));
        }
        if (btnCompletar && !yaCompletada) {
            btnCompletar.disabled = !puedeCompletar;
        }
    }

    function syncServidor(segundos) {
        if (segundos < 1) return Promise.resolve();
        const body = new FormData();
        body.append('ajax', '1');
        body.append('accion', 'registrar_tiempo_video');
        body.append('token_csrf', csrfToken);
        body.append('segundos', String(segundos));

        return fetch(postUrl, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && typeof data.total === 'number') {
                    segundosSesion = data.total;
                    puedeCompletar = data.total >= REQUERIDO;
                    actualizarUI();
                }
            })
            .catch(function () {});
    }

    function iniciarContador() {
        if (tickInterval || yaCompletada) return;
        tickInterval = setInterval(function () {
            segundosSesion++;
            pendienteSync++;
            if (segundosSesion >= REQUERIDO) {
                puedeCompletar = true;
            }
            actualizarUI();
            if (pendienteSync >= 5) {
                const enviar = pendienteSync;
                pendienteSync = 0;
                syncServidor(enviar);
            }
        }, 1000);
    }

    function detenerContador() {
        if (!tickInterval) return;
        clearInterval(tickInterval);
        tickInterval = null;
        if (pendienteSync > 0) {
            const enviar = pendienteSync;
            pendienteSync = 0;
            syncServidor(enviar);
        }
    }

    function initYouTube() {
        if (!youtubeId || typeof YT === 'undefined') return;
        new YT.Player('yt-player', {
            videoId: youtubeId,
            playerVars: { rel: 0, modestbranding: 1 },
            events: {
                onStateChange: function (event) {
                    if (event.data === YT.PlayerState.PLAYING) {
                        iniciarContador();
                    } else {
                        detenerContador();
                    }
                }
            }
        });
    }

    if (videoType === 'youtube') {
        window.onYouTubeIframeAPIReady = initYouTube;
        if (window.YT && YT.Player) {
            initYouTube();
        }
    }

    if (videoType === 'html5') {
        const video = document.getElementById('lesson-video');
        if (video) {
            video.addEventListener('play', iniciarContador);
            video.addEventListener('pause', detenerContador);
            video.addEventListener('ended', detenerContador);
        }
    }

    if (btnCompletar) {
        btnCompletar.addEventListener('click', function () {
            if (btnCompletar.disabled) return;
            btnCompletar.disabled = true;
            const body = new FormData();
            body.append('ajax', '1');
            body.append('accion', 'marcar_leccion_completada');
            body.append('token_csrf', csrfToken);

            fetch(postUrl, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.ok) {
                        window.location.reload();
                    } else {
                        alert(data && data.mensaje ? data.mensaje : 'No se pudo marcar como completada.');
                        btnCompletar.disabled = false;
                    }
                })
                .catch(function () {
                    alert('Error de conexión. Intenta de nuevo.');
                    btnCompletar.disabled = false;
                });
        });
    }

    window.addEventListener('beforeunload', detenerContador);
    actualizarUI();
})();
