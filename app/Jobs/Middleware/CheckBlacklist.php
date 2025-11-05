<?php

namespace App\Jobs\Middleware;

use App\Models\BlacklistedNumber;
use Illuminate\Support\Facades\Log;

class CheckBlacklist
{
    public function __construct(private string $mobile) {}

    public function handle($job, $next)
    {
        if (BlacklistedNumber::isBlacklisted($this->mobile)) {
            Log::info("SMS blocked to blacklisted number: {$this->mobile}");

            // Delete job without executing
            $job->delete();

            return;
        }

        $next($job);
    }
}
