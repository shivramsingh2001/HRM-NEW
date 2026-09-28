<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBiometricPunch;
use App\Models\ApiClient;
use App\Models\AttendanceLocation;
use App\Models\BiometricDevice;
use App\Models\BiometricEnrollment;
use App\Models\BiometricPunch;
use App\Models\User;
use App\Services\Biometric\BiometricRosterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Tenant admin for biometric terminals: register devices, mint the bridge's API
 * key, map enroll numbers to employees, inspect raw punches.
 */
class BiometricController extends Controller
{
    private const TZS = ['Asia/Kolkata', 'Asia/Dubai', 'Asia/Karachi', 'Asia/Dhaka', 'UTC'];

    public function index()
    {
        $tenantId = (int) Auth::user()->tenant_id;

        $devices = BiometricDevice::where('tenant_id', $tenantId)
            ->withCount([
                'enrollments as mapped_count' => fn ($q) => $q->whereNotNull('user_id'),
                'enrollments as unmapped_count' => fn ($q) => $q->whereNull('user_id'),
            ])
            ->orderBy('name')->get();

        return view('client.settings.biometric.index', [
            'devices' => $devices,
            'branches' => AttendanceLocation::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            'timezones' => self::TZS,
            'totalDevices' => $devices->count(),
            'activeDevices' => $devices->where('is_active', true)->count(),
            'inactiveDevices' => $devices->where('is_active', false)->count(),
            'mappedTotal' => $devices->sum('mapped_count'),
            'unmappedTotal' => $devices->sum('unmapped_count'),
        ]);
    }

