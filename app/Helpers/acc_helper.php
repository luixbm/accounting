<?php

use Config\Accounting;

if (! function_exists('money')) {
    /**
     * Format a number with thousands separators. Negatives shown in parentheses.
     */
    function money($value, int $decimals = 2, bool $blankZero = false): string
    {
        $value = (float) $value;
        if ($blankZero && abs($value) < 0.005) {
            return '';
        }
        $s = number_format(abs($value), $decimals, '.', ',');

        return $value < 0 ? '(' . $s . ')' : $s;
    }
}

if (! function_exists('base_currency')) {
    /**
     * The active company's base (functional) currency row. Resolved from
     * companies.base_currency, falling back to the currency flagged is_base,
     * then the first active currency. Cached per company for the request.
     *
     * @return array<string,mixed>|null
     */
    function base_currency(): ?array
    {
        static $cache = [];
        $code = strtoupper((string) (active_company()['base_currency'] ?? ''));
        $key  = $code ?: '*';
        if (! array_key_exists($key, $cache)) {
            $m = model(\App\Models\CurrencyModel::class);
            $cache[$key] = ($code ? $m->where('code', $code)->first() : null)
                ?: $m->where('is_base', 1)->first()
                ?: $m->orderBy('id', 'ASC')->first();
        }

        return $cache[$key];
    }
}

if (! function_exists('base_code')) {
    /** ISO code of the active company's base currency (e.g. IDR, MYR, HKD). */
    function base_code(): string
    {
        return (string) (base_currency()['code'] ?? 'IDR');
    }
}

if (! function_exists('base_symbol')) {
    /** Display symbol of the active company's base currency (falls back to the code). */
    function base_symbol(): string
    {
        $c = base_currency();

        return (string) ($c['symbol'] ?? '') ?: (string) ($c['code'] ?? 'IDR');
    }
}

if (! function_exists('money_c')) {
    /**
     * money() prefixed with the active company's base-currency symbol.
     * e.g. "Rp 1,000.00" for an IDR company, "RM 1,000.00" for MYR.
     */
    function money_c($value, bool $blankZero = false, int $decimals = 2): string
    {
        $n = money($value, $decimals, $blankZero);

        return $n === '' ? '' : base_symbol() . ' ' . $n;
    }
}

if (! function_exists('rupiah')) {
    /** Back-compat alias. Now formats in the active company's base currency, not always IDR. */
    function rupiah($value, bool $blankZero = false, int $decimals = 2): string
    {
        return money_c($value, $blankZero, $decimals);
    }
}

if (! function_exists('allowed_company_ids')) {
    /**
     * Company ids the current user may switch into, or null when unrestricted
     * (no `user_companies` rows, or no logged-in user / API request). Cached
     * for the request. Never calls active_company_id() — no recursion.
     *
     * @return list<int>|null
     */
    function allowed_company_ids(): ?array
    {
        static $cache = false;

        if ($cache !== false) {
            return $cache;
        }
        $uid = (int) (function_exists('auth') && auth()->loggedIn() ? auth()->id() : 0);
        if ($uid <= 0) {
            return $cache = null;
        }
        try {
            $ids = model(\App\Models\UserCompanyModel::class)->idsFor($uid);
        } catch (\Throwable $e) {
            return $cache = null; // table not migrated yet
        }

        return $cache = ($ids === [] ? null : $ids);
    }
}

if (! function_exists('user_can_company')) {
    /** May the current user work in this company id? */
    function user_can_company(int $companyId): bool
    {
        $ids = allowed_company_ids();

        return $ids === null || in_array($companyId, $ids, true);
    }
}

