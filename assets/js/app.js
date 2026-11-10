/* MindCare - interaksi ringan */
(function () {
    'use strict';

    var toggle = document.querySelector('[data-menu-toggle]');
    var sidebar = document.querySelector('[data-sidebar]');

    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('is-open');
        });
    }

    document.querySelectorAll('[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var message = form.getAttribute('data-confirm') || 'Yakin melanjutkan?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    var questions = document.querySelectorAll('[data-questionnaire]');
    if (questions.length) {
        var total = document.querySelectorAll('[data-question-group]').length;
        var bar = document.querySelector('[data-progress-bar]');
        var label = document.querySelector('[data-progress-label]');
        var badge = document.querySelector('[data-progress-badge]');

        function refresh() {
            var answered = 0;
            document.querySelectorAll('[data-question-group]').forEach(function (group) {
                if (group.querySelector('input[type="radio"]:checked')) {
                    answered += 1;
                }
            });

            var percent = total > 0 ? Math.round((answered / total) * 100) : 0;
            if (bar) { bar.style.width = percent + '%'; }
            if (label) { label.textContent = percent + '%'; }
            if (badge) { badge.textContent = answered + ' dari ' + total + ' dijawab'; }
        }

        document.querySelectorAll('[data-question-group] input[type="radio"]').forEach(function (input) {
            input.addEventListener('change', refresh);
        });

        refresh();
    }
})();
