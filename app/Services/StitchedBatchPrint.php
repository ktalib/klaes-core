<?php

namespace App\Services;

use Illuminate\Contracts\View\View;

/**
 * Builds one printable document out of several single-record print views.
 *
 * Batch printing deliberately reuses each record's own print template rather than
 * a bespoke batch copy: a separate batch template drifts from the individual one
 * and starts issuing letters that do not match what the single print produces.
 */
class StitchedBatchPrint
{
    /**
     * Attribute values may contain '>' — the standard recommendation template opens
     * with <body onload="… setTimeout(() => window.close(), 500);"> — so the open
     * tag has to be matched allowing for quoted sections. A plain [^>]* stops at the
     * '>' inside the arrow function and spills the rest into the page as text.
     */
    private const OPEN_TAG = '(?:"[^"]*"|\'[^\']*\'|[^>])*';

    /**
     * @param  iterable<View>  $views  One per record, in print order.
     * @return array{head: string, bodies: array<int, string>}
     */
    public function stitch(iterable $views): array
    {
        $head   = '';
        $bodies = [];

        foreach ($views as $view) {
            $html = $view->render();

            if ($head === '' && preg_match('#<head\b' . self::OPEN_TAG . '>(.*?)</head>#is', $html, $m)) {
                $head = $m[1];
            }

            $body = preg_match('#<body\b' . self::OPEN_TAG . '>(.*?)</body>#is', $html, $m) ? $m[1] : $html;

            $bodies[] = $this->stripSelfDrivingScripts($body);
        }

        return ['head' => $head, 'bodies' => $bodies];
    }

    /**
     * Drop only the scripts that make a single-record document drive itself.
     *
     * N copies of those in one page would fire N print dialogs, close the window
     * mid-batch, and — because they assign window.onafterprint — log just the last
     * record. The batch wrapper does all three once.
     *
     * Everything else is kept: the RofO letter paints its ministry banner from a
     * script, so removing scripts wholesale left the banner blank (white text on a
     * white background).
     */
    private function stripSelfDrivingScripts(string $body): string
    {
        return preg_replace_callback(
            '#<script\b[^>]*>(.*?)</script>#is',
            function ($m) {
                $selfDriving = '/window\.print\s*\(|window\.close\s*\(|afterprint|log-print/i';

                return preg_match($selfDriving, $m[1]) ? '' : $m[0];
            },
            $body
        );
    }
}
