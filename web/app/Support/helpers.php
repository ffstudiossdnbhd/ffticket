<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function selected(mixed $actual, mixed $expected): string
{
    return (string)$actual === (string)$expected ? ' selected' : '';
}

function checked(mixed $value): string
{
    return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? ' checked' : '';
}

function initials(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return 'FF';
    }

    $parts = preg_split('/\s+/', $name) ?: [];
    $letters = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $letters .= strtoupper(substr($part, 0, 1));
    }

    return $letters === '' ? 'FF' : $letters;
}

function option_endpoint(string $type): ?string
{
    return match ($type) {
        'categories' => 'categories/crud.php',
        'urgency-types' => 'urgency-types/crud.php',
        'locations' => 'locations/crud.php',
        default => null,
    };
}

function badge_class(string $prefix, mixed $value): string
{
    $slug = strtolower(trim((string)$value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    return $prefix . '-' . ($slug === '' ? 'default' : $slug);
}
