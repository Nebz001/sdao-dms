<?php

use App\Models\PushToken;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class]);
    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
});

function mobilePushTokenHeaders(User $user): array
{
    return ['Authorization' => 'Bearer '.$user->createToken('Push test')->plainTextToken];
}

function validMobileExpoToken(string $suffix): string
{
    return "ExponentPushToken[{$suffix}]";
}

test('an authenticated approver can register a device without receiving its push token back', function () {
    $token = validMobileExpoToken('device-token-001');

    $response = $this->withHeaders(mobilePushTokenHeaders($this->adviser))
        ->postJson('/api/mobile/push-tokens', [
            'token' => $token,
            'device_id' => '8d4ca0e4-e4be-4cae-8fda-1a54fc4cad01',
            'platform' => 'android',
            'device_name' => 'SDAO DMS Mobile',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.registered', true)
        ->assertJsonPath('data.device_id', '8d4ca0e4-e4be-4cae-8fda-1a54fc4cad01')
        ->assertJsonPath('data.platform', 'android')
        ->assertJsonMissingPath('data.token');

    $this->assertDatabaseHas('push_tokens', [
        'user_id' => $this->adviser->id,
        'token' => $token,
        'platform' => 'android',
    ]);
});

test('registering a rotated token updates the existing device', function () {
    $headers = mobilePushTokenHeaders($this->adviser);
    $deviceId = '8d4ca0e4-e4be-4cae-8fda-1a54fc4cad02';
    $oldToken = validMobileExpoToken('device-token-old');
    $newToken = validMobileExpoToken('device-token-new');

    $this->withHeaders($headers)->postJson('/api/mobile/push-tokens', [
        'token' => $oldToken,
        'device_id' => $deviceId,
        'platform' => 'ios',
    ])->assertCreated();

    $this->withHeaders($headers)->postJson('/api/mobile/push-tokens', [
        'token' => $newToken,
        'device_id' => $deviceId,
        'platform' => 'ios',
    ])->assertOk();

    expect(PushToken::where('user_id', $this->adviser->id)->count())->toBe(1);
    $this->assertDatabaseMissing('push_tokens', ['token' => $oldToken]);
    $this->assertDatabaseHas('push_tokens', ['token' => $newToken, 'device_id' => $deviceId]);
});

test('a push token cannot be claimed by another account or device', function () {
    $token = validMobileExpoToken('shared-device-token');
    $this->withHeaders(mobilePushTokenHeaders($this->adviser))->postJson('/api/mobile/push-tokens', [
        'token' => $token,
        'device_id' => '8d4ca0e4-e4be-4cae-8fda-1a54fc4cad03',
        'platform' => 'android',
    ])->assertCreated();

    $chair = User::where('email', 'chair-cs@nu-lipa.edu.ph')->firstOrFail();
    $this->withHeaders(mobilePushTokenHeaders($chair))->postJson('/api/mobile/push-tokens', [
        'token' => $token,
        'device_id' => '8d4ca0e4-e4be-4cae-8fda-1a54fc4cad04',
        'platform' => 'android',
    ])->assertStatus(409);

    $this->assertDatabaseHas('push_tokens', ['user_id' => $this->adviser->id, 'token' => $token]);
});

test('an approver can unregister only their own push token', function () {
    $ownedToken = validMobileExpoToken('owned-device-token');
    $otherToken = validMobileExpoToken('other-device-token');
    $chair = User::where('email', 'chair-cs@nu-lipa.edu.ph')->firstOrFail();
    $this->adviser->pushTokens()->create([
        'device_id' => '8d4ca0e4-e4be-4cae-8fda-1a54fc4cad05',
        'token' => $ownedToken,
        'platform' => 'android',
        'last_seen_at' => now(),
    ]);
    $chair->pushTokens()->create([
        'device_id' => '8d4ca0e4-e4be-4cae-8fda-1a54fc4cad06',
        'token' => $otherToken,
        'platform' => 'ios',
        'last_seen_at' => now(),
    ]);

    $this->withHeaders(mobilePushTokenHeaders($this->adviser))
        ->deleteJson('/api/mobile/push-tokens', [
            'token' => $otherToken,
            'device_id' => '8d4ca0e4-e4be-4cae-8fda-1a54fc4cad06',
        ])
        ->assertNoContent();

    $this->assertDatabaseHas('push_tokens', [
        'user_id' => $chair->id,
        'token' => $otherToken,
    ]);

    $this->withHeaders(mobilePushTokenHeaders($this->adviser))
        ->deleteJson('/api/mobile/push-tokens', [
            'token' => $ownedToken,
            'device_id' => '8d4ca0e4-e4be-4cae-8fda-1a54fc4cad05',
        ])
        ->assertNoContent();

    $this->assertDatabaseMissing('push_tokens', ['token' => $ownedToken]);
    $this->assertDatabaseHas('push_tokens', ['token' => $otherToken]);
});

test('push-token registration requires authentication, a mobile approver, and a valid Expo token', function () {
    $this->postJson('/api/mobile/push-tokens', [])->assertUnauthorized();

    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->withHeaders(mobilePushTokenHeaders($student))->postJson('/api/mobile/push-tokens', [
        'token' => validMobileExpoToken('student-device-token'),
        'device_id' => '8d4ca0e4-e4be-4cae-8fda-1a54fc4cad07',
        'platform' => 'android',
    ])->assertForbidden();

    Auth::forgetGuards();

    $this->withHeaders(mobilePushTokenHeaders($this->adviser))->postJson('/api/mobile/push-tokens', [
        'token' => 'not-an-expo-token',
        'device_id' => '8d4ca0e4-e4be-4cae-8fda-1a54fc4cad08',
        'platform' => 'android',
    ])->assertJsonValidationErrors('token');
});
