<?php

namespace Tests\Unit;

use App\Http\Middleware\GzipResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GzipResponseTest extends TestCase
{
    private function through(string $acceptEncoding, $response)
    {
        $request = Request::create('/instrument_registration');
        if ($acceptEncoding !== '') {
            $request->headers->set('Accept-Encoding', $acceptEncoding);
        }

        return (new GzipResponse)->handle($request, fn () => $response);
    }

    private function bigHtml(): string
    {
        return '<html>' . str_repeat('<tr><td>CON-RES-2026-487</td></tr>', 2000) . '</html>';
    }

    public function test_large_html_is_gzipped_when_the_browser_accepts_it(): void
    {
        $html = $this->bigHtml();
        $response = $this->through('gzip, deflate, br', new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']));

        $this->assertSame('gzip', $response->headers->get('Content-Encoding'));
        $this->assertSame('Accept-Encoding', $response->headers->get('Vary'));
        $this->assertSame((string) strlen($response->getContent()), $response->headers->get('Content-Length'));
        $this->assertSame($html, gzdecode($response->getContent()));
    }

    public function test_browsers_without_gzip_get_the_plain_body(): void
    {
        $html = $this->bigHtml();
        $response = $this->through('', new Response($html, 200, ['Content-Type' => 'text/html']));

        $this->assertFalse($response->headers->has('Content-Encoding'));
        $this->assertSame($html, $response->getContent());
    }

    public function test_small_bodies_and_already_encoded_bodies_are_left_alone(): void
    {
        $small = $this->through('gzip', new Response('<p>ok</p>', 200, ['Content-Type' => 'text/html']));
        $this->assertFalse($small->headers->has('Content-Encoding'));

        $encoded = $this->through('gzip', new Response($this->bigHtml(), 200, ['Content-Type' => 'text/html', 'Content-Encoding' => 'br']));
        $this->assertSame('br', $encoded->headers->get('Content-Encoding'));
    }

    public function test_binary_and_streamed_responses_are_left_alone(): void
    {
        $pdf = $this->through('gzip', new Response(str_repeat('%PDF', 5000), 200, ['Content-Type' => 'application/pdf']));
        $this->assertFalse($pdf->headers->has('Content-Encoding'));

        $streamed = $this->through('gzip', new StreamedResponse(fn () => print('x'), 200, ['Content-Type' => 'text/html']));
        $this->assertFalse($streamed->headers->has('Content-Encoding'));
    }
}
