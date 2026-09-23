{{--
    Shared behaviour for the single-row filter bar (#filterForm): selects submit on change, dates are
    validated then submitted, and the search box submits ~0.6s after typing stops and gets its cursor
    back after the reload so typing can continue. Include inside @section('script-area').
--}}
<script>
    $(function() {
        const form = $('#filterForm');
        if (!form.length) return;

        form.find('select.filter-select').on('change', function() {
            form.submit();
        });

        form.find('input[type="date"]').on('change', function() {
            const from = form.find('input[name="from_date"]').val();
            const to = form.find('input[name="to_date"]').val();
            if (from && to && from > to) {
                if (window.toastr) toastr.error('From date cannot be greater than To date');
                $(this).val('');
                return;
            }
            form.submit();
        });

        const key = 'exFilterSearchFocus:' + location.pathname;
        const box = form.find('input[name="search"]');
        let last = box.val(),
            timer;
        box.on('input', function() {
            clearTimeout(timer);
            timer = setTimeout(function() {
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
