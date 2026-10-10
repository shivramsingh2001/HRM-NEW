{{-- Filters submit on change; search submits shortly after typing stops (same as the Clock In/Out Log). --}}
<script>
    $(function () {
        const form = $('#filterForm');
        form.find('.auto-submit').on('change', () => form.submit());

        const key = 'shiftReportSearchFocus';
        const box = form.find('input[name="search"]');
        let last = box.val(), timer;
        box.on('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                if (box.val() === last) return;
                try { sessionStorage.setItem(key, '1'); } catch (e) {}
                form.submit();
            }, 600);
        });
        try {
            if (sessionStorage.getItem(key)) {
                sessionStorage.removeItem(key);
                const el = box.get(0);
                if (el) { el.focus(); el.setSelectionRange(el.value.length, el.value.length); }
            }
        } catch (e) {}
    });
</script>
