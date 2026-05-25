<?php

/**
 * Supported service configuration.
 *
 * Each service defines:
 *  - label         display name (e.g. "YouTube")
 *  - input_hosts   hosts accepted in the input field (lowercase)
 *  - routes        map of link types handled by the service
 *
 * Each route may contain:
 *  - parse(array $parts): ?array
 *      Validates components returned by parse_url() from the pasted URL.
 *      Returns a value map (e.g. ['id' => 'abc']) when the URL matches this route,
 *      or null otherwise.
 *  - short_path        short URL template (with {key} placeholders)
 *  - canonical_url     canonical service URL template
 *  - ios_url           iOS app deep-link template
 *  - android_url       Android app deep-link template (intent://)
 *  - short_pattern     regex with named groups for reverse short-URL matching
 *
 * Template placeholders:
 *  - {name}          raw field value
 *  - {name|urlenc}   value after rawurlencode()
 *  - {canonical_url} available in ios_url / android_url after canonical_url is expanded
 */

return [
    'yt' => [
        'label' => 'YouTube',
        'input_hosts' => [
            'youtube.com',
            'www.youtube.com',
            'm.youtube.com',
            'youtu.be',
            'www.youtu.be',
        ],
        'routes' => [
            'channel' => [
                'parse' => static function (array $parts): ?array {
                    $path = trim((string) ($parts['path'] ?? ''), '/');
                    if ($path === '' || $path[0] !== '@') {
                        return null;
                    }
                    $segments = explode('/', $path);
                    $rawHandle = substr($segments[0], 1);
                    if (preg_match('/^[A-Za-z0-9._-]{3,30}$/', $rawHandle) !== 1) {
                        return null;
                    }
                    return ['handle' => $rawHandle];
                },
                'short_path' => '/yt/@{handle}',
                'canonical_url' => 'https://www.youtube.com/@{handle}',
                'ios_url' => 'youtube://www.youtube.com/@{handle}',
                'android_url' => 'intent://www.youtube.com/@{handle}#Intent;package=com.google.android.youtube;scheme=https;S.browser_fallback_url={canonical_url|urlenc};end',
                'short_pattern' => '#^/yt/@(?P<handle>[A-Za-z0-9._-]{3,30})$#',
            ],
            'video' => [
                'parse' => static function (array $parts): ?array {
                    $host = strtolower((string) ($parts['host'] ?? ''));
                    $path = trim((string) ($parts['path'] ?? ''), '/');
                    $query = [];
                    parse_str((string) ($parts['query'] ?? ''), $query);

                    $videoId = '';
                    if ($host === 'youtu.be' || $host === 'www.youtu.be') {
                        $videoId = $path;
                    } elseif ($path === 'watch') {
                        $videoId = (string) ($query['v'] ?? '');
                    } else {
                        $segments = $path === '' ? [] : explode('/', $path);
                        if (isset($segments[1]) && in_array($segments[0], ['shorts', 'live', 'embed'], true)) {
                            $videoId = $segments[1];
                        }
                    }

                    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) !== 1) {
                        return null;
                    }

                    return ['id' => $videoId];
                },
                'short_path' => '/yt/{id}',
                'canonical_url' => 'https://www.youtube.com/watch?v={id}',
                'ios_url' => 'youtube://www.youtube.com/watch?v={id}',
                'android_url' => 'intent://www.youtube.com/watch?v={id}#Intent;package=com.google.android.youtube;scheme=https;S.browser_fallback_url={canonical_url|urlenc};end',
                'short_pattern' => '#^/yt/(?P<id>[A-Za-z0-9_-]{11})$#',
            ],
        ],
    ],

    'ig' => [
        'label' => 'Instagram',
        'input_hosts' => [
            'instagram.com',
            'www.instagram.com',
            'm.instagram.com',
        ],
        'routes' => [
            'media' => [
                'parse' => static function (array $parts): ?array {
                    $path = trim((string) ($parts['path'] ?? ''), '/');
                    $segments = $path === '' ? [] : explode('/', $path);
                    $type = $segments[0] ?? '';
                    $identifier = $segments[1] ?? '';

                    if ($identifier === '') {
                        return null;
                    }
                    if (!in_array($type, ['p', 'reel', 'reels', 'tv'], true)) {
                        return null;
                    }
                    if (preg_match('/^[A-Za-z0-9_-]{5,30}$/', $identifier) !== 1) {
                        return null;
                    }
                    if ($type === 'reels') {
                        $type = 'reel';
                    }

                    return ['type' => $type, 'id' => $identifier];
                },
                'short_path' => '/ig/{type}/{id}',
                'canonical_url' => 'https://www.instagram.com/{type}/{id}/',
                // iOS: dedykowany URL scheme wymusza otwarcie aplikacji Instagram.
                // Universal Link (HTTPS) nie dziala, gdy URL jest ustawiany przez
                // window.location w Safari na tej samej karcie - Apple celowo to blokuje.
                'ios_url' => 'instagram://media?id={id|urlenc}',
                // Android: S.browser_fallback_url zapewnia, ze brak aplikacji
                // przekieruje na canonical_url zamiast pokazywac blad przegladarki.
                'android_url' => 'intent://instagram.com/{type}/{id}/#Intent;package=com.instagram.android;scheme=https;S.browser_fallback_url={canonical_url|urlenc};end',
                'short_pattern' => '#^/ig/(?P<type>p|reel|tv)/(?P<id>[A-Za-z0-9_-]{5,30})$#',
            ],
            'profile' => [
                'parse' => static function (array $parts): ?array {
                    $path = trim((string) ($parts['path'] ?? ''), '/');
                    $segments = $path === '' ? [] : explode('/', $path);
                    $type = $segments[0] ?? '';
                    $identifier = $segments[1] ?? '';

                    if ($identifier !== '') {
                        return null;
                    }
                    if (preg_match('/^[A-Za-z0-9._]{1,30}$/', $type) !== 1) {
                        return null;
                    }

                    return ['handle' => $type];
                },
                'short_path' => '/ig/profile/{handle}',
                'canonical_url' => 'https://www.instagram.com/{handle}/',
                // iOS: dedykowany URL scheme wymusza otwarcie aplikacji Instagram.
                // Universal Link (HTTPS) nie dziala, gdy URL jest ustawiany przez
                // window.location w Safari na tej samej karcie - Apple celowo to blokuje.
                'ios_url' => 'instagram://user?username={handle|urlenc}',
                // Android: S.browser_fallback_url zapewnia, ze brak aplikacji
                // przekieruje na canonical_url zamiast pokazywac blad przegladarki.
                'android_url' => 'intent://instagram.com/{handle}/#Intent;package=com.instagram.android;scheme=https;S.browser_fallback_url={canonical_url|urlenc};end',
                'short_pattern' => '#^/ig/profile/(?P<handle>[A-Za-z0-9._]{1,30})$#',
            ],
        ],
    ],

    'x' => [
        'label' => 'X',
        'input_hosts' => [
            'x.com',
            'www.x.com',
            'twitter.com',
            'www.twitter.com',
            'mobile.twitter.com',
        ],
        'routes' => [
            'status' => [
                'parse' => static function (array $parts): ?array {
                    $path = trim((string) ($parts['path'] ?? ''), '/');
                    $segments = $path === '' ? [] : explode('/', $path);

                    if (count($segments) < 3 || $segments[1] !== 'status') {
                        return null;
                    }
                    $statusId = $segments[2];
                    if (preg_match('/^[0-9]{5,25}$/', $statusId) !== 1) {
                        return null;
                    }

                    return ['id' => $statusId];
                },
                'short_path' => '/x/{id}',
                'canonical_url' => 'https://x.com/i/web/status/{id}',
                'ios_url' => 'twitter://status?id={id}',
                'android_url' => 'intent://x.com/i/web/status/{id}#Intent;package=com.twitter.android;scheme=https;S.browser_fallback_url={canonical_url|urlenc};end',
                'short_pattern' => '#^/x/(?P<id>[0-9]{5,25})$#',
            ],
        ],
    ],

    'fb' => (static function (): array {
        $facebookHosts = ['facebook.com', 'www.facebook.com', 'm.facebook.com'];
        $fbWatchHosts  = ['fb.watch', 'www.fb.watch'];
        $fbMeHosts     = ['fb.me', 'www.fb.me'];

        return [
        'label' => 'Facebook',
        'input_hosts' => array_merge($facebookHosts, $fbWatchHosts, $fbMeHosts),
        'routes' => [
            // fb.watch short host — input URL parsing only.
            // Reverse lookup is handled by the "watch" route below (same short_path).
            'watch_short_host' => [
                'parse' => static function (array $parts) use ($fbWatchHosts): ?array {
                    $host = strtolower((string) ($parts['host'] ?? ''));
                    if (!in_array($host, $fbWatchHosts, true)) {
                        return null;
                    }
                    $path = trim((string) ($parts['path'] ?? ''), '/');
                    $segments = $path === '' ? [] : explode('/', $path);
                    $identifier = $segments[0] ?? '';
                    if (preg_match('/^[A-Za-z0-9_-]{4,40}$/', $identifier) !== 1) {
                        return null;
                    }
                    return ['id' => $identifier];
                },
                'short_path' => '/fb/watch/{id}',
                'canonical_url' => 'https://www.facebook.com/watch/?v={id|urlenc}',
            ],
            'reel' => [
                'parse' => static function (array $parts) use ($facebookHosts): ?array {
                    $host = strtolower((string) ($parts['host'] ?? ''));
                    if (!in_array($host, $facebookHosts, true)) {
                        return null;
                    }
                    $path = trim((string) ($parts['path'] ?? ''), '/');
                    $segments = $path === '' ? [] : explode('/', $path);
                    if (($segments[0] ?? '') !== 'reel') {
                        return null;
                    }
                    $identifier = $segments[1] ?? '';
                    if (preg_match('/^[0-9]{6,25}$/', $identifier) !== 1) {
                        return null;
                    }
                    return ['id' => $identifier];
                },
                'short_path' => '/fb/reel/{id}',
                'canonical_url' => 'https://www.facebook.com/reel/{id}',
                'ios_url' => 'fb://facewebmodal/f?href={canonical_url|urlenc}',
                'android_url' => 'intent://facebook.com/reel/{id}#Intent;package=com.facebook.katana;scheme=https;S.browser_fallback_url={canonical_url|urlenc};end',
                'short_pattern' => '#^/fb/reel/(?P<id>[0-9]{6,25})$#',
            ],
            'watch' => [
                'parse' => static function (array $parts) use ($facebookHosts): ?array {
                    $host = strtolower((string) ($parts['host'] ?? ''));
                    if (!in_array($host, $facebookHosts, true)) {
                        return null;
                    }
                    $path = trim((string) ($parts['path'] ?? ''), '/');
                    $segments = $path === '' ? [] : explode('/', $path);
                    if (($segments[0] ?? '') !== 'watch') {
                        return null;
                    }
                    $query = [];
                    parse_str((string) ($parts['query'] ?? ''), $query);
                    $identifier = (string) ($query['v'] ?? '');
                    if (preg_match('/^[0-9A-Za-z_-]{6,40}$/', $identifier) !== 1) {
                        return null;
                    }
                    return ['id' => $identifier];
                },
                'short_path' => '/fb/watch/{id}',
                'canonical_url' => 'https://www.facebook.com/watch/?v={id|urlenc}',
                'ios_url' => 'fb://facewebmodal/f?href={canonical_url|urlenc}',
                'android_url' => 'intent://facebook.com/watch/?v={id}#Intent;package=com.facebook.katana;scheme=https;S.browser_fallback_url={canonical_url|urlenc};end',
                'short_pattern' => '#^/fb/watch/(?P<id>[0-9A-Za-z_-]{4,40})$#',
            ],
            'post' => [
                'parse' => static function (array $parts) use ($facebookHosts): ?array {
                    $host = strtolower((string) ($parts['host'] ?? ''));
                    if (!in_array($host, $facebookHosts, true)) {
                        return null;
                    }
                    $path = trim((string) ($parts['path'] ?? ''), '/');
                    $segments = $path === '' ? [] : explode('/', $path);
                    if (count($segments) < 3 || ($segments[1] ?? '') !== 'posts') {
                        return null;
                    }
                    $profile = $segments[0];
                    $postId = $segments[2];
                    if (preg_match('/^[A-Za-z0-9._-]{3,100}$/', $profile) !== 1) {
                        return null;
                    }
                    if (preg_match('/^(pfbid)?[A-Za-z0-9]{10,130}$/', $postId) !== 1) {
                        return null;
                    }
                    return ['profile' => $profile, 'post_id' => $postId];
                },
                'short_path' => '/fb/post/{profile}/{post_id}',
                'canonical_url' => 'https://www.facebook.com/{profile|urlenc}/posts/{post_id|urlenc}',
                'ios_url' => 'fb://facewebmodal/f?href={canonical_url|urlenc}',
                'android_url' => 'intent://facebook.com/{profile}/posts/{post_id}#Intent;package=com.facebook.katana;scheme=https;S.browser_fallback_url={canonical_url|urlenc};end',
                'short_pattern' => '#^/fb/post/(?P<profile>[A-Za-z0-9._-]{3,100})/(?P<post_id>(?:pfbid)?[A-Za-z0-9]{10,130})$#',
            ],
            'profile' => [
                'parse' => static function (array $parts) use ($facebookHosts, $fbMeHosts): ?array {
                    $host = strtolower((string) ($parts['host'] ?? ''));
                    $allowedHosts = array_merge($facebookHosts, $fbMeHosts);
                    if (!in_array($host, $allowedHosts, true)) {
                        return null;
                    }
                    $path = trim((string) ($parts['path'] ?? ''), '/');
                    if ($path === '') {
                        return null;
                    }
                    $segments = explode('/', $path);
                    if (count($segments) !== 1) {
                        return null;
                    }
                    $handle = $segments[0];
                    // Reserved Facebook paths that must not be treated as profile handles.
                    $reserved = [
                        'watch', 'reel', 'reels', 'post', 'posts', 'profile.php',
                        'p', 'sharer', 'plugins', 'dialog', 'login', 'logout',
                        'settings', 'help', 'business', 'gaming', 'marketplace',
                        'pages', 'groups', 'events', 'photo', 'photos', 'video',
                        'videos', 'story', 'stories', 'search', 'friends',
                        'notifications', 'messages', 'bookmarks', 'about',
                        'terms', 'policies', 'careers', 'developers', 'ads',
                        'blog', 'press', 'home.php', 'index.php', 'sharer.php',
                        'recover', 'security', 'privacy',
                    ];
                    if (in_array(strtolower($handle), $reserved, true)) {
                        return null;
                    }
                    if (preg_match('/^(?!\.)[A-Za-z0-9.]{5,50}$/', $handle) !== 1) {
                        return null;
                    }
                    return ['handle' => $handle];
                },
                'short_path' => '/fb/profile/{handle}',
                'canonical_url' => 'https://www.facebook.com/{handle}',
                'ios_url' => 'fb://facewebmodal/f?href={canonical_url|urlenc}',
                'android_url' => 'intent://facebook.com/{handle}#Intent;package=com.facebook.katana;scheme=https;S.browser_fallback_url={canonical_url|urlenc};end',
                'short_pattern' => '#^/fb/profile/(?P<handle>[A-Za-z0-9.]{5,50})$#',
            ],
        ],
        ];
    })(),

    'li' => (static function (): array {
        $linkedinHosts = ['linkedin.com', 'www.linkedin.com', 'm.linkedin.com', 'mobile.linkedin.com'];

        return [
        'label' => 'LinkedIn',
        'input_hosts' => $linkedinHosts,
        'routes' => [
            'activity' => [
                'parse' => static function (array $parts) use ($linkedinHosts): ?array {
                    $host = strtolower((string) ($parts['host'] ?? ''));
                    if (!in_array($host, $linkedinHosts, true)) {
                        return null;
                    }
                    $path = trim((string) ($parts['path'] ?? ''), '/');
                    $segments = $path === '' ? [] : explode('/', $path);
                    if (count($segments) < 3
                        || ($segments[0] ?? '') !== 'feed'
                        || ($segments[1] ?? '') !== 'update'
                    ) {
                        return null;
                    }
                    $urn = $segments[2];
                    if (preg_match('/^urn:li:activity:([0-9]{10,25})$/', $urn, $matches) !== 1) {
                        return null;
                    }

                    return ['id' => $matches[1]];
                },
                'short_path' => '/li/{id}',
                'canonical_url' => 'https://www.linkedin.com/feed/update/urn:li:activity:{id}/',
                'ios_url' => 'https://www.linkedin.com/feed/update/urn:li:activity:{id}/',
                'android_url' => 'intent://www.linkedin.com/feed/update/urn:li:activity:{id}/#Intent;package=com.linkedin.android;scheme=https;S.browser_fallback_url={canonical_url|urlenc};end',
                'short_pattern' => '#^/li/(?P<id>[0-9]{10,25})$#',
            ],
            'profile' => [
                'parse' => static function (array $parts) use ($linkedinHosts): ?array {
                    $host = strtolower((string) ($parts['host'] ?? ''));
                    if (!in_array($host, $linkedinHosts, true)) {
                        return null;
                    }
                    $path = trim((string) ($parts['path'] ?? ''), '/');
                    $segments = $path === '' ? [] : explode('/', $path);
                    if (count($segments) !== 2 || ($segments[0] ?? '') !== 'in') {
                        return null;
                    }
                    $handle = $segments[1];
                    if (preg_match('/^[A-Za-z0-9_-]{3,100}$/', $handle) !== 1) {
                        return null;
                    }
                    return ['handle' => $handle];
                },
                'short_path' => '/li/in/{handle}',
                'canonical_url' => 'https://www.linkedin.com/in/{handle|urlenc}/',
                // iOS: dedykowany URL scheme wymusza otwarcie aplikacji LinkedIn.
                // Universal Link (HTTPS) nie działa, gdy URL jest ustawiany przez
                // window.location w Safari na tej samej karcie — Apple celowo to blokuje.
                'ios_url' => 'linkedin://profile/{handle|urlenc}',
                'android_url' => 'intent://www.linkedin.com/in/{handle}/#Intent;package=com.linkedin.android;scheme=https;S.browser_fallback_url={canonical_url|urlenc};end',
                'short_pattern' => '#^/li/in/(?P<handle>[A-Za-z0-9_-]{3,100})$#',
            ],
        ],
        ];
    })(),
];
