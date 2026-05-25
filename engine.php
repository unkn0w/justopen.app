<?php

/**
 * justopen.app application engine — routing, URL parsing, and template rendering.
 * User-facing strings live in translations.php, service definitions in services.php,
 * and the visual layer in templates/*.html.
 */

const JO_SUPPORTED_LANGS = ['pl', 'en'];
const JO_DEFAULT_LANG    = 'en';
const JO_INPUT_MAX_LEN   = 2000;


/* -------------------------------------------------------------------------
 * Configuration: translations and services (lazy-loaded, in-memory cache)
 * ------------------------------------------------------------------------- */

function jo_translations(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = require __DIR__ . '/translations.php';
    }
    return $cache;
}

function jo_services(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = require __DIR__ . '/services.php';
    }
    return $cache;
}

function jo_t(string $lang, string $key): string
{
    $translations = jo_translations();
    if (isset($translations[$lang][$key])) {
        return $translations[$lang][$key];
    }
    return $translations[JO_DEFAULT_LANG][$key] ?? $key;
}

function jo_translation_strings(string $lang): array
{
    $translations = jo_translations();
    return $translations[$lang] ?? $translations[JO_DEFAULT_LANG] ?? [];
}


/* -------------------------------------------------------------------------
 * Security and conversion helpers
 * ------------------------------------------------------------------------- */

function jo_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function jo_json($value): string
{
    $json = json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    return $json === false ? '""' : $json;
}

function jo_limit_input(string $value, int $maxLength = JO_INPUT_MAX_LEN): string
{
    $value = trim($value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }
    return substr($value, 0, $maxLength);
}


/* -------------------------------------------------------------------------
 * Mini template engine for templates/*.html
 *  - {{key}}        — value escaped with htmlspecialchars
 *  - {{{key}}}      — raw value (for embedding other templates or JSON literals)
 *  - {{#if key}}…{{/if}} — conditional block (true when the value is non-empty)
 *  - dots in keys allow nested array access ({{result.short_url}})
 *
 * Note: the parser does not support nested {{#if}} blocks, but handles multiple
 * independent blocks at the same level (while loop around preg_replace).
 * ------------------------------------------------------------------------- */

function jo_render_template(string $name, array $context): string
{
    $path = __DIR__ . '/templates/' . $name . '.html';
    if (!is_file($path)) {
        throw new RuntimeException('Template not found: ' . $name);
    }
    $template = file_get_contents($path);
    if ($template === false) {
        throw new RuntimeException('Unable to read template: ' . $name);
    }
    return jo_template_apply($template, $context);
}

function jo_template_apply(string $template, array $context): string
{
    $ifPattern = '/\{\{#if\s+([a-zA-Z0-9_.]+)\s*\}\}([\s\S]*?)\{\{\/if\}\}/';
    while (preg_match($ifPattern, $template) === 1) {
        $template = preg_replace_callback($ifPattern, static function (array $m) use ($context) {
            $value = jo_template_lookup($context, $m[1]);
            return jo_template_truthy($value) ? $m[2] : '';
        }, $template);
    }

    $template = preg_replace_callback('/\{\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}\}/', static function (array $m) use ($context) {
        return (string) jo_template_lookup($context, $m[1]);
    }, $template);

    $template = preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', static function (array $m) use ($context) {
        return jo_escape(jo_template_lookup($context, $m[1]));
    }, $template);

    return $template;
}

function jo_template_lookup(array $context, string $key)
{
    $parts = explode('.', $key);
    $value = $context;
    foreach ($parts as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        return '';
    }
    return $value;
}

function jo_template_truthy($value): bool
{
    if ($value === null || $value === false || $value === '' || $value === 0 || $value === '0') {
        return false;
    }
    if (is_array($value)) {
        return !empty($value);
    }
    return true;
}

/**
 * Expands {name} and {name|filter} placeholders (the "urlenc" filter applies
 * rawurlencode). Used when building URLs from services.php configuration.
 */
