<?php

namespace App\Support;

/**
 * Home page wording the shop edits itself (Settings → Home page): the big banner text and the four
 * small "why buy here" boxes under it. Empty boxes fall back to the built-in wording.
 */
class HomeContent
{
    /** Icons the admin can pick for a box (Heroicons outline paths). */
    public const ICONS = [
        'rate' => ['Gold rate chart', 'M3 3v18h18M7 15l4-4 3 3 5-6'],
        'hallmark' => ['Check badge', 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        'store' => ['Shop / showroom', 'M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72'],
        'secure' => ['Lock (secure payment)', 'M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z'],
        'diamond' => ['Diamond', 'M6 3h12l4 6-10 12L2 9l4-6zM2 9h20M12 21 8 9l4-6 4 6-4 12'],
        'factory' => ['Factory', 'M3 21V10l6 4V10l6 4V6l6 4v11H3z'],
        'gift' => ['Gift', 'M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 109.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1114.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z'],
        'chat' => ['Chat / WhatsApp', 'M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z'],
        'card' => ['Card / pay later', 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z'],
        'delivery' => ['Delivery truck', 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12'],
    ];

    /** Built-in boxes: [icon, translation key]. */
    private const DEFAULT_BOXES = [['rate', 'rate'], ['hallmark', 'hallmark'], ['store', 'store'], ['secure', 'secure']];

    /** Banner text in the visitor's language: own text from Settings, else the built-in wording. */
    public static function text(string $key): string
    {
        $own = Settings::get('home_'.$key.(app()->getLocale() === 'ar' ? '_ar' : ''));

        return filled($own) ? (string) $own : __('shop.home.'.$key);
    }

    /** @return array<array{icon: string, title: string, text: string}> the four boxes under the banner */
    public static function boxes(): array
    {
        $ar = app()->getLocale() === 'ar' ? '_ar' : '';
        $boxes = [];
        foreach (self::DEFAULT_BOXES as $i => [$icon, $key]) {
            $n = $i + 1;
            $boxes[] = [
                'icon' => self::ICONS[Settings::get("home_box{$n}_icon") ?: $icon][1] ?? self::ICONS[$icon][1],
                'title' => Settings::get("home_box{$n}_title{$ar}") ?: __("shop.home.trust_{$key}_title"),
                'text' => Settings::get("home_box{$n}_text{$ar}") ?: __("shop.home.trust_{$key}"),
            ];
        }

        return $boxes;
    }

    /** "Card · Apple Pay · …" from the payment methods that are switched on. */
    public static function paymentList(): string
    {
        $on = array_filter([
            __('shop.footer.pay_card') => true,
            'Apple Pay' => Settings::bool('applepay_enabled'),
            'Google Pay' => Settings::bool('googlepay_enabled'),
            'Tabby' => Settings::bool('tabby_enabled'),
            'Tamara' => Settings::bool('tamara_enabled'),
            __('shop.footer.pay_cod') => Settings::bool('cod_enabled'),
        ]);

        return implode(' · ', array_keys($on));
    }

    public static function payLaterOn(): bool
    {
        return Settings::bool('tabby_enabled') || Settings::bool('tamara_enabled');
    }
}
