<?php

namespace Tests;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

abstract class TestCase extends BaseTestCase
{
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