if (! function_exists('active_company_id')) {
    /**
     * The company the current request is working in. On an authenticated API
     * request this is the token's company; otherwise it is the session's,
     * constrained to the user's allowed branches.
     */
    function active_company_id(): int
    {
        $api = \App\Libraries\Api\ApiContext::companyId();
        if ($api !== null && $api > 0) {
            return $api;
        }

        $s       = session();
        $allowed = allowed_company_ids();
        $id      = (int) $s->get('active_company_id');
        if ($id > 0 && ($allowed === null || in_array($id, $allowed, true))) {
            return $id;
        }

        // pick the first active company the user is allowed into
        $q = model(\App\Models\CompanyModel::class)->where('is_active', 1)->orderBy('id', 'ASC');
        if ($allowed !== null) {
            $q->whereIn('id', $allowed ?: [0]);
        }
        $first = $q->first();
        $id    = $first ? (int) $first['id'] : ($allowed[0] ?? 1);
        $s->set('active_company_id', $id);

        return $id;
    }
}

if (! function_exists('active_company')) {
    /** @return array<string,mixed>|null */
    function active_company(): ?array
    {
        static $cache = [];
        $id = active_company_id();
        if (! array_key_exists($id, $cache)) {
            $cache[$id] = model(\App\Models\CompanyModel::class)->find($id);
        }

        return $cache[$id];
    }
}

if (! function_exists('company_name')) {
    function company_name(): string
    {
        $c = active_company();

        return $c['name'] ?? 'Simple Accounting';
    }
}

if (! function_exists('company_logo_url')) {
    /**
     * Public URL of the active company's logo, or null if none set.
     */
    function company_logo_url(): ?string
    {
        $c    = active_company();
        $path = (string) ($c['logo_path'] ?? '');
        if ($path === '') {
            return null;
        }

        return is_file(FCPATH . ltrim($path, '/')) ? base_url($path) : null;
    }
}

if (! function_exists('company_initials')) {
    function company_initials(): string
    {
        $words = preg_split('/\s+/', trim(company_name())) ?: [];
        $ini   = '';
        foreach (array_slice($words, 0, 2) as $w) {
            $ini .= mb_substr($w, 0, 1);
        }

        return mb_strtoupper($ini ?: 'SA');
    }
}

if (! function_exists('app_locale')) {
    /**
     * Resolve the active UI language: the viewer's cookie, then the company
     * default setting, then Indonesian.
     */
    function app_locale(): string
    {
        $ok = ['id', 'en'];
        $c  = (string) (service('request')->getCookie('locale') ?? '');
        if (in_array($c, $ok, true)) {
            return $c;
        }
        $s = (string) (setting()->get('Accounting.locale') ?? '');

        return in_array($s, $ok, true) ? $s : 'id';
    }
}

if (! function_exists('app_theme')) {
    /**
     * Default UI theme from settings: light | green | blue | dark.
     * (Per-user choice is layered on top client-side via localStorage.)
     */
    function app_theme(): string
    {
        $t = (string) acc_setting('theme');

        return in_array($t, ['light', 'green', 'blue', 'dark'], true) ? $t : 'light';
    }
}

if (! function_exists('acc_setting')) {
    /**
     * Read an Accounting config value: per-company override first, then a
     * global override, then the Config\Accounting default.
     */
    function acc_setting(string $key)
    {
        $ctx = 'company:' . active_company_id();
        $val = setting()->get('Accounting.' . $key, $ctx);
        if ($val === null || $val === '') {
            $val = setting()->get('Accounting.' . $key);
        }
        if ($val === null || $val === '') {
            $val = (new Accounting())->{$key} ?? null;
        }

        return $val;
    }
}

if (! function_exists('acc_setting_set')) {
    function acc_setting_set(string $key, $value): void
    {
        setting()->set('Accounting.' . $key, $value, 'company:' . active_company_id());
    }
}

if (! function_exists('user_can')) {
    /**
     * Null-safe permission check for the current user.
     */
    function user_can(string $permission): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can($permission);
    }
}

if (! function_exists('status_badge')) {
    function status_badge(string $status): string
    {
        $map = [
            'draft'  => 'badge badge-gray',
            'posted' => 'badge badge-green',
            'void'   => 'badge badge-red',
            'open'   => 'badge badge-green',
            'closed' => 'badge badge-red',
        ];
        $cls = $map[$status] ?? 'badge badge-gray';

        return '<span class="' . $cls . '">' . esc(ucfirst($status)) . '</span>';
    }
}

