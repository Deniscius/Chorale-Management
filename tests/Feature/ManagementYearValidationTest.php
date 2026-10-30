<?php

namespace Tests\Feature;

use App\Models\ManagementYear;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ManagementYearValidationTest extends TestCase
{
    public function test_civil_year_must_follow_calendar_dates(): void
    {
        $data = ManagementYear::validatedAttributes([
            'type' => 'civile', 'label' => '2027',
            'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31',
        ]);
        $this->assertSame('civile', $data['type']);
    }

    public function test_civil_year_rejects_a_pastoral_range(): void
    {
        $this->expectException(ValidationException::class);
        ManagementYear::validatedAttributes([
            'type' => 'civile', 'label' => '2026-2027',
            'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31',
        ]);
    }

    public function test_pastoral_year_requires_a_theme(): void
    {
        $this->expectException(ValidationException::class);
        ManagementYear::validatedAttributes([
            'type' => 'pastorale', 'label' => '2026-2027',
            'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31',
            'theme' => '',
        ]);
    }

    public function test_pastoral_year_accepts_custom_dates_and_theme(): void
    {
        $data = ManagementYear::validatedAttributes([
            'type' => 'pastorale', 'label' => '2026-2027',
            'starts_on' => '2026-10-01', 'ends_on' => '2027-09-30',
            'theme' => 'Vivre la fraternité',
        ]);
        $this->assertSame('Vivre la fraternité', $data['theme']);
    }
}