    public function storeDevice(Request $request)
    {
        $tenantId = (int) Auth::user()->tenant_id;
        $data = $request->validate([
            'serial_number' => ['required', 'string', 'max:120', 'unique:biometric_devices,serial_number'],
            'name' => ['required', 'string', 'max:120'],
            'ip_address' => ['nullable', 'string', 'max:64'],
            'p2p_uid' => ['nullable', 'string', 'max:32'],
            'site_timezone' => ['nullable', 'string', 'max:64'],
            'branch_id' => ['nullable', 'integer'],
            'direction_mode' => ['required', 'in:auto,in,out,by_verify_mode'],
            'provision_scope' => ['nullable', 'in:tenant,branch'],
            'default_privilege' => ['nullable', 'integer', 'in:0,1,2,3'],
            'allow_direct_onboarding' => ['nullable', 'boolean'],
        ]);
        unset($data['provision_scope'], $data['default_privilege'], $data['allow_direct_onboarding']);

        BiometricDevice::create($data + [
            'tenant_id' => $tenantId,
            'is_active' => true,
            'auto_provision' => $request->boolean('auto_provision', true),
            'provision_scope' => $request->input('provision_scope', 'tenant'),
            'default_privilege' => (int) $request->input('default_privilege', 0),
            'allow_direct_onboarding' => $request->boolean('allow_direct_onboarding', false),
            'created_by' => Auth::id(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Device added.']);
        }

        return back()->with('success', 'Device added.');
    }

    public function updateDevice(Request $request, BiometricDevice $device)
    {
        $this->authorizeTenant($device);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'ip_address' => ['nullable', 'string', 'max:64'],
            'p2p_uid' => ['nullable', 'string', 'max:32'],
            'site_timezone' => ['nullable', 'string', 'max:64'],
            'branch_id' => ['nullable', 'integer'],
            'direction_mode' => ['required', 'in:auto,in,out,by_verify_mode'],
            'is_active' => ['nullable', 'boolean'],
            'provision_scope' => ['nullable', 'in:tenant,branch'],
            'default_privilege' => ['nullable', 'integer', 'in:0,1,2,3'],
            'allow_direct_onboarding' => ['nullable', 'boolean'],
        ]);
        unset($data['provision_scope'], $data['default_privilege'], $data['allow_direct_onboarding']);
        $device->update($data + [
            'is_active' => $request->boolean('is_active'),
            'auto_provision' => $request->boolean('auto_provision'),
            'provision_scope' => $request->input('provision_scope', 'tenant'),
            'default_privilege' => (int) $request->input('default_privilege', 0),
            'allow_direct_onboarding' => $request->boolean('allow_direct_onboarding'),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Device updated.']);
        }

        return back()->with('success', 'Device updated.');
    }

    public function destroyDevice(BiometricDevice $device)
    {
        $this->authorizeTenant($device);
        $device->enrollments()->delete();
        $device->delete();

        return back()->with('success', 'Device removed.');
    }

    public function generateBridgeKey(BiometricDevice $device)
    {
        $this->authorizeTenant($device);

        $issued = ApiClient::issue(
            (int) $device->tenant_id,
            "Biometric bridge · {$device->name}",
            ['biometric:write', 'biometric:read'],
            Auth::id(),
            600,
        );
        $device->update(['api_client_id' => $issued['model']->id]);

        return back()
            ->with('success', 'Bridge key generated. Copy the secret now — it is shown only once.')
            ->with('new_api_secret', $issued['secret']);
    }

    /**
     * FkWeb direct push: mint (or, with rotate=1, replace) the device's secret
     * push token and show the Webserver URL to type into the terminal.
     */
    public function pushUrl(Request $request, BiometricDevice $device)
    {
        $this->authorizeTenant($device);

        if (! $device->push_token || $request->boolean('rotate')) {
            $device->update(['push_token' => Str::random(40)]);
        }

        $url = rtrim(config('biometric.fkweb.base_url'), '/') . '/api/v1/biometric/fkweb/' . $device->push_token;

        return back()
            ->with('success', $request->boolean('rotate')
                ? 'Push URL rotated — the old URL stops working now; update the terminal.'
                : 'Push URL ready.')
            ->with('push_url', ['device' => $device->id, 'name' => $device->name, 'url' => $url]);
    }

    public function downloadConfig(BiometricDevice $device)
    {
        $this->authorizeTenant($device);

        $config = [
            'Hrm' => [
                'BaseUrl' => rtrim(config('app.url'), '/'),
                'ApiKey' => '<paste the bridge key secret here>',
            ],
            'PollIntervalSeconds' => 120,
            'RealTimeCapture' => ['Enabled' => false, 'Port' => 5005],
            'Rendezvous' => ['Host' => '', 'Port' => 4000],
            'Devices' => [[
                'Serial' => $device->serial_number,
                'Mode' => $device->p2p_uid ? 'P2p' : 'Tcp',
                'Ip' => $device->ip_address,
                'Port' => 5005,
                'Password' => 0,
                'MachineNumber' => 1,
                'P2pUid' => $device->p2p_uid,
                'SiteTimeZone' => $device->site_timezone ?: 'Asia/Kolkata',
                'BigUserId' => false,
                'Provision' => (bool) $device->auto_provision,
                'CardNumberBase' => (int) config('biometric.roster.card_number_base', 10),
            ]],
        ];

        return response()
            ->streamDownload(fn () => print(json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
                "bridge-{$device->serial_number}.appsettings.json",
                ['Content-Type' => 'application/json']);
    }

    public function enrollments(Request $request, BiometricDevice $device)
    {
        $this->authorizeTenant($device);
        $tenantId = (int) $device->tenant_id;
        $q = trim((string) $request->input('q', ''));

        $counts = [
            'synced' => BiometricEnrollment::where('biometric_device_id', $device->id)->where('sync_state', 'synced')->count(),
            'pending' => BiometricEnrollment::where('biometric_device_id', $device->id)->where('sync_state', 'pending')->count(),
            'removing' => BiometricEnrollment::where('biometric_device_id', $device->id)->where('sync_state', 'removing')->count(),
            'failed' => BiometricEnrollment::where('biometric_device_id', $device->id)->where('sync_state', 'failed')->count(),
        ];

        $rows = BiometricEnrollment::where('biometric_device_id', $device->id)
            ->with('user:id,name,employee_id,card_number')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('enroll_no', 'like', "%{$q}%")
                        ->orWhere('device_user_id', 'like', "%{$q}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%")->orWhere('employee_id', 'like', "%{$q}%"));
                });
            })
            ->orderByRaw("FIELD(sync_state,'failed','pending','removing','synced')")
            ->orderBy('enroll_no')->paginate(50)->withQueryString();

        return view('client.settings.biometric.enrollments', [
            'device' => $device,
            'rows' => $rows,
            'q' => $q,
            'counts' => $counts,
            'employees' => User::where('tenant_id', $tenantId)->where('status', 1)
                ->orderBy('name')->get(['id', 'name', 'employee_id']),
        ]);
    }

    /** Recompute the desired roster; the bridge applies it on its next cycle. */
    public function syncRoster(BiometricDevice $device, BiometricRosterService $roster)
    {
        $this->authorizeTenant($device);
        $r = $roster->rebuild($device);

        $msg = "Roster rebuilt — {$r['targets']} employee(s): {$r['pending']} to push, {$r['removing']} to remove. The bridge applies this on its next poll.";
        if ($r['conflicts']) {
            $msg .= " {$r['conflicts']} enroll number(s) are kept as manual mappings — remove those rows to let auto-provision take them over.";
        }

        return back()->with('success', $msg);
    }

    /** Set (or clear) an employee's card number from the roster page. */
    public function setCard(Request $request, BiometricEnrollment $enrollment, BiometricRosterService $roster)
    {
        if ((int) $enrollment->tenant_id !== (int) Auth::user()->tenant_id) {
            abort(403);
        }
        $data = $request->validate(['card_number' => ['nullable', 'string', 'max:32']]);

        $user = $enrollment->user_id
            ? User::where('id', $enrollment->user_id)->where('tenant_id', $enrollment->tenant_id)->first()
            : null;
        if (! $user) {
            return back()->with('error', 'This enrollment has no mapped employee.');
        }

        $user->update(['card_number' => $data['card_number'] ?: null]);
        $roster->syncUser($user->fresh());

        return back()->with('success',
            $data['card_number'] ? 'Card number saved — queued for push to the device.' : 'Card number cleared.');
    }

    public function repushEnrollment(BiometricEnrollment $enrollment)
    {
        if ((int) $enrollment->tenant_id !== (int) Auth::user()->tenant_id) {
            abort(403);
        }
        $enrollment->update(['sync_state' => 'pending', 'last_error' => null]);

        return back()->with('success', 'Queued for re-push to the device.');
    }

    public function removeEnrollment(BiometricEnrollment $enrollment)
    {
        if ((int) $enrollment->tenant_id !== (int) Auth::user()->tenant_id) {
            abort(403);
        }
        if ($enrollment->device_user_id) {
            $enrollment->update(['sync_state' => 'removing']);

            return back()->with('success', 'Queued for removal from the device.');
        }
        $enrollment->delete();

        return back()->with('success', 'Enrollment removed.');
    }

    public function bulkRepushEnrollments(Request $request, BiometricDevice $device)
    {
        $this->authorizeTenant($device);
        $data = $request->validate([
            'enrollment_ids' => ['required', 'array', 'min:1'],
            'enrollment_ids.*' => ['integer'],
        ]);

        $count = BiometricEnrollment::where('biometric_device_id', $device->id)
            ->whereIn('id', $data['enrollment_ids'])
            ->update(['sync_state' => 'pending', 'last_error' => null]);

        $message = "{$count} enrollment(s) queued for re-push.";

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }

    public function bulkRemoveEnrollments(Request $request, BiometricDevice $device)
    {
        $this->authorizeTenant($device);
        $data = $request->validate([
            'enrollment_ids' => ['required', 'array', 'min:1'],
            'enrollment_ids.*' => ['integer'],
        ]);

        $rows = BiometricEnrollment::where('biometric_device_id', $device->id)
            ->whereIn('id', $data['enrollment_ids'])->get();

        $queued = 0;
        $deleted = 0;
        foreach ($rows as $r) {
            if ($r->device_user_id) {
                $r->update(['sync_state' => 'removing']);
                $queued++;
            } else {
                $r->delete();
                $deleted++;
            }
        }

        $message = trim(($queued ? "{$queued} queued for removal from the device. " : '').($deleted ? "{$deleted} removed." : ''));
        $message = $message ?: 'Nothing to remove.';

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }

    public function mapEnrollment(Request $request, BiometricDevice $device)
    {
        $this->authorizeTenant($device);
        $data = $request->validate([
            'enroll_no' => ['required', 'string', 'max:64'],
            'user_id' => ['required', 'integer'],
        ]);

        if (! User::where('id', $data['user_id'])->where('tenant_id', $device->tenant_id)->exists()) {
            return back()->with('error', 'That employee is not in your company.');
        }

        BiometricEnrollment::updateOrCreate(
            ['biometric_device_id' => $device->id, 'enroll_no' => $data['enroll_no']],
            ['tenant_id' => $device->tenant_id, 'user_id' => $data['user_id'], 'created_by' => Auth::id()],
        );

        return back()->with('success', 'Mapping saved.');
    }

    public function autoMap(BiometricDevice $device)
    {
        $this->authorizeTenant($device);
        $tenantId = (int) $device->tenant_id;

        $mapped = 0;
        BiometricEnrollment::where('biometric_device_id', $device->id)
            ->whereNull('user_id')->get()
            ->each(function ($e) use ($tenantId, &$mapped) {
                $userId = User::where('tenant_id', $tenantId)
                    ->where('employee_id', $e->enroll_no)->where('status', 1)->value('id');
                if ($userId) {
                    $e->update(['user_id' => $userId]);
                    $mapped++;
                }
            });

        // Also create mappings for employees whose employee_id looks like an
        // enroll number even if the device never reported them.
        return back()->with('success', "Auto-mapped {$mapped} enrollment(s) by employee ID.");
    }

    public function unmapEnrollment(BiometricEnrollment $enrollment)
    {
        if ((int) $enrollment->tenant_id !== (int) Auth::user()->tenant_id) {
            abort(403);
        }
        $enrollment->update(['user_id' => null]);

        return back()->with('success', 'Mapping removed.');
    }

    public function punches(Request $request)
    {
        $tenantId = (int) Auth::user()->tenant_id;
        $q = trim((string) $request->input('q', ''));

        $punches = BiometricPunch::where('tenant_id', $tenantId)
            ->with(['user:id,name,employee_id', 'device:id,name'])
            ->when($request->input('status'), fn ($query, $s) => $query->where('status', $s))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('enroll_no', 'like', "%{$q}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%")->orWhere('employee_id', 'like', "%{$q}%"))
                        ->orWhereHas('device', fn ($d) => $d->where('name', 'like', "%{$q}%"));
                });
            })
            ->orderByDesc('id')->paginate(50)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'html' => view('client.settings.biometric.partials._punch-rows', ['punches' => $punches])->render(),
                'pagination' => $punches->links()->toHtml(),
                'is_empty' => $punches->isEmpty(),
            ]);
        }

        return view('client.settings.biometric.punches', ['punches' => $punches, 'q' => $q]);
    }

    public function reprocessPunch(BiometricPunch $punch)
    {
        if ((int) $punch->tenant_id !== (int) Auth::user()->tenant_id) {
            abort(403);
        }
        $punch->update(['status' => 'pending', 'error' => null]);
        ProcessBiometricPunch::dispatch($punch->id);

        return back()->with('success', 'Punch queued for reprocessing.');
    }

    private function authorizeTenant(BiometricDevice $device): void
    {
        if ((int) $device->tenant_id !== (int) Auth::user()->tenant_id) {
            abort(403);
        }
    }
}
