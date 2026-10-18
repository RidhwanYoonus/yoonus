<?php

namespace App\Support;

/**
 * Drawn stand-in artwork (ring, necklace, bangle, earrings, pendant, set) used until real
 * product photography is uploaded, and as illustrations on category tiles.
 */
class JewelleryArt
{
    public const TYPES = ['ring', 'necklace', 'bangle', 'earrings', 'pendant', 'set'];

    /** Guess the piece type from a category slug or product name. */
    public static function typeFor(?string ...$hints): string
    {
        $text = strtolower(implode(' ', array_filter($hints)));
        foreach ([
            'earrings' => ['earring', 'jhumka', 'stud', 'hoop', 'أقراط'],
            'bangle' => ['bangle', 'bracelet', 'kada', 'أساور', 'سوار'],
            'necklace' => ['necklace', 'chain', 'choker', 'قلادة', 'سلسلة'],
            'pendant' => ['pendant', 'coin', 'hamsa', 'charm', 'معلقة'],
            'set' => ['set', 'bridal', 'طقم'],
            'ring' => ['ring', 'band', 'solitaire', 'خاتم'],
        ] as $type => $words) {
            foreach ($words as $w) {
                if (str_contains($text, $w)) {
                    return $type;
                }
            }
        }

        return 'ring';
    }

    public static function stoneColour(?string $stone): ?string
    {
        $s = strtolower((string) $stone);

        return match (true) {
            str_contains($s, 'emerald') => '#2f8f62',
            str_contains($s, 'ruby') => '#b3263b',
            str_contains($s, 'sapphire') => '#2a4fa0',
            str_contains($s, 'pearl') => '#f6efe4',
            $s !== '' => '#e9f5fb',            // diamond / other clear stones
            default => null,
        };
    }

    /** Full product placeholder (600×600) with background and label. */
    public static function product(string $type, ?string $stone, int $hue, string $label): string
    {
        $art = self::art($type, self::stoneColour($stone), 'p', $hue);
        $label = e($label);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 600">
  <defs>
    <radialGradient id="pbg" cx="50%" cy="38%" r="80%"><stop offset="0" stop-color="#ffffff" stop-opacity=".5"/><stop offset=".6" stop-color="#e9e6dc" stop-opacity=".18"/><stop offset="1" stop-color="#c9ccd3" stop-opacity=".22"/></radialGradient>
    <radialGradient id="pshadow" cx="50%" cy="50%" r="50%"><stop offset="0" stop-color="#8a6d2b" stop-opacity=".25"/><stop offset="1" stop-color="#8a6d2b" stop-opacity="0"/></radialGradient>
  </defs>
  <rect width="600" height="600" fill="url(#pbg)"/>
  <ellipse cx="300" cy="500" rx="190" ry="22" fill="url(#pshadow)"/>
  {$art}
  <text x="300" y="566" font-family="Inter,Arial,sans-serif" font-size="18" font-weight="600" fill="#8f939d" text-anchor="middle" letter-spacing="7">{$label}</text>
</svg>
SVG;
    }

