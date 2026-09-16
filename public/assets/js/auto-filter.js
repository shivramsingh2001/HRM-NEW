/**
 * Generic auto-apply filter binder — add data-auto-filter to a <form> and
 * every select/checkbox/radio submits immediately on change, while text/
 * search/date/number inputs submit after a short debounce (so typing a date
 * by hand or a search term doesn't fire a request per keystroke). No "Apply"
 * button needed. First adopted on the Meeting Management list page; safe to
 * reuse on any other filter form in the project.
 *
 * Usage: <form method="GET" data-auto-filter data-auto-filter-delay="350">
 */
(function () {
    function initAutoFilter(form) {
        var delay = parseInt(form.getAttribute('data-auto-filter-delay') || '350', 10);
        var timer = null;

        function submit() {
            clearTimeout(timer);
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        }

        function debouncedSubmit() {
            clearTimeout(timer);
            timer = setTimeout(submit, delay);
        }

        form.querySelectorAll('select, input[type="checkbox"], input[type="radio"]').forEach(function (el) {
            el.addEventListener('change', submit);
        });

        form.querySelectorAll('input[type="text"], input[type="search"], input[type="date"], input[type="number"]').forEach(function (el) {
            el.addEventListener('input', debouncedSubmit);
            el.addEventListener('change', debouncedSubmit);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-auto-filter]').forEach(initAutoFilter);
    });
})();
