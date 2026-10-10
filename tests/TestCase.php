<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // CI gak build aset frontend — fake Vite biar view tanpa manifest gak meledak
        $this->withoutVite();
    }
}
