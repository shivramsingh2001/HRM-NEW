{{--
    Approve / reject / cancel confirmation for the Comp-off and Floating Week-offs
    pages (replaces the browser prompt() / confirm()). Buttons on the page carry
    .js-decide with data-url, data-kind (approved | rejected | cancel) and
    data-title (what is being decided, e.g. "Ritik · Sat, 18 Oct 2026").
    Approve / reject post {action, remarks}; cancel posts nothing. On success
    the page reloads.
--}}
<x-ui.modal id="lrDecisionModal" title="Confirm" size="md" bodyOnly>
    <x-slot:footer>
        <button type="button" class="btn btn-modal-cancel btn-sm" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary btn-sm" id="lrDecisionGo"><i class="feather-check me-1"></i><span>Confirm</span></button>
    </x-slot:footer>
    <p class="mb-2" style="font-size:12.5px" id="lrDecisionText"></p>
    <div id="lrDecisionRemarksWrap">
        <label class="form-label" for="lrDecisionRemarks" style="font-size:11.5px;font-weight:600;color:#475569">Remarks <span class="text-muted fw-normal">(optional)</span></label>
        <textarea class="form-control form-control-sm" id="lrDecisionRemarks" rows="2" maxlength="500" placeholder="Shown to the employee"></textarea>
    </div>
</x-ui.modal>

<script>
    // jQuery / bootstrap / toastr load after the create-modal section — wait for them.
    document.addEventListener('DOMContentLoaded', function () {
        const csrf = $('meta[name="csrf-token"]').attr('content');
        const modal = () => bootstrap.Modal.getOrCreateInstance(document.getElementById('lrDecisionModal'));
        const kinds = {
            approved: { title: 'Approve request', verb: 'Approve', btn: 'btn-success', icon: 'feather-check' },
            rejected: { title: 'Reject request', verb: 'Reject', btn: 'btn-danger', icon: 'feather-x' },
            cancel: { title: 'Cancel', verb: 'Yes, cancel it', btn: 'btn-danger', icon: 'feather-x-circle' },
        };
        let current = null;

        $(document).on('click', '.js-decide', function (e) {
            e.preventDefault();
            const d = $(this).data();
            const k = kinds[d.kind];
            current = d;
            $('#lrDecisionModalLabel').text(k.title);
            $('#lrDecisionText').text(d.question || (k.verb + ' — ' + (d.title || '') + '?'));
            $('#lrDecisionRemarksWrap').toggle(d.kind !== 'cancel');
            $('#lrDecisionRemarks').val('');
            $('#lrDecisionGo').attr('class', 'btn btn-sm ' + k.btn).prop('disabled', false)
                .find('i').attr('class', k.icon + ' me-1').end().find('span').text(k.verb);
            modal().show();
        });

        $('#lrDecisionGo').on('click', function () {
            if (!current) return;
            const btn = $(this).prop('disabled', true);
            const data = current.kind === 'cancel' ? {} : { action: current.kind, remarks: $('#lrDecisionRemarks').val() };
            $.ajax({ url: current.url, type: 'POST', data: data, headers: { 'X-CSRF-TOKEN': csrf },
                success: r => { modal().hide(); toastr.success(r.message); setTimeout(() => location.reload(), 900); },
                error: x => { btn.prop('disabled', false); toastr.error(x.responseJSON?.message || (x.responseJSON?.errors ? Object.values(x.responseJSON.errors)[0][0] : 'Failed')); } });
        });
    });
</script>