function jo_template_string(string $template, array $params): string
{
    return preg_replace_callback('/\{([a-zA-Z0-9_]+)(?:\|([a-zA-Z0-9_]+))?\}/', static function (array $m) use ($params) {
        $key   = $m[1];
        $value = (string) ($params[$key] ?? '');
        $filter = $m[2] ?? '';
        if ($filter === 'urlenc') {
            return rawurlencode($value);
        }
        return $value;
    }, $template);
}


/* -------------------------------------------------------------------------
 * Language and platform detection, base URL construction
 * ------------------------------------------------------------------------- */

function jo_detect_language(string $acceptLanguage, ?string $override): string
{
    if ($override !== null) {
        $override = strtolower(trim($override));
        if (in_array($override, JO_SUPPORTED_LANGS, true)) {
            return $override;
        }
    }
    if (preg_match('/\bpl\b/i', $acceptLanguage) === 1
        || preg_match('/\bpl-[a-z]{2}\b/i', $acceptLanguage) === 1
    ) {
        return 'pl';
    }
    return JO_DEFAULT_LANG;
}

function jo_detect_platform(string $userAgent): string
{
    $userAgent = strtolower($userAgent);
    if (strpos($userAgent, 'android') !== false) {
        return 'android';
    }
    if (strpos($userAgent, 'iphone') !== false
        || strpos($userAgent, 'ipad') !== false
        || strpos($userAgent, 'ipod') !== false
    ) {
        return 'ios';
    }
    return 'desktop';
}

function jo_base_url(array $server): string
{
    $https  = strtolower((string) ($server['HTTPS'] ?? ''));
    $scheme = ($https === 'on' || $https === '1') ? 'https' : 'http';
    $host   = preg_replace('/[^A-Za-z0-9\.\-:]/', '', (string) ($server['HTTP_HOST'] ?? 'localhost'));
    if ($host === '') {
        $host = 'localhost';
    }
    return $scheme . '://' . $host;
}


/* -------------------------------------------------------------------------
 * Security headers
 * ------------------------------------------------------------------------- */

function jo_security_headers(): array
{
    return [
        'Content-Type'            => 'text/html; charset=UTF-8',
        'X-Content-Type-Options'  => 'nosniff',
        'Referrer-Policy'         => 'no-referrer',
        'X-Frame-Options'         => 'DENY',
        'Content-Security-Policy' => "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; img-src 'self' data:; connect-src 'none'; style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com",
    ];
}


/* -------------------------------------------------------------------------
 * URL handling: input parsing and reverse short_path matching
 * ------------------------------------------------------------------------- */

function jo_supported_input_hosts(): array
{
    static $hosts = null;
    if ($hosts !== null) {
        return $hosts;
    }
    $collected = [];
    foreach (jo_services() as $service) {
        foreach ($service['input_hosts'] ?? [] as $host) {
            $collected[] = strtolower($host);
        }
    }
    $hosts = array_values(array_unique($collected));
    return $hosts;
}

function jo_parse_input_url(string $input): array
{
    $input = jo_limit_input($input);
    if ($input === '') {
        throw new InvalidArgumentException('Empty URL.');
    }

    $parts = parse_url($input);
    if (!is_array($parts)) {
        throw new InvalidArgumentException('Invalid URL.');
    }

    $scheme = strtolower($parts['scheme'] ?? '');
    if ($scheme !== 'https' && $scheme !== 'http') {
        throw new InvalidArgumentException('Unsupported scheme.');
    }

    $host = strtolower($parts['host'] ?? '');
    if ($host === '' || !in_array($host, jo_supported_input_hosts(), true)) {
        throw new InvalidArgumentException('Unsupported host.');
    }

    foreach (jo_services() as $serviceId => $service) {
        $serviceHosts = array_map('strtolower', $service['input_hosts'] ?? []);
        if (!in_array($host, $serviceHosts, true)) {
            continue;
        }

        foreach ($service['routes'] ?? [] as $routeName => $route) {
            if (!isset($route['parse']) || !is_callable($route['parse'])) {
                continue;
            }
            $captured = ($route['parse'])($parts);
            if (!is_array($captured)) {
                continue;
            }

            $shortPath    = jo_template_string((string) ($route['short_path'] ?? ''), $captured);
            $canonicalUrl = jo_template_string((string) ($route['canonical_url'] ?? ''), $captured);

            return [
                'provider'      => $serviceId,
                'label'         => (string) ($service['label'] ?? $serviceId),
                'route'         => $routeName,
                'short_path'    => $shortPath,
                'canonical_url' => $canonicalUrl,
                'identifier'    => $captured['id'] ?? ($captured['handle'] ?? ''),
            ];
        }
    }

    throw new InvalidArgumentException('Unsupported provider.');
}

