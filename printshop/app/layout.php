<?php
declare(strict_types=1);
/** Shared page chrome: public site header/footer and the sidebar layout for dashboards. */

function icon(string $name, string $cls = ''): string { return '<i data-lucide="' . e($name) . '" class="' . e($cls) . '" aria-hidden="true"></i>'; }

function page_head(string $title, string $bodyClass = ''): void
{
    $biz = setting('business_name', 'Print Shop');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . e($title) . ' | ' . e($biz) . '</title>';
    echo '<script>try{var t=localStorage.getItem("theme");if(!t&&matchMedia("(prefers-color-scheme: dark)").matches)t="dark";if(t)document.documentElement.dataset.theme=t}catch(e){}</script>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">';
    echo '<link rel="stylesheet" href="' . e(url('assets/css/app.css')) . '"></head><body class="' . e($bodyClass) . '">';
}

function page_scripts(string $extra = ''): void
{
    echo '<script src="https://cdn.jsdelivr.net/npm/lucide@0.469.0/dist/umd/lucide.min.js"></script>';
    echo $extra;
    echo '<script src="' . e(url('assets/js/app.js')) . '"></script></body></html>';
}

function brand_logo(): string
{
    $logo = setting('logo', '');
    $name = e(setting('business_name', 'Print Shop'));
    $img = $logo ? '<img src="' . e(url('uploads/branding/' . $logo)) . '" alt="" class="brand-logo">' : '<span class="brand-mark">' . icon('printer') . '</span>';
    return '<a class="brand" href="' . e(url('index.php')) . '">' . $img . '<span>' . $name . '</span></a>';
}

function theme_button(): string
{
    return '<button type="button" class="icon-btn" id="themeToggle" aria-label="Toggle light or dark mode" aria-pressed="false">' . icon('moon') . '</button>';
}

function public_header(string $title, string $active = ''): void
{
    page_head($title, 'public');
    $u = current_user();
    $links = ['index.php' => ['Home', 'home'], 'services.php' => ['Services', 'services'], 'pricing.php' => ['Pricing', 'pricing'],
        'about.php' => ['About Us', 'about'], 'contact.php' => ['Contact Us', 'contact'], 'track.php' => ['Track Order', 'track']];
    $isOpen = store_open_now();
    $statusBadge = $isOpen
        ? '<span class="shop-status-pill open" title="We are open for store pickup">' . icon('store') . ' Open Now</span>'
        : '<span class="shop-status-pill closed" title="Store is currently closed">' . icon('clock') . ' Store Closed</span>';

    echo '<header class="site-header"><div class="container nav">' . brand_logo() . $statusBadge;
    echo '<button class="icon-btn nav-toggle" id="navToggle" aria-label="Open menu" aria-controls="navLinks" aria-expanded="false">' . icon('menu') . '</button>';
    echo '<nav class="nav-links" id="navLinks" aria-label="Main">';
    foreach ($links as $href => [$label, $key]) {
        echo '<a href="' . e(url($href)) . '"' . ($active === $key ? ' class="active" aria-current="page"' : '') . '>' . e($label) . '</a>';
    }
    echo '<span class="nav-sep"></span>';
    if ($u) {
        $dash = $u['role'] === 'admin' ? 'admin/index.php' : 'customer/dashboard.php';
        echo '<a class="btn btn-sm btn-outline" href="' . e(url($dash)) . '">' . icon('layout-dashboard') . ' Dashboard</a>';
        echo '<a class="btn btn-sm btn-ghost" href="' . e(url('logout.php')) . '">Log out</a>';
    } else {
        echo '<a href="' . e(url('login.php')) . '">Login</a><a class="btn btn-sm btn-primary" href="' . e(url('register.php')) . '">Register</a>';
    }
    echo theme_button() . '</nav></div></header><main>' . flash_render();
}

