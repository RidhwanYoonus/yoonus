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
        // Photos are turned upright and shrunk (a phone picture of 8 MB becomes ~300 KB); PDFs are kept as they are.
        return Images::storeJpeg($file, 'ids/'.now()->format('Y/m'), self::DISK);
    }

    /**
     * A photo uploaded on its own as soon as it was chosen (so a form that comes back with an error keeps it):
     * the form then only sends "{field}_token". Kept in this visitor's session only.
     */
    public static function remember(UploadedFile $file): string
    {
        $token = \Illuminate\Support\Str::random(32);
        $uploads = session('id_uploads', []);
        $uploads[$token] = ['path' => self::store($file), 'name' => mb_substr($file->getClientOriginalName(), 0, 80)];
        // Keep the last few; older ones were replaced by the customer.
        while (count($uploads) > 6) {
            self::delete(array_shift($uploads)['path'] ?? null);
        }
        session(['id_uploads' => $uploads]);

        return $token;
    }

    /** The photo uploaded earlier for this field, or null. */
    public static function pending(\Illuminate\Http\Request $request, string $field): ?array
    {
        $token = (string) $request->input($field.'_token', '');

        return $token !== '' ? (session('id_uploads', [])[$token] ?? null) : null;
    }

    /** Earlier upload by token (for showing "attached" again after an error). */
    public static function pendingByToken(?string $token): ?array
    {
        return $token ? (session('id_uploads', [])[$token] ?? null) : null;
    }

    public static function has(\Illuminate\Http\Request $request, string $field): bool
    {
        return $request->hasFile($field) || self::pending($request, $field) !== null;
    }

    /** Stores the photo sent with the form, or takes the one uploaded earlier. */
    public static function take(\Illuminate\Http\Request $request, string $field): ?string
    {
        if ($request->hasFile($field)) {
            return self::store($request->file($field));
        }
        if ($p = self::pending($request, $field)) {
            $uploads = session('id_uploads', []);
            unset($uploads[$request->input($field.'_token')]);
            session(['id_uploads' => $uploads]);

            return $p['path'];
        }

        return null;
    }

    public static function delete(?string ...$paths): void
    {
        foreach (array_filter($paths) as $p) {
            Storage::disk(self::DISK)->delete($p);
        }
    }
}
