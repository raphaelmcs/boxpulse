<?php

namespace Tests\Unit;

use App\Timers\CompileTimer;
use PHPUnit\Framework\TestCase;

class CompileTimerTest extends TestCase
{
    public function test_legacy_seconds_compile_to_the_shared_millisecond_contract(): void
    {
        foreach (json_decode(file_get_contents(__DIR__.'/../fixtures/timer-configurations.json'), true) as $case) {
            $config = (new CompileTimer)->compile($case['block']);
            $this->assertSame($case['compiled'], $config);
            foreach ($case['expected'] as $key => $value) {
                $this->assertSame($value, $config[$key], $case['name'].': '.$key);
            }
        }
    }
}
