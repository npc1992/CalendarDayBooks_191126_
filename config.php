<?php
/**
 * Calendar Day Entry & Daily Sum Tracker - Server Settings Endpoint
 *
 * Reads and writes settings (currency, branding title/logo/favicon,
 * limit color highlight thresholds, and Google Sheet DB config) to settings.json.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$settingsFile = __DIR__ . DIRECTORY_SEPARATOR . 'settings.json';

/**
 * Default settings structure matching the tracker client.
 */
function defaultSettingsDocument(): array
{
    return [
        'schemaVersion' => '1.0',
        'updatedAt' => gmdate('c'),
        'currency' => 'INR',
        'branding' => [
            'title' => 'Calendar Day Entry & Daily Sum Tracker',
            'subtitle' => 'Track daily bills & cheques with automatic separate daily sum totals',
            'logoUrl' => '',
            'faviconValue' => '',
        ],
        'limitSettings' => [
            'enabled' => true,
            'lightRedLimits' => [100000, 85000, 75000],
            'lightOrangeLimits' => [65000, 55000],
            'lightYellowLimits' => [45000, 35000],
            'lightGreenLimits' => [25000, 15000],
        ],
        'sheetDbConfig' => [
            'webhookUrl' => '',
            'autoSync' => true,
            'lastSyncAt' => '',
        ],
    ];
}

/**
 * Normalizes and validates an incoming settings document.
 */
function normalizeSettingsDocument($raw, array $fallback): array
{
    if (!is_array($raw)) {
        return $fallback;
    }

    $source = (isset($raw['settings']) && is_array($raw['settings'])) ? $raw['settings'] : $raw;
    $allowedCurrencies = ['INR', 'USD', 'EUR', 'GBP'];
    $currency = (isset($source['currency']) && in_array($source['currency'], $allowedCurrencies, true))
        ? $source['currency']
        : $fallback['currency'];

    $brandingIn = (isset($source['branding']) && is_array($source['branding'])) ? $source['branding'] : [];
    $title = trim((string) ($brandingIn['title'] ?? $fallback['branding']['title']));
    if ($title === '') {
        $title = $fallback['branding']['title'];
    }
    $subtitle = isset($brandingIn['subtitle'])
        ? (string) $brandingIn['subtitle']
        : $fallback['branding']['subtitle'];
    $logoUrl = trim((string) ($brandingIn['logoUrl'] ?? ''));
    $faviconValue = trim((string) ($brandingIn['faviconValue'] ?? ''));

    $limitsIn = (isset($source['limitSettings']) && is_array($source['limitSettings']))
        ? $source['limitSettings']
        : [];
    $parseNumList = static function ($arr, array $defaultArr): array {
        if (!is_array($arr)) {
            return $defaultArr;
        }
        $clean = [];
        foreach ($arr as $val) {
            if (is_numeric($val)) {
                $num = round((float) $val, 2);
                if ($num > 0 && !in_array($num, $clean, true)) {
                    $clean[] = $num;
                }
            }
        }
        rsort($clean, SORT_NUMERIC);
        return $clean;
    };

    $sheetIn = (isset($source['sheetDbConfig']) && is_array($source['sheetDbConfig']))
        ? $source['sheetDbConfig']
        : [];
    $webhookUrl = trim((string) ($sheetIn['webhookUrl'] ?? ''));
    if ($webhookUrl !== '' && !preg_match('#^https?://#i', $webhookUrl)) {
        $webhookUrl = '';
    }

    return [
        'schemaVersion' => '1.0',
        'updatedAt' => gmdate('c'),
        'currency' => $currency,
        'branding' => [
            'title' => $title,
            'subtitle' => $subtitle,
            'logoUrl' => $logoUrl,
            'faviconValue' => $faviconValue,
        ],
        'limitSettings' => [
            'enabled' => isset($limitsIn['enabled']) ? (bool) $limitsIn['enabled'] : true,
            'lightRedLimits' => $parseNumList(
                $limitsIn['lightRedLimits'] ?? null,
                $fallback['limitSettings']['lightRedLimits']
            ),
            'lightOrangeLimits' => $parseNumList(
                $limitsIn['lightOrangeLimits'] ?? null,
                $fallback['limitSettings']['lightOrangeLimits']
            ),
            'lightYellowLimits' => $parseNumList(
                $limitsIn['lightYellowLimits'] ?? null,
                $fallback['limitSettings']['lightYellowLimits']
            ),
            'lightGreenLimits' => $parseNumList(
                $limitsIn['lightGreenLimits'] ?? null,
                $fallback['limitSettings']['lightGreenLimits']
            ),
        ],
        'sheetDbConfig' => [
            'webhookUrl' => $webhookUrl,
            'autoSync' => isset($sheetIn['autoSync']) ? (bool) $sheetIn['autoSync'] : true,
            'lastSyncAt' => trim((string) ($sheetIn['lastSyncAt'] ?? '')),
        ],
    ];
}

/**
 * Reads settings from settings.json or creates it with defaults if missing.
 */
function readOrCreateSettings(string $filePath): array
{
    $defaults = defaultSettingsDocument();
    if (!file_exists($filePath)) {
        @file_put_contents(
            $filePath,
            json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            LOCK_EX
        );
        return $defaults;
    }

    $content = @file_get_contents($filePath);
    if ($content === false || trim($content) === '') {
        return $defaults;
    }

    $decoded = json_decode($content, true);
    if (!is_array($decoded)) {
        return $defaults;
    }

    return normalizeSettingsDocument($decoded, $defaults);
}

try {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($method === 'GET') {
        $doc = readOrCreateSettings($settingsFile);
        echo json_encode([
            'ok' => true,
            'source' => 'settings.json',
            'settings' => $doc,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method === 'POST') {
        $rawBody = file_get_contents('php://input');
        $payload = json_decode($rawBody ?: '{}', true);
        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid JSON request body.']);
            exit;
        }

        $current = readOrCreateSettings($settingsFile);
        $normalized = normalizeSettingsDocument($payload, $current);
        $encoded = json_encode(
            $normalized,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($encoded === false) {
            throw new RuntimeException('Failed to encode settings to JSON.');
        }

        $written = @file_put_contents($settingsFile, $encoded . PHP_EOL, LOCK_EX);
        if ($written === false) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'error' => 'Unable to write settings.json. Check directory write permissions.',
            ]);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'message' => 'Settings saved to settings.json',
            'settings' => $normalized,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ]);
}