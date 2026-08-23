<?php

namespace Tests\Ci4;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class HealthTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testHealthEndpoint(): void
    {
        $result = $this->get('/health');

        $result->assertStatus(200);
        $result->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        $result->assertJSONExact([
            'status'  => 'ok',
            'service' => 'ci4',
        ]);
    }
}
