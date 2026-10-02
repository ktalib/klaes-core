{{--
    The report stage tracker, shared by the report detail page and anything
    else that shows a chain (rebuild plan Phase 4).

    Colours follow the brief: done green, active amber, waiting slate, returned
    red; skipped is muted so it is never mistaken for progress. Each stage shows
    its responsible officer, date, status and note.

    Expects:
      $report       CadastralReport, steps loaded
      $steps        the steps, in order
      $workflow     App\Services\Cadastral\ReportWorkflow
      $interactive  bool, default false: show the current step's actions
      $userNames    users.id => name, for the assignee

    The actions are offered only on the current step, and only when
    ReportWorkflow::blockReason() has nothing against the signed-in user; when
    it does, the buttons render disabled with that same sentence, so the screen
    never offers what the server would refuse.
--}}
@once
<style>
    .cadastral-proto .cad-tracker { list-style: none; margin: 0; padding: 0; }
    .cadastral-proto .cad-tracker li { position: relative; display: flex; gap: 12px; padding: 0 0 18px; }
    .cadastral-proto .cad-tracker li:last-child { padding-bottom: 0; }
    .cadastral-proto .cad-tracker li:not(:last-child)::before {
        content: ''; position: absolute; left: 12px; top: 26px; bottom: 2px; width: 2px; background: #e2e8f0;
    }
    .cadastral-proto .cad-tracker li.is-done:not(:last-child)::before { background: #86efac; }
    .cadastral-proto .cad-tracker .dot {
        flex: 0 0 26px; height: 26px; border-radius: 50%; display: grid; place-items: center;
        font-size: 11px; font-weight: 700; background: #f1f5f9; color: #64748b; border: 2px solid #cbd5e1;
    }
    .cadastral-proto .cad-tracker .is-done .dot     { background: #dcfce7; color: #166534; border-color: #22c55e; }
    .cadastral-proto .cad-tracker .is-active .dot   { background: #fef3c7; color: #92400e; border-color: #f59e0b; }
    .cadastral-proto .cad-tracker .is-returned .dot { background: #fee2e2; color: #991b1b; border-color: #ef4444; }
    .cadastral-proto .cad-tracker .is-skipped .dot  { background: #f8fafc; color: #94a3b8; border-color: #e2e8f0; }
    .cadastral-proto .cad-tracker .name { font-size: 13.5px; font-weight: 600; color: var(--gray-800); }
    .cadastral-proto .cad-tracker .is-waiting .name,
    .cadastral-proto .cad-tracker .is-skipped .name { color: #64748b; font-weight: 500; }
    .cadastral-proto .cad-tracker .meta { font-size: 12px; color: var(--gray-500); }
    .cadastral-proto .cad-tracker .state { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    .cadastral-proto .cad-tracker .is-done .state     { color: #15803d; }
    .cadastral-proto .cad-tracker .is-active .state   { color: #b45309; }
    .cadastral-proto .cad-tracker .is-returned .state { color: #b91c1c; }
    .cadastral-proto .cad-tracker .is-waiting .state,
    .cadastral-proto .cad-tracker .is-skipped .state  { color: #94a3b8; }
    .cadastral-proto .cad-tracker .note { font-size: 12px; color: var(--gray-600); margin-top: 2px; white-space: pre-wrap; }

    .cadastral-proto .cad-step-actions { margin-top: 8px; padding: 10px; border: 1px solid #fde68a; background: #fffbeb; border-radius: 6px; }
    .cadastral-proto .cad-step-actions.is-returned { border-color: #fecaca; background: #fef2f2; }
    .cadastral-proto .cad-step-actions form { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; margin: 0; }
    .cadastral-proto .cad-step-actions input[type=text],
    .cadastral-proto .cad-step-actions textarea {
        padding: 6px 8px; border: 1px solid var(--gray-300); border-radius: 4px; font-size: 12.5px; font-family: var(--font);
    }
    .cadastral-proto .cad-step-actions .row { display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-start; }
    .cadastral-proto .cad-step-actions .blocked { font-size: 12px; color: #92400e; display: flex; gap: 6px; align-items: flex-start; margin-top: 6px; }
    .cadastral-proto .cad-step-actions button[disabled] { opacity: .45; cursor: not-allowed; }
    .cadastral-proto .cad-step-actions details summary { cursor: pointer; font-size: 12.5px; color: #b91c1c; list-style: none; }
</style>
@endonce

@php
    $interactive = $interactive ?? false;
    $userNames   = $userNames ?? [];
    $applicationInstalled = \App\Models\Cadastral\CadastralReport::applicationInstalled();
@endphp

<ol class="cad-tracker">
    @foreach ($steps as $step)
        @php
            $state = in_array($step->status, ['done', 'active', 'returned', 'skipped'], true) ? $step->status : 'waiting';
            $postLabel = $workflow->postLabel($step->required_post);
            $isCurrent = in_array($step->status, ['active', 'returned'], true) && ! $report->isFinished();

            // Who: the officer who did it, or whose desk it is on now.
            if (in_array($state, ['done', 'returned'], true) && $step->actor_name) {
                $who = $step->actor_name;
            } elseif ($isCurrent && $report->assigned_user_id && isset($userNames[$report->assigned_user_id])) {
                $who = $userNames[$report->assigned_user_id] . ' (assigned)';
            } else {
                $who = $postLabel ? 'Desk: ' . $postLabel : 'Any Cadastral officer';
            }

            $when = $state === 'done' ? $step->completed_at : ($state === 'waiting' ? null : $step->started_at);
        @endphp
        <li class="is-{{ $state }}">
            <span class="dot">
                @if ($state === 'done')
                    <i class="fas fa-check"></i>
                @elseif ($state === 'returned')
                    <i class="fas fa-rotate-left"></i>
                @else
                    {{ $step->step_no }}
                @endif
            </span>
            <div style="flex:1;min-width:0;">
                <div class="name">{{ $step->step_name }}</div>
                <div class="meta">
                    <span class="state">{{ $state === 'active' ? 'Active' : ucfirst($state) }}</span>
                    · {{ $who }}
                    @if ($when) · {{ $when->format('j M Y, H:i') }} @endif
                </div>
                @if ($step->note)
                    <div class="note">“{{ $step->note }}”</div>
                @endif

                @if ($interactive && $isCurrent)
                    @php
                        $reason = $workflow->blockReason($report, $step);
                        $advanceReason = $reason;
                        if (! $advanceReason && $step->step_key === 'report' && $applicationInstalled) {
                            $missing = $report->unansweredApplicationQuestions();
                            if ($missing) {
                                $advanceReason = 'Answer every question of the Report on Application below first (missing: '
                                    . implode(', ', array_map(fn ($c) => 'Q' . substr($c, 1, 1), $missing)) . ').';
                            }
                        }
                        $canReturn = $step->step_no > 1;
                        $superOverride = ! $reason && $step->required_post && $workflow->isSuperAdmin()
                            && ! in_array($step->required_post, \App\Models\CadastralOfficer::postsFor(auth()->id()), true);
                    @endphp

                    <div class="cad-step-actions {{ $state === 'returned' ? 'is-returned' : '' }}">
                        <div class="row">
                            @if ($step->step_key === 'approval')
                                @canDo('Cad - Records', 'approve')
                                    <form method="POST" action="{{ route('cadastral-module.reports.approve', $report) }}">
                                        @csrf
                                        <input type="text" name="note" placeholder="Approval note (optional)" maxlength="4000" style="width:200px;" @disabled($advanceReason) />
                                        <button type="submit" class="btn btn-success btn-sm" @disabled($advanceReason)><i class="fas fa-check"></i> Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('cadastral-module.reports.reject', $report) }}">
                                        @csrf
                                        <input type="text" name="note" required maxlength="4000" placeholder="Reason for rejecting (required)" style="width:220px;" @disabled($reason) />
                                        <button type="submit" class="btn btn-danger btn-sm" @disabled($reason)
                                                onclick="return confirm('Reject {{ $report->report_ref }}? This closes the report.');"><i class="fas fa-xmark"></i> Reject</button>
                                    </form>
                                @else
                                    <span class="helper-text" style="margin:0;">You do not have the approve permission on Cadastral records.</span>
                                @endcanDo
                            @elseif ($step->step_key === 'dispatch')
                                @canDo('Cad - Records', 'edit')
                                    <form method="POST" action="{{ route('cadastral-module.reports.mark-dispatched', $report) }}">
                                        @csrf
                                        <input type="text" name="dispatched_to" required maxlength="255" placeholder="Dispatched to (required)"
                                               value="{{ old('dispatched_to', $report->dispatched_to) }}" style="width:200px;" @disabled($advanceReason) />
                                        <input type="text" name="note" maxlength="3000" placeholder="Dispatch details — means, reference, received by"
                                               style="width:280px;" @disabled($advanceReason) />
                                        <button type="submit" class="btn btn-primary btn-sm" @disabled($advanceReason)><i class="fas fa-paper-plane"></i> Dispatch</button>
                                    </form>
                                @endcanDo
                            @else
                                @canDo('Cad - Records', 'edit')
                                    <form method="POST" action="{{ route('cadastral-module.reports.steps.mark-done', [$report, $step]) }}">
                                        @csrf
                                        <input type="text" name="note" maxlength="4000" placeholder="Stage note (optional)" style="width:220px;" @disabled($advanceReason) />
                                        <button type="submit" class="btn btn-success btn-sm" @disabled($advanceReason)><i class="fas fa-forward"></i> Advance</button>
                                    </form>
                                @endcanDo
                            @endif
                        </div>

                        @if ($canReturn)
                            @canDo('Cad - Records', 'edit')
                                <details style="margin-top:8px;" @if ($reason) aria-disabled="true" @endif>
                                    <summary><i class="fas fa-rotate-left"></i> Return to the previous stage</summary>
                                    <form method="POST" action="{{ route('cadastral-module.reports.steps.mark-returned', [$report, $step]) }}" style="margin-top:6px;">
                                        @csrf
                                        <textarea name="note" rows="2" required minlength="3" maxlength="4000" style="width:100%;max-width:520px;"
                                                  placeholder="What has to be corrected (required)" @disabled($reason)></textarea>
                                        <button type="submit" class="btn btn-outline btn-sm" @disabled($reason)><i class="fas fa-rotate-left"></i> Return</button>
                                    </form>
                                </details>
                            @endcanDo
                        @endif

                        @if ($advanceReason)
                            <div class="blocked"><i class="fas fa-lock" style="margin-top:2px;"></i><span>{{ $advanceReason }}</span></div>
                        @elseif ($superOverride)
                            <div class="blocked"><i class="fas fa-user-shield" style="margin-top:2px;"></i><span>You are acting as Super Admin; this is the {{ $postLabel }}'s step.</span></div>
                        @endif
                    </div>
                @endif
            </div>
        </li>
    @endforeach
</ol>
