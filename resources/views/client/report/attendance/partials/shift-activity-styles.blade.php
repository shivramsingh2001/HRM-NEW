{{-- Shared styles for the Shift Change Log and Shift Requests Register (same look as the Clock In/Out Log). --}}
<style>
    .filter-row { display: flex; flex-wrap: nowrap; gap: 6px; align-items: center; overflow-x: auto; }
    .form-control-sm-custom { border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 8px; font-size: 11px; height: 32px; background-color: #fff; }
    .form-control-sm-custom:focus { border-color: #0D6EFD; box-shadow: 0 0 0 .15rem rgba(13, 110, 253, .12); outline: none; }
    .btn-sm-custom-outline { background: #f4f6fb; color: #475569; border: 1px solid #dfe5f0; border-radius: 8px; padding: 6px 14px; font-size: 11px; font-weight: 600; text-decoration: none; white-space: nowrap; height: 32px; display: inline-flex; align-items: center; gap: 4px; }
    .btn-sm-custom-outline:hover { background: #EFF6FF; color: #0D6EFD; }
    .date-sep { font-size: 11px; color: #6b7385; flex: none; }

    .sa-table-wrap { overflow-x: auto; }
    .sa-table { font-size: 11px; width: max-content; min-width: 100%; border-collapse: separate; border-spacing: 0; }
    .sa-table th, .sa-table td { padding: 7px 10px; vertical-align: top; border-bottom: 1px solid #eef1f7; white-space: nowrap; }
    .sa-table thead th { font-size: 9.5px; font-weight: 700; color: #6b7385; background: #f7faff; text-transform: uppercase; letter-spacing: .3px; border-bottom: 1px solid #EFF6FF; }
    .sa-table tbody tr:hover td { background: #fafcff; }
    .sa-table .col-emp { position: sticky; left: 0; background: #fff; z-index: 1; border-right: 1px solid #eef1f7; vertical-align: middle; }
    .sa-table thead .col-emp { background: #f7faff; z-index: 2; }
    .sa-table tbody tr:hover td.col-emp { background: #fafcff; }
    .sa-table .wrap { white-space: normal !important; min-width: 200px; max-width: 300px; }
    .sub { font-size: 9.5px; color: #6b7385; }
    .chip { display: inline-block; padding: 1px 6px; border-radius: 6px; font-size: 9.5px; font-weight: 600; background: #eef3fd; color: #0D6EFD; }
    .shift-pill { display: inline-flex; align-items: center; gap: 4px; font-weight: 600; }
    .shift-pill i { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
    .muted { color: #94a3b8; }
</style>
