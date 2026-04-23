// ══════════════════════════════════════════════════════════════════════
// DEBUG TABS — Pegar en consola del navegador en /user-account/
// ══════════════════════════════════════════════════════════════════════
(function() {
    'use strict';

    var LOG = function(tag, msg, data) {
        var style = 'background:#1a1a2e;color:#e94560;padding:2px 6px;border-radius:3px;font-weight:bold;';
        if (data !== undefined) {
            console.log('%c[FPLMS:' + tag + ']', style, msg, data);
        } else {
            console.log('%c[FPLMS:' + tag + ']', style, msg);
        }
    };

    // 1. Verificar que existe el contenedor de tabs
    var blocks = document.querySelector('.masterstudy-enrolled-courses-tabs__blocks');
    if (!blocks) {
        LOG('ERROR', '❌ .masterstudy-enrolled-courses-tabs__blocks NO encontrado. ¿Estás en la sección de estudiante?');
        return;
    }
    LOG('INIT', '✅ blocks encontrado', blocks);

    // 2. Listar todos los tabs presentes
    var allTabs = blocks.querySelectorAll('[data-status]');
    LOG('TABS', 'Tabs encontrados: ' + allTabs.length);
    allTabs.forEach(function(t) {
        LOG('TABS', '  → data-status="' + t.dataset.status + '" active=' + t.classList.contains('masterstudy-enrolled-courses-tabs__block_active'));
    });

    // 3. Verificar la lista de cursos
    var courseList = document.querySelector('.masterstudy-enrolled-courses');
    LOG('LIST', courseList ? '✅ .masterstudy-enrolled-courses encontrado' : '❌ NO encontrado');

    // 4. Interceptar TODOS los clicks en el contenedor de tabs (antes y después)
    blocks.addEventListener('click', function(e) {
        var b = e.target.closest('[data-status]');
        LOG('CLICK-CAPTURE', 'capture click en data-status="' + (b ? b.dataset.status : '?') + '"', {
            target: e.target,
            defaultPrevented: e.defaultPrevented,
            cancelBubble: e.cancelBubble
        });
    }, true);  // capture

    blocks.addEventListener('click', function(e) {
        var b = e.target.closest('[data-status]');
        LOG('CLICK-BUBBLE', 'bubble click en data-status="' + (b ? b.dataset.status : '?') + '"', {
            target: e.target,
            cancelBubble: e.cancelBubble
        });
    }, false); // bubble

    // 5. Observar cambios en la lista de cursos
    if (courseList) {
        var mutCount = 0;
        new MutationObserver(function(muts) {
            mutCount++;
            var cards = courseList.querySelectorAll('.masterstudy-course-card, .masterstudy-enrolled-courses-list__item, .masterstudy-enrolled-courses__item');
            var visible = 0;
            cards.forEach(function(c) { if (c.style.display !== 'none') visible++; });
            LOG('MUT-' + mutCount, 'DOM cambió. Cards totales=' + cards.length + ' visibles=' + visible);
        }).observe(courseList, { childList: true, subtree: true });
    }

    // 6. Parchear XMLHttpRequest para ver peticiones AJAX
    var origOpen = XMLHttpRequest.prototype.open;
    var origSend = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function(method, url) {
        this._fplms_url = url;
        return origOpen.apply(this, arguments);
    };
    XMLHttpRequest.prototype.send = function(body) {
        var url = this._fplms_url || '';
        if (url.indexOf('admin-ajax') !== -1 || url.indexOf('wp-json') !== -1) {
            LOG('AJAX', 'Request: ' + url, body ? body.toString().substring(0, 200) : '(no body)');
            var xhr = this;
            var origOnReadyState = this.onreadystatechange;
            this.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    LOG('AJAX', 'Response ' + xhr.status + ': ' + xhr.responseText.substring(0, 300));
                }
                if (origOnReadyState) origOnReadyState.apply(this, arguments);
            };
        }
        return origSend.apply(this, arguments);
    };

    // 7. Parchear fetch también
    var origFetch = window.fetch;
    window.fetch = function(input, init) {
        var url = (typeof input === 'string') ? input : input.url;
        if (url && (url.indexOf('admin-ajax') !== -1 || url.indexOf('wp-json') !== -1)) {
            LOG('FETCH', 'Request: ' + url, init ? JSON.stringify(init).substring(0, 200) : '(no init)');
            return origFetch.apply(this, arguments).then(function(resp) {
                var cloned = resp.clone();
                cloned.text().then(function(txt) {
                    LOG('FETCH', 'Response ' + resp.status + ': ' + txt.substring(0, 300));
                });
                return resp;
            });
        }
        return origFetch.apply(this, arguments);
    };

    LOG('READY', '🔍 Debug activo. Ahora haz clic en los tabs y observa los logs.');
    LOG('INSTRUCCIONES', 
        '1. Haz clic en "Todos" → observa los logs\n' +
        '2. Haz clic en "En Progreso" → observa los logs\n' +
        '3. Haz clic en "Todos" de nuevo → observa los logs\n' +
        '4. Haz clic en "Completado" → observa los logs\n' +
        'Reporta lo que ves después de cada clic.'
    );
})();
