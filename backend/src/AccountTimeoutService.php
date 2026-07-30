<?php
declare(strict_types=1);

namespace FFTicket;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final class AccountTimeoutService
{
    public const TIMEZONE = 'Asia/Kuala_Lumpur';
    public const GRACE_SECONDS = 60;

    public static function status(array $user): ?array
    {
        $until = self::utcDate($user['timeout_until'] ?? null);
        if ($until === null || $until <= self::now()) {
            return null;
        }

        $effectiveAt = self::utcDate($user['timeout_effective_at'] ?? null) ?? self::now();
        return [
            'release_at' => $until->format('Y-m-d\TH:i:s\Z'),
            'release_at_myt' => $until->setTimezone(new DateTimeZone(self::TIMEZONE))->format('Y-m-d H:i'),
            'effective_at' => $effectiveAt->format('Y-m-d\TH:i:s\Z'),
            'effective_at_myt' => $effectiveAt->setTimezone(new DateTimeZone(self::TIMEZONE))->format('Y-m-d H:i'),
            'warning' => $effectiveAt > self::now(),
        ];
    }

    public static function assertCanSignIn(array $user): void
    {
        $state = self::status($user);
        if ($state !== null) {
            json_response('error', 'This account is temporarily timed out until ' . $state['release_at_myt'] . ' MYT.', $state, 423);
        }
    }

    public static function assertCanUseApi(array $user): void
    {
        $state = self::status($user);
        if ($state !== null && !$state['warning']) {
            json_response('error', 'Your timeout is active until ' . $state['release_at_myt'] . ' MYT.', $state, 423);
        }
    }

    public static function parseMytRelease(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        $timezone = new DateTimeZone(self::TIMEZONE);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, $timezone);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $date === false ||
            ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) ||
            $date->format('Y-m-d\TH:i') !== $value
        ) {
            return null;
        }

        return $date->setTimezone(new DateTimeZone('UTC'));
    }

    public static function newEffectiveAt(): DateTimeImmutable
    {
        return self::now()->add(new DateInterval('PT' . self::GRACE_SECONDS . 'S'));
    }

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    private static function utcDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
