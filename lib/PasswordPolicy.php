<?php
/**
 * XPLabs - Password strength rules for admin-set passwords.
 */

namespace XPLabs\Lib;

class PasswordPolicy
{
    public const MIN_LENGTH = 8;
    public const MAX_LENGTH = 128;

    /**
     * Human-readable rules for UI.
     *
     * @return string[]
     */
    public static function requirements(): array
    {
        return [
            'At least ' . self::MIN_LENGTH . ' characters',
            'At least one uppercase letter (A–Z)',
            'At least one lowercase letter (a–z)',
            'At least one number (0–9)',
            'At least one symbol (!@#$%^&* etc.)',
            'Must not match the user\'s LRN',
        ];
    }

    /**
     * Validate password; returns error message or null if valid.
     */
    public static function validate(string $password, ?string $lrn = null): ?string
    {
        $password = (string) $password;
        $len = strlen($password);

        if ($len < self::MIN_LENGTH) {
            return 'Password must be at least ' . self::MIN_LENGTH . ' characters.';
        }
        if ($len > self::MAX_LENGTH) {
            return 'Password must be at most ' . self::MAX_LENGTH . ' characters.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return 'Password must include at least one uppercase letter.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            return 'Password must include at least one lowercase letter.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            return 'Password must include at least one number.';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return 'Password must include at least one symbol character.';
        }
        if ($lrn !== null && $lrn !== '' && hash_equals(strtolower($lrn), strtolower($password))) {
            return 'Password cannot be the same as the user\'s LRN.';
        }

        return null;
    }
}
