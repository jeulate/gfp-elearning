/**
 * FairPlay LMS — Traduccion de tipos de pregunta (MasterStudy Quiz Builder)
 *
 * Funciona en el editor visual React de quizzes, incluyendo rutas:
 * - /user-account/edit-course/{course}/curriculum/sections/{section}/quiz/{quiz}
 * - /edit-quiz/{quiz}
 */
(function () {
    'use strict';

    var path = (window.location && window.location.pathname) ? window.location.pathname : '';
    var isQuizBuilder = (
        /\/user-account\/edit-course\/\d+\/curriculum\/sections\/\d+\/quiz\/\d+/.test(path) ||
        /\/edit-quiz\/\d+/.test(path)
    );

    if (!isQuizBuilder) {
        return;
    }

    var translations = {
        'true-false': 'Verdadero/Falso',
        'true/false': 'Verdadero/Falso',
        'matching': 'Coincidencia',
        'fill in the gap': 'Rellenar el espacio',
        'ordering': 'Ordenar',
        'image matching': 'Coincidencia de imagenes'
    };

    function normalizeLabel(value) {
        return String(value || '')
            .replace(/[‐‑‒–—−]/g, '-')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase();
    }

    function translateTextNode(node) {
        if (!node || node.nodeType !== 3) { return; }

        var raw = node.nodeValue;
        if (!raw) { return; }

        var core = raw.trim();
        if (!core) { return; }

        var translated = translations[normalizeLabel(core)];
        if (!translated) { return; }

        var leading = raw.match(/^\s*/);
        var trailing = raw.match(/\s*$/);
        node.nodeValue = (leading ? leading[0] : '') + translated + (trailing ? trailing[0] : '');
    }

    function applyTranslations(root) {
        var scope = root && root.nodeType ? root : document.body;
        if (!scope || typeof document.createTreeWalker !== 'function') { return; }

        if (scope.nodeType === 3) {
            translateTextNode(scope);
            return;
        }

        var walker = document.createTreeWalker(scope, NodeFilter.SHOW_TEXT, null, false);
        var current;
        while ((current = walker.nextNode())) {
            translateTextNode(current);
        }
    }

    applyTranslations(document.body);

    if (window.MutationObserver) {
        new MutationObserver(function (mutations) {
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
                    applyTranslations(m.target);
                }
            }
        }).observe(document.body, { childList: true, subtree: true, characterData: true });
    }

    // React-select suele re-renderizar menu al abrir/cerrar; reforzar traduccion.
    var tries = 0;
    var timer = setInterval(function () {
        applyTranslations(document.body);
        tries++;
        if (tries >= 40) {
            clearInterval(timer);
        }
    }, 250);

    document.addEventListener('click', function () {
        setTimeout(function () { applyTranslations(document.body); }, 0);
    }, true);
})();
