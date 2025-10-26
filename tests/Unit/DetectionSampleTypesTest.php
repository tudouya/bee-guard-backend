<?php

namespace Tests\Unit;

use App\Models\Detection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetectionSampleTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_detection_stores_multiple_sample_types(): void
    {
        $detection = Detection::query()->create([
            'sample_no' => 'SAMPLE-001',
            'sample_types' => ['adult_bee', 'other'],
        ]);

        $fresh = $detection->fresh();

        $this->assertSame(['adult_bee', 'other'], $fresh->sample_types);
        $this->assertSame(['成蜂', '其他'], $fresh->sample_type_labels);
    }
}
