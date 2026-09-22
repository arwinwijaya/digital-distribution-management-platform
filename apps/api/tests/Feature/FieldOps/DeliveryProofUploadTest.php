<?php

namespace Tests\Feature\FieldOps;

use App\Models\Delivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Support\DeliveryTestFixtures;
use Tests\TestCase;

/**
 * Phase 8 — T8: proof-of-delivery media upload (photo + signature).
 *
 * AC-5 contract: the assigned driver uploads photo + signature through the disk
 * abstraction, `proof_of_delivery` json gains `photo_url`/`signature_url` and the
 * `pod_captured_at`/`pod_latitude`/`pod_longitude` columns are stamped. Invalid
 * type/size and cross-driver access are rejected.
 */
class DeliveryProofUploadTest extends TestCase
{
    use DeliveryTestFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function photo(string $name = 'proof.jpg', int $kilobytes = 100): UploadedFile
    {
        return UploadedFile::fake()->image($name, 800, 600)->size($kilobytes);
    }

    private function signature(string $name = 'signature.png', int $kilobytes = 40): UploadedFile
    {
        return UploadedFile::fake()->image($name, 400, 200)->size($kilobytes);
    }

    public function test_assigned_driver_can_upload_proof_of_delivery(): void
    {
        ['driver' => $driver, 'delivery' => $delivery] = $this->createDeliveryFixture('ORD-POD-1', 'pod-1');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($driver))
            ->post("/api/deliveries/{$delivery->id}/proof", [
                'photo' => $this->photo(),
                'signature' => $this->signature(),
                'latitude' => -6.2000000,
                'longitude' => 106.8166667,
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.proof_of_delivery.photo_url', fn ($url) => is_string($url) && $url !== '')
            ->assertJsonPath('data.proof_of_delivery.signature_url', fn ($url) => is_string($url) && $url !== '');

        $fresh = $delivery->fresh();
        $this->assertNotNull($fresh->pod_captured_at);
        $this->assertSame('-6.2000000', $fresh->pod_latitude);
        $this->assertSame('106.8166667', $fresh->pod_longitude);
        $this->assertIsArray($fresh->proof_of_delivery);
        $this->assertArrayHasKey('photo_url', $fresh->proof_of_delivery);
        $this->assertArrayHasKey('signature_url', $fresh->proof_of_delivery);

        $photoPath = $fresh->proof_of_delivery['photo_path'];
        Storage::disk('local')->assertExists($photoPath);
    }

    public function test_upload_requires_both_photo_and_signature(): void
    {
        ['driver' => $driver, 'delivery' => $delivery] = $this->createDeliveryFixture('ORD-POD-2', 'pod-2');

        $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($driver))
            ->post("/api/deliveries/{$delivery->id}/proof", ['photo' => $this->photo()])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['signature']);
    }

    public function test_upload_rejects_an_oversized_photo(): void
    {
        ['driver' => $driver, 'delivery' => $delivery] = $this->createDeliveryFixture('ORD-POD-3', 'pod-3');

        $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($driver))
            ->post("/api/deliveries/{$delivery->id}/proof", [
                'photo' => $this->photo('huge.jpg', 6000),
                'signature' => $this->signature(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_upload_rejects_a_non_image_photo(): void
    {
        ['driver' => $driver, 'delivery' => $delivery] = $this->createDeliveryFixture('ORD-POD-4', 'pod-4');

        $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($driver))
            ->post("/api/deliveries/{$delivery->id}/proof", [
                'photo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
                'signature' => $this->signature(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_another_driver_cannot_upload_proof(): void
    {
        ['delivery' => $delivery] = $this->createDeliveryFixture('ORD-POD-5', 'pod-5');
        $intruder = User::factory()->driver()->create([
            'email' => 'intruder-pod@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($intruder))
            ->post("/api/deliveries/{$delivery->id}/proof", [
                'photo' => $this->photo(),
                'signature' => $this->signature(),
            ])
            ->assertStatus(403);

        $this->assertNull($delivery->fresh()->pod_captured_at);
    }

    public function test_upload_is_rejected_for_a_completed_delivery(): void
    {
        ['driver' => $driver, 'delivery' => $delivery] = $this->createDeliveryFixture(
            'ORD-POD-6',
            'pod-6',
            Delivery::DELIVERED,
        );

        $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($driver))
            ->post("/api/deliveries/{$delivery->id}/proof", [
                'photo' => $this->photo(),
                'signature' => $this->signature(),
            ])
            ->assertStatus(422);
    }

    public function test_upload_requires_authentication(): void
    {
        ['delivery' => $delivery] = $this->createDeliveryFixture('ORD-POD-7', 'pod-7');

        $this->post("/api/deliveries/{$delivery->id}/proof", [
            'photo' => $this->photo(),
            'signature' => $this->signature(),
        ])->assertStatus(401);
    }

    public function test_upload_on_unknown_delivery_is_not_found(): void
    {
        ['driver' => $driver] = $this->createDeliveryFixture('ORD-POD-8', 'pod-8');

        $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($driver))
            ->post('/api/deliveries/999999/proof', [
                'photo' => $this->photo(),
                'signature' => $this->signature(),
            ])
            ->assertStatus(404);
    }

    public function test_filesystems_config_exposes_local_default_and_public_disk(): void
    {
        $this->assertSame('local', config('filesystems.default'));
        $this->assertArrayHasKey('local', config('filesystems.disks'));
        $this->assertArrayHasKey('public', config('filesystems.disks'));
    }
}
