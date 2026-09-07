<?php
// includes/env.php
// Minimal .env loader — no Composer package needed.

function load_env(string $path): void
{
    // On a host like Render, config comes in as real environment variables
    // set in the dashboard — there's no .env file to upload at all. Only
    // treat a missing file as an error when nothing has configured DB_HOST
    // some other way either, so a genuinely mis-set-up local install still
    // fails loudly instead of connecting to nothing.
    if (!file_exists($path)) {
        if (getenv('DB_HOST') !== false) {
            return;
        }
        throw new RuntimeException(".env file not found at {$path}, and no environment variables are set either.");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments and blank lines
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Strip surrounding quotes if present
        if (strlen($value) >= 2 && (
            ($value[0] === '"' && $value[-1] === '"') ||
            ($value[0] === "'" && $value[-1] === "'")
        )) {
            $value = substr($value, 1, -1);
        }

        // putenv + $_ENV so either style of access works
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }
}