function jo_resolve_short_path(string $path): array
{
    $path = trim($path);
    if ($path === '') {
        throw new InvalidArgumentException('Empty path.');
    }
    $path = '/' . ltrim($path, '/');

    foreach (jo_services() as $serviceId => $service) {
        foreach ($service['routes'] ?? [] as $routeName => $route) {
            if (empty($route['short_pattern'])) {
                continue;
            }
            if (preg_match($route['short_pattern'], $path, $m) !== 1) {
                continue;
            }

            $captured = [];
            foreach ($m as $k => $v) {
                if (!is_int($k)) {
                    $captured[$k] = $v;
                }
            }

            $canonical = jo_template_string((string) ($route['canonical_url'] ?? ''), $captured);
            $context   = $captured + ['canonical_url' => $canonical];

            $iosUrl     = isset($route['ios_url'])     ? jo_template_string($route['ios_url'],     $context) : $canonical;
            $androidUrl = isset($route['android_url']) ? jo_template_string($route['android_url'], $context) : $canonical;

            return [
                'provider'      => $serviceId,
                'label'         => (string) ($service['label'] ?? $serviceId),
                'route'         => $routeName,
                'canonical_url' => $canonical,
                'ios_url'       => $iosUrl,
                'android_url'   => $androidUrl,
            ];
        }
    }

    throw new InvalidArgumentException('Unknown short path.');
}


/* -------------------------------------------------------------------------
 * Rendering: build context and inject into templates/*.html
 * ------------------------------------------------------------------------- */

function jo_layout_context(string $lang, string $title, string $baseUrl): array
{
    return [
        't'                => jo_translation_strings($lang),
        'lang'             => $lang,
        'title'            => $title,
        'meta_description' => jo_t($lang, 'meta_description'),
        'home_url'         => $baseUrl . '/?lang=' . $lang,
        'og_image'         => $baseUrl . '/ogimage.png',
        'language_pl_class' => $lang === 'pl' ? 'text-emerald-200' : 'text-zinc-200 hover:text-emerald-200',
        'language_en_class' => $lang === 'en' ? 'text-emerald-200' : 'text-zinc-200 hover:text-emerald-200',
    ];
}

function jo_render_with_layout(string $lang, string $title, string $baseUrl, string $content): string
{
    $context = jo_layout_context($lang, $title, $baseUrl);
    $context['content'] = $content;
    return jo_render_template('_layout', $context);
}

function jo_render_home(array $view): string
{
    $lang    = (string) ($view['lang'] ?? JO_DEFAULT_LANG);
    $baseUrl = (string) ($view['base_url'] ?? '');

    $context = [
        't'      => jo_translation_strings($lang),
        'lang'   => $lang,
        'input'  => (string) ($view['input'] ?? ''),
        'error'  => is_string($view['error'] ?? null) ? $view['error'] : '',
        'result' => null,
    ];

    $result = $view['result'] ?? null;
    if (is_array($result)) {
        $context['result'] = [
            'short_url'         => (string) $result['short_url'],
            'canonical_url'     => (string) $result['canonical_url'],
            'provider_label'    => (string) $result['provider_label'],
            'copy_success_json' => jo_json(jo_t($lang, 'copy_success')),
            'copy_error_json'   => jo_json(jo_t($lang, 'copy_error')),
        ];
    }

    $content = jo_render_template('index', $context);
    return jo_render_with_layout($lang, jo_t($lang, 'site_title'), $baseUrl, $content);
}

