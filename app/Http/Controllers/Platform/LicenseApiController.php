<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Licensing\LicenseServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** POST https://aqarnova.com/api/license/{activate|verify|deactivate} — called by self-hosted copies. */
class LicenseApiController extends Controller
{
    public function __construct(private LicenseServer $server)
    {
        abort_if(blank(config('rowad.license.signing_key')), 503, 'Licensing is not configured on this platform.');
    }

    public function activate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:40'],
            'domain' => ['required', 'string', 'max:190'],
            'instance_id' => ['required', 'string', 'max:64'],
            'version' => ['nullable', 'string', 'max:40'],
            'php' => ['nullable', 'string', 'max:20'],
        ]);
        [$status, $body] = $this->server->activate($data, $request->ip());

        return response()->json($body, $status);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:40'],
            'instance_id' => ['required', 'string', 'max:64'],
            'version' => ['nullable', 'string', 'max:40'],
        ]);
        [$status, $body] = $this->server->verify($data, $request->ip());

        return response()->json($body, $status);
    }

    public function deactivate(Request $request): JsonResponse
    {
        $data = $request->validate(['key' => ['required', 'string', 'max:40'], 'instance_id' => ['required', 'string', 'max:64']]);
        [$status, $body] = $this->server->deactivate($data);

        return response()->json($body, $status);
    }
}
