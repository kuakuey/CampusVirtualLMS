(function () {
    const config = document.getElementById('lesson-progress');
    if (!config) return;

    const REQUERIDO = parseInt(config.dataset.requerido || '600', 10);
    const videoType = config.dataset.videoType;
    const youtubeId = config.dataset.youtubeId || '';
    const csrfToken = config.dataset.csrf;
    const postUrl = config.dataset.url;
    const yaCompletada = config.dataset.completada === '1';
    const INTERVALO_AVISO = 15;
    const MAX_AVISO = 120;

    const elTiempo = document.getElementById('video-tiempo-texto');
    const elBarra = document.getElementById('video-tiempo-barra');
    const btnCompletar = document.getElementById('btn-marcar-completada');

    let totalServidor = 0;
    let sinEnviar = 0;
    let sobranteMs = 0;
    let marcaInicio = 0;
    let tickInterval = null;
    let videoReproduciendo = false;
    let puedeCompletar = yaCompletada;

    function formatearTiempo(total) {
        const mins = Math.floor(total / 60);
        const secs = total % 60;
        return mins + ':' + String(secs).padStart(2, '0');
    }

    function segundosTramo() {
        if (!marcaInicio) return 0;
        return Math.max(0, Math.floor((Date.now() - marcaInicio) / 1000));
    }

    function tiempoVisible() {
        return totalServidor + sinEnviar + segundosTramo();
    }

    function porcentajeSesion() {
        return Math.min(100, Math.round((tiempoVisible() / REQUERIDO) * 100));
    }

    function actualizarUI() {
        const visible = tiempoVisible();
        if (visible >= REQUERIDO) {
            puedeCompletar = true;
        }
        if (elTiempo) {
            elTiempo.textContent = formatearTiempo(visible) + ' / ' + formatearTiempo(REQUERIDO);
        }
        if (elBarra) {
            elBarra.style.width = porcentajeSesion() + '%';
            elBarra.setAttribute('aria-valuenow', String(porcentajeSesion()));
        }
        if (btnCompletar && !yaCompletada) {
            btnCompletar.disabled = !puedeCompletar;
        }
    }

    function congelarTramo() {
        if (!marcaInicio) return;
        const transcurridoMs = Date.now() - marcaInicio;
        marcaInicio = 0;
        const delta = Math.floor(transcurridoMs / 1000);
        sobranteMs = transcurridoMs - (delta * 1000);
        if (delta > 0) {
            sinEnviar += delta;
        }
    }

    function pararIntervalo() {
        if (!tickInterval) return;
        clearInterval(tickInterval);
        tickInterval = null;
    }

    function cuerpoAviso(segundos) {
        const body = new FormData();
        body.append('ajax', '1');
        body.append('accion', 'registrar_tiempo_video');
        body.append('token_csrf', csrfToken);
        body.append('segundos', String(segundos));
        return body;
    }

    function syncServidor(segundos, alCerrar) {
        if (segundos < 1) return Promise.resolve();
        const body = cuerpoAviso(segundos);

        if (alCerrar && navigator.sendBeacon) {
            if (!navigator.sendBeacon(postUrl, body)) {
                totalServidor -= segundos;
                sinEnviar += segundos;
            }
            return Promise.resolve();
        }

        return fetch(postUrl, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || data.ok === false) {
                    totalServidor -= segundos;
                    sinEnviar += segundos;
                } else if (typeof data.total === 'number' && data.total > totalServidor) {
                    totalServidor = data.total;
                }
                puedeCompletar = tiempoVisible() >= REQUERIDO;
                actualizarUI();
            })
            .catch(function () {
                totalServidor -= segundos;
                sinEnviar += segundos;
                actualizarUI();
            });
    }

    function enviarPendiente(alCerrar) {
        congelarTramo();
        const seguir = !alCerrar && videoReproduciendo && document.visibilityState !== 'hidden';
        if (sinEnviar < 1) {
            if (seguir) reanudarReloj();
            return Promise.resolve();
        }
        let enviar = sinEnviar;
        if (enviar > MAX_AVISO) {
            enviar = MAX_AVISO;
        }
        sinEnviar -= enviar;
        totalServidor += enviar;
        const aviso = syncServidor(enviar, alCerrar);
        if (seguir) reanudarReloj();
        return aviso;
    }

    function tick() {
        actualizarUI();
        if (sinEnviar + segundosTramo() >= INTERVALO_AVISO) {
            enviarPendiente(false);
        }
    }

    function asegurarIntervalo() {
        if (tickInterval || yaCompletada) return;
        tickInterval = setInterval(tick, 1000);
    }

    function reanudarReloj() {
        if (yaCompletada || marcaInicio) return;
        if (!videoReproduciendo || document.visibilityState === 'hidden') return;
        marcaInicio = Date.now() - sobranteMs;
        sobranteMs = 0;
        asegurarIntervalo();
    }

    function detenerYEnviar(alCerrar) {
        pararIntervalo();
        const aviso = enviarPendiente(alCerrar);
        actualizarUI();
        return aviso;
    }

    function initYouTube() {
        if (!youtubeId || typeof YT === 'undefined') return;
        new YT.Player('yt-player', {
            videoId: youtubeId,
            playerVars: { rel: 0, modestbranding: 1 },
            events: {
                onStateChange: function (event) {
                    if (event.data === YT.PlayerState.PLAYING) {
                        videoReproduciendo = true;
                        reanudarReloj();
                    } else if (event.data === YT.PlayerState.BUFFERING) {
                        congelarTramo();
                        pararIntervalo();
                    } else {
                        videoReproduciendo = false;
                        detenerYEnviar(false);
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
            video.addEventListener('playing', function () {
                videoReproduciendo = true;
                reanudarReloj();
            });
            video.addEventListener('waiting', function () {
                congelarTramo();
                pararIntervalo();
            });
            video.addEventListener('pause', function () {
                videoReproduciendo = false;
                detenerYEnviar(false);
            });
            video.addEventListener('ended', function () {
                videoReproduciendo = false;
                detenerYEnviar(false);
            });
        }
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            detenerYEnviar(false);
            return;
        }
        if (videoReproduciendo) {
            reanudarReloj();
        }
    });

    window.addEventListener('pagehide', function () {
        detenerYEnviar(true);
    });

    if (btnCompletar) {
        btnCompletar.addEventListener('click', function () {
            if (btnCompletar.disabled) return;
            btnCompletar.disabled = true;
            detenerYEnviar(false).then(function () {
                if (tiempoVisible() < REQUERIDO) {
                    puedeCompletar = false;
                    actualizarUI();
                    return;
                }
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
        });
    }

    actualizarUI();
})();
