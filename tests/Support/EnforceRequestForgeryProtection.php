<?php

namespace Tests\Support;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

/** Exercise the real protection while keeping the testing environment and SQLite guard. */
class EnforceRequestForgeryProtection extends PreventRequestForgery
{
    protected function runningUnitTests()
    {
        return false;
    }
}
