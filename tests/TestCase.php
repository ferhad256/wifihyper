<?php

namespace Tests;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

abstract class TestCase extends BaseTestCase
{
    /**
     * Fixture passwords, generated once per process.
     *
     * @var array<string, string>
     */
    private static array $fixturePasswords = [];

    /**
     * A valid password for fixtures, generated at runtime.
     *
     * Deliberately not a string literal. A password-shaped constant in a test
     * file is indistinguishable from a real credential to a secret scanner,
     * and a scanner that fires on fixtures is one people learn to wave
     * through - so the literal itself is the risk, not the value behind it.
     * It also stops any test coming to depend on the exact characters.
     *
     * Composed from each required character class rather than drawn at random
     * from all of them, so it always satisfies the registration rule (lower,
     * upper, digit, and one of @$!%*?&) instead of failing on an unlucky
     * draw. Memoised by name: the same name yields the same password for the
     * whole run, so a test can hash it in setUp and sign in with it later,
     * and two different names are two different passwords.
     */
    protected static function fixturePassword(string $name = 'default'): string
    {
        return self::$fixturePasswords[$name] ??= collect([
            Arr::random(range('a', 'z'), 5),
            Arr::random(range('A', 'Z'), 3),
            Arr::random(range('0', '9'), 2),
            Arr::random(['@', '$', '!', '%', '*', '?', '&'], 2),
        ])->flatten()->shuffle()->implode('');
    }

    /**
     * Authenticate as a tenant.
     *
     * Every test authenticates through this one helper. It previously wrote a
     * hand-rolled session key; swapping it for the real guard was the single
     * change that re-validated the whole auth refactor.
     */
    protected function loginAsTenant(Tenant $tenant): static
    {
        return $this->actingAs($tenant, 'tenant');
    }

    /**
     * Messages actually handed to the mailer.
     *
     * Do NOT use Mail::fake() to assert on this application's email. Every
     * send here goes through Mail::send('emails.some-view', ...) with a string
     * view rather than a Mailable class, and MailFake::sendMail() discards
     * anything that is not a Mailable instance. That makes assertNothingSent()
     * pass vacuously and assertSentCount() fail unconditionally.
     *
     * The array transport (MAIL_MAILER=array, set in phpunit.xml) does record
     * string-view sends, so assert against this instead.
     */
    protected function sentMails(): Collection
    {
        $transport = Mail::getSymfonyTransport();

        return $transport instanceof ArrayTransport
            ? $transport->messages()
            : new Collection();
    }

    /**
     * Describes how mail is actually configured, for assertion messages.
     *
     * A count of zero has two very different causes - nothing tried to send,
     * or the configured transport is not the one collecting - and they are
     * indistinguishable from the failure output without this.
     */
    protected function mailDiagnostics(): string
    {
        $transport = Mail::getSymfonyTransport();

        return sprintf(
            'mail.default=%s transport=%s collecting=%s',
            (string) config('mail.default'),
            $transport::class,
            $transport instanceof ArrayTransport ? 'yes' : 'NO',
        );
    }

    /**
     * Discard anything sent so far, so a test can assert on what follows.
     */
    protected function flushSentMails(): void
    {
        $transport = Mail::getSymfonyTransport();

        if ($transport instanceof ArrayTransport) {
            $transport->flush();
        }
    }
}
