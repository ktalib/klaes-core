@php
    use App\Models\InstrumentWorkflow\InstrumentApplication as IA;
    $stepsJs = collect($steps)->map(fn ($s) => [
        'key' => $s['key'], 'label' => $s['label'], 'department' => $s['department'], 'enabled' => $s['enabled'],
        'screen' => $s['screen'], 'waiting' => (int) ($waiting[$s['stage']] ?? 0),
    ])->values();
    $checkActions = collect($actions)->filter(fn ($a, $k) => str_starts_with($k, 'check.'));
    $otherActions = collect($actions)->reject(fn ($a, $k) => str_starts_with($k, 'check.'));
@endphp

@unless($workflowInstalled)
    <div class="iw-alert info">Pipeline settings can be configured here. The instrument application workflow is not installed on this deployment, so saving these settings will not move existing files or applications.</div>
@endunless

<form method="POST" action="{{ route('configurable-entries.pipeline.save') }}" class="space-y-5"
      x-data="cePipeline(@js($stepsJs))">
    @csrf

    {{-- Flow preview --}}
    <div class="iw-card">
        <div class="iw-card-head">
            <div>
                <div class="iw-card-title"><span class="iw-icon-tile blue"><i data-lucide="git-commit-horizontal" class="h-5 w-5"></i></span> Instrument Registration Pipeline</div>
                <div class="iw-card-sub">An application goes to the first check that is <strong>on</strong> and not yet passed, in this order. When none is left, the Land Information Certificate is generated. Checks that are off are recorded as skipped.</div>
            </div>
        </div>
        <div class="iw-card-body">
            <div class="cep-flow">
                <span class="cep-node fixed">Application fee<small>KLAES REV-M</small></span>
                <template x-for="step in steps" :key="step.key">
                    <span class="cep-node" :class="{ off: !step.enabled }">
                        <span x-text="step.department"></span>
                        <small x-text="step.enabled ? 'check' : 'off · skipped'"></small>
                    </span>
                </template>
                <span class="cep-node fixed">LIC generated<small>automatic</small></span>
                <span class="cep-node fixed">TIN &amp; Registration Fee</span>
                <span class="cep-node fixed">BIR</span>
                <span class="cep-node fixed">Registration &amp; signing</span>
            </div>
        </div>
    </div>

    {{-- Checks --}}
    <div class="iw-card">
        <div class="iw-card-head">
            <div>
                <div class="iw-card-title">Ministry checks</div>
                <div class="iw-card-sub">Order, on/off, the name shown on screens and the department. Each check keeps its own screen whatever its position.</div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="ce-table">
                <thead><tr><th style="width:90px">Order</th><th style="width:70px">On</th><th>Name</th><th>Department</th><th>Recorded in</th><th class="text-right">Waiting now</th></tr></thead>
                <tbody>
                    <template x-for="(step, index) in steps" :key="step.key">
                        <tr :class="{ 'cep-row-off': !step.enabled }">
                            <td>
                                <div class="flex items-center gap-1">
                                    <span class="cep-num" x-text="index + 1"></span>
                                    <button type="button" class="cep-move" @click="move(index, -1)" :disabled="index === 0" title="Move up">▲</button>
                                    <button type="button" class="cep-move" @click="move(index, 1)" :disabled="index === steps.length - 1" title="Move down">▼</button>
                                </div>
                                <input type="hidden" :name="`steps[${step.key}][sort_order]`" :value="index + 1">
                            </td>
                            <td>
                                <input type="hidden" :name="`steps[${step.key}][enabled]`" :value="step.enabled ? 1 : 0">
                                <label class="ce-switch"><input type="checkbox" x-model="step.enabled"><span></span></label>
                            </td>
                            <td><input type="text" class="iw-input" style="padding:7px 10px" :name="`steps[${step.key}][label]`" x-model="step.label" required maxlength="150"></td>
                            <td><input type="text" class="iw-input" style="padding:7px 10px" :name="`steps[${step.key}][department]`" x-model="step.department" required maxlength="150"></td>
                            <td class="ce-muted" x-text="step.screen"></td>
                            <td class="text-right">
                                <span class="ce-pill" :class="step.waiting ? (step.enabled ? 'blue' : 'yellow') : ''" x-text="step.waiting"></span>
                                <div class="ce-muted" x-show="step.waiting && !step.enabled" x-cloak>move on when saved</div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <div class="iw-alert warn" style="margin:0 22px 18px" x-show="steps.every(s => !s.enabled)" x-cloak>
            <i data-lucide="alert-triangle" class="h-4 w-4 mt-0.5"></i>
            <div>Every check is off: the LIC will be generated as soon as the application fee receipt is issued.</div>
        </div>
        <div class="iw-alert warn" style="margin:0 22px 18px" x-show="steps.some(s => !s.enabled)" x-cloak>
            <i data-lucide="alert-triangle" class="h-4 w-4 mt-0.5"></i>
            <div><strong>Workflow alert:</strong> <span x-text="steps.filter(s => !s.enabled).map(s => s.label).join(', ')"></span> will be skipped. Saving moves waiting applications forward and writes this change to User Activity Logs.</div>
        </div>
    </div>

    {{-- Roles --}}
    <div class="iw-card">
        <div class="iw-card-head">
            <div>
                <div class="iw-card-title">Roles per step</div>
                <div class="iw-card-sub">Which user roles may act on each step. Super Admin can always act. A step with no role can only be done by Super Admin.</div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="ce-table">
                <thead><tr><th style="min-width:240px">Step</th><th style="min-width:420px">Roles</th></tr></thead>
                <tbody>
                    @foreach($checkActions->merge($otherActions) as $action => $meta)
                        <tr>
                            <td>
                                <div class="font-medium text-gray-900">{{ $meta['label'] }}</div>
                                <div class="ce-muted">{{ $meta['where'] }} · <span class="iw-mono">{{ $action }}</span></div>
                                <div class="ce-muted">{{ $meta['custom'] ? 'Set here' : 'Default from configuration' }}</div>
                            </td>
                            <td>@include('system_admin.configurable_entries.partials.role_picker', ['action' => $action, 'selected' => $meta['roles']])</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="iw-card">
        <div class="iw-card-foot" style="border-radius:16px;border-top:0">
            <span class="ce-muted">Saving applies to applications from their next step. Applications waiting at a check you turn off move on straight away.</span>
            <button class="iw-btn iw-btn-primary"><i data-lucide="save" class="h-4 w-4"></i> Save pipeline</button>
        </div>
    </div>
