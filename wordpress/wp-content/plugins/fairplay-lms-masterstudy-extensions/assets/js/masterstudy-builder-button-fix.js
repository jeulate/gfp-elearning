/**
 * FairPlay LMS - Fix del boton "anadir respuesta" en el Quiz Builder de MasterStudy.
 *
 * Robusto ante:
 * - Cambios de nombre de clase Chakra entre builds (chakra-j6rous puede variar)
 * - Navegacion SPA desde /user-account/ antes de llegar al builder
 * - Timer que expira antes de que el usuario llegue al quiz editor
 * - Re-renders de React que sobreescriben estilos inline
 */
(function () {
    'use strict';

    /* Deteccion de ruta */

    function isBuilderPath() {
        var path = (window.location && window.location.pathname) ? window.location.pathname : '';
        return (
            /\/user-account\/edit-course\/\d+/.test(path) ||
            /\/edit-quiz\/\d+/.test(path) ||
            /\/edit-course\/\d+/.test(path) ||
            /\/curriculum\//.test(path) ||
            /\/quiz\/\d+/.test(path)
        );
    }

    /* Inyeccion de CSS global (metodo principal — sobrevive re-renders de React) */

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

    /* Aplicar estilos inline al boton (metodo secundario / refuerzo) */

    function applyButtonStyle(button) {
        if (!button) { return; }
        button.style.setProperty('width',     '52px',     'important');
        button.style.setProperty('min-width', '52px',     'important');
        button.style.setProperty('max-width', '52px',     'important');
        button.style.setProperty('flex',      '0 0 52px', 'important');
        var inner = button.querySelector('div');
        if (inner) {
            inner.style.setProperty('width',           '100%',   'important');
            inner.style.setProperty('justify-content', 'center', 'important');
        }
    }

    /* Estrategias de localizacion del boton */

    // Estrategia 1: por clase fija (puede cambiar entre builds de Chakra)
    var CHAKRA_SELECTORS = [
        'button.chakra-button.chakra-j6rous',
        'button.chakra-button[class*="j6r"]',
        'button.chakra-button[class*="chakra-j"]'
    ];

    // Estrategia 2 (principal): busqueda estructural por input de respuesta + boton adyacente.
    function getButtonsByStructure() {
        var buttons = [];
        var groups  = document.querySelectorAll('div[role="group"]');
        for (var i = 0; i < groups.length; i++) {
            var input = groups[i].querySelector('input[placeholder]');
            if (!input) { continue; }
            var ph = String(input.getAttribute('placeholder') || '').toLowerCase();
            if (ph.indexOf('respuesta') === -1 && ph.indexOf('answer') === -1) { continue; }
            var sibling = input.nextElementSibling;
            if (sibling && sibling.tagName === 'BUTTON') { buttons.push(sibling); }
        }
        return buttons;
    }

    // Estrategia 3: boton dentro del input-group
    function getButtonsByInputGroup() {
        var buttons = [];
        var groups  = document.querySelectorAll('.chakra-input__right-addon, [class*="input__right"]');
        for (var i = 0; i < groups.length; i++) {
            var btn = groups[i].querySelector('button.chakra-button');
            if (btn) { buttons.push(btn); }
        }
        return buttons;
    }

    function runFix() {
        if (!isBuilderPath()) { return; }
        var unique = new Set();
        CHAKRA_SELECTORS.forEach(function (sel) {
            try { document.querySelectorAll(sel).forEach(function (b) { unique.add(b); }); } catch (e) {}
        });
        getButtonsByStructure().forEach(function (b) { unique.add(b); });
        getButtonsByInputGroup().forEach(function (b) { unique.add(b); });
        unique.forEach(applyButtonStyle);
    }

    /* Polling con reinicio en cambio de ruta SPA */

    var _pollingTimer = null;
    var _pollingTries = 0;
    var MAX_TRIES     = 160; // 40 s desde que se entra al builder

    function startPolling() {
        injectButtonCSS();
        if (_pollingTimer) { clearInterval(_pollingTimer); }
        _pollingTries = 0;
        _pollingTimer = setInterval(function () {
            runFix();
            _pollingTries++;
            if (_pollingTries >= MAX_TRIES) {
                clearInterval(_pollingTimer);
                _pollingTimer = null;
            }
        }, 250);
    }

    /* Arranque inicial */

    injectButtonCSS();
    runFix();
    if (isBuilderPath()) { startPolling(); }

    /* MutationObserver: re-aplica cuando React re-renderiza el builder */

    var _mutTimer = null;
    new MutationObserver(function () {
        clearTimeout(_mutTimer);
        _mutTimer = setTimeout(runFix, 60);
    }).observe(document.body, {
        childList:       true,
        subtree:         true,
        attributes:      true,
        attributeFilter: ['class', 'style', 'disabled', 'aria-disabled', 'placeholder']
    });

    /* Visibilidad del tab */

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            runFix();
            if (isBuilderPath() && !_pollingTimer) { startPolling(); }
        }
    });

    /* Deteccion de navegacion SPA */

    var _lastPath = (window.location && window.location.pathname) ? window.location.pathname : '';

    function onPathChange(newPath) {
        _lastPath = newPath;
        runFix();
        if (isBuilderPath()) {
            startPolling();
        } else {
            if (_pollingTimer) { clearInterval(_pollingTimer); _pollingTimer = null; }
        }
    }

    setInterval(function () {
        var currentPath = (window.location && window.location.pathname) ? window.location.pathname : '';
        if (currentPath !== _lastPath) { onPathChange(currentPath); }
    }, 200);

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