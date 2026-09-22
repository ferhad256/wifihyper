<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\InputSanitization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * InputSanitization runs globally, which puts it in front of every Livewire
 * request once Filament is installed. These tests pin the two properties that
 * make that safe, and the one that keeps the middleware useful.
 */
class InputSanitizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Run the middleware and hand back the request the application would see.
     */
    private function pipe(Request $request): Request
    {
        $received = null;

        (new InputSanitization())->handle($request, function (Request $r) use (&$received) {
            $received = $r;
            return new Response();
        });

        return $received;
    }

    private function jsonRequest(string $uri, array $payload, array $headers = []): Request
    {
        return Request::create(
            $uri,
            'POST',
            [],
            [],
            [],
            array_merge(['CONTENT_TYPE' => 'application/json'], $headers),
            json_encode($payload)
        );
    }

    /**
     * The regression this middleware change exists to prevent.
     *
     * Livewire sends a `snapshot` string and a checksum computed over it. The
     * snapshot is JSON-inside-JSON, so it is full of double quotes. Encoding
     * them to &quot; invalidates the checksum and every Livewire interaction
     * fails - meaning no Filament page would work at all.
     */
    public function test_a_livewire_payload_passes_through_untouched(): void
    {
        $snapshot = '{"data":{"name":"O\'Brien & Sons","qty":3},"checksum":"abc123"}';

        // Livewire::getUpdateUri(), not a hand-written '/livewire/update'.
        // Livewire 4 hashes APP_KEY into its endpoint prefix, so the literal
        // path exists in no environment and asserting against it proved
        // nothing - which is how this shipped broken.
        $request = $this->jsonRequest(Livewire::getUpdateUri(), [
            'components' => [
                ['snapshot' => $snapshot, 'calls' => []],
            ],
        ], ['HTTP_X_LIVEWIRE' => '1']);

        $received = $this->pipe($request);

        $this->assertSame(
            $snapshot,
            $received->input('components.0.snapshot'),
            'The Livewire snapshot was rewritten - checksum validation would fail.'
        );
    }

    /**
     * The header-and-content-type check, which is what actually holds.
     *
     * A path list can only exempt an endpoint whose name is known, and
     * Livewire's name is a hash of APP_KEY that differs per environment. This
     * asserts the exemption survives a prefix nobody anticipated.
     */
    public function test_a_livewire_payload_is_exempt_whatever_the_endpoint_is_called(): void
    {
        $snapshot = '{"data":{"note":"a quoted word"},"checksum":"abc123"}';

        $request = $this->jsonRequest('/some-unrecognised-prefix/update', [
            'components' => [['snapshot' => $snapshot, 'calls' => []]],
        ], ['HTTP_X_LIVEWIRE' => '1']);

        $this->assertSame($snapshot, $this->pipe($request)->input('components.0.snapshot'));
    }

    /**
     * The end-to-end version: a real round trip over HTTP, through the global
     * middleware stack, to the endpoint Livewire actually publishes.
     *
     * Every other test here drives the middleware in isolation, which is why
     * they all passed while production answered "Invalid Livewire snapshot
     * structure" on every sign-in. Only a real request catches a wrong URL.
     */
    public function test_a_real_livewire_round_trip_survives_the_middleware_stack(): void
    {
        $html = $this->get('/admin/login')->assertOk()->getContent();

        $this->assertSame(
            1,
            preg_match('/wire:snapshot="([^"]+)"/', $html, $matches),
            'No Livewire component was rendered on the admin login page.'
        );

        $response = $this->withHeaders(['X-Livewire' => '1'])->postJson(Livewire::getUpdateUri(), [
            '_token' => csrf_token(),
            'components' => [[
                'snapshot' => html_entity_decode($matches[1], ENT_QUOTES),
                'updates' => [],
                'calls' => [],
            ]],
        ]);

        $response->assertOk();
        $this->assertArrayHasKey('components', $response->json());
    }

    public function test_filament_panel_paths_pass_through_untouched(): void
    {
        foreach (['/dashboard/login', '/admin/login'] as $uri) {
            $received = $this->pipe($this->jsonRequest($uri, ['quote' => 'He said "hi"']));

            $this->assertSame(
                'He said "hi"',
                $received->input('quote'),
                "Payload for {$uri} was rewritten."
            );
        }
    }

    /**
     * The middleware must still do its job everywhere else.
     */
    public function test_ordinary_requests_are_still_sanitized(): void
    {
        $request = Request::create('/login', 'POST', ['name' => '  <script>alert(1)</script>  ']);

        $received = $this->pipe($request);

        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', $received->input('name'));
    }

    /**
     * Casting every value to string turned null into "", true into "1" and
     * integers into numeric strings, which changed the shape of typed
     * payloads and defeated "nullable" validation downstream.
     */
    public function test_non_string_values_keep_their_type(): void
    {
        $request = $this->jsonRequest('/webhooks/resend', [
            'nothing' => null,
            'flag' => true,
            'count' => 42,
        ]);

        $received = $this->pipe($request);

        $this->assertNull($received->input('nothing'));
        $this->assertTrue($received->input('flag'));
        $this->assertSame(42, $received->input('count'));
    }

    public function test_null_bytes_and_control_characters_are_removed(): void
    {
        $request = Request::create('/login', 'POST', ['code' => "AB\0CD\x07EF"]);

        $this->assertSame('ABCDEF', $this->pipe($request)->input('code'));
    }
}