</form>

<script>
    window.ceRoleNames = @js($roleNames);

    function cePipeline(steps) {
        return {
            steps,
            move(index, delta) {
                const target = index + delta;
                if (target < 0 || target >= this.steps.length) return;
                const [step] = this.steps.splice(index, 1);
                this.steps.splice(target, 0, step);
            },
        };
    }

    function ceRolePicker(selected) {
        return {
            selected: [...selected],
            query: '',
            open: false,
            get matches() {
                const q = this.query.trim().toLowerCase();
                return (window.ceRoleNames || [])
                    .filter(r => !this.selected.includes(r) && (q === '' || r.toLowerCase().includes(q)))
                    .slice(0, 60);
            },
            add(role) { if (!this.selected.includes(role)) this.selected.push(role); this.query = ''; },
            remove(role) { this.selected = this.selected.filter(r => r !== role); },
        };
    }
</script>

<style>
    .cep-flow { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .cep-node { position: relative; display: inline-flex; flex-direction: column; align-items: center; padding: 8px 14px; border-radius: 12px; border: 2px solid #2563eb; background: #eff6ff; color: #1e3a8a; font-size: 13px; font-weight: 700; line-height: 1.2; }
    .cep-node small { font-size: 10px; font-weight: 600; color: #3b82f6; text-transform: uppercase; letter-spacing: .04em; margin-top: 2px; }
    .cep-node.fixed { border-color: #d1d5db; background: #f9fafb; color: #374151; }
    .cep-node.fixed small { color: #9ca3af; }
    .cep-node.off { border-style: dashed; border-color: #d1d5db; background: #fff; color: #9ca3af; text-decoration: line-through; }
    .cep-node.off small { text-decoration: none; color: #b45309; }
    .cep-node + .cep-node::before, template + .cep-node::before { content: '→'; position: absolute; left: -16px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-weight: 400; text-decoration: none; }
    .cep-num { width: 24px; height: 24px; border-radius: 99px; background: #2563eb; color: #fff; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; }
    .cep-row-off .cep-num { background: #9ca3af; }
    .cep-move { width: 24px; height: 24px; border-radius: 6px; border: 1px solid #e5e7eb; background: #fff; color: #4b5563; font-size: 9px; cursor: pointer; }
    .cep-move:hover:not(:disabled) { background: #f3f4f6; }
    .cep-move:disabled { opacity: .35; cursor: default; }
    .cep-row-off td { background: #fafafa; }
    .cep-flow { column-gap: 22px; }
</style>