function jo_render_jump(string $lang, array $resolved, string $platform, string $baseUrl): string
{
    $attemptUrl  = $platform === 'android' ? $resolved['android_url'] : $resolved['ios_url'];
    $fallbackUrl = $resolved['canonical_url'];

    $context = [
        't'                  => jo_translation_strings($lang),
        'lang'               => $lang,
        'provider_label'     => (string) $resolved['label'],
        'attempt_url'        => (string) $attemptUrl,
        'fallback_url'       => (string) $fallbackUrl,
        'attempt_url_json'   => jo_json($attemptUrl),
        'fallback_url_json'  => jo_json($fallbackUrl),
        'home_url'           => $baseUrl . '/?lang=' . $lang,
    ];

    $content = jo_render_template('jump', $context);
    return jo_render_with_layout($lang, jo_t($lang, 'mobile_title'), $baseUrl, $content);
}

function jo_render_404(string $lang, string $message, string $baseUrl): string
{
    $context = [
        't'        => jo_translation_strings($lang),
        'message'  => $message,
        'home_url' => $baseUrl . '/?lang=' . $lang,
    ];

    $content = jo_render_template('404', $context);
    return jo_render_with_layout($lang, jo_t($lang, 'site_title'), $baseUrl, $content);
}


/* -------------------------------------------------------------------------
 * Routing — entry point used by index.php
 * ------------------------------------------------------------------------- */

function jo_route_request(array $server, array $get, array $post): array
{
    $method     = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
    $requestUri = (string) ($server['REQUEST_URI'] ?? '/');
    $path       = parse_url($requestUri, PHP_URL_PATH);
    $path       = is_string($path) ? $path : '/';

    $langOverride = $post['lang'] ?? $get['lang'] ?? null;
    $lang         = jo_detect_language(
        (string) ($server['HTTP_ACCEPT_LANGUAGE'] ?? ''),
        is_string($langOverride) ? $langOverride : null
    );

    $baseUrl = jo_base_url($server);
    $headers = jo_security_headers();

    if ($path === '/') {
        $view = [
            'lang'     => $lang,
            'input'    => '',
            'result'   => null,
            'error'    => null,
            'base_url' => $baseUrl,
        ];

        if ($method === 'POST') {
            $inputUrl       = jo_limit_input((string) ($post['url'] ?? ''));
            $view['input']  = $inputUrl;
            try {
                $parsed = jo_parse_input_url($inputUrl);
                $view['result'] = [
                    'short_url'      => $baseUrl . $parsed['short_path'],
                    'canonical_url'  => $parsed['canonical_url'],
                    'provider_label' => $parsed['label'],
                ];
            } catch (InvalidArgumentException $exception) {
                $view['error'] = jo_t($lang, 'error_invalid_url');
            }
        }

        return [
            'type'    => 'page',
            'status'  => 200,
            'headers' => $headers,
            'body'    => jo_render_home($view),
        ];
    }

    try {
        $resolved = jo_resolve_short_path($path);
    } catch (InvalidArgumentException $exception) {
        return [
            'type'    => 'page',
            'status'  => 404,
            'headers' => $headers,
            'body'    => jo_render_404($lang, jo_t($lang, 'error_not_found'), $baseUrl),
        ];
    }

    $platform = jo_detect_platform((string) ($server['HTTP_USER_AGENT'] ?? ''));
    if ($platform === 'desktop') {
        return [
            'type'     => 'redirect',
            'status'   => 302,
            'headers'  => $headers,
            'location' => $resolved['canonical_url'],
        ];
    }

    return [
        'type'    => 'page',
        'status'  => 200,
        'headers' => $headers,
        'body'    => jo_render_jump($lang, $resolved, $platform, $baseUrl),
    ];
}
