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
     * Tenant auth is currently a hand-rolled session key rather than a Laravel
     * guard (see App\Http\Middleware\TenantAuth). Every test goes through this
     * one helper so that when the tenant guard lands, only this method changes
     * and the whole suite re-validates the refactor.
     */
    protected function loginAsTenant(Tenant $tenant): static
    {
        return $this->withSession(['tenant_id' => $tenant->id]);

        // After the tenant guard refactor this becomes:
        // return $this->actingAs($tenant, 'tenant');
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
