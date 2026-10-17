<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Customer proof of identity: which orders need it, validation, masking and private storage. */
class IdDocument
{
    public const TYPES = ['emirates_id', 'passport', 'gcc_id', 'driving_licence'];

    public const DISK = 'local';   // storage/app/private — never web-accessible

    /** Settings → Delivery & pickup: 'delivery' (home delivery only), 'all' or 'off'. */
    public static function requiredFor(string $fulfilment): bool
    {
        return match ((string) Settings::get('id_verification', 'delivery')) {
            'all' => true,
            'delivery' => $fulfilment === 'delivery',
            default => false,
        };
    }

    public static function normalise(string $type, string $number): string
    {
        $n = strtoupper(preg_replace('/[\s-]+/', '', $number));

        return $type === 'emirates_id' ? preg_replace('/\D/', '', $n) : $n;
    }

    /** Validation rule for the ID number of the chosen type. */
    public static function numberRule(?string $type): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($type) {
            $n = self::normalise((string) $type, (string) $value);
            $ok = match ($type) {
                'emirates_id' => (bool) preg_match('/^784\d{12}$/', $n),
                'passport' => (bool) preg_match('/^[A-Z0-9]{6,12}$/', $n),
                default => (bool) preg_match('/^[A-Z0-9]{5,20}$/', $n),
            };
            if (! $ok) {
                $fail(__($type === 'emirates_id' ? 'shop.id.invalid_eid' : 'shop.id.invalid_number'));
            }
        };
    }

    public static function last4(string $number): string
    {
        return substr($number, -4);
    }

    public static function masked(?string $type, ?string $last4): string
    {
        return $type ? __('shop.id.types.'.$type).' •••• '.$last4 : '—';
    }

    public static function store(UploadedFile $file): string
    {
        return $file->storeAs('ids/'.now()->format('Y/m'), Str::random(40).'.'.strtolower($file->guessExtension() ?: 'jpg'), self::DISK);
    }

    public static function delete(?string ...$paths): void
    {
        foreach (array_filter($paths) as $p) {
            Storage::disk(self::DISK)->delete($p);
        }
    }
}
