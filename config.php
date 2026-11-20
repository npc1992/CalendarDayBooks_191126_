<?php
/**
 * Calendar Day Entry & Daily Sum Tracker - Self-Contained PHP Settings File
 *
 * Stores all application settings (currency, branding title/logo/favicon,
 * limit color highlight thresholds, and Google Sheet DB config) directly
 * inside config.php without requiring a separate settings.json file.
 */

declare(strict_types=1);

const TRACKER_CONFIG_DATA = [
    'schemaVersion' => '1.0',
    'updatedAt' => '2026-11-20T00:00:00Z',
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

/**
 * Default fallback settings structure.
 */
function defaultTrackerSettings(): array
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
 * Normalizes and validates an incoming settings array.
 */
function normalizeTrackerSettings($raw, array $fallback): array
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
 * Formats a PHP array as clean, readable PHP source code for TRACKER_CONFIG_DATA.
 */
function exportPhpConfigConstant(array $settings): string
{
    $phpArray = var_export($settings, true);
    $phpArray = preg_replace('/^array \(/m', '[', $phpArray);
    $phpArray = preg_replace('/\)$/m', ']', $phpArray);
    $phpArray = str_replace('array (', '[', $phpArray);
    $phpArray = str_replace('),', '],', $phpArray);
    $phpArray = preg_replace('/=>\s*\n\s*\[/', '=> [', $phpArray);
    return "const TRACKER_CONFIG_DATA = " . $phpArray . ";";
}

/**
 * Persists updated settings directly into config.php by replacing TRACKER_CONFIG_DATA.
 */
function writeSettingsIntoConfigPhp(string $selfPath, array $settings): bool
{
    $sourceCode = @file_get_contents($selfPath);
    if ($sourceCode === false || $sourceCode === '') {
        return false;
    }

    $newConstBlock = exportPhpConfigConstant($settings);
    $pattern = '/const\s+TRACKER_CONFIG_DATA\s*=\s*\[.*?\];/s';
    if (!preg_match($pattern, $sourceCode)) {
        return false;
    }

    $updatedCode = preg_replace($pattern, $newConstBlock, $sourceCode, 1);
    if (!is_string($updatedCode) || $updatedCode === '') {
        return false;
    }

    return @file_put_contents($selfPath, $updatedCode, LOCK_EX) !== false;
}

// Serve HTTP JSON API when accessed directly via browser/fetch
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    try {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $defaults = defaultTrackerSettings();
        $currentSettings = normalizeTrackerSettings(TRACKER_CONFIG_DATA, $defaults);

        if ($method === 'GET') {
            echo json_encode([
                'ok' => true,
                'source' => 'config.php',
                'settings' => $currentSettings,
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

            $normalized = normalizeTrackerSettings($payload, $currentSettings);
            $saved = writeSettingsIntoConfigPhp(__FILE__, $normalized);

            if (!$saved) {
                http_response_code(500);
                echo json_encode([
                    'ok' => false,
                    'error' => 'Unable to write updated settings into config.php. Check file write permissions.',
                ]);
                exit;
            }

            echo json_encode([
                'ok' => true,
                'message' => 'Settings saved directly to config.php',
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
} 
