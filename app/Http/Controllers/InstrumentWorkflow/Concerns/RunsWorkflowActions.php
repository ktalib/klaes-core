<?php

namespace App\Http\Controllers\InstrumentWorkflow\Concerns;

use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs a workflow action and turns the outcome into a response. Forms on the
 * workflow screens submit with fetch (Accept: application/json) and get JSON
 * back, so the page refreshes in place; a plain form post still gets a redirect
 * with a flash message. A WorkflowGuardException carries an officer-facing
 * reason and is shown as-is; anything else is logged and reported generically.
 */
trait RunsWorkflowActions
{
    protected function attempt(callable $action, string $success, ?string $redirectTo = null): Response
    {
        try {
            $result = $action();
        } catch (WorkflowGuardException $e) {
            return $this->actionFailed($e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Instrument workflow action failed', [
                'error' => $e->getMessage(),
                'at' => $e->getFile() . ':' . $e->getLine(),
            ]);

            return $this->actionFailed('The action could not be completed: ' . $e->getMessage());
        }

        return $this->actionSucceeded(is_string($result) ? $result : $success, $redirectTo);
    }

    /**
     * Success. With a redirect target the message is also flashed, so a script
     * that navigates there shows it; without one the script reloads the current
     * page's content in place.
     */
    protected function actionSucceeded(string $message, ?string $redirectTo = null): Response
    {
        if (request()->expectsJson()) {
            if ($redirectTo) {
                session()->flash('success', $message);
            }

            return response()->json(['success' => true, 'message' => $message, 'redirect' => $redirectTo]);
        }

        return ($redirectTo ? redirect($redirectTo) : back())->with('success', $message);
    }

    protected function actionFailed(string $message, int $status = 422): Response
    {
        if (request()->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], $status);
        }

        return back()->withInput()->with('error', $message);
    }
}

