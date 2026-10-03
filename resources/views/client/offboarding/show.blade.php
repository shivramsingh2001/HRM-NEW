@extends('client.layout.master')

@section('style')
    <style>
        /* ==================== PANELS — compact ==================== */
        .ob-panel { background: #fff; border: 1px solid #edf2f7; border-radius: 10px; margin-bottom: 10px; overflow: hidden; box-shadow: 0 1px 2px rgba(15,23,42,.02); }
        .ob-panel__head { padding: 8px 14px; border-bottom: 1px solid #f1f5f9; background: #fafbfc; display: flex; align-items: center; justify-content: space-between; gap: 8px; min-height: 36px; }
        .ob-panel__title { margin: 0; font-size: 11.5px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 5px; text-transform: uppercase; letter-spacing: .02em; }
        .ob-panel__title i { font-size: 12px; color: var(--icon-color, #0D6EFD); }
        .ob-panel__body { padding: 12px 14px; font-size: 11.5px; }
        .ob-panel__body p { font-size: 11px; }

        /* ==================== BUTTONS ==================== */
        .ob-btn { display: inline-flex; align-items: center; gap: 5px; background: linear-gradient(135deg, #0D6EFD, #0D6EFD); color: #fff; border: none; font-size: 11px; font-weight: 500; padding: 5px 12px; border-radius: 6px; cursor: pointer; text-decoration: none; line-height: 1.4; transition: filter .15s ease, box-shadow .15s ease; }
        .ob-btn:hover { filter: brightness(0.92); color: #fff; box-shadow: 0 2px 6px rgba(13, 110, 253,.25); }
        .ob-btn i { font-size: 11px; }
        .ob-btn--outline { background: #fff; color: var(--primary, #0D6EFD); border: 1px solid #cbd5e1; }
        .ob-btn--outline:hover { border-color: var(--primary, #0D6EFD); background: #f8fafc; }
        .ob-btn--danger { background: linear-gradient(135deg, #b91c1c, #ef4444); }
        .ob-btn--sm { padding: 3px 9px; font-size: 10px; }

        /* ==================== FORM FIELDS ==================== */
        .field-label { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; color: #64748b; margin-bottom: 3px; display: block; }
        .field-input, .field-select, textarea.field-input { width: 100%; font-size: 11px; color: #1e293b; padding: 5px 8px; border: 1px solid #e2e8f0; border-radius: 6px; background: #fff; transition: border-color .15s ease, box-shadow .15s ease; }
        .field-input:focus, .field-select:focus, textarea.field-input:focus { outline: none; border-color: var(--primary, #0D6EFD); box-shadow: 0 0 0 3px rgba(13, 110, 253,.08); }

        /* ==================== META GRID ==================== */
        .ob-meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px 14px; }
        .ob-meta-item .label { font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #94a3b8; margin-bottom: 1px; }
        .ob-meta-item .value { font-size: 11.5px; font-weight: 600; color: #1e293b; }

        /* ==================== CLEARANCE TASKS ==================== */
        .ob-task-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 6px 0; border-bottom: 1px solid #f1f5f9; }
        .ob-task-row:last-child { border-bottom: none; }
        .ob-task-row .task-label { font-size: 11.5px; font-weight: 500; color: #1e293b; }
        .ob-task-row .task-sub { font-size: 9.5px; color: #94a3b8; }
        .ob-category-label { font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #64748b; margin: 10px 0 4px; }
        .ob-category-label:first-child { margin-top: 0; }

        /* ==================== TABLES ==================== */
        table.ob-table { width: 100%; font-size: 11px; }
        table.ob-table th { font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; color: #94a3b8; text-align: left; padding: 5px 8px; border-bottom: 1px solid #f1f5f9; }
        table.ob-table td { padding: 5px 8px; border-bottom: 1px solid #f8fafc; }
        .text-add { color: #16a34a; }
        .text-deduct { color: #dc2626; }
        .ob-total-row td { font-weight: 700; border-top: 2px solid #e2e8f0; }

        /* ==================== INLINE (details/summary) FORMS ==================== */
        details.ob-inline-form { margin-top: 6px; }
        details.ob-inline-form summary { cursor: pointer; font-size: 10.5px; color: var(--primary,#0D6EFD); font-weight: 600; list-style: none; padding: 3px 0; }
        details.ob-inline-form summary::-webkit-details-marker { display: none; }
        details.ob-inline-form summary::before { content: '›'; display: inline-block; margin-right: 4px; transition: transform .15s ease; }
        details.ob-inline-form[open] summary::before { transform: rotate(90deg); }
        details.ob-inline-form[open] summary { margin-bottom: 6px; }

        /* ==================== TIMELINE / EMPTY TEXT ==================== */
        .ob-timeline-item { font-size: 10.5px; color: #475569; padding: 4px 0; border-bottom: 1px dashed #f1f5f9; }
        .ob-timeline-item:last-child { border-bottom: none; }
        .ob-empty-hint { margin: 0; font-size: 10.5px; color: #94a3b8; }

        /* Tighten Bootstrap gap/margin utilities on this page specifically */
        .main-content .row.g-2 { --bs-gutter-y: 8px; }
        .main-content .d-flex.gap-2 { gap: 6px !important; }
        .main-content .alert { font-size: 11.5px; padding: 8px 12px; margin-bottom: 10px; }
    </style>
@endsection

@section('content-area')
    <x-ui.page-header title="Offboarding Request" :parent="['label' => 'Offboarding', 'route' => 'offboarding.index']" />

    <div class="main-content" style="padding: 14px !important;">

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        {{-- ===== HEADER ===== --}}
        <div class="ob-panel">
            <div class="ob-panel__head">
                <h5 class="ob-panel__title"><i class="feather-user-minus"></i> {{ $offboarding->employee?->name }}</h5>
                <div class="d-flex gap-2">
                    <x-ui.status-badge :status="$offboarding->badge_status" :label="$offboarding->status_label" />
                    @if ($offboarding->status === 'approved')
                        <x-ui.status-badge :status="$offboarding->badge_status" :label="$offboarding->stage_label" />
                    @endif
                </div>
            </div>
            <div class="ob-panel__body">
                <div class="ob-meta-grid">
                    <div class="ob-meta-item"><div class="label">Employee ID</div><div class="value">{{ $offboarding->employee?->employee_id }}</div></div>
                    <div class="ob-meta-item"><div class="label">Department</div><div class="value">{{ $offboarding->employee?->jobDetails?->Department?->name ?? '—' }}</div></div>
                    <div class="ob-meta-item"><div class="label">Reason</div><div class="value">{{ $offboarding->reason_label }}</div></div>
                    <div class="ob-meta-item"><div class="label">Request Date</div><div class="value">{{ optional($offboarding->request_date)->format('d M Y') }}</div></div>
                    <div class="ob-meta-item"><div class="label">Notice Required</div><div class="value">{{ $offboarding->notice_period_days_required ?? 0 }} day(s)</div></div>
                    <div class="ob-meta-item"><div class="label">Last Working Date</div><div class="value">{{ optional($offboarding->last_working_date)->format('d M Y') }}</div></div>
                    <div class="ob-meta-item"><div class="label">Eligible for Rehire</div><div class="value">{{ $offboarding->eligible_for_rehire ? 'Yes' : 'No' }}</div></div>
                    <div class="ob-meta-item"><div class="label">Submitted By</div><div class="value">{{ $offboarding->createdBy?->name }}</div></div>
                </div>
                @if ($offboarding->reason_detail)
                    <p class="mt-2 mb-0" style="font-size:11px;color:#475569;">{{ $offboarding->reason_detail }}</p>
                @endif

                @if ($offboarding->status === 'pending_approval' && $isCurrentApprover)
                    <form action="{{ route('offboarding.decide', $offboarding->id) }}" method="POST" class="mt-2 d-flex gap-2 align-items-start flex-wrap">
                        @csrf
                        <textarea name="remarks" class="field-input" style="max-width:260px" rows="1" placeholder="Remarks (optional)"></textarea>
                        <button type="submit" name="action" value="approved" class="ob-btn"><i class="feather-check"></i> Approve</button>
                        <button type="submit" name="action" value="rejected" class="ob-btn ob-btn--danger"><i class="feather-x"></i> Reject</button>
                    </form>
                @endif

                @if ($canEdit && in_array($offboarding->status, ['pending_approval', 'approved']))
                    <details class="ob-inline-form">
                        <summary><i class="feather-slash"></i> Cancel this request</summary>
                        <form action="{{ route('offboarding.cancel', $offboarding->id) }}" method="POST" class="d-flex gap-2">
                            @csrf
                            <textarea name="reason" class="field-input" rows="1" placeholder="Cancellation reason" required></textarea>
                            <button type="submit" class="ob-btn ob-btn--danger">Cancel Request</button>
                        </form>
                    </details>
                @endif

                @if ($canEdit && $offboarding->status === 'approved' && !in_array($offboarding->current_stage, ['ready_to_complete', 'completed']))
                    <details class="ob-inline-form">
                        <summary><i class="feather-rotate-ccw"></i> Reopen an earlier stage</summary>
                        <form action="{{ route('offboarding.stage.reopen', $offboarding->id) }}" method="POST" class="d-flex gap-2">
                            @csrf
                            <select name="stage" class="field-select" style="max-width:180px" required>
                                <option value="knowledge_transfer">Knowledge Transfer</option>
                                <option value="clearance">Clearance</option>
                                <option value="exit_interview">Exit Interview</option>
                                <option value="settlement">Final Settlement</option>
                            </select>
                            <input type="text" name="reason" class="field-input" placeholder="Reason" required>
                            <button type="submit" class="ob-btn ob-btn--outline">Reopen</button>
                        </form>
                    </details>
                @endif
            </div>
        </div>

        {{-- ===== APPROVAL TRAIL ===== --}}
        @if ($offboarding->approvalRequest)
            <div class="ob-panel">
                <div class="ob-panel__head"><h5 class="ob-panel__title"><i class="feather-check-square"></i> Approval Trail</h5></div>
                <div class="ob-panel__body">
                    @forelse ($offboarding->approvalRequest->actions as $action)
                        <div class="ob-timeline-item">
                            <strong>Level {{ $action->level }}</strong> — {{ ucfirst($action->action) }} by {{ $action->actor?->name ?? 'System' }}
                            ({{ optional($action->acted_at)->format('d M Y, h:i A') }})
                            @if ($action->remarks) — "{{ $action->remarks }}" @endif
                        </div>
                    @empty
                        <p class="ob-empty-hint">No decisions recorded yet — currently at level {{ $offboarding->approvalRequest->current_level }}.</p>
                    @endforelse
                </div>
            </div>
        @endif

        @if ($offboarding->status !== 'pending_approval')

        {{-- ===== NOTICE PERIOD ===== --}}
        <div class="ob-panel">
            <div class="ob-panel__head"><h5 class="ob-panel__title"><i class="feather-calendar"></i> Notice Period</h5></div>
            <div class="ob-panel__body">
                <div class="ob-meta-grid mb-2">
                    <div class="ob-meta-item"><div class="label">Original Last Working Date</div><div class="value">{{ optional($offboarding->original_last_working_date)->format('d M Y') }}</div></div>
                    <div class="ob-meta-item"><div class="label">Current Last Working Date</div><div class="value">{{ optional($offboarding->last_working_date)->format('d M Y') }}</div></div>
                </div>

                @forelse ($offboarding->noticeOverrides as $override)
                    <div class="ob-timeline-item">
                        <strong>{{ ucfirst(str_replace('_',' ',$override->type)) }}</strong> requested by {{ $override->requestedBy?->name }}
                        → {{ $override->requested_last_working_date->format('d M Y') }}
                        <x-ui.status-badge :status="$override->status === 'approved' ? 'approved' : ($override->status === 'rejected' ? 'rejected' : 'pending')" :label="ucfirst($override->status)" />
                        <div style="color:#94a3b8;">"{{ $override->reason }}"</div>
                        @if ($override->status === 'pending' && $canEdit)
                            <form action="{{ route('offboarding.notice-override.decide', [$offboarding->id, $override->id]) }}" method="POST" class="d-flex gap-2 mt-1">
                                @csrf
                                <input type="text" name="decision_notes" class="field-input" style="max-width:220px" placeholder="Decision notes">
                                <button type="submit" name="approve" value="1" class="ob-btn ob-btn--sm">Approve</button>
                                <button type="submit" name="approve" value="0" class="ob-btn ob-btn--sm ob-btn--danger">Reject</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="ob-empty-hint">No notice period changes requested.</p>
                @endforelse

                @if ($canEdit)
                    <details class="ob-inline-form">
                        <summary><i class="feather-edit-2"></i> Request waiver / early release / extension</summary>
                        <form action="{{ route('offboarding.notice-override.request', $offboarding->id) }}" method="POST" class="row g-2">
                            @csrf
                            <div class="col-md-3">
                                <select name="type" class="field-select" required>
                                    <option value="waiver">Waiver</option>
                                    <option value="early_release">Early Release</option>
                                    <option value="extension">Extension</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="date" name="requested_last_working_date" class="field-input" required>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="reason" class="field-input" placeholder="Reason" required>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="ob-btn w-100">Request</button>
                            </div>
                        </form>
                    </details>
                @endif
            </div>
        </div>

        {{-- ===== KNOWLEDGE TRANSFER ===== --}}
        <div class="ob-panel">
            <div class="ob-panel__head">
                <h5 class="ob-panel__title"><i class="feather-share-2"></i> Knowledge Transfer</h5>
                <x-ui.status-badge :status="$offboarding->knowledge_transfer_status" />
            </div>
            <div class="ob-panel__body">
                @if ($canEdit && $offboarding->current_stage === 'knowledge_transfer')
                    @if ($offboarding->knowledge_transfer_status === 'not_started')
                        <form action="{{ route('offboarding.knowledge-transfer.start', $offboarding->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="ob-btn">Start Knowledge Transfer</button>
                        </form>
                    @else
                        <form action="{{ route('offboarding.knowledge-transfer.complete', $offboarding->id) }}" method="POST" class="d-flex gap-2">
                            @csrf
                            <textarea name="notes" class="field-input" rows="1" placeholder="Handover notes (optional)"></textarea>
                            <button type="submit" class="ob-btn">Mark Complete</button>
                        </form>
                    @endif
                @else
                    <p class="ob-empty-hint">{{ $offboarding->knowledge_transfer_notes ?? 'No notes recorded.' }}</p>
                @endif
            </div>
        </div>

        {{-- ===== CLEARANCE ===== --}}
        <div class="ob-panel">
            <div class="ob-panel__head">
                <h5 class="ob-panel__title"><i class="feather-clipboard"></i> Clearance</h5>
                <x-ui.status-badge :status="$offboarding->clearance_status" />
            </div>
            <div class="ob-panel__body">
                @forelse ($offboarding->clearanceTasks->groupBy('category') as $category => $tasks)
                    <div class="ob-category-label">{{ strtoupper($category) }}</div>
                    @foreach ($tasks as $task)
                        <div class="ob-task-row">
                            <div>
                                <div class="task-label">{{ $task->label }}</div>
                                @if ($task->source === 'asset' && $task->assetAssignment?->asset)
                                    <div class="task-sub">Asset: {{ $task->assetAssignment->asset->asset_code }}</div>
                                @endif
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <x-ui.status-badge :status="$task->status" />
                                @if ($canEdit && $task->status === 'pending' && $offboarding->current_stage === 'clearance')
                                    <form action="{{ route('offboarding.clearance-task.update', [$offboarding->id, $task->id]) }}" method="POST" class="d-flex gap-1">
                                        @csrf
                                        <button type="submit" name="status" value="completed" class="ob-btn ob-btn--sm">Complete</button>
                                        <button type="submit" name="status" value="waived" class="ob-btn ob-btn--sm ob-btn--outline">Waive</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @empty
                    <p class="ob-empty-hint">No clearance items — nothing outstanding for this employee.</p>
                @endforelse
            </div>
        </div>

        {{-- ===== EXIT INTERVIEW ===== --}}
        <div class="ob-panel">
            <div class="ob-panel__head">
                <h5 class="ob-panel__title"><i class="feather-message-circle"></i> Exit Interview</h5>
                <x-ui.status-badge :status="$offboarding->exit_interview_skipped ? 'cancelled' : $offboarding->exit_interview_status" :label="$offboarding->exit_interview_skipped ? 'Skipped' : null" />
            </div>
            <div class="ob-panel__body">
                @if ($offboarding->exitInterview)
                    <div class="ob-meta-grid mb-2">
                        <div class="ob-meta-item"><div class="label">Avg. Rating</div><div class="value">{{ $offboarding->exitInterview->average_rating ?? '—' }}/5</div></div>
                        <div class="ob-meta-item"><div class="label">Would Recommend</div><div class="value">{{ $offboarding->exitInterview->would_recommend_label }}</div></div>
                    </div>
                    <p class="mb-0" style="font-size:11px;color:#475569;">{{ $offboarding->exitInterview->feedback_comments }}</p>
                @elseif ($canEdit && $offboarding->current_stage === 'exit_interview')
                    <form action="{{ route('offboarding.exit-interview.record', $offboarding->id) }}" method="POST" class="row g-2 mb-2">
                        @csrf
                        <div class="col-md-6"><label class="field-label">Interview Date</label><input type="date" name="interview_date" class="field-input" value="{{ now()->toDateString() }}" required></div>
                        @foreach (['work_environment_rating'=>'Work Environment','management_rating'=>'Management','career_growth_rating'=>'Career Growth','compensation_rating'=>'Compensation','work_life_balance_rating'=>'Work-Life Balance'] as $field => $label)
                            <div class="col-md-6">
                                <label class="field-label">{{ $label }} (1-5)</label>
                                <select name="{{ $field }}" class="field-select">
                                    <option value="">—</option>
                                    @for ($i = 1; $i <= 5; $i++)<option value="{{ $i }}">{{ $i }}</option>@endfor
                                </select>
                            </div>
                        @endforeach
                        <div class="col-md-6">
                            <label class="field-label">Would recommend us?</label>
                            <select name="would_recommend" class="field-select">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div class="col-12"><label class="field-label">Feedback</label><textarea name="feedback_comments" class="field-input" rows="2"></textarea></div>
                        <div class="col-12"><button type="submit" class="ob-btn">Save Exit Interview</button></div>
                    </form>
                    <details class="ob-inline-form">
                        <summary>Skip exit interview instead</summary>
                        <form action="{{ route('offboarding.exit-interview.skip', $offboarding->id) }}" method="POST" class="d-flex gap-2">
                            @csrf
                            <input type="text" name="reason" class="field-input" placeholder="Reason for skipping" required>
                            <button type="submit" class="ob-btn ob-btn--outline">Skip</button>
                        </form>
                    </details>
                @else
                    <p class="ob-empty-hint">Not recorded yet.</p>
                @endif
            </div>
        </div>

        {{-- ===== FINAL SETTLEMENT ===== --}}
        <div class="ob-panel">
            <div class="ob-panel__head">
                <h5 class="ob-panel__title"><i class="feather-dollar-sign"></i> Final Settlement</h5>
                <x-ui.status-badge :status="$offboarding->final_settlement_status" />
            </div>
            <div class="ob-panel__body">
                @if ($offboarding->settlementItems->isNotEmpty())
                    <table class="ob-table">
                        <thead><tr><th>Line</th><th>Computed</th><th>Override</th><th>Final</th>@if($canEdit && $offboarding->final_settlement_status==='pending')<th></th>@endif</tr></thead>
                        <tbody>
                            @foreach ($offboarding->settlementItems as $item)
                                <tr>
                                    <td>{{ $item->label }} <span class="{{ $item->is_addition ? 'text-add' : 'text-deduct' }}">({{ $item->is_addition ? '+' : '−' }})</span></td>
                                    <td>{{ number_format($item->computed_amount, 2) }}</td>
                                    <td>{{ $item->override_amount !== null ? number_format($item->override_amount, 2) : '—' }}</td>
                                    <td>{{ number_format($item->final_amount, 2) }}</td>
                                    @if ($canEdit && $offboarding->final_settlement_status === 'pending')
                                        <td>
                                            <form action="{{ route('offboarding.settlement.override-line', [$offboarding->id, $item->id]) }}" method="POST" class="d-flex gap-1">
                                                @csrf
                                                <input type="number" step="0.01" name="amount" class="field-input" style="width:100px" placeholder="Override">
                                                <button type="submit" class="ob-btn ob-btn--sm">Save</button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                            <tr class="ob-total-row">
                                <td>Total</td><td>{{ number_format($offboarding->settlement_computed_total, 2) }}</td><td></td>
                                <td>{{ number_format($offboarding->settlement_final_total, 2) }}</td>
                                @if ($canEdit && $offboarding->final_settlement_status === 'pending')<td></td>@endif
                            </tr>
                        </tbody>
                    </table>

                    @if ($canEdit && $offboarding->final_settlement_status === 'pending')
                        <details class="ob-inline-form">
                            <summary><i class="feather-plus"></i> Add a line (e.g. severance)</summary>
                            <form action="{{ route('offboarding.settlement.add-line', $offboarding->id) }}" method="POST" class="row g-2">
                                @csrf
                                <div class="col-md-3">
                                    <select name="line_type" class="field-select" required>
                                        <option value="severance">Severance</option>
                                        <option value="custom_addition">Custom Addition</option>
                                        <option value="custom_deduction">Custom Deduction</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select name="is_addition" class="field-select" required>
                                        <option value="1">Addition (+)</option>
                                        <option value="0">Deduction (−)</option>
                                    </select>
                                </div>
                                <div class="col-md-3"><input type="text" name="label" class="field-input" placeholder="Label" required></div>
                                <div class="col-md-2"><input type="number" step="0.01" name="amount" class="field-input" placeholder="Amount" required></div>
                                <div class="col-md-2"><button type="submit" class="ob-btn w-100">Add</button></div>
                            </form>
                        </details>

                        <form action="{{ route('offboarding.settlement.finalize', $offboarding->id) }}" method="POST" class="mt-2">
                            @csrf
                            <button type="submit" class="ob-btn">Finalize Settlement</button>
                        </form>
                    @endif

                    @if ($canEdit && $offboarding->final_settlement_status === 'processing')
                        <form action="{{ route('offboarding.settlement.mark-paid', $offboarding->id) }}" method="POST" class="mt-2 d-flex gap-2">
                            @csrf
                            <input type="text" name="payment_reference" class="field-input" style="max-width:220px" placeholder="Payment reference (optional)">
                            <button type="submit" class="ob-btn">Mark as Paid</button>
                        </form>
                    @endif
                @else
                    <p class="ob-empty-hint">Settlement worksheet not generated yet — complete the exit interview stage first.</p>
                @endif
            </div>
        </div>

        {{-- ===== COMPLETE ===== --}}
        @if ($canEdit && $offboarding->current_stage === 'ready_to_complete')
            <div class="ob-panel">
                <div class="ob-panel__body d-flex justify-content-between align-items-center">
                    <p class="mb-0" style="font-size:11px;color:#475569;">All steps are done. Completing will deactivate the employee's account.</p>
                    <form action="{{ route('offboarding.complete', $offboarding->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="ob-btn"><i class="feather-check-circle"></i> Complete Offboarding</button>
                    </form>
                </div>
            </div>
        @endif

        {{-- ===== REMARKS ===== --}}
        @if ($canEdit)
            <div class="ob-panel">
                <div class="ob-panel__head"><h5 class="ob-panel__title"><i class="feather-edit-3"></i> Remarks</h5></div>
                <div class="ob-panel__body">
                    <form action="{{ route('offboarding.remarks.update', $offboarding->id) }}" method="POST" class="row g-2">
                        @csrf
                        <div class="col-md-4"><label class="field-label">HR</label><textarea name="hr_remarks" class="field-input" rows="2">{{ $offboarding->hr_remarks }}</textarea></div>
                        <div class="col-md-4"><label class="field-label">Finance</label><textarea name="finance_remarks" class="field-input" rows="2">{{ $offboarding->finance_remarks }}</textarea></div>
                        <div class="col-md-4"><label class="field-label">IT</label><textarea name="it_remarks" class="field-input" rows="2">{{ $offboarding->it_remarks }}</textarea></div>
                        <div class="col-12"><button type="submit" class="ob-btn">Save Remarks</button></div>
                    </form>
                </div>
            </div>
        @endif

        @endif {{-- status !== pending_approval --}}

    </div>
@endsection
