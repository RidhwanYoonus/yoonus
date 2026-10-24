<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SignupFormEaseTest extends TestCase
{
    use RefreshDatabase;

    private array $base = ['name' => 'Anna', 'email' => 'anna@example.com', 'password' => 'Secret123', 'password_confirmation' => 'Secret123'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(ReferenceDataSeeder::class);
    }

    public function test_sign_up_form_has_show_password_live_ticks_and_reads_the_id_photo(): void
    {
        $this->get('/register')->assertOk()
            ->assertSee('Show password')->assertSee('At least 8 characters')->assertSee('Capital and small letter')->assertSee('Passwords match')
            ->assertSee('we fill in the number and expiry date for you')->assertSee('id-upload', false)->assertSee('shopIdReader', false);

        Settings::set(['id_auto_read' => false]);
        $this->get('/register')->assertDontSee('this.read(f)', false);
    }

    public function test_live_check_tells_about_used_email_and_weak_password(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $this->postJson('/register/check', ['email' => 'taken@example.com'])->assertJson(['ok' => false])->assertJsonPath('errors.email', fn ($m) => filled($m));
        $this->postJson('/register/check', ['email' => 'new@example.com'])->assertJson(['ok' => true]);
        $this->postJson('/register/check', ['password' => 'secret'])->assertJson(['ok' => false])->assertJsonPath('errors.password', fn ($m) => filled($m));
        $this->postJson('/register/check', ['password' => 'Secret123'])->assertJson(['ok' => true]);
    }

    public function test_id_photo_sent_early_survives_a_form_error_and_is_saved_with_the_account(): void
    {
        $token = $this->post('/id-upload', ['file' => UploadedFile::fake()->image('front.jpg', 800, 500)], ['Accept' => 'application/json'])
            ->assertOk()->json('token');
        $this->assertNotEmpty($token);

        // The form comes back with an error: the photo is still shown as attached.
        $this->from('/register')->post('/register', ['password_confirmation' => 'Other123'] + $this->base + ['id_type' => 'emirates_id', 'id_number' => '784-1990-1234567-1', 'id_front_token' => $token])
            ->assertSessionHasErrors('password');
        $this->get('/register')->assertSee('front.jpg')->assertSee($token, false);

        $this->post('/register', $this->base + ['id_type' => 'emirates_id', 'id_number' => '784-1990-1234567-1', 'id_front_token' => $token])->assertSessionHasNoErrors();
        $user = User::where('email', 'anna@example.com')->sole();
        $this->assertNotNull($user->id_front_path);
        Storage::disk('local')->assertExists($user->id_front_path);
    }

    public function test_someone_elses_token_does_not_attach_a_photo(): void
    {
        $this->post('/register', $this->base + ['id_type' => 'emirates_id', 'id_number' => '784-1990-1234567-1', 'id_front_token' => str_repeat('x', 32)])->assertSessionHasNoErrors();
        $this->assertNull(User::where('email', 'anna@example.com')->value('id_front_path'));
    }

    public function test_upload_rejects_other_files_and_is_off_when_photos_are_not_asked(): void
    {
        $this->post('/id-upload', ['file' => UploadedFile::fake()->create('x.exe', 10)], ['Accept' => 'application/json'])->assertStatus(422);
        Settings::set(['id_photos' => 'hidden']);
        $this->post('/id-upload', ['file' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])->assertNotFound();
    }
}
