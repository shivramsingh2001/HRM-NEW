<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use App\Models\BiometricEnrollment;
use App\Services\Biometric\BiometricPunchIngestService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Direct-push receiver for terminals in "FkWeb" server-client mode (device menu
 * Comm -> Webserver URL = http://<host>/api/v1/biometric/fkweb/{push_token}).
 * Replaces the Windows bridge for devices that can reach the server.
 *
 * Wire format (observed on firmware M61BH v3.16.1842): HTTP/1.0 POST, headers
 * request_code / dev_id / trans_id / blk_no, body = 4-byte little-endian JSON
 * length + JSON + optional binary. The device reads only the reply headers
 * (response_code, trans_id); a non-OK reply makes it keep and resend the item.
 *
 *   receive_cmd          ~10s poll for server commands  -> ERROR_NO_CMD (none queued)
 *   realtime_glog        one punch, oldest unsent first  -> OK once persisted
 *   realtime_enroll_data user + FP/face templates, 1 KB blocks -> OK; block 1's
 *                        JSON links the enrollment, templates are discarded
 *
 * Auth = the unguessable push_token in the path (the device cannot send
 * headers); dev_id must also match the device serial.
 */
class BiometricFkWebController extends Controller
{
    public function handle(Request $request, string $token, BiometricPunchIngestService $ingest): Response
    {
        $transId = (string) $request->header('trans_id', '');

        $device = BiometricDevice::where('push_token', $token)->first();
        if (! $device || ! $device->is_active) {
            return $this->reply('ERROR', $transId, 404);
        }

        $devId = $request->header('dev_id');
        if ($devId !== null && $devId !== $device->serial_number) {
            Log::warning('FkWeb dev_id mismatch', ['device_id' => $device->id, 'dev_id' => $devId]);

            return $this->reply('ERROR', $transId, 403);
        }

        $blkNo = (int) $request->header('blk_no', 1);
        $requestCode = (string) $request->header('request_code', '')
            ?: $this->inferRequestCode($request->getContent());

        try {
            switch ($requestCode) {
                case 'receive_cmd':
                    $json = $this->decode($request->getContent());
                    $device->forceFill([
                        'last_seen_at' => now(),
                        'model' => $json['fk_name'] ?? $device->model,
                    ])->save();

                    return $this->reply('ERROR_NO_CMD', $transId);

                case 'realtime_glog':
                    $this->punch($device, $this->decode($request->getContent()), $ingest);

                    return $this->reply('OK', $transId);

                case 'realtime_enroll_data':
                    if ($blkNo <= 1) {
                        $this->enrollment($device, $this->decode($request->getContent()), $ingest);
                    }

                    return $this->reply('OK', $transId);

                case 'realtime_enroll_data_continuation':
                    return $this->reply('OK', $transId);

                default:
                    Log::info('FkWeb unhandled request_code', ['device_id' => $device->id, 'request_code' => $requestCode]);

                    return $this->reply('OK', $transId);
            }
        } catch (\Throwable $e) {
            // Non-OK => the device keeps the record and resends it next cycle.
            Log::error('FkWeb ingest failed', [
                'device_id' => $device->id,
                'request_code' => $requestCode,
                'error' => $e->getMessage(),
            ]);

            return $this->reply('ERROR', $transId, 500);
        }
    }

    /** @param  array<string,mixed>|null  $json */
    private function punch(BiometricDevice $device, ?array $json, BiometricPunchIngestService $ingest): void
    {
        $at = isset($json['io_time']) ? Carbon::createFromFormat('YmdHis', (string) $json['io_time']) : false;
        if (! $json || empty($json['user_id']) || ! $at) {
            // Unparseable — ack anyway, or the device resends it forever and
            // stalls every punch queued behind it.
            Log::warning('FkWeb malformed realtime_glog dropped', ['device_id' => $device->id, 'json' => $json]);

            return;
        }

        $verifyMode = isset($json['verify_mode']) ? (int) $json['verify_mode'] : 0;

        $ingest->ingest($device, [[
            'enroll_no' => $this->enrollNo((string) $json['user_id']),
            'punched_at' => $at->format('Y-m-d H:i:s'),   // device-local wall time, like the bridge
            'raw_verify_mode' => $verifyMode,
            'method' => config('biometric.fkweb.verify_mode_methods')[$verifyMode] ?? null,
            'io_mode' => $json['io_mode'] ?? null,
            'source' => 'fkweb',
        ]]);
    }

    /**
     * Block 1 of an enroll upload carries {user_id, user_name, ...}. Link the
     * enrollment like BiometricV1Controller::reportEnrollments does (without
     * direct onboarding); the biometric templates are never stored.
     *
     * @param  array<string,mixed>|null  $json
     */
    private function enrollment(BiometricDevice $device, ?array $json, BiometricPunchIngestService $ingest): void
    {
        if (! $json || empty($json['user_id'])) {
            return;
        }

        $enrollNo = $this->enrollNo((string) $json['user_id']);
        $name = isset($json['user_name']) ? mb_substr((string) $json['user_name'], 0, 120) : null;
        $selfUserId = $ingest->userIdFromEnroll($device, $enrollNo);

        $row = BiometricEnrollment::firstOrNew([
            'biometric_device_id' => $device->id,
            'enroll_no' => $enrollNo,
        ]);

        if (! $row->exists) {
            $row->fill([
                'tenant_id' => $device->tenant_id,
                'name_on_device' => $name,
                'user_id' => $selfUserId,
                'device_user_id' => $selfUserId,
                'source' => $selfUserId ? 'auto' : 'manual',
                'sync_state' => 'synced',
            ])->save();

            return;
        }

        $patch = [];
        if (empty($row->name_on_device) && $name) {
            $patch['name_on_device'] = $name;
        }
        if (! $row->user_id && $selfUserId) {
            $patch += ['user_id' => $selfUserId, 'device_user_id' => $selfUserId, 'source' => 'auto'];
        }
        if ($patch) {
            $row->update($patch);
        }
    }

    /** @return array<string,mixed>|null */
    private function decode(string $raw): ?array
    {
        if (strlen($raw) < 4) {
            return null;
        }

        $len = unpack('V', substr($raw, 0, 4))[1];
        $json = json_decode(rtrim(substr($raw, 4, $len), "\0"), true);

        return is_array($json) ? $json : null;
    }

    /**
     * nginx (underscores_in_headers off) and Apache+FPM silently drop headers
     * containing "_" — i.e. request_code/trans_id/blk_no. Recover the request
     * type from the body shape; an undecodable body is an enroll continuation
     * block (raw template bytes), which only needs an OK.
     */
    private function inferRequestCode(string $raw): string
    {
        $json = $this->decode($raw);

        return match (true) {
            $json === null => 'realtime_enroll_data_continuation',
            isset($json['io_time']) => 'realtime_glog',
            isset($json['enroll_data_array']) => 'realtime_enroll_data',
            isset($json['fk_info']) || isset($json['fk_name']) => 'receive_cmd',
            default => '',
        };
    }

    /** Device ids arrive zero-padded ("00000015"); enrollments key on "15". */
    private function enrollNo(string $userId): string
    {
        return ctype_digit($userId) ? (ltrim($userId, '0') ?: '0') : $userId;
    }

    private function reply(string $code, string $transId, int $status = 200): Response
    {
        return response('', $status, [
            'response_code' => $code,
            'trans_id' => $transId,
            'Content-Type' => 'application/octet-stream',
            // Required: without it the firmware treats the reply as incomplete and
            // never advances past receive_cmd (no punches are ever sent).
            'Content-Length' => '0',
        ]);
    }
}
