<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Mobile\DestroyPushTokenRequest;
use App\Http\Requests\Api\Mobile\StorePushTokenRequest;
use App\Models\PushToken;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PushTokenController extends Controller
{
    public function store(StorePushTokenRequest $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $data = $request->validated();
        try {
            [$pushToken, $created] = DB::transaction(function () use ($user, $data): array {
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

                $matchingToken = PushToken::query()
                    ->where('token', $data['token'])
                    ->first();
                $matchingDevice = PushToken::query()
                    ->where('user_id', $user->id)
                    ->where('device_id', $data['device_id'])
                    ->first();

                if ($matchingToken !== null && (
                    $matchingToken->user_id !== $user->id
                    || $matchingDevice === null
                    || $matchingDevice->id !== $matchingToken->id
                )) {
                    abort(409, 'This push token is already registered to another account or device.');
                }

                $created = $matchingDevice === null;
                $pushToken = $matchingDevice ?? new PushToken([
                    'user_id' => $user->id,
                    'device_id' => $data['device_id'],
                ]);
                $pushToken->fill([
                    'token' => $data['token'],
                    'platform' => $data['platform'],
                    'device_name' => $data['device_name'] ?? null,
                    'last_seen_at' => now(),
                ])->save();

                return [$pushToken, $created];
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'This push token is already registered to another account or device.');
        }

        return response()->json([
            'data' => [
                'registered' => true,
                'device_id' => $pushToken->device_id,
                'platform' => $pushToken->platform,
                'device_name' => $pushToken->device_name,
                'last_seen_at' => $pushToken->last_seen_at->toISOString(),
            ],
        ], $created ? 201 : 200);
    }

    public function destroy(DestroyPushTokenRequest $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $data = $request->validated();
        $user->pushTokens()
            ->where('token', $data['token'])
            ->when(isset($data['device_id']), fn ($query) => $query->where('device_id', $data['device_id']))
            ->delete();

        return response()->json(null, 204);
    }
}
