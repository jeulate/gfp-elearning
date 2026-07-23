(function () {
    'use strict';

    var emptyStateId = 'fplms-gradebook-empty-course';
    var gradebookPathPattern = /\/user-account\/gradebook\/?$/;

    function isGradebookPage() {
        return gradebookPathPattern.test(window.location.pathname);
    }

    function hasCourseId(value) {
        return value !== null &&
            typeof value !== 'undefined' &&
            String(value).trim() !== '';
    }

    function removeLoadingState(element) {
        if (!element) {
            return;
        }

        element.classList.remove(
            'masterstudy-loading',
            'masterstudy-loader',
            'loading',
            'is-loading'
        );
        element.removeAttribute('aria-busy');

        element.querySelectorAll(
            '.masterstudy-loader, .masterstudy-preloader, .dataTables_processing'
        ).forEach(function (loader) {
            loader.hidden = true;
            loader.style.display = 'none';
        });
    }

    function getGradebookContainer() {
        return document.querySelector('.masterstudy-account-gradebook') ||
            document.querySelector('.masterstudy-account-gradebook__students') ||
            document.querySelector('#masterstudy-datatable-students');
    }

    function showEmptyState() {
        if (document.getElementById(emptyStateId)) {
            return;
        }

        var container = getGradebookContainer();

        if (!container) {
            return;
        }

        var gradebook = container.closest('.masterstudy-account-gradebook') || container;
        var studentsSection = gradebook.querySelector(
            '.masterstudy-account-gradebook__students'
        );
        var statsSection = gradebook.querySelector(
            '.masterstudy-account-gradebook__course-info, ' +
            '.masterstudy-account-gradebook__stats'
        );

        removeLoadingState(gradebook);
        removeLoadingState(studentsSection);
        removeLoadingState(statsSection);

        if (studentsSection) {
            studentsSection.hidden = true;
        }

        if (statsSection) {
            statsSection.hidden = true;
        }

        var message = document.createElement('div');
        message.id = emptyStateId;
        message.className = 'masterstudy-message masterstudy-message_info';
        message.setAttribute('role', 'status');
        message.style.marginTop = '24px';
        message.style.padding = '18px 20px';
        message.style.borderRadius = '8px';
        message.style.background = '#f4f7fb';
        message.style.color = '#273044';
        message.textContent =
            'No tienes cursos disponibles para consultar en el libro de calificaciones.';

        var courseSelector = gradebook.querySelector(
            '.masterstudy-account-gradebook__course-select'
        );
        var insertionTarget = courseSelector || gradebook.firstElementChild;

        if (insertionTarget && insertionTarget.parentNode) {
            insertionTarget.insertAdjacentElement('afterend', message);
        } else {
            gradebook.appendChild(message);
        }
    }

    function hideEmptyState() {
        var message = document.getElementById(emptyStateId);

        if (message) {
            message.remove();
        }

        var gradebook = getGradebookContainer();

        if (!gradebook) {
            return;
        }

        var root = gradebook.closest('.masterstudy-account-gradebook') || gradebook;

        root.querySelectorAll(
            '.masterstudy-account-gradebook__students, ' +
            '.masterstudy-account-gradebook__course-info, ' +
            '.masterstudy-account-gradebook__stats'
        ).forEach(function (section) {
            section.hidden = false;
        });
    }

    document.addEventListener('msfieldEvent', function (event) {
        if (!isGradebookPage()) {
            return;
        }

        var detail = event.detail || {};

        if (hasCourseId(detail.value)) {
            hideEmptyState();
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();

        window.requestAnimationFrame(showEmptyState);
    }, true);
}());
