<?php

namespace Tests\Feature\CadastralDept;

use App\Http\Controllers\CadastralDept\DeskController;
use App\Models\User;
use App\Services\CadastralDept\WorkflowDefinition;
use App\Services\CadastralDept\WorkflowEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The cards on a Cadastral desk page count what that page lists, on every sidebar list
 * (user, 2026-11-06: 1st Charting said 93 over an empty table). READ-ONLY against sqlsrv.
 */
class DeskCardsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function open(string $desk, ?string $queue = null)
    {
        $user = User::query()->whereRaw("LOWER(ISNULL(type, '')) IN ('supper admin', 'super admin')")->orderBy('id')->first();
        if (!$user) {
            $this->markTestSkipped('No super admin account in this database.');
        }
        $this->actingAs($user);
        $request = Request::create('/cadastral-dept/desks/' . $desk, 'GET', array_filter(['queue' => $queue]));
        $request->setUserResolver(fn () => $user);
        app()->instance('request', $request);

        return app(DeskController::class)->show($request, $desk, app(WorkflowEngine::class))->getData();
    }

    private function card(array $data, string $label): ?int
    {
        foreach ($data['cdCards'] as $card) {
            if ($card['label'] === $label) {
                return (int) $card['value'];
            }
        }

        return null;
    }

    public function test_every_list_counts_only_its_own_files(): void
    {
        foreach (app(WorkflowDefinition::class)->queues() as $desk => $q) {
            foreach (array_keys((array) ($q['entries'] ?? [])) as $key) {
                $data = $this->open($desk, $key);
                $name = "$desk / $key";

                $first = (int) $data['cdCards'][0]['value'];
                $this->assertContains($data['cdCards'][0]['label'], ['On this list', 'At this desk'], $name);
                $this->assertSame($data['here']->count(), $first, $name);
                $this->assertSame($first, $this->card($data, 'Tasks still open') + $this->card($data, 'Ready to send on'), $name);

                $out = $this->card($data, 'Out with other departments');
                if ($out !== null) {
                    $this->assertSame($data['away']->count(), $out, $name);
                }
            }
        }
    }

    public function test_a_list_that_shows_no_done_files_has_no_card_for_them(): void
    {
        $data = $this->open('cadastral_gis', 'buffer');

        $this->assertSame('On this list', $data['cdCards'][0]['label']);
        $this->assertNull($this->card($data, 'Sent on'));
        $this->assertNull($this->card($data, 'Finished'));
    }

    public function test_a_task_list_labels_its_done_files_and_counts_them(): void
    {
        $data = $this->open('ad_information', 'first_charting');

        $this->assertSame('On this list', $data['cdCards'][0]['label']);
        $this->assertSame($data['sentTotal'], $this->card($data, 'Done, still open') + $this->card($data, 'Done, finished'));
        $this->assertNull($this->card($data, 'Sent on'));
    }

    public function test_the_whole_desk_page_keeps_its_own_cards(): void
    {
        $data = $this->open('ad_information');

        $this->assertSame('At this desk', $data['cdCards'][0]['label']);
        $this->assertSame($data['here']->count(), (int) $data['cdCards'][0]['value']);
        $this->assertNotNull($this->card($data, 'Sent on'));
    }
}
