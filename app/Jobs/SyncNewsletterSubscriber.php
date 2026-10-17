<?php

namespace App\Jobs;

use App\Models\NewsletterSubscriber;
use App\Support\Settings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/** Push confirmed (double opt-in) subscribers to Mailchimp and/or Klaviyo when keys are set. */
class SyncNewsletterSubscriber implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $subscriberId) {}

    public function handle(): void
    {
        $s = NewsletterSubscriber::find($this->subscriberId);
        if (! $s?->confirmed_at) {
            return;
        }

        if (($key = Settings::get('mailchimp_api_key')) && ($list = Settings::get('mailchimp_list_id'))) {
            $dc = substr(strrchr($key, '-') ?: '-us1', 1);
            Http::withBasicAuth('shop', $key)->put(
                "https://{$dc}.api.mailchimp.com/3.0/lists/{$list}/members/".md5(strtolower($s->email)),
                ['email_address' => $s->email, 'status_if_new' => 'subscribed', 'language' => $s->locale],
            )->throw();
        }

        if (($key = Settings::get('klaviyo_api_key')) && ($list = Settings::get('klaviyo_list_id'))) {
            Http::withHeaders(['Authorization' => 'Klaviyo-API-Key '.$key, 'revision' => '2024-10-15'])
                ->post('https://a.klaviyo.com/api/profile-subscription-bulk-create-jobs', ['data' => [
                    'type' => 'profile-subscription-bulk-create-job',
                    'attributes' => ['profiles' => ['data' => [[
                        'type' => 'profile',
                        'attributes' => ['email' => $s->email, 'subscriptions' => ['email' => ['marketing' => ['consent' => 'SUBSCRIBED']]]],
                    ]]]],
                    'relationships' => ['list' => ['data' => ['type' => 'list', 'id' => $list]]],
                ]])->throw();
        }
    }
}