function public_footer(): void
{
    $biz = e(setting('business_name', 'Print Shop'));
    echo '</main><footer class="site-footer"><div class="container footer-grid"><div><strong>' . $biz . '</strong><p class="muted">' . e(setting('tagline', '')) . '</p></div>';
    echo '<div><strong>Visit us</strong><p class="muted">' . nl2br(e(setting('address', ''))) . '<br>' . e(setting('phone', '')) . '<br>' . e(setting('email', '')) . '</p></div>';
    echo '<div><strong>Quick links</strong><p><a href="' . e(url('services.php')) . '">Services</a><br><a href="' . e(url('track.php')) . '">Track an order</a><br><a href="' . e(url('contact.php')) . '">Contact</a></p></div></div>';
    echo '<div class="container footer-bottom">© ' . date('Y') . ' ' . $biz . '. Orders are collected in store. We do not deliver.</div></footer>';
    page_scripts();
}

function dash_nav(string $area): array
{
    if ($area === 'admin') {
        return [
            ['index.php', 'Dashboard', 'layout-dashboard'], ['orders.php', 'Orders', 'clipboard-list'], ['pickups.php', 'Pickups', 'package-check'],
            ['payments.php', 'Payments', 'wallet'], ['services.php', 'Services', 'printer'], ['pricing.php', 'Pricing', 'tags'],
            ['customers.php', 'Customers', 'users'], ['reports.php', 'Reports', 'chart-no-axes-combined'], ['messages.php', 'Messages', 'mail'],
            ['settings.php', 'Settings', 'settings'], ['audit.php', 'Audit Log', 'shield-check'],
        ];
    }
    return [
        ['dashboard.php', 'Dashboard', 'layout-dashboard'], ['order_new.php', 'New Printing Order', 'file-plus-2'], ['orders.php', 'My Orders', 'clipboard-list'],
        ['../track.php', 'Track Order', 'search'], ['payments.php', 'Payments', 'wallet'], ['notifications.php', 'Notifications', 'bell'],
        ['profile.php', 'My Profile', 'user-round'],
    ];
}

function dash_header(string $title, string $area, string $active): void
{
    $u = current_user();
    page_head($title, 'dash dash-' . $area);
    $unread = $u ? unread_count((int)$u['id']) : 0;
    $prefix = $area === 'admin' ? 'admin/' : 'customer/';
    echo '<div class="shell"><aside class="sidebar" id="sidebar"><div class="sidebar-top">' . brand_logo() . '</div>';
    echo '<div class="area-tag">' . ($area === 'admin' ? 'Administrator' : 'Customer portal') . '</div><nav>';
    foreach (dash_nav($area) as [$href, $label, $ic]) {
        $isActive = $active === basename($href, '.php');
        $badge = ($href === 'notifications.php' && $unread) ? '<span class="count">' . $unread . '</span>' : '';
        echo '<a href="' . e(url($href[0] === '.' ? ltrim(substr($href, 3), '/') : $prefix . $href)) . '"' . ($isActive ? ' class="active" aria-current="page"' : '') . '>' . icon($ic) . '<span>' . e($label) . '</span>' . $badge . '</a>';
    }
    echo '</nav><div class="sidebar-bottom"><a href="' . e(url('index.php')) . '">' . icon('globe') . '<span>View website</span></a>';
    echo '<a href="' . e(url('logout.php')) . '">' . icon('log-out') . '<span>Logout</span></a></div></aside>';
    echo '<div class="content"><header class="topbar"><button class="icon-btn" id="sidebarToggle" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false">' . icon('menu') . '</button>';
    echo '<h1>' . e($title) . '</h1><div class="topbar-right">' . theme_button();
    echo '<a class="icon-btn bell" href="' . e(url($prefix . 'notifications.php')) . '" aria-label="Notifications (' . $unread . ' unread)">' . icon('bell') . ($unread ? '<span class="dot">' . ($unread > 99 ? '99+' : $unread) . '</span>' : '') . '</a>';
    echo '<span class="user-chip">' . icon('user-round') . e($u['full_name'] ?? '') . '</span></div></header><main class="page">' . flash_render();
}

function dash_footer(string $extra = ''): void
{
    echo '</main></div></div>';
    page_scripts($extra);
}