    /** Small transparent illustration for category tiles. */
    public static function icon(string $type, string $id = 'i'): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="60 40 480 480" aria-hidden="true" class="h-full w-full">'.self::art($type, '#e9f5fb', $id, 42).'</svg>';
    }

    private static function art(string $type, ?string $stone, string $id, int $hue): string
    {
        $gold = <<<DEFS
<defs>
  <linearGradient id="{$id}g" x1="0" y1="0" x2="1" y2="1">
    <stop offset="0" stop-color="hsl({$hue},85%,78%)"/><stop offset=".35" stop-color="hsl({$hue},80%,55%)"/>
    <stop offset=".7" stop-color="hsl({$hue},75%,38%)"/><stop offset="1" stop-color="hsl({$hue},80%,62%)"/>
  </linearGradient>
  <linearGradient id="{$id}s" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="{$stone}"/></linearGradient>
</defs>
DEFS;
        $g = "url(#{$id}g)";
        $s = $stone ? "url(#{$id}s)" : null;
        $gem = fn (int $x, int $y, int $r) => $s
            ? "<g><path d='M".($x - $r).' '.$y.' L'.$x.' '.($y - $r).' L'.($x + $r).' '.$y.' L'.$x.' '.($y + (int) ($r * 1.3))."Z' fill='{$s}' stroke='#9fb3c2' stroke-width='2'/><path d='M".($x - $r).' '.$y.' L'.($x + $r).' '.$y.' M'.$x.' '.($y - $r).' L'.$x.' '.($y + (int) ($r * 1.3))."' stroke='#ffffff' stroke-opacity='.7' stroke-width='1.5'/></g>"
            : "<circle cx='{$x}' cy='{$y}' r='".(int) ($r * .7)."' fill='{$g}' stroke='hsl({$hue},70%,32%)' stroke-width='2'/>";
        $beads = function (array $pts, int $r) use ($g, $hue) {
            return implode('', array_map(fn ($p) => "<circle cx='{$p[0]}' cy='{$p[1]}' r='{$r}' fill='{$g}' stroke='hsl({$hue},70%,30%)' stroke-width='1'/>", $pts));
        };
        $curve = function (int $cx, int $top, int $w, int $depth, int $n) {
            $pts = [];
            for ($i = 0; $i <= $n; $i++) {
                $t = $i / $n;
                $x = $cx - $w / 2 + $w * $t;
                $y = $top + $depth * (1 - (2 * $t - 1) ** 2);
                $pts[] = [(int) round($x), (int) round($y)];
            }

            return $pts;
        };

        $body = match ($type) {
            'necklace' => $beads($curve(300, 120, 340, 250, 34), 9)
                ."<path d='M300 370 v26' stroke='{$g}' stroke-width='5'/>".$gem(300, 420, 30),
            'bangle' => "<ellipse cx='300' cy='300' rx='170' ry='120' fill='none' stroke='{$g}' stroke-width='38'/>"
                ."<ellipse cx='300' cy='300' rx='170' ry='120' fill='none' stroke='#fff' stroke-opacity='.35' stroke-width='3' transform='translate(-5,-6)'/>"
                .$beads(array_map(fn ($a) => [(int) (300 + 170 * cos($a)), (int) (300 + 120 * sin($a))], range(0, 2 * M_PI, M_PI / 8)), 6),
            'earrings' => implode('', array_map(fn ($x) => "<path d='M{$x} 150 a22 22 0 1 1 0.1 0' fill='none' stroke='{$g}' stroke-width='7'/>"
                ."<path d='M{$x} 172 v64' stroke='{$g}' stroke-width='6'/>"
                ."<path d='M{$x} 236 C".($x - 60).' 330 '.($x - 40).' 420 '.$x.' 430 C'.($x + 40).' 420 '.($x + 60).' 330 '.$x." 236Z' fill='{$g}' stroke='hsl({$hue},70%,30%)' stroke-width='2'/>"
                .$gem($x, 360, 22), [210, 390])),
            'pendant' => "<path d='M190 90 L300 230 L410 90' fill='none' stroke='{$g}' stroke-width='5' stroke-dasharray='2 9' stroke-linecap='round'/>"
                ."<circle cx='300' cy='250' r='16' fill='none' stroke='{$g}' stroke-width='7'/>"
                ."<circle cx='300' cy='360' r='95' fill='{$g}' stroke='hsl({$hue},70%,30%)' stroke-width='3'/>"
                ."<circle cx='300' cy='360' r='72' fill='none' stroke='#fff' stroke-opacity='.45' stroke-width='3'/>"
                ."<path d='M300 300 l17 40 h43 l-35 26 13 42 -38 -25 -38 25 13 -42 -35 -26 h43z' fill='none' stroke='hsl({$hue},70%,28%)' stroke-width='3'/>",
            'set' => $beads($curve(300, 100, 300, 210, 30), 8)."<path d='M300 310 v20' stroke='{$g}' stroke-width='5'/>".$gem(300, 350, 26)
                .implode('', array_map(fn ($x) => "<circle cx='{$x}' cy='420' r='12' fill='none' stroke='{$g}' stroke-width='5'/><path d='M{$x} 432 C".($x - 28).' 470 '.($x - 18).' 505 '.$x.' 508 C'.($x + 18).' 505 '.($x + 28).' 470 '.$x." 432Z' fill='{$g}'/>", [170, 430])),
            default => "<ellipse cx='300' cy='330' rx='150' ry='150' fill='none' stroke='{$g}' stroke-width='34'/>"
                ."<ellipse cx='300' cy='330' rx='150' ry='150' fill='none' stroke='#fff' stroke-opacity='.4' stroke-width='3' transform='translate(-6,-7)'/>"
                ."<path d='M262 186 h76 l-12 -22 h-52z' fill='{$g}'/>".$gem(300, 150, 34),
        };

        return $gold."<g>{$body}</g>";
    }
}
