<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Enums;

enum CustomerDataMode: string
{
    case RELY_ON_COOKIE = 'rely-on-cookie';
    case ALWAYS = 'always';
    case NEVER = 'never';

    /**
     * Resolves a stored configuration value into a mode.
     *
     * Handles legacy boolean values (from before this setting became a select):
     * true meant "send customer data" (ALWAYS), false meant "do not send" (NEVER).
     * Anything unrecognised falls back to the privacy-preserving cookie-driven default.
     */
    public static function fromConfigValue(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? self::ALWAYS : self::NEVER;
        }

        if (is_string($value) && $value !== '') {
            return self::tryFrom($value) ?? self::RELY_ON_COOKIE;
        }

        return self::RELY_ON_COOKIE;
    }

    /**
     * Whether customer PII may be sent to Nosto given the marketing "nosto_track" cookie state.
     *
     * Backend contexts (no HTTP request) pass false, so only ALWAYS sends there.
     * When this returns false the caller should also enable Nosto's "doNotTrack".
     */
    public function shouldSendCustomerData(bool $nostoTrackCookiePresent): bool
    {
        return match ($this) {
            self::ALWAYS => true,
            self::NEVER => false,
            self::RELY_ON_COOKIE => $nostoTrackCookiePresent,
        };
    }
}
