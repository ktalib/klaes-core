<section class="analytics-summary" aria-labelledby="analytics-overview-heading">
    @php
        $analyticsPlaceholder = !empty($analytics['_placeholder']) || !empty($analytics['_fallback']);
    @endphp

    <div class="card-grid">
        <article class="summary-card">
            <header class="card-header">
                <span class="card-icon bg-blue-100 text-blue-600">
                    <i class="fas fa-layer-group"></i>
                </span>
                <h2 id="analytics-overview-heading" class="card-title">Overall Stats</h2>
            </header>
            <dl class="card-metrics">
                <div>
                    <dt>Total files</dt>
                    <dd data-metric="total-files">
                        {{ $analyticsPlaceholder ? 'Loading…' : number_format($analytics['total_files'] ?? 0) }}
                    </dd>
                </div>
                <div>
                    <dt>Matched files</dt>
                    <dd data-metric="matched-files">
                        {{ $analyticsPlaceholder ? 'Loading…' : number_format($analytics['matched_files'] ?? 0) }}
                    </dd>
                </div>
                <div>
                    <dt>Unmatched files</dt>
                    <dd data-metric="unmatched-files">
                        {{ $analyticsPlaceholder ? 'Loading…' : number_format($analytics['unmatched_files'] ?? 0) }}
                    </dd>
                </div>
                <div>
                    <dt>Matching rate</dt>
                    <dd data-metric="matching-percentage">
                        {{ $analyticsPlaceholder ? 'Loading…' : number_format($analytics['matching_percentage'] ?? 0, 2) . '%' }}
                    </dd>
                </div>
            </dl>
            <footer class="card-footer">
                <div class="footer-item">
                    <span class="label">Matches today</span>
                    <span class="value" data-metric="today-matches">
                        {{ $analyticsPlaceholder ? 'Loading…' : number_format($analytics['today_matches'] ?? 0) }}
                    </span>
                </div>
                <div class="footer-item">
                    <span class="label">Last match</span>
                    <span class="value" data-metric="last-match-time">
                        @if ($analyticsPlaceholder)
                            —
                        @elseif (!empty($analytics['last_match_time']))
                            {{ \Carbon\Carbon::parse($analytics['last_match_time'])->diffForHumans() }}
                        @else
                            —
                        @endif
                    </span>
                </div>
            </footer>
        </article>

        <article class="summary-card">
            <header class="card-header">
                <span class="card-icon bg-emerald-100 text-emerald-600">
                    <i class="fas fa-map-marked-alt"></i>
                </span>
                <h2 class="card-title">Land Use Breakdown</h2>
            </header>
            <div class="landuse-table-wrapper">
                <table class="landuse-table">
                    <thead>
                        <tr>
                            <th scope="col">Land use</th>
                            <th scope="col" class="numeric">Total</th>
                            <th scope="col" class="numeric">Share</th>
                        </tr>
                    </thead>
                    <tbody data-table="landuse">
                        @forelse ($landUseStats as $stat)
                            <tr>
                                <th scope="row">{{ $stat->landuse ?? 'Unknown' }}</th>
                                <td class="numeric">{{ number_format($stat->count ?? $stat->total_files ?? 0) }}</td>
                                <td class="numeric">{{ number_format($stat->percentage ?? $stat->match_percentage ?? 0, 2) }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="empty">No land use data available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        {{-- Active Groups card temporarily hidden --}}

        <article class="summary-card">
            <header class="card-header">
                <span class="card-icon bg-amber-100 text-amber-600">
                    <i class="fas fa-history"></i>
                </span>
                <h2 class="card-title">Recent Activity</h2>
            </header>
            <ul class="activity-list" data-list="recent-activity">
                @forelse ($recentActivity as $activity)
                    <li>
                        <div class="activity-meta">
                            <span class="file-number">{{ $activity->awaiting_fileno ?? '—' }}</span>
                            <span class="match-status {{ ($activity->mls_fileno ?? null) ? 'matched' : 'pending' }}">
                                {{ ($activity->mls_fileno ?? null) ? 'Matched' : 'Pending' }}
                            </span>
                        </div>
                        <div class="activity-details">
                            <span>MLS: {{ $activity->mls_fileno ?? 'N/A' }}</span>
                            <span>Group {{ $activity->group_number ?? '—' }} · Pos {{ $activity->position_in_group ?? '—' }}</span>
                            <span>{{ optional($activity->matched_at ? \Carbon\Carbon::parse($activity->matched_at) : null)?->diffForHumans() ?? '—' }}</span>
                        </div>
                    </li>
                @empty
                    <li class="empty">No recent matches recorded.</li>
                @endforelse
            </ul>
        </article>
    </div>
</section>
