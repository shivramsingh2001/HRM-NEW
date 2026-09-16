{{--
    Shared create/edit form, rendered inside <x-ui.drawer>. Two instances of
    this partial live on the SAME page (meeting/index.blade.php) — one for
    "Schedule Meeting" (create), one shared edit drawer whose fields get
    repopulated client-side from a data-meeting JSON blob on each row's Edit
    button (see index.blade.php's editMeetingDrawer script). Because both
    instances are in the DOM at once, every element id is namespaced with
    $idPrefix so they never collide.

    Expects: $meeting (null for create/the empty edit template), $allUsers,
    $isEditable (bool), $formAction (route URL), $formMethod ('POST' or
    'PUT'), $idPrefix (string, e.g. '' for create, 'edit_' for the shared
    edit drawer).
--}}
@php
    $idPrefix = $idPrefix ?? '';

    $oldParticipants = old('participants');
    $participantsArray = [];
    if (is_string($oldParticipants) && $oldParticipants !== '') {
        $participantsArray = json_decode($oldParticipants, true) ?: [];
    } elseif (is_array($oldParticipants)) {
        $participantsArray = $oldParticipants;
    } elseif ($meeting) {
        $participantsArray = $meeting->participants->pluck('user_id')->toArray();
    }

    $oldMomWriters = old('mom_writers');
    $momWriterId = null;
    if (is_string($oldMomWriters) && $oldMomWriters !== '') {
        $decoded = json_decode($oldMomWriters, true) ?: [];
        $momWriterId = $decoded[0] ?? null;
    } elseif (is_array($oldMomWriters)) {
        $momWriterId = $oldMomWriters[0] ?? null;
    } elseif ($meeting) {
        $momWriterId = $meeting->participants->where('is_mom_writer', true)->first()?->user_id;
    }

    $meetingType = old('meeting_type', $meeting->meeting_type ?? 'physical');
    $reminderValue = old('reminder_minutes', $meeting->reminder_minutes_before ?? 15);
    $agendaItems = old('agenda_items') ?? ($meeting->agenda_items ?? []);
@endphp

<div id="{{ $idPrefix }}formRoot">
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <i class="feather-alert-circle me-1"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <strong class="d-block mb-1">Please fix the following:</strong>
            <ul class="mb-0 ps-3" style="font-size: 11.5px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="alert alert-warning mb-3 d-none" id="{{ $idPrefix }}lockedNotice" role="alert">
        <i class="feather-alert-triangle me-1"></i>
        This meeting is no longer scheduled and can't be edited.
    </div>

    <form action="{{ $formAction }}" method="POST" id="{{ $idPrefix }}meetingForm">
        @csrf
        @if ($formMethod === 'PUT')
            @method('PUT')
        @endif

        <div class="meeting-form-section mb-3">
            <label class="form-label fw-semibold" for="{{ $idPrefix }}title">Meeting Title <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-sm @error('title') is-invalid @enderror" id="{{ $idPrefix }}title"
                name="title" placeholder="e.g., Q4 Product Review, Weekly Sync"
                value="{{ old('title', $meeting->title ?? '') }}" required {{ !$isEditable ? 'readonly' : '' }}>
            @error('title')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="meeting-form-section mb-3">
            <label class="form-label fw-semibold" for="{{ $idPrefix }}description">Description</label>
            <textarea class="form-control form-control-sm @error('description') is-invalid @enderror" id="{{ $idPrefix }}description"
                name="description" rows="3" placeholder="What will be discussed?"
                {{ !$isEditable ? 'readonly' : '' }}>{{ old('description', $meeting->description ?? '') }}</textarea>
        </div>

        <div class="meeting-form-section mb-3">
            <label class="form-label fw-semibold d-block mb-2">Meeting Type <span class="text-danger">*</span></label>
            <div class="meeting-type-cards">
                <div class="meeting-type-card {{ $meetingType == 'physical' ? 'active' : '' }}" data-value="physical">
                    <input type="radio" name="meeting_type" value="physical" {{ $meetingType == 'physical' ? 'checked' : '' }} {{ !$isEditable ? 'disabled' : '' }}>
                    <i class="feather-users type-icon"></i>
                    <span class="type-name">Physical</span>
                </div>
                <div class="meeting-type-card {{ $meetingType == 'virtual' ? 'active' : '' }}" data-value="virtual">
                    <input type="radio" name="meeting_type" value="virtual" {{ $meetingType == 'virtual' ? 'checked' : '' }} {{ !$isEditable ? 'disabled' : '' }}>
                    <i class="feather-video type-icon"></i>
                    <span class="type-name">Virtual</span>
                </div>
                <div class="meeting-type-card {{ $meetingType == 'hybrid' ? 'active' : '' }}" data-value="hybrid">
                    <input type="radio" name="meeting_type" value="hybrid" {{ $meetingType == 'hybrid' ? 'checked' : '' }} {{ !$isEditable ? 'disabled' : '' }}>
                    <i class="feather-globe type-icon"></i>
                    <span class="type-name">Hybrid</span>
                </div>
            </div>
        </div>

        <div class="meeting-form-section mb-3">
            <label class="form-label fw-semibold" for="{{ $idPrefix }}location" id="{{ $idPrefix }}locationLabel">Location <span class="text-danger">*</span></label>
            <textarea class="form-control form-control-sm @error('location') is-invalid @enderror" id="{{ $idPrefix }}location"
                name="location" rows="2" placeholder="Conference Room A, 3rd Floor" {{ !$isEditable ? 'readonly' : '' }}>{{ old('location', $meeting->location ?? '') }}</textarea>
        </div>

        <div class="meeting-form-section mb-3" id="{{ $idPrefix }}virtualLinkGroup" style="display:none;">
            <label class="form-label fw-semibold" for="{{ $idPrefix }}virtual_meeting_link">Virtual Meeting Link</label>
            <input type="text" class="form-control form-control-sm @error('virtual_meeting_link') is-invalid @enderror"
                id="{{ $idPrefix }}virtual_meeting_link" name="virtual_meeting_link" placeholder="https://meet.google.com/xxx-xxxx-xxx"
                value="{{ old('virtual_meeting_link', $meeting->virtual_meeting_link ?? '') }}" {{ !$isEditable ? 'readonly' : '' }}>
        </div>

        <div class="meeting-form-section mb-3">
            <div class="row g-2">
                <div class="col-4">
                    <label class="form-label fw-semibold" for="{{ $idPrefix }}meeting_date">Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control form-control-sm @error('meeting_date') is-invalid @enderror"
                        id="{{ $idPrefix }}meeting_date" name="meeting_date"
                        value="{{ old('meeting_date', $meeting && $meeting->meeting_date ? \Carbon\Carbon::parse($meeting->meeting_date)->format('Y-m-d') : '') }}"
                        min="{{ date('Y-m-d') }}" required {{ !$isEditable ? 'readonly' : '' }}>
                </div>
                <div class="col-4">
                    <label class="form-label fw-semibold" for="{{ $idPrefix }}start_time">Start <span class="text-danger">*</span></label>
                    <input type="time" class="form-control form-control-sm @error('start_time') is-invalid @enderror"
                        id="{{ $idPrefix }}start_time" name="start_time"
                        value="{{ old('start_time', $meeting && $meeting->start_time ? \Carbon\Carbon::parse($meeting->start_time)->format('H:i') : '10:00') }}"
                        required {{ !$isEditable ? 'readonly' : '' }}>
                </div>
                <div class="col-4">
                    <label class="form-label fw-semibold" for="{{ $idPrefix }}end_time">End <span class="text-danger">*</span></label>
                    <input type="time" class="form-control form-control-sm @error('end_time') is-invalid @enderror"
                        id="{{ $idPrefix }}end_time" name="end_time"
                        value="{{ old('end_time', $meeting && $meeting->end_time ? \Carbon\Carbon::parse($meeting->end_time)->format('H:i') : '11:00') }}"
                        required {{ !$isEditable ? 'readonly' : '' }}>
                </div>
            </div>
            @error('end_time')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        {{-- ── Structured agenda (optional, backs meetings.agenda_items) ── --}}
        <div class="meeting-form-section mb-3">
            <label class="form-label fw-semibold d-block mb-1">Agenda Items <span class="text-muted fw-normal">(optional)</span></label>
            <div id="{{ $idPrefix }}agendaItemsList">
                @foreach ($agendaItems ?: [] as $item)
                    <div class="input-group input-group-sm mb-1 agenda-item-row">
                        <input type="text" class="form-control" placeholder="Agenda item" value="{{ $item['title'] ?? '' }}" {{ !$isEditable ? 'readonly' : '' }}>
                        <button type="button" class="btn btn-outline-secondary remove-agenda-item" {{ !$isEditable ? 'disabled' : '' }}><i class="feather-x"></i></button>
                    </div>
                @endforeach
            </div>
            @if ($isEditable)
                <button type="button" class="btn btn-sm btn-light border" id="{{ $idPrefix }}addAgendaItem">
                    <i class="feather-plus"></i> Add agenda item
                </button>
            @endif
            <input type="hidden" name="agenda_items" id="{{ $idPrefix }}agendaItemsInput" value="">
        </div>

        <div class="meeting-form-section mb-3">
            <label class="form-label fw-semibold d-block mb-2">Participants &amp; MOM Writer <span class="text-danger">*</span></label>

            @if ($isEditable)
                <div class="meeting-participants-box mb-2">
                    <div class="mp-head"><span>All Users</span><span id="{{ $idPrefix }}allUsersCount">{{ count($allUsers) }}</span></div>
                    <div class="mp-search"><input type="text" id="{{ $idPrefix }}participantSearch" placeholder="Search name or email…"></div>
                    <div class="mp-list" id="{{ $idPrefix }}allUsersList">
                        @foreach ($allUsers as $userItem)
                            @php
                                $initials = collect(explode(' ', $userItem->name))->take(2)->map(fn($w) => strtoupper($w[0]))->join('');
                                $isSelected = in_array($userItem->id, $participantsArray);
                            @endphp
                            <div class="mp-item {{ $isSelected ? 'selected' : '' }}" data-user-id="{{ $userItem->id }}"
                                data-user-name="{{ $userItem->name }}" data-user-email="{{ $userItem->email }}">
                                <div class="mp-avatar">{{ $initials }}</div>
                                <div class="mp-name">{{ $userItem->name }}<small>{{ $userItem->email }}</small></div>
                                <div class="mp-check">✓</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="meeting-participants-box">
                <div class="mp-head"><span>Selected</span><span id="{{ $idPrefix }}selectedCount">{{ count($participantsArray) }}</span></div>
                <div class="mp-selected-list" id="{{ $idPrefix }}selectedParticipantsList">
                    @if (empty($participantsArray))
                        <div class="meeting-empty-hint">No participants selected yet.</div>
                    @endif
                </div>
            </div>

            <input type="hidden" name="participants" id="{{ $idPrefix }}participantsInput" value="{{ json_encode($participantsArray) }}">
            <input type="hidden" name="mom_writers" id="{{ $idPrefix }}momWritersInput" value="{{ $momWriterId ? json_encode([$momWriterId]) : json_encode([]) }}">
            @error('participants')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="meeting-form-section mb-3">
            <label class="form-label fw-semibold d-block mb-2">Reminder</label>
            <div class="meeting-reminder-pills">
                @foreach ([['5', '5 min'], ['10', '10 min'], ['15', '15 min'], ['30', '30 min'], ['60', '1 hr'], ['120', '2 hrs'], ['1440', '1 day'], ['0', 'None']] as [$val, $lbl])
                    <div class="meeting-reminder-pill">
                        <input type="radio" name="reminder_choice" id="{{ $idPrefix }}rem_{{ $val }}" value="{{ $val }}" {{ $reminderValue == $val ? 'checked' : '' }}>
                        <label for="{{ $idPrefix }}rem_{{ $val }}">{{ $lbl }}</label>
                    </div>
                @endforeach
            </div>
            <select name="reminder_minutes" id="{{ $idPrefix }}reminder_minutes" style="display:none">
                @foreach (['5', '10', '15', '30', '60', '120', '1440', '0'] as $val)
                    <option value="{{ $val }}">{{ $val }}</option>
                @endforeach
            </select>
        </div>

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary btn-sm" id="{{ $idPrefix }}submitBtn">
                <i class="feather-{{ $meeting ? 'save' : 'calendar' }} me-1"></i> <span id="{{ $idPrefix }}submitBtnText">{{ $meeting ? 'Update Meeting' : 'Schedule Meeting' }}</span>
            </button>
            <button type="button" class="btn btn-modal-cancel btn-sm" data-bs-dismiss="offcanvas">Cancel</button>
        </div>
    </form>
</div>

<script>
    (function() {
        const idPrefix = "{{ $idPrefix }}";
        const root = document.getElementById(idPrefix + 'formRoot');
        if (!root) return;

        const byId = (name) => root.querySelector('#' + idPrefix + name);

        function escapeHtml(t) {
            const d = document.createElement('div');
            d.textContent = t || '';
            return d.innerHTML;
        }

        function getInitials(name) {
            return (name || '?').trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();
        }

        // ── Meeting type cards + location label swap ──
        const locationLabel = byId('locationLabel');
        const virtualLinkGroup = byId('virtualLinkGroup');

        function updateLocationField(type) {
            if (!locationLabel) return;
            if (type === 'virtual') {
                locationLabel.innerHTML = 'Location <span class="text-muted fw-normal">(optional for fully virtual)</span>';
                if (virtualLinkGroup) virtualLinkGroup.style.display = '';
            } else if (type === 'hybrid') {
                locationLabel.innerHTML = 'Location <span class="text-danger">*</span>';
                if (virtualLinkGroup) virtualLinkGroup.style.display = '';
            } else {
                locationLabel.innerHTML = 'Location <span class="text-danger">*</span>';
                if (virtualLinkGroup) virtualLinkGroup.style.display = 'none';
            }
        }

        root.querySelectorAll('.meeting-type-card').forEach(card => {
            card.addEventListener('click', function() {
                root.querySelectorAll('.meeting-type-card').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                const radio = this.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
                updateLocationField(this.dataset.value);
            });
        });
        const initialType = root.querySelector('.meeting-type-card.active');
        if (initialType) updateLocationField(initialType.dataset.value);

        // ── Time validation ──
        const startTime = byId('start_time');
        const endTime = byId('end_time');
        const meetingDate = byId('meeting_date');

        function validateTimes() {
            if (startTime && endTime && startTime.value && endTime.value) {
                if (endTime.value <= startTime.value) {
                    endTime.setCustomValidity('End time must be after start time');
                } else {
                    endTime.setCustomValidity('');
                }
            }
        }
        if (startTime && endTime) {
            startTime.addEventListener('change', validateTimes);
            endTime.addEventListener('change', validateTimes);
        }

        // ── Agenda items repeater ──
        const agendaList = byId('agendaItemsList');
        const agendaInput = byId('agendaItemsInput');
        const addAgendaBtn = byId('addAgendaItem');

        function syncAgendaInput() {
            if (!agendaList || !agendaInput) return;
            const items = Array.from(agendaList.querySelectorAll('.agenda-item-row input')).map(i => i.value.trim()).filter(v => v).map(title => ({
                title
            }));
            agendaInput.value = items.length ? JSON.stringify(items) : '';
        }

        function addAgendaRow(value) {
            if (!agendaList) return;
            const row = document.createElement('div');
            row.className = 'input-group input-group-sm mb-1 agenda-item-row';
            row.innerHTML = `<input type="text" class="form-control" placeholder="Agenda item" value="${escapeHtml(value || '')}">
                <button type="button" class="btn btn-outline-secondary remove-agenda-item"><i class="feather-x"></i></button>`;
            agendaList.appendChild(row);
        }

        if (addAgendaBtn) {
            addAgendaBtn.addEventListener('click', function() {
                addAgendaRow('');
            });
        }
        if (agendaList) {
            agendaList.addEventListener('click', function(e) {
                const btn = e.target.closest('.remove-agenda-item');
                if (!btn) return;
                btn.closest('.agenda-item-row').remove();
                syncAgendaInput();
            });
            agendaList.addEventListener('input', syncAgendaInput);
        }
        syncAgendaInput();

        // ── Participants picker ──
        let selectedParticipants = [];
        let momWriterId = null;
        const participantsInput = byId('participantsInput');
        const momWritersInput = byId('momWritersInput');

        function loadParticipantsFromInputs() {
            try {
                const ids = JSON.parse((participantsInput && participantsInput.value) || '[]');
                selectedParticipants = ids.map(id => {
                    const item = root.querySelector(`#${idPrefix}allUsersList .mp-item[data-user-id="${id}"]`);
                    return item ? {
                        id: parseInt(id),
                        name: item.dataset.userName,
                        email: item.dataset.userEmail
                    } : {
                        id: parseInt(id),
                        name: '#' + id,
                        email: ''
                    };
                });
            } catch (e) {
                selectedParticipants = [];
            }
            try {
                const momArr = JSON.parse((momWritersInput && momWritersInput.value) || '[]');
                momWriterId = momArr.length ? momArr[0] : null;
            } catch (e) {
                momWriterId = null;
            }
        }
        loadParticipantsFromInputs();

        function renderSelected() {
            const list = byId('selectedParticipantsList');
            const count = byId('selectedCount');
            if (count) count.textContent = selectedParticipants.length;
            if (!list) return;

            if (selectedParticipants.length === 0) {
                list.innerHTML = '<div class="meeting-empty-hint">No participants selected yet.</div>';
            } else {
                list.innerHTML = selectedParticipants.map(p => {
                    const isMom = momWriterId === p.id;
                    return `<div class="mp-selected-row" data-id="${p.id}">
                        <div class="mp-avatar">${escapeHtml(getInitials(p.name))}</div>
                        <div class="mp-name">${escapeHtml(p.name)}<small>${escapeHtml(p.email)}</small></div>
                        <label class="mp-mom-toggle ${isMom ? 'active' : ''}">
                            <input type="checkbox" class="mom-writer-checkbox" data-id="${p.id}" ${isMom ? 'checked' : ''}>
                            ✦ MOM
                        </label>
                        <button type="button" class="mp-remove" data-id="${p.id}"><i class="feather-x"></i></button>
                    </div>`;
                }).join('');
            }

            if (participantsInput) participantsInput.value = JSON.stringify(selectedParticipants.map(p => p.id));
            if (momWritersInput) momWritersInput.value = momWriterId ? JSON.stringify([momWriterId]) : JSON.stringify([]);

            root.querySelectorAll(`#${idPrefix}allUsersList .mp-item`).forEach(item => {
                item.classList.toggle('selected', selectedParticipants.some(p => p.id === parseInt(item.dataset.userId)));
            });
        }

        const allUsersList = byId('allUsersList');
        if (allUsersList) {
            const searchInput = byId('participantSearch');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const q = this.value.toLowerCase();
                    allUsersList.querySelectorAll('.mp-item').forEach(item => {
                        const name = (item.dataset.userName || '').toLowerCase();
                        const email = (item.dataset.userEmail || '').toLowerCase();
                        item.style.display = (name.includes(q) || email.includes(q)) ? '' : 'none';
                    });
                });
            }

            allUsersList.addEventListener('click', function(e) {
                const item = e.target.closest('.mp-item');
                if (!item) return;
                const userId = parseInt(item.dataset.userId);

                if (selectedParticipants.find(p => p.id === userId)) {
                    selectedParticipants = selectedParticipants.filter(p => p.id !== userId);
                    if (momWriterId === userId) momWriterId = null;
                } else {
                    selectedParticipants.push({
                        id: userId,
                        name: item.dataset.userName,
                        email: item.dataset.userEmail
                    });
                }
                renderSelected();
            });
        }

        const selectedList = byId('selectedParticipantsList');
        if (selectedList) {
            selectedList.addEventListener('change', function(e) {
                if (!e.target.classList.contains('mom-writer-checkbox')) return;
                const userId = parseInt(e.target.dataset.id);
                momWriterId = e.target.checked ? userId : (momWriterId === userId ? null : momWriterId);
                renderSelected();
            });
            selectedList.addEventListener('click', function(e) {
                const btn = e.target.closest('.mp-remove');
                if (!btn) return;
                const userId = parseInt(btn.dataset.id);
                selectedParticipants = selectedParticipants.filter(p => p.id !== userId);
                if (momWriterId === userId) momWriterId = null;
                renderSelected();
            });
        }

        renderSelected();

        // ── Reminder pills ──
        root.querySelectorAll('input[name="reminder_choice"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const rm = byId('reminder_minutes');
                if (rm) rm.value = this.value;
            });
            if (radio.checked) {
                const rm = byId('reminder_minutes');
                if (rm) rm.value = radio.value;
            }
        });

        // ── Submit validation ──
        const meetingForm = byId('meetingForm');
        if (meetingForm) {
            meetingForm.addEventListener('submit', function(e) {
                if (selectedParticipants.length === 0) {
                    e.preventDefault();
                    alert('Please select at least one participant for the meeting.');
                    return false;
                }
                if (meetingDate && meetingDate.value && new Date(meetingDate.value) < new Date().setHours(0, 0, 0, 0)) {
                    e.preventDefault();
                    alert('Meeting date cannot be in the past.');
                    return false;
                }
                validateTimes();
                if (endTime && endTime.validationMessage) {
                    e.preventDefault();
                    alert('End time must be after start time.');
                    return false;
                }
                syncAgendaInput();
                if (participantsInput) participantsInput.value = JSON.stringify(selectedParticipants.map(p => p.id));
                if (momWritersInput) momWritersInput.value = momWriterId ? JSON.stringify([momWriterId]) : JSON.stringify([]);
            });
        }

        /**
         * Repopulates every field of this form instance from a plain data
         * object — used by the shared edit drawer (idPrefix="edit_") when a
         * row's Edit button is clicked, instead of navigating to a separate
         * edit page. { title, description, meeting_type, location,
         * virtual_meeting_link, meeting_date, start_time, end_time,
         * reminder_minutes, agenda_items: [{title}], participants:
         * [{id,name,email}], mom_writer_id, form_action, editable }.
         */
        root.populateFromData = function(data) {
            data = data || {};
            if (byId('title')) byId('title').value = data.title || '';
            if (byId('description')) byId('description').value = data.description || '';
            if (byId('location')) byId('location').value = data.location || '';
            if (byId('virtual_meeting_link')) byId('virtual_meeting_link').value = data.virtual_meeting_link || '';
            if (byId('meeting_date')) byId('meeting_date').value = data.meeting_date || '';
            if (byId('start_time')) byId('start_time').value = data.start_time || '';
            if (byId('end_time')) byId('end_time').value = data.end_time || '';

            const type = data.meeting_type || 'physical';
            root.querySelectorAll('.meeting-type-card').forEach(c => c.classList.remove('active'));
            const typeCard = root.querySelector(`.meeting-type-card[data-value="${type}"]`);
            if (typeCard) {
                typeCard.classList.add('active');
                const radio = typeCard.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
            }
            updateLocationField(type);

            const reminderVal = String(data.reminder_minutes ?? 15);
            root.querySelectorAll('input[name="reminder_choice"]').forEach(r => {
                r.checked = (r.value === reminderVal);
            });
            if (byId('reminder_minutes')) byId('reminder_minutes').value = reminderVal;

            if (agendaList) {
                agendaList.innerHTML = '';
                (data.agenda_items || []).forEach(item => addAgendaRow(typeof item === 'string' ? item : (item.title || '')));
            }
            syncAgendaInput();

            selectedParticipants = (data.participants || []).map(p => ({
                id: parseInt(p.id),
                name: p.name,
                email: p.email
            }));
            momWriterId = data.mom_writer_id || null;
            renderSelected();

            if (data.form_action && meetingForm) meetingForm.action = data.form_action;

            const editable = data.editable !== false;
            const notice = document.getElementById(idPrefix + 'lockedNotice');
            const submitBtn = byId('submitBtn');
            if (notice) notice.classList.toggle('d-none', editable);
            if (submitBtn) submitBtn.classList.toggle('d-none', !editable);
        };
    })();
</script>
