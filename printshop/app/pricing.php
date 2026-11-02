<?php
declare(strict_types=1);

const PAPER_SIZES = ['A4', 'A3', 'Letter', 'Legal', 'Custom'];
const PAPER_TYPES = ['Regular', 'Glossy', 'Matte', 'Photo Paper', 'Cardstock'];
const BINDINGS = ['none' => 'No binding', 'spiral' => 'Spiral binding', 'soft' => 'Soft binding', 'hard' => 'Hard binding'];
const COLORS = ['bw' => 'Black & White', 'color' => 'Colored'];
const SIDES = ['single' => 'Single-sided', 'double' => 'Double-sided'];
const FIELD_LABELS = [
    'size' => 'Paper size', 'paper' => 'Paper type', 'color' => 'Printing color', 'sides' => 'Printing side',
    'orientation' => 'Orientation', 'pages' => 'Number of pages', 'copies' => 'Number of copies',
    'binding' => 'Binding', 'dimensions' => 'Width & height (ft)',
];
const PRICING_MODES = [
    'per_page' => 'Per page (uses the printing rates below)',
    'flat_page' => 'Flat price per page',
    'per_unit' => 'Per piece / set',
    'per_sqft' => 'Per square foot',
    'quote' => 'Quote required (starting price)',
];

function pricing_rules(bool $fresh = false): array
{
    static $r = null;
    if ($r === null || $fresh) {
        $r = [];
        foreach (fetch_all('SELECT rule_key, amount FROM pricing_rules') as $row) $r[$row['rule_key']] = (float)$row['amount'];
    }
    return $r;
}

function service_options(array $service): array
{
    $o = json_decode((string)($service['options'] ?? '{}'), true);
    $o = is_array($o) ? $o : [];
    return [
        'fields' => $o['fields'] ?? ['copies'],
        'sizes' => $o['sizes'] ?? ['A4'],
        'papers' => $o['papers'] ?? ['Regular'],
        'colors' => $o['colors'] ?? ['bw'],
        'sides' => $o['sides'] ?? ['single'],
        'bindings' => $o['bindings'] ?? ['none'],
    ];
}

/** Human readable summary of a service's price, used on public pages. */
function service_price_label(array $s): string
{
    $r = pricing_rules();
    switch ($s['pricing_mode']) {
        case 'per_page':
            $g = $s['rate_group'] ?: 'print';
            $o = service_options($s);
            $vals = [];
            foreach ($o['colors'] as $c) if (isset($r["$g.$c"])) $vals[] = $r["$g.$c"];
            if (!$vals) return 'Price on request';
            return 'From ' . peso(min($vals)) . ' / page';
        case 'quote': return 'From ' . peso($s['base_price']) . ' · quote required';
        default: return peso($s['base_price']) . ' / ' . $s['price_unit'];
    }
}

/**
 * Server-side price calculation. The browser calls this for a live estimate and the
 * order endpoint calls it again so the customer can never choose their own price.
 */
