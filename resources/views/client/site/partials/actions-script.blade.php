{{-- Activate / deactivate and delete calls; shared by the Sites list and the site detail page.
     After a delete, the detail page goes back to the list ($afterDelete). --}}
<script>
    toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 5000 };

    function siteCall(url, type, data, done) {
        $.ajax({
            url: url, type: type, data: data,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'Accept': 'application/json' },
            success: r => { toastr.success(r.message); setTimeout(done, 800); },
            error: x => toastr.error(x.responseJSON?.message || 'Something went wrong. Please try again.'),
        });
    }

    function setSiteStatus(id, status) {
        const label = status === 'active' ? 'active' : 'inactive';
        if (!confirm('Mark this site ' + label + '?' + (status === 'inactive' ? ' Employees will no longer be able to pick it for a visit.' : ''))) return;
        siteCall("{{ url('sites') }}/" + id + '/status', 'POST', { status: status }, () => location.reload());
    }

    function deleteSite(id, code) {
        if (!confirm('Delete site ' + code + '? This cannot be undone.')) return;
        siteCall("{{ url('sites') }}/" + id, 'DELETE', {}, () => { location.href = "{{ $afterDelete ?? route('sites.index') }}"; });
    }
</script>
