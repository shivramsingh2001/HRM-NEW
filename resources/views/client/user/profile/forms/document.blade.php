{{-- Employee 360 — add or remove one document (employee.update.step, step 7).
     That step treats the posted list as the full set, so every document being kept is sent along as hidden fields. --}}
@php $kept = $documents->reject(fn ($d) => $remove && $d->id === $remove->id)->values(); @endphp
<x-p360.form :action="route('employee.update.step', $encId)" reload="page"
    :submit="$remove ? 'Remove document' : 'Upload'" :submitClass="$remove ? 'btn-danger' : 'btn-primary'">
    <input type="hidden" name="step" value="7">
    @foreach ($kept as $i => $document)
        <input type="hidden" name="documents[{{ $i }}][id]" value="{{ $document->id }}">
        <input type="hidden" name="documents[{{ $i }}][document_type]" value="{{ $document->document_type }}">
        <input type="hidden" name="documents[{{ $i }}][document_type_other]" value="{{ $document->document_type_other }}">
        <input type="hidden" name="documents[{{ $i }}][document_name]" value="{{ $document->document_name }}">
    @endforeach

    @if ($remove)
        <p class="mb-0">Remove <strong>{{ $remove->document_type_label }}</strong>{{ $remove->document_name ? ' — ' . $remove->document_name : '' }}
            from {{ $user->name }}'s documents? The file is deleted and cannot be restored.</p>
    @else
        @php $n = $kept->count(); @endphp
        <div class="row g-2">
            <div class="col-md-6">
                <label class="form-label">Document type <span class="text-danger">*</span></label>
                <select name="documents[{{ $n }}][document_type]" class="form-control" id="p360DocType" required>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 d-none" id="p360DocOther">
                <label class="form-label">Which document? <span class="text-danger">*</span></label>
                <input type="text" name="documents[{{ $n }}][document_type_other]" class="form-control" maxlength="100">
            </div>
            <div class="col-md-6">
                <label class="form-label">Name / number</label>
                <input type="text" name="documents[{{ $n }}][document_name]" class="form-control" maxlength="150">
            </div>
            <div class="col-12">
                <label class="form-label">File <span class="text-danger">*</span></label>
                <input type="file" name="documents[{{ $n }}][file]" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                <div class="p360-note mt-1">PDF, JPG, PNG, DOC or DOCX — up to 5 MB.</div>
            </div>
        </div>
        <script>
            $('#p360DocType').on('change', function() {
                $('#p360DocOther').toggleClass('d-none', this.value !== 'other').find('input').prop('required', this.value === 'other');
            });
        </script>
    @endif
</x-p360.form>