function calc_price(array $s, array $in): array
{
    $o = service_options($s);
    $f = $o['fields'];
    $r = pricing_rules();
    $err = [];
    $has = fn(string $k) => in_array($k, $f, true);

    $copiesInput = $has('copies') ? ($in['copies'] ?? 1) : 1;
    $pagesInput = $has('pages') ? ($in['pages'] ?? 1) : 1;
    $validInteger = static fn($value): bool => is_int($value)
        || (is_string($value) && preg_match('/^\d+$/D', $value) === 1);
    if (!$validInteger($copiesInput)) $err[] = 'Copies must be a whole number.';
    if (!$validInteger($pagesInput)) $err[] = 'Pages must be a whole number.';
    $copies = (int)$copiesInput;
    $pages = (int)$pagesInput;
    if ($copies < 1 || $copies > 10000) $err[] = 'Copies must be between 1 and 10,000.';
    if ($pages < 1 || $pages > 5000) $err[] = 'Pages must be between 1 and 5,000.';
    $copies = max(1, min(10000, $copies));
    $pages = max(1, min(5000, $pages));

    $pick = function (string $field, string $key, array $allowed, string $label, $default) use ($has, $in, &$err) {
        if (!$has($field)) return $default;
        $v = (string)($in[$key] ?? $default);
        if (!in_array($v, $allowed, true)) { $err[] = "Invalid $label."; return $default; }
        return $v;
    };
    $size = $pick('size', 'size', $o['sizes'], 'paper size', $o['sizes'][0] ?? 'A4');
    $paper = $pick('paper', 'paper', $o['papers'], 'paper type', $o['papers'][0] ?? 'Regular');
    $color = $pick('color', 'color', $o['colors'], 'printing color', $o['colors'][0] ?? 'bw');
    $sides = $pick('sides', 'sides', $o['sides'], 'printing side', $o['sides'][0] ?? 'single');
    $binding = $pick('binding', 'binding', $o['bindings'], 'binding option', 'none');
    $orient = $pick('orientation', 'orientation', ['portrait', 'landscape'], 'orientation', 'portrait');
    if (!$has('color')) $color = $o['colors'][0] ?? 'bw';
    if (!$has('sides')) $sides = $o['sides'][0] ?? 'single';

    $w = $h = null;
    if ($has('dimensions')) {
        $w = (float)($in['width'] ?? 0); $h = (float)($in['height'] ?? 0);
        if ($w < 0.5 || $w > 100 || $h < 0.5 || $h > 100) $err[] = 'Width and height must be between 0.5 and 100 ft.';
    }

    $lines = [];
    $add = function (string $label, float $amt, string $detail = '') use (&$lines) { $lines[] = ['label' => $label, 'detail' => $detail, 'amount' => round($amt, 2)]; };
    $sizeMult = ($has('size') && isset($r["size.$size"])) ? $r["size.$size"] : 1.0;
    $paperAdd = ($has('paper') && isset($r["paper.$paper"])) ? $r["paper.$paper"] : 0.0;

    switch ($s['pricing_mode']) {
        case 'per_page':
            $g = $s['rate_group'] ?: 'print';
            $rate = $r["$g.$color"] ?? 0.0;
            $units = $pages * $copies;
            $base = $units * $rate;
            $add('Printing', $base, "$pages page(s) × $copies copy/copies × " . peso($rate));
            if ($sizeMult != 1.0) $add("Paper size adjustment ($size)", $base * ($sizeMult - 1), 'size rate × ' . $sizeMult);
            if ($sides === 'double' && isset($r['side.double']) && $r['side.double'] != 1.0) {
                $add('Double-sided adjustment', $base * $sizeMult * ($r['side.double'] - 1), 'rate × ' . $r['side.double']);
            }
            if ($paperAdd > 0) $add("Paper type ($paper)", $units * $paperAdd, "$units sheet(s) × " . peso($paperAdd));
            break;
        case 'flat_page':
            $add($s['name'], $pages * $copies * (float)$s['base_price'], "$pages page(s) × $copies × " . peso($s['base_price']));
            break;
        case 'per_sqft':
            $area = ($w ?? 0) * ($h ?? 0);
            $add($s['name'], $area * $copies * (float)$s['base_price'], round($area, 2) . " sq ft × $copies × " . peso($s['base_price']));
            if ($paperAdd > 0) $add("Material ($paper)", $area * $copies * $paperAdd, '');
            break;
        case 'quote':
            $add('Starting price', $copies * (float)$s['base_price'], "$copies × " . peso($s['base_price']) . ' (final quote after review)');
            break;
        default: // per_unit
            $base = $copies * (float)$s['base_price'];
            $add($s['name'], $base, "$copies × " . peso($s['base_price']));
            if ($sizeMult != 1.0) $add("Size adjustment ($size)", $base * ($sizeMult - 1), 'size rate × ' . $sizeMult);
            if ($paperAdd > 0) $add("Paper type ($paper)", $copies * $paperAdd, "$copies × " . peso($paperAdd));
    }
    if ($binding !== 'none' && $has('binding')) {
        $bp = $r["binding.$binding"] ?? 0.0;
        $add(BINDINGS[$binding], $copies * $bp, "$copies × " . peso($bp));
    }

    $total = round(array_sum(array_column($lines, 'amount')), 2);
    $review = !empty($s['needs_review']) || $s['pricing_mode'] === 'quote' || ($has('size') && $size === 'Custom');
    return [
        'ok' => !$err, 'errors' => $err, 'lines' => $lines, 'total' => max(0, $total),
        'unit_price' => round($total / $copies, 2), 'needs_review' => $review,
        'normalized' => [
            'size' => $has('size') ? $size : null, 'paper' => $has('paper') ? $paper : null,
            'color' => ($has('color') || $s['pricing_mode'] === 'per_page') ? $color : null,
            'sides' => ($has('sides') || $s['pricing_mode'] === 'per_page') ? $sides : null,
            'orientation' => $has('orientation') ? $orient : null,
            'binding' => $has('binding') ? $binding : null,
            'pages' => $pages, 'copies' => $copies, 'width' => $w, 'height' => $h,
        ],
    ];
}
