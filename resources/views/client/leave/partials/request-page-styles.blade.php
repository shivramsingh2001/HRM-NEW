{{--
    Shared look for the Comp-off and Floating Week-offs pages (leave.comp-off.index,
    leave.floating-weekoffs.index): "how it works" note, kpi5 stat row, status tabs,
    employee / date cells and the drawer form. Same anatomy as Week-off Swaps.
--}}
<style>
    .lr-page .content-area-body { padding: 10px 14px !important; }

    /* "How it works" note */
    .lr-info { display: flex; gap: 12px; align-items: flex-start; background: #fff; border: 1px solid var(--border, #e2e8f0); border-radius: 10px; padding: 12px 14px; margin-bottom: 12px; box-shadow: var(--shadow-sm); }
    .lr-info-icon { width: 34px; height: 34px; flex-shrink: 0; border-radius: 50%; background: var(--primary-light, #EFF6FF); color: var(--icon-color, #0D6EFD); display: inline-flex; align-items: center; justify-content: center; font-size: 15px; }
    .lr-info h6 { font-size: 13px; font-weight: 700; margin: 0 0 3px; color: #0f172a; }
    .lr-info p { font-size: 12px; color: #475569; margin: 0; line-height: 1.5; }

    /* Stat cards (kpi5) */
    .lr-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 12px; }
    .lr-stats.cols-2 { grid-template-columns: repeat(2, 1fr); }
    @media (max-width: 992px) { .lr-stats { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 576px) { .lr-stats, .lr-stats.cols-2 { grid-template-columns: 1fr; } }
    .lr-stats .kpi5-value small { font-size: 11px; font-weight: 600; color: #64748b; }

    /* Status tabs in a card header */
    .lr-tabs { display: inline-flex; gap: 2px; background: #f1f5f9; border-radius: 8px; padding: 2px; }
    .lr-tabs a { font-size: 11.5px; font-weight: 600; color: #475569; padding: 4px 10px; border-radius: 6px; text-decoration: none; white-space: nowrap; }
    .lr-tabs a:hover { color: #0D6EFD; }
    .lr-tabs a.active { background: #fff; color: #0D6EFD; box-shadow: 0 1px 2px rgba(15, 23, 42, .08); }

    /* Tabbed card (My … | Team Requests) — same tabs as Shift Requests */
    .lr-tabcard .nav-tabs .nav-link { padding: 9px 14px; font-size: 13px; font-weight: 600; color: #475569; }
    .lr-tabcard .nav-tabs .nav-link.active { color: #0D6EFD; }
    .lr-tabcard .nav-tabs .count { font-size: 10px; font-weight: 700; color: #fff; background: var(--primary, #0D6EFD); border-radius: 999px; padding: 1px 7px; margin-left: 4px; }
    .lr-tabcard .nav-tabs .count.is-grey { background: #e2e8f0; color: #475569; }
    .lr-subbar { display: flex; align-items: center; gap: 8px; padding: 10px 14px; border-bottom: 1px solid #f1f5f9; }

    /* Table cells */
    .lr-page td, .lr-page th { font-size: 12px; vertical-align: middle; }
    .lr-emp { display: flex; align-items: center; gap: 8px; min-width: 160px; }
    .lr-avatar { width: 30px; height: 30px; border-radius: 50%; background: #0D6EFD; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex: none; }
    .lr-emp strong { display: block; color: #0f172a; font-weight: 600; }
    .lr-emp small, .lr-date small, .lr-muted { color: #64748b; font-size: 11px; }
    .lr-date strong { display: block; color: #0f172a; font-weight: 600; }
    .lr-tag { display: inline-block; font-size: 10.5px; font-weight: 600; padding: 2px 8px; border-radius: 999px; background: var(--primary-light, #EFF6FF); color: #0D6EFD; white-space: nowrap; }
    .lr-tag.is-grey { background: #f1f5f9; color: #475569; }
    .lr-reason { max-width: 240px; white-space: normal; color: #475569; }
    .lr-page .status-badge[data-status="expired"] { background: #f1f5f9; color: #475569; }
    #lrDecisionModal textarea { font-size: 12px; }
    #lrDecisionModal .modal-body { padding: 14px 16px; }
    .lr-actions { display: inline-flex; gap: 6px; justify-content: flex-end; }

    /* Drawer form */
    .lr-drawer .form-label { font-size: 11.5px; font-weight: 600; color: #475569; margin-bottom: 4px; }
    .lr-drawer .form-control, .lr-drawer .form-select { font-size: 12px; }
    .lr-hint { display: flex; align-items: flex-start; gap: 8px; font-size: 12px; border-radius: 8px; padding: 8px 10px; background: #f8fafc; color: #475569; border: 1px dashed #dbe3ef; }
    .lr-hint.is-ok { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
    .lr-hint.is-bad { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
</style>
