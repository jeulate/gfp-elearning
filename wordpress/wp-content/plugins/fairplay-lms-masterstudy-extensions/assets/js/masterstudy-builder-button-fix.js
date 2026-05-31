/**
 * Force visible width for the add-answer button in MasterStudy quiz builder.
 * Handles dynamic Chakra re-renders.
 */
(function () {
    'use strict';

    function isBuilderPath() {
        var path = (window.location && window.location.pathname) ? window.location.pathname : '';
        return /\/user-account\/edit-course\/\d+/.test(path) || /\/edit-quiz\/\d+/.test(path);
    }

    function applyButtonStyle(button) {
        if (!button) {
            return;
        }

        button.style.setProperty('width', '52px', 'important');
        button.style.setProperty('min-width', '52px', 'important');
        button.style.setProperty('max-width', '52px', 'important');
        button.style.setProperty('flex', '0 0 52px', 'important');

        var inner = button.querySelector(':scope > div');
        if (inner) {
            inner.style.setProperty('width', '100%', 'important');
            inner.style.setProperty('justify-content', 'center', 'important');
        }
    }

    function getButtonsByStructure() {
        var buttons = [];
        var groups = document.querySelectorAll('div[role="group"]');

        for (var i = 0; i < groups.length; i++) {
            var input = groups[i].querySelector('input[placeholder]');
            if (!input) {
                continue;
            }

            var placeholder = String(input.getAttribute('placeholder') || '').toLowerCase();
            if (placeholder.indexOf('respuesta') === -1 && placeholder.indexOf('answer') === -1) {
                continue;
            }

            var sibling = input.nextElementSibling;
            if (sibling && sibling.tagName === 'BUTTON' && sibling.classList.contains('chakra-button')) {
                buttons.push(sibling);
            }
        }

        return buttons;
    }

    function runFix() {
        if (!isBuilderPath()) {
            return;
        }

        var unique = new Set();

        var directButtons = document.querySelectorAll('button.chakra-button.chakra-j6rous');
        for (var i = 0; i < directButtons.length; i++) {
            unique.add(directButtons[i]);
        }

        var structural = getButtonsByStructure();
        for (var j = 0; j < structural.length; j++) {
            unique.add(structural[j]);
        }

        unique.forEach(function (button) {
            applyButtonStyle(button);
        });
    }

    runFix();

    var observer = new MutationObserver(function () {
        runFix();
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['class', 'style', 'disabled', 'aria-disabled', 'placeholder']
    });

    var tries = 0;
    var timer = setInterval(function () {
        runFix();
        tries++;
        if (tries >= 120) {
            clearInterval(timer);
        }
    }, 250);

    var lastPath = (window.location && window.location.pathname) ? window.location.pathname : '';
    setInterval(function () {
        var currentPath = (window.location && window.location.pathname) ? window.location.pathname : '';
        if (currentPath !== lastPath) {
            lastPath = currentPath;
            runFix();
        }
    }, 300);
})();
