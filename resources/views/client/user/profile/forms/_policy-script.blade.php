{{-- Shared by the policy forms: a row's input is editable only while its "custom" box is ticked. --}}
<script>
    (function() {
        const $form = $('#p360Modal form');
        $form.on('change', 'input[type=checkbox][name^="custom"]', function() {
            $(this).closest('tr, [data-policy-cell]').find('[name^="value"]').prop('disabled', !this.checked);
        });
        $form.on('click', '[data-p360-policy-reset]', function(e) {
            e.preventDefault();
            $form.find('input[type=checkbox][name^="custom"]').prop('checked', false).trigger('change');
            $form[0].requestSubmit();
        });
    })();
</script>
