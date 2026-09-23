<?php

namespace Tests;

use App\Services\FirebaseService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\NullFirebaseService;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // SAFETY NET: these tests run against the shared dev database (real users with real
        // device tokens) on a machine that has the real Firebase credentials. Every notification
        // service receives FirebaseService through the container, so binding a stub here makes it
        // impossible for ANY test to push to a real device. See Tests\Support\NullFirebaseService.
        $this->app->instance(FirebaseService::class, new NullFirebaseService());
    }
}
