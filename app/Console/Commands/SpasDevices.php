<?php

namespace App\Console\Commands;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * List and revoke the API tokens held by field devices.
 *
 * WHY REVOCATION IS THE CONTROL HERE
 * SPAS tokens deliberately do not expire (config/sanctum.php `expiration` is
 * null). A surveyor can be offline for days, and a token that quietly expired
 * mid-survey would lock them out of an app holding unsynced field work — the
 * exact failure the offline design exists to prevent.
 *
 * The trade is that a lost or stolen handset stays authorised until someone
 * revokes it, and every handset holds the full record list including owner
 * names and phone numbers. So revocation has to be quick and obvious, which is
 * what this command is for.
 *
 * Revoking is safe: the surveyor simply signs in again, and unsynced local work
 * is NOT discarded by the app on logout or on a 401.
 */
class SpasDevices extends Command
{
    protected $signature = 'spas:devices
                            {--user= : Filter by username, email or user id}
                            {--stale= : Only tokens unused for this many days}
                            {--revoke=* : Token id(s) to revoke}
                            {--revoke-user= : Revoke every token for this username, email or id}
                            {--revoke-stale= : Revoke every token unused for this many days}
                            {--ability=spas-mobile : Token ability to filter on; "any" for all}';

    protected $description = 'List or revoke SPAS field-device API tokens. Tokens do not expire, so revocation is how a lost handset is cut off.';

    public function handle(): int
    {
        $ability = $this->option('ability');

        $query = PersonalAccessToken::query()->orderByDesc('created_at');

        if ($ability && $ability !== 'any') {
            // `abilities` is a JSON array column; a LIKE is enough to filter and
            // avoids depending on JSON support across drivers.
            $query->where('abilities', 'like', '%"'.$ability.'"%');
        }

        if ($this->option('user')) {
            $user = $this->resolveUser($this->option('user'));

            if (! $user) {
                $this->error('No user matched "'.$this->option('user').'".');

                return self::FAILURE;
            }

            $query->where('tokenable_id', $user->id);
        }

        if ($this->option('stale')) {
            $cutoff = now()->subDays((int) $this->option('stale'));
            $query->where(function ($q) use ($cutoff) {
                $q->whereNull('last_used_at')->orWhere('last_used_at', '<', $cutoff);
            });
        }

        // --- revocation paths -------------------------------------------------

        if ($ids = $this->option('revoke')) {
            return $this->revokeByIds($ids);
        }

        if ($identifier = $this->option('revoke-user')) {
            return $this->revokeForUser($identifier, $ability);
        }

        if ($days = $this->option('revoke-stale')) {
            return $this->revokeStale((int) $days, $ability);
        }

        // --- listing ----------------------------------------------------------

        $tokens = $query->get();

        if ($tokens->isEmpty()) {
            $this->info('No matching tokens.');

            return self::SUCCESS;
        }

        $users = User::whereIn('id', $tokens->pluck('tokenable_id')->unique())->get()->keyBy('id');

        $this->table(
            ['id', 'user', 'device', 'abilities', 'last used', 'created'],
            $tokens->map(function ($token) use ($users) {
                $user = $users->get($token->tokenable_id);

                return [
                    $token->id,
                    $user ? ($user->username ?: $user->email) : '#'.$token->tokenable_id,
                    $token->name,
                    implode(',', $token->abilities ?? []),
                    $token->last_used_at
                        ? $token->last_used_at->diffForHumans()
                        : 'never',
                    optional($token->created_at)->format('Y-m-d'),
                ];
            })->all()
        );

        $never = $tokens->whereNull('last_used_at')->count();

        $this->newLine();
        $this->line($tokens->count().' token(s). '.$never.' never used.');
        $this->comment('Revoke with:  php artisan spas:devices --revoke=<id>');

        return self::SUCCESS;
    }

    private function resolveUser(string $identifier): ?User
    {
        return User::where('username', $identifier)
            ->orWhere('email', $identifier)
            ->when(is_numeric($identifier), fn ($q) => $q->orWhere('id', (int) $identifier))
            ->first();
    }

    private function revokeByIds(array $ids): int
    {
        $tokens = PersonalAccessToken::whereIn('id', $ids)->get();

        if ($tokens->isEmpty()) {
            $this->error('No tokens found with id(s): '.implode(', ', $ids));

            return self::FAILURE;
        }

        foreach ($tokens as $token) {
            $this->line("  revoking #{$token->id}  {$token->name}");
        }

        if (! $this->confirmRevocation($tokens->count())) {
            return self::SUCCESS;
        }

        $deleted = PersonalAccessToken::whereIn('id', $tokens->pluck('id'))->delete();
        $this->info("Revoked {$deleted} token(s). Those devices must sign in again; their unsynced work is untouched.");

        return self::SUCCESS;
    }

    private function revokeForUser(string $identifier, string $ability): int
    {
        $user = $this->resolveUser($identifier);

        if (! $user) {
            $this->error('No user matched "'.$identifier.'".');

            return self::FAILURE;
        }

        $query = PersonalAccessToken::where('tokenable_id', $user->id);

        if ($ability && $ability !== 'any') {
            $query->where('abilities', 'like', '%"'.$ability.'"%');
        }

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('That user holds no matching tokens.');

            return self::SUCCESS;
        }

        $this->line("  {$count} token(s) for ".($user->username ?: $user->email));

        if (! $this->confirmRevocation($count)) {
            return self::SUCCESS;
        }

        $query->delete();
        $this->info("Revoked {$count} token(s).");

        return self::SUCCESS;
    }

    private function revokeStale(int $days, string $ability): int
    {
        $cutoff = now()->subDays($days);

        $query = PersonalAccessToken::where(function ($q) use ($cutoff) {
            $q->whereNull('last_used_at')->orWhere('last_used_at', '<', $cutoff);
        });

        if ($ability && $ability !== 'any') {
            $query->where('abilities', 'like', '%"'.$ability.'"%');
        }

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info("No tokens unused for {$days} day(s).");

            return self::SUCCESS;
        }

        $this->warn("{$count} token(s) unused for {$days} day(s) or never used.");

        if (! $this->confirmRevocation($count)) {
            return self::SUCCESS;
        }

        $query->delete();
        $this->info("Revoked {$count} token(s).");

        return self::SUCCESS;
    }

    private function confirmRevocation(int $count): bool
    {
        // Non-interactive runs (cron, CI) proceed: the flags were explicit.
        if (! $this->input->isInteractive()) {
            return true;
        }

        return $this->confirm("Revoke {$count} token(s)?", false);
    }
}
