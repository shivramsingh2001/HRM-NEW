<!--! ================================================================ !-->
<!--! Footer Script !-->
<!--! ================================================================ !-->
<!--! BEGIN: Vendors JS !-->
<script src="{{ asset('assets/vendors/js/vendors.min.js') }}"></script>
<!-- vendors.min.js {always must need to be top} -->
<script src="{{ asset('assets/vendors/js/daterangepicker.min.js') }}"></script>
<script src="{{ asset('assets/vendors/js/apexcharts.min.js') }}"></script>
<script src="{{ asset('assets/vendors/js/circle-progress.min.js') }}"></script>
<!--! END: Vendors JS !-->
<!--! BEGIN: Apps Init  !-->
<script src="{{ asset('assets/js/common-init.min.js') }}"></script>

<!--! END: Apps Init !-->
<!--! BEGIN: Theme Customizer  !-->
<script src="{{ asset('assets/js/theme-customizer-init.min.js') }}"></script>
<script src="{{ asset('assets/js/customers-init.min.js') }}"></script>
<!--! END: Theme Customizer !-->

<script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
<script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

{{-- <script src="{{ asset('assets/vendors/js/dataTables.min.js') }}"></script>
<script src="{{ asset('assets/vendors/js/dataTables.bs5.min.js') }}"></script> --}}

<script>
    $('.select2').select2({
        placeholder: "Select Option",

    });
</script>

<!-- Global Bootstrap tooltip init — any element with data-bs-toggle="tooltip" gets one for free -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        bootstrap.Tooltip.getOrCreateInstance(el);
    });
});
</script>

<!-- Custom CSS table -->
{{-- <script>
document.addEventListener('DOMContentLoaded', function () {
    const tableResponsive = document.querySelector('.table-responsive');
    const contentArea = document.querySelector('.content-area');

    // PC wheel fix — bypass PerfectScrollbar, scroll contentArea directly
    tableResponsive.addEventListener('wheel', function (e) {
        e.stopImmediatePropagation();
        contentArea.scrollTop += e.deltaY;
    }, { passive: false });

    // Mobile touch fix
    let touchStartX = 0;
    let touchStartY = 0;
    let isHorizontalScroll = null;

    tableResponsive.addEventListener('touchstart', function (e) {
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
        isHorizontalScroll = null;
    }, { passive: true });

    tableResponsive.addEventListener('touchmove', function (e) {
        const dx = Math.abs(e.touches[0].clientX - touchStartX);
        const dy = Math.abs(e.touches[0].clientY - touchStartY);

        if (isHorizontalScroll === null) {
            isHorizontalScroll = dx > dy;
        }

        if (isHorizontalScroll) {
            e.stopPropagation();
        }
    }, { passive: true });

    // Horizontal drag scroll with cursor
    let isDown = false;
    let startX;
    let scrollLeft;

    tableResponsive.addEventListener('mousedown', function (e) {
        if (e.target.closest('a, button, .audio-play-btn, .progress-container, .dropdown')) return;
        isDown = true;
        tableResponsive.style.cursor = 'grabbing';
        startX = e.pageX - tableResponsive.offsetLeft;
        scrollLeft = tableResponsive.scrollLeft;
    });

    document.addEventListener('mouseup', function () {
        isDown = false;
        tableResponsive.style.cursor = 'grab';
    });

    tableResponsive.addEventListener('mouseleave', function () {
        isDown = false;
        tableResponsive.style.cursor = 'grab';
    });

    tableResponsive.addEventListener('mousemove', function (e) {
        if (!isDown) return;
        e.preventDefault();
        const x = e.pageX - tableResponsive.offsetLeft;
        const walk = x - startX;
        tableResponsive.scrollLeft = scrollLeft - walk;
    });

});
</script> --}}
<!-- Custom Table End -->

<!-- Custom CSS Table -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const contentArea = document.querySelector('.content-area');

    document.querySelectorAll('.table-responsive').forEach(function (tableResponsive) {

        // PC wheel fix — bypass PerfectScrollbar, scroll contentArea directly
        tableResponsive.addEventListener('wheel', function (e) {
            e.stopImmediatePropagation();
            contentArea.scrollTop += e.deltaY;
        }, { passive: false });

        // Mobile touch fix
        let touchStartX = 0;
        let touchStartY = 0;
        let isHorizontalScroll = null;

        tableResponsive.addEventListener('touchstart', function (e) {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
            isHorizontalScroll = null;
        }, { passive: true });

        tableResponsive.addEventListener('touchmove', function (e) {
            const dx = Math.abs(e.touches[0].clientX - touchStartX);
            const dy = Math.abs(e.touches[0].clientY - touchStartY);
            if (isHorizontalScroll === null) {
                isHorizontalScroll = dx > dy;
            }
            if (isHorizontalScroll) {
                e.stopPropagation();
            }
        }, { passive: true });

        // Horizontal drag scroll with cursor
        let isDown = false;
        let startX;
        let scrollLeft;

        tableResponsive.addEventListener('mousedown', function (e) {
            if (e.target.closest('a, button, .audio-play-btn, .progress-container, .dropdown')) return;
            isDown = true;
            tableResponsive.style.cursor = 'grabbing';
            startX = e.pageX - tableResponsive.offsetLeft;
            scrollLeft = tableResponsive.scrollLeft;
        });

        tableResponsive.addEventListener('mouseup', function () {
            isDown = false;
            tableResponsive.style.cursor = 'grab';
        });

        tableResponsive.addEventListener('mouseleave', function () {
            isDown = false;
            tableResponsive.style.cursor = 'grab';
        });

        tableResponsive.addEventListener('mousemove', function (e) {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - tableResponsive.offsetLeft;
            const walk = x - startX;
            tableResponsive.scrollLeft = scrollLeft - walk;
        });

    }); // end forEach
});
</script>
<!-- Custom CSS Table End -->