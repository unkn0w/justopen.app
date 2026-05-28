<?php

/**
 * Minimal test suite for justopen.app URL generation.
 * Run: php tests.php
 */

require_once __DIR__ . '/engine.php';

$passed = 0;
$failed = 0;

function assert_eq($actual, $expected, string $label): void
{
    global $passed, $failed;
    if ($actual === $expected) {
        $passed++;
        echo "  ✓ {$label}\n";
    } else {
        $failed++;
        echo "  ✗ {$label}\n";
        echo "    expected: " . var_export($expected, true) . "\n";
        echo "    actual:   " . var_export($actual, true) . "\n";
    }
}

function assert_contains(string $haystack, string $needle, string $label): void
{
    global $passed, $failed;
    if (strpos($haystack, $needle) !== false) {
        $passed++;
        echo "  ✓ {$label}\n";
    } else {
        $failed++;
        echo "  ✗ {$label}\n";
        echo "    '{$needle}' not found in:\n    {$haystack}\n";
    }
}

function assert_not_contains(string $haystack, string $needle, string $label): void
{
    global $passed, $failed;
    if (strpos($haystack, $needle) === false) {
        $passed++;
        echo "  ✓ {$label}\n";
    } else {
        $failed++;
        echo "  ✗ {$label}\n";
        echo "    '{$needle}' should NOT be in:\n    {$haystack}\n";
    }
}

// ─── YouTube video URL parsing ──────────────────────────────────────────────

echo "\n[YouTube video parsing]\n";

$result = jo_parse_input_url('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
assert_eq($result['provider'], 'yt', 'provider is yt');
assert_eq($result['route'], 'video', 'route is video');
assert_eq($result['identifier'], 'dQw4w9WgXcQ', 'video id extracted');
assert_eq($result['short_path'], '/yt/dQw4w9WgXcQ', 'short_path correct');

$result2 = jo_parse_input_url('https://youtu.be/dQw4w9WgXcQ');
assert_eq($result2['identifier'], 'dQw4w9WgXcQ', 'youtu.be short URL parsed');

$result3 = jo_parse_input_url('https://www.youtube.com/shorts/dQw4w9WgXcQ');
assert_eq($result3['identifier'], 'dQw4w9WgXcQ', 'shorts URL parsed');

// ─── YouTube channel URL parsing ────────────────────────────────────────────

echo "\n[YouTube channel parsing]\n";

$result = jo_parse_input_url('https://www.youtube.com/@MrBeast');
assert_eq($result['provider'], 'yt', 'provider is yt');
assert_eq($result['route'], 'channel', 'route is channel');
assert_eq($result['identifier'], 'MrBeast', 'handle extracted');
assert_eq($result['short_path'], '/yt/@MrBeast', 'short_path correct');

// ─── Short path resolution: YouTube video ───────────────────────────────────

echo "\n[Short path resolution - YouTube video]\n";

$resolved = jo_resolve_short_path('/yt/dQw4w9WgXcQ');
assert_eq($resolved['provider'], 'yt', 'resolved provider is yt');
assert_eq($resolved['canonical_url'], 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'canonical URL');
assert_contains($resolved['ios_url'], 'youtube://', 'iOS uses youtube:// scheme');
assert_contains($resolved['android_url'], 'intent://', 'Android uses intent:// scheme');
assert_contains($resolved['android_url'], 'scheme=vnd.youtube', 'Android intent uses vnd.youtube scheme (ReVanced compatible)');
assert_contains($resolved['android_url'], 'S.browser_fallback_url=', 'Android has fallback URL');
assert_not_contains($resolved['android_url'], 'package=', 'Android intent has NO hardcoded package (ReVanced fix)');

// ─── Short path resolution: YouTube channel ─────────────────────────────────

echo "\n[Short path resolution - YouTube channel]\n";

$resolved = jo_resolve_short_path('/yt/@MrBeast');
assert_eq($resolved['canonical_url'], 'https://www.youtube.com/@MrBeast', 'canonical URL');
assert_not_contains($resolved['android_url'], 'package=', 'Channel android_url has NO hardcoded package');
assert_contains($resolved['android_url'], 'intent://www.youtube.com/@MrBeast', 'Channel intent URL correct');

// ─── X (Twitter) still has package= (unchanged) ────────────────────────────

echo "\n[X/Twitter - unchanged, still has package]\n";

$resolved = jo_resolve_short_path('/x/1234567890');
assert_contains($resolved['android_url'], 'package=com.twitter.android', 'X still uses package=');

// ─── Instagram - unchanged ──────────────────────────────────────────────────

echo "\n[Instagram - unchanged, uses HTTPS approach]\n";

$result = jo_parse_input_url('https://www.instagram.com/p/ABC123456xyz');
assert_eq($result['provider'], 'ig', 'provider is ig');

// ─── Invalid URL handling ───────────────────────────────────────────────────

echo "\n[Invalid URL handling]\n";

$threw = false;
try { jo_parse_input_url(''); } catch (InvalidArgumentException $e) { $threw = true; }
assert_eq($threw, true, 'empty URL throws');

$threw = false;
try { jo_parse_input_url('ftp://example.com'); } catch (InvalidArgumentException $e) { $threw = true; }
assert_eq($threw, true, 'ftp:// throws');

$threw = false;
try { jo_parse_input_url('https://evil.com/watch?v=abc'); } catch (InvalidArgumentException $e) { $threw = true; }
assert_eq($threw, true, 'unsupported host throws');

// ─── Summary ────────────────────────────────────────────────────────────────

echo "\n" . str_repeat('─', 50) . "\n";
echo "Results: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
