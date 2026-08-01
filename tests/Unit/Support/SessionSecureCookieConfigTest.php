<?php

namespace Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * config/session.php's "secure" value must default true in production without
 * relying on SESSION_SECURE_COOKIE being set — that env var is absent from
 * both .env.example and the deployed production .env, so a structural default
 * is the only thing standing between the session cookie and being sent over
 * plain HTTP.
 */
#[TestDox('Session secure cookie config')]
class SessionSecureCookieConfigTest extends TestCase
{
    #[Test]
    #[DataProvider('scenarios')]
    #[TestDox('resolves the secure flag correctly for each environment/override combination')]
    public function resolves_correctly(?string $appEnv, ?string $override, bool $expected): void
    {
        $this->withEnv('APP_ENV', $appEnv);
        $this->withEnv('SESSION_SECURE_COOKIE', $override);

        $config = require config_path('session.php');

        $this->assertSame($expected, $config['secure']);
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: bool}>
     */
    public static function scenarios(): array
    {
        return [
            'production with no override defaults secure' => ['production', null, true],
            'local with no override defaults insecure' => ['local', null, false],
            'testing with no override defaults insecure' => ['testing', null, false],
            'production can be explicitly disabled' => ['production', 'false', false],
            'local can be explicitly enabled' => ['local', 'true', true],
        ];
    }

    /** Temporarily override a real process env var (config files read env(), not config()) and restore it after the test. */
    private function withEnv(string $key, ?string $value): void
    {
        $original = $_ENV[$key] ?? null;

        if ($value === null) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        } else {
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }

        $this->beforeApplicationDestroyed(function () use ($key, $original): void {
            if ($original === null) {
                unset($_ENV[$key], $_SERVER[$key]);
                putenv($key);
            } else {
                $_ENV[$key] = $_SERVER[$key] = $original;
                putenv("{$key}={$original}");
            }
        });
    }
}