/** Confirmation-dialog markup used by all [data-confirm] buttons. */
function confirm_form(string $action, array $hidden, string $label, string $confirm, string $class = 'btn btn-sm btn-primary'): string
{
    $h = csrf_field();
    foreach ($hidden as $k => $v) $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    return '<form method="post" action="' . e($action) . '" class="inline">' . $h . '<button class="' . e($class) . '" data-confirm="' . e($confirm) . '">' . $label . '</button></form>';
}

function order_progress(string $status): string
{
    $steps = ['order_received', 'awaiting_review', 'pending_payment', 'payment_verified', 'processing', 'printing', 'quality_checking', 'ready_for_pickup', 'completed'];
    if ($status === 'cancelled') return '<div class="alert alert-error">This order was cancelled.</div>';
    $idx = array_search($status, $steps, true);
    if ($status === 'awaiting_customer_confirmation') $idx = 1;
    $out = '<ol class="progress">';
    foreach ($steps as $i => $s) {
        $cls = $idx !== false && $i < $idx ? 'done' : ($i === $idx ? 'current' : '');
        $label = $s === 'awaiting_review' && $status === 'awaiting_customer_confirmation' ? 'Awaiting Your Confirmation' : ORDER_STATUSES[$s];
        $out .= '<li class="' . $cls . '"><span class="step-dot">' . ($cls === 'done' ? icon('check') : '') . '</span><span class="step-label">' . e($label) . '</span></li>';
    }
    return $out . '</ol>';
}

function category_icon(string $slug): string
{
    return ['document-printing' => 'file-text', 'photo-printing' => 'image', 'document-services' => 'copy', 'binding-services' => 'book-open',
        'marketing-materials' => 'megaphone', 'custom-printing' => 'ruler'][$slug] ?? 'printer';
}

function service_card(array $s): string
{
    $o = service_options($s);
    $chips = '';
    foreach (array_slice($o['sizes'], 0, 4) as $z) if (in_array('size', $o['fields'], true)) $chips .= '<span class="chip">' . e($z) . '</span>';
    foreach ($o['colors'] as $c) if (in_array('color', $o['fields'], true)) $chips .= '<span class="chip">' . e(COLORS[$c]) . '</span>';
    foreach ($o['bindings'] as $b) if ($b !== 'none' && in_array('binding', $o['fields'], true)) $chips .= '<span class="chip">' . e(BINDINGS[$b]) . '</span>';
    $media = !empty($s['image'])
        ? '<img src="' . e(url('uploads/services/' . $s['image'])) . '" alt="" loading="lazy">'
        : '<div class="svc-ph">' . icon(category_icon($s['category_slug'] ?? '')) . '</div>';
    return '<article class="card svc-card">' . $media . '<h3>' . e($s['name']) . '</h3><p class="muted grow">' . e($s['description']) . '</p>'
        . ($chips ? '<div class="chips">' . $chips . '</div>' : '')
        . '<div class="price">' . e(service_price_label($s)) . '</div>'
        . '<div class="small muted">Ready in about ' . (int)$s['processing_days'] . ' day(s)' . ($s['needs_review'] ? ' · final price confirmed after review' : '') . '</div>'
        . '<a class="btn btn-primary" href="' . e(url('customer/order_new.php?service=' . $s['slug'])) . '">Order Now</a></article>';
}

function active_services(string $extraWhere = '', array $params = []): array
{
    return fetch_all("SELECT s.*, c.name AS category_name, c.slug AS category_slug FROM services s JOIN service_categories c ON c.id = s.category_id
        WHERE s.is_active = 1 $extraWhere ORDER BY c.sort_order, s.sort_order, s.id", $params);
}

function hours_table(): string
{
    $today = strtolower(substr(date('D'), 0, 3));
    $out = '<table class="hours-table">';
    foreach (store_hours() as $k => $h) {
        $out .= '<tr' . ($k === $today ? ' class="today"' : '') . '><td>' . DAYS[$k] . '</td><td>' . (!empty($h['closed']) ? 'Closed' : fmt_time($h['open']) . ' – ' . fmt_time($h['close'])) . '</td></tr>';
    }
    return $out . '</table>';
}