if (! function_exists('module_enabled')) {
    /**
     * True when a controller class is present, so the sidebar can show a
     * link for a module only once its code has shipped.
     */
    function module_enabled(string $controller): bool
    {
        return class_exists('App\\Controllers\\' . $controller);
    }
}

if (! function_exists('nav_icon')) {
    /**
     * Tiny 16px stroke icon for the sidebar. Uses currentColor.
     */
    function nav_icon(string $name): string
    {
        $p = [
            'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
            'accounts'  => '<path d="M4 5a2 2 0 0 1 2-2h11l3 3v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
            'supplier'  => '<path d="M3 7h11v8H3zM14 10h4l3 3v2h-7z"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/>',
            'customer'  => '<circle cx="9" cy="8" r="3"/><path d="M4 20c0-3 2.5-5 5-5s5 2 5 5"/><path d="M16 11a3 3 0 0 0 0-6"/><path d="M18 20c0-2-1-3.5-2.5-4.3"/>',
            'journal'   => '<path d="M5 4h10l4 4v12H5z"/><path d="M15 4v4h4"/><path d="M9 13h6M9 16h6"/>',
            'purchase'  => '<path d="M4 6h16l-1.5 9h-13z"/><path d="M4 6 3 3H1"/><circle cx="9" cy="19" r="1.6"/><circle cx="17" cy="19" r="1.6"/>',
            'sales'     => '<path d="M4 19V5M4 19h16"/><path d="M8 15l3-4 3 3 5-7"/>',
            'job'       => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
            'reports'   => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 16v-4M12 16V8M16 16v-6"/>',
            'currency'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v10M9.5 9.5c0-1.2 1.1-2 2.5-2s2.5.9 2.5 2-1 1.7-2.5 2-2.5.9-2.5 2 1.1 2 2.5 2 2.5-.8 2.5-2"/>',
            'period'    => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/>',
            'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1l2-1.5-2-3.5-2.4 1a7 7 0 0 0-1.7-1L14.5 2h-4l-.3 2.5a7 7 0 0 0-1.7 1l-2.4-1-2 3.5 2 1.5a7 7 0 0 0 0 2l-2 1.5 2 3.5 2.4-1a7 7 0 0 0 1.7 1l.3 2.5h4l.3-2.5a7 7 0 0 0 1.7-1l2.4 1 2-3.5-2-1.5a7 7 0 0 0 .1-1z"/>',
            'users'     => '<circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="9" r="2.5"/><path d="M15 14.5c2.5.4 4.5 2.6 4.5 5.5"/>',
            'megaphone' => '<path d="M3 11v2a1 1 0 0 0 1 1h2l9 5V6L6 11H4a1 1 0 0 0-1 0z"/><path d="M15 8a4 4 0 0 1 0 8"/><path d="M7 14v4a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-3"/>',
        ];

        return '<svg class="ic" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
            . 'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . ($p[$name] ?? '') . '</svg>';
    }
}

if (! function_exists('date_id')) {
    function date_id(?string $date): string
    {
        if (! $date) {
            return '';
        }

        return date('d/m/Y', strtotime($date));
    }
}

if (! function_exists('user_avatar_url')) {
    /**
     * Public URL of a user's profile photo, or null if none / missing on disk.
     * Defaults to the logged-in user. Results are cached per request.
     *
     * @see \App\Libraries\AvatarStore
     */
    function user_avatar_url(?int $userId = null): ?string
    {
        static $cache = [];

        $userId ??= (int) (auth()->id() ?? 0);
        if ($userId <= 0) {
            return null;
        }
        if (! array_key_exists($userId, $cache)) {
            $row  = db_connect()->table('users')->select('avatar_path')->where('id', $userId)->get()->getRowArray();
            $path = (string) ($row['avatar_path'] ?? '');
            $cache[$userId] = ($path !== '' && is_file(FCPATH . ltrim($path, '/'))) ? base_url($path) : null;
        }

        return $cache[$userId];
    }
}

