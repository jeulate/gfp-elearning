/**
 * FairPlay LMS — Traduccion de tipos de pregunta (MasterStudy Quiz Builder)
 *
 * Funciona en el editor visual React de quizzes, incluyendo rutas SPA:
 * - /user-account/edit-course/{course}/curriculum/sections/{section}/quiz/{quiz}
 * - /edit-quiz/{quiz}
 *
 * Robusto ante:
 * - Navegación SPA (Vue Router) desde /user-account/
 * - Re-renders de React-select que sobreescriben texto traducido
 * - Timer que expira antes de que el usuario llegue al builder
 */
(function () {
    'use strict';

    /* ── Detección de ruta ─────────────────────────────────────────────────────── */

    function isQuizBuilderPath() {
        var path = (window.location && window.location.pathname) ? window.location.pathname : '';
        return (
            /\/user-account\/edit-course\/\d+/.test(path) ||
            /\/edit-quiz\/\d+/.test(path) ||
            /\/edit-course\/\d+/.test(path) ||
            /\/curriculum\//.test(path) ||
            /\/quiz\/\d+/.test(path)
        );
    }

    /* ── Inyección CSS del botón "Agregar" (fallback por si button-fix.js no carga) ── */

    function injectButtonCSS() {
        var id = 'fplms-chakra-btn-fix-css';
        if (document.getElementById(id)) { return; }
        var s = document.createElement('style');
        s.id = id;
        s.textContent =
            'button.chakra-button.chakra-j6rous,' +
            'button.chakra-button[class*="j6r"]{' +
            'width:52px!important;min-width:52px!important;' +
            'max-width:52px!important;flex:0 0 52px!important;}' +
            'button.chakra-button.chakra-j6rous > div,' +
            'button.chakra-button[class*="j6r"] > div{' +
            'width:100%!important;justify-content:center!important;}';
        (document.head || document.documentElement).appendChild(s);
    }

    /* ── Tabla de traducciones ────────────────────────────────────────────────────── */

    /* Etiquetas propias del plugin MasterStudy (en español) y sus variantes en inglés.
       Prioridad: las etiquetas que el plugin ya muestra en inglés se traducen al español
       que el propio plugin usa para mantener coherencia visual. */
    var translations = {
        /* --- Verdadero/Falso --- */
        'true-false':           'Verdadero/Falso',
        'true/false':           'Verdadero/Falso',
        'true false':           'Verdadero/Falso',
        'truefalse':            'Verdadero/Falso',
        /* --- Elección única (single choice — etiqueta del plugin) --- */
        'single choice':        'Elección única',
        'single_choice':        'Elección única',
        /* --- Opción múltiple (multiple choice — etiqueta del plugin) --- */
        'multiple choice':      'Opción múltiple',
        'multiple_choice':      'Opción múltiple',
        /* --- Coincidencia --- */
        'matching':             'Coincidencia',
        /* --- Rellenar el espacio --- */
        'fill in the gap':      'Rellenar el espacio',
        'fill in the blank':    'Rellenar el espacio',
        'fill_in_the_gap':      'Rellenar el espacio',
        'fill_in_the_blank':    'Rellenar el espacio',
        /* --- Ordenar --- */
        'ordering':             'Ordenar',
        /* --- Coincidencia de imágenes --- */
        'image matching':       'Coincidencia de imágenes',
        'image_matching':       'Coincidencia de imágenes',
        /* --- Respuesta corta --- */
        'short answer':         'Respuesta corta',
        'short_answer':         'Respuesta corta',
        /* --- Palabras clave --- */
        'keywords':             'Palabras clave',
        /* --- Ensayo --- */
        'essay':                'Ensayo',
        /* --- Punto de calor --- */
        'hotspot':              'Punto de calor',
        'hot spot':             'Punto de calor'
    };

    function normalizeLabel(value) {
        return String(value || '')
            .replace(/[‐‑‒–—−]/g, '-')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase();
    }

    /* ── Traducción de nodos de texto ───────────────────────────────────────────────────── */

    function translateTextNode(node) {
        if (!node || node.nodeType !== 3) { return; }
        var raw = node.nodeValue;
        if (!raw) { return; }
        var core = raw.trim();
        if (!core) { return; }
        var translated = translations[normalizeLabel(core)];
        if (!translated) { return; }
        var leading  = raw.match(/^\s*/);
        var trailing = raw.match(/\s*$/);
        node.nodeValue = (leading ? leading[0] : '') + translated + (trailing ? trailing[0] : '');
    }

    function applyTranslations(root) {
        var scope = (root && root.nodeType) ? root : document.body;
        if (!scope || typeof document.createTreeWalker !== 'function') { return; }
        if (scope.nodeType === 3) { translateTextNode(scope); return; }
        var walker = document.createTreeWalker(scope, NodeFilter.SHOW_TEXT, null, false);
        var current;
        while ((current = walker.nextNode())) { translateTextNode(current); }
    }

    function runTranslations(root) {
        if (!isQuizBuilderPath()) { return; }
        applyTranslations(root || document.body);
    }

    /* ── Polling con reinicio en cambio de ruta SPA ───────────────────────────────────────── */

    var _pollingTimer = null;

    function startPolling() {
        injectButtonCSS();
        if (_pollingTimer) { clearInterval(_pollingTimer); _pollingTimer = null; }
        _pollingTimer = setInterval(function () {
            if (!isQuizBuilderPath()) {
                clearInterval(_pollingTimer);
                _pollingTimer = null;
                return;
            }
            runTranslations(document.body);
        }, 250);
    }

    // Keepalive: si el polling paró pero seguimos en ruta builder, lo reinicia
    setInterval(function () {
        if (isQuizBuilderPath() && !_pollingTimer) { startPolling(); }
    }, 3000);

    /* ── Arranque inicial ──────────────────────────────────────────────────────────────── */

    injectButtonCSS();
    runTranslations(document.body);
    if (isQuizBuilderPath()) { startPolling(); }

    /* ── MutationObserver: captura re-renders de React/Vue ──────────────────────────────── */

    if (window.MutationObserver) {
        new MutationObserver(function (mutations) {
            if (!isQuizBuilderPath()) { return; }
            for (var i = 0; i < mutations.length; i++) {
                var m = mutations[i];
                if (m.addedNodes && m.addedNodes.length) {
                    for (var j = 0; j < m.addedNodes.length; j++) {
                        var added = m.addedNodes[j];
                        if (added && (added.nodeType === 1 || added.nodeType === 3)) {
                            applyTranslations(added);
                        }
                    }
                }
                if (m.type === 'characterData' && m.target) {
                    translateTextNode(m.target);
                }
            }
        }).observe(document.body, { childList: true, subtree: true, characterData: true });
    }

    /* ── Eventos de interacción: React-select re-renderiza al abrir dropdown ──────────── */

    document.addEventListener('click',     function () { setTimeout(function () { runTranslations(document.body); }, 0); },   true);
    document.addEventListener('mousedown', function () { setTimeout(function () { runTranslations(document.body); }, 150); }, true);
    document.addEventListener('focus',     function () { setTimeout(function () { runTranslations(document.body); }, 50); },  true);

    /* ── Visibilidad del tab: re-traducir al volver ──────────────────────────────────────── */

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            runTranslations(document.body);
            if (isQuizBuilderPath() && !_pollingTimer) { startPolling(); }
        }
    });

    /* ── Detección de navegación SPA: polling de ruta + pushState/replaceState ───────── */

    var _lastPath = (window.location && window.location.pathname) ? window.location.pathname : '';

    function onPathChange(newPath) {
        _lastPath = newPath;
        injectButtonCSS();
        runTranslations(document.body);
        if (isQuizBuilderPath()) {
            startPolling(); // reinicia el timer al entrar al builder
        } else {
            if (_pollingTimer) { clearInterval(_pollingTimer); _pollingTimer = null; }
        }
    }

    // Polling de ruta cada 200 ms (más rápido que antes)
    setInterval(function () {
        var currentPath = (window.location && window.location.pathname) ? window.location.pathname : '';
        if (currentPath !== _lastPath) { onPathChange(currentPath); }
    }, 200);

    // Respuesta inmediata via pushState / replaceState
    (function () {
        function patchHistory(method) {
            var original = history[method];
            if (!original) { return; }
            history[method] = function () {
                var result = original.apply(this, arguments);
                setTimeout(function () {
                    var currentPath = (window.location && window.location.pathname) ? window.location.pathname : '';
                    if (currentPath !== _lastPath) { onPathChange(currentPath); }
                }, 0);
                return result;
            };
        }
        try { patchHistory('pushState'); patchHistory('replaceState'); } catch (e) {}
        window.addEventListener('popstate', function () {
            var currentPath = (window.location && window.location.pathname) ? window.location.pathname : '';
            if (currentPath !== _lastPath) { onPathChange(currentPath); }
        });
    }());

}());
