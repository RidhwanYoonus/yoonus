<?php

namespace Tests\Feature;

use App\Filament\Resources\VideoCallRequestResource\Pages\ListVideoCallRequests;
use App\Models\User;
use App\Models\VideoCallRequest;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VideoCallLinkTest extends TestCase
{
    use RefreshDatabase;

    private function newCall(): VideoCallRequest
    {
        return VideoCallRequest::create(['token' => str_repeat('a', 40), 'room' => 'shop-abc', 'name' => 'Mona', 'phone' => '+971501112233', 'status' => 'waiting']);
    }

    public function test_jitsi_gets_name_parameters_but_a_google_meet_link_stays_clean(): void
    {
        $call = $this->newCall();
        $this->assertStringStartsWith('https://meet.jit.si/shop-abc#userInfo.displayName=', $call->joinUrl('Mona'));
        Settings::set(['video_call_base_url' => 'https://meet.google.com/csg-wfsx-ihn']);
        $this->assertSame('https://meet.google.com/csg-wfsx-ihn', $call->joinUrl('Mona'));
    }

    public function test_staff_send_a_meeting_link_for_one_call_and_the_customer_page_switches_to_it(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $call = $this->newCall();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        Livewire::test(ListVideoCallRequests::class)
            ->callTableAction('sendLink', $call, ['meeting_url' => 'https://meet.google.com/new-link-abc'])->assertHasNoTableActionErrors();

        $this->assertSame('answered', $call->fresh()->status);
        $this->getJson(route('video-call.status', $call))->assertJson(['status' => 'answered', 'join' => 'https://meet.google.com/new-link-abc']);
    }
}