if (! function_exists('user_avatar_initials')) {
    /** 1-2 uppercase letters for the fallback avatar chip. */
    function user_avatar_initials(?string $seed = null): string
    {
        $seed ??= (string) (auth()->user()->username ?? auth()->user()->email ?? '');
        $seed = trim($seed);
        if ($seed === '') {
            return '?';
        }
        $seed  = explode('@', $seed)[0];
        $words = preg_split('/[\s._-]+/', $seed, -1, PREG_SPLIT_NO_EMPTY) ?: [$seed];
        $ini   = '';
        foreach (array_slice($words, 0, 2) as $w) {
            $ini .= mb_substr($w, 0, 1);
        }
        if (mb_strlen($ini) < 2) {
            $ini = mb_substr($seed, 0, 2);
        }

        return mb_strtoupper($ini);
    }
}

if (! function_exists('user_avatar_tag')) {
    /**
     * <img> when the user has a photo, otherwise an initials chip. $class is
     * appended to the base ".avatar" class (e.g. "avatar-sm").
     */
    function user_avatar_tag(?int $userId = null, string $class = '', ?string $seed = null): string
    {
        $userId ??= (int) (auth()->id() ?? 0);
        $cls = trim('avatar ' . $class);
        $url = user_avatar_url($userId);
        if ($url) {
            return '<img class="' . esc($cls, 'attr') . '" src="' . esc($url, 'attr') . '" alt="">';
        }

        return '<span class="' . esc($cls, 'attr') . ' is-fallback">' . esc(user_avatar_initials($seed)) . '</span>';
    }
}

if (! function_exists('current_announcements')) {
    /**
     * Live announcements for the active company (active + inside their date
     * window), most prominent first. Empty when signed out. Cached per request.
     *
     * @return list<array<string,mixed>>
     */
    function current_announcements(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }
        if (! function_exists('auth') || ! auth()->loggedIn()) {
            return $cache = [];
        }

        try {
            $cache = model(\App\Models\AnnouncementModel::class)->current();
        } catch (\Throwable $e) {
            $cache = []; // table not migrated yet, etc. - never break the layout
        }

        return $cache;
    }
}

if (! function_exists('sticky_filters')) {
    /**
     * Remember a list's filter values across navigation. Call once at the top of
     * a list controller's index():
     *
     *   $filters = sticky_filters('purchases', ['status', 'supplier_id', 'q', 'from', 'to']);
     *   if ($filters instanceof \CodeIgniter\HTTP\RedirectResponse) { return $filters; }
     *
     * - a request carrying any of $fields is authoritative and is saved to the session
     * - a bare request restores the saved filter by redirecting with it in the URL
     *   (so pagination / export / row links inherit it too)
     * - ?fclear=1 forgets the saved filter (the "clear" icon on the filter bar)
     *
     * @param list<string> $fields
     *
     * @return array<string,mixed>|\CodeIgniter\HTTP\RedirectResponse
     */
    function sticky_filters(string $key, array $fields)
    {
        $skey = 'lf.' . $key;
        $get  = service('request')->getGet() ?? [];

        if (array_key_exists('fclear', $get)) {
            session()->remove($skey);
            unset($get['fclear']);

            return redirect()->to(current_url() . ($get !== [] ? '?' . http_build_query($get) : ''));
        }

        $present = array_intersect_key($get, array_flip($fields));
        if ($present !== []) {
            $keep = array_filter($present, static fn ($v) => $v !== '' && $v !== null && $v !== []);
            $keep !== [] ? session()->set($skey, $keep) : session()->remove($skey);
        } elseif (is_array($saved = session($skey)) && $saved !== []) {
            return redirect()->to(current_url() . '?' . http_build_query(array_merge($get, $saved)));
        }

        $out = [];
        foreach ($fields as $f) {
            $out[$f] = $get[$f] ?? null;
        }

        return $out;
    }
}
