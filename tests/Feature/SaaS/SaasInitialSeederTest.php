<?php

namespace Tests\Feature\SaaS;

use App\Models\LandingFaq;
use App\Models\LandingFeature;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\SystemSetting;
use Database\Seeders\SaasInitialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The seeder ships with a SaaS install and runs on every `db:seed`.
 * It must be idempotent: re-running after SuperAdmin has tweaked a
 * plan price or hero title should not overwrite those edits, and
 * duplicate rows must not appear.
 */
class SaasInitialSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_expected_row_counts(): void
    {
        $this->seed(SaasInitialSeeder::class);

        $this->assertEquals(3, Plan::count());
        $this->assertEquals(19, PlanFeature::count()); // 5 + 7 + 7 across tiers
        $this->assertEquals(6, LandingFeature::count());
        $this->assertEquals(6, LandingFaq::count());
        $this->assertGreaterThanOrEqual(26, SystemSetting::count());
    }

    public function test_seeder_is_idempotent_row_counts_stay_same(): void
    {
        $this->seed(SaasInitialSeeder::class);
        $beforeRows = [
            'plans'            => Plan::count(),
            'plan_features'    => PlanFeature::count(),
            'landing_features' => LandingFeature::count(),
            'landing_faqs'     => LandingFaq::count(),
        ];

        $this->seed(SaasInitialSeeder::class);
        $afterRows = [
            'plans'            => Plan::count(),
            'plan_features'    => PlanFeature::count(),
            'landing_features' => LandingFeature::count(),
            'landing_faqs'     => LandingFaq::count(),
        ];

        $this->assertEquals($beforeRows, $afterRows);
    }

    public function test_seeder_preserves_superadmin_edits_to_settings(): void
    {
        $this->seed(SaasInitialSeeder::class);

        // SuperAdmin customises a setting.
        SystemSetting::set('app_name', 'الجهة المخصّصة', 'general');
        $this->assertEquals('الجهة المخصّصة', SystemSetting::get('app_name'));

        // Re-seeding should NOT stomp the custom value.
        $this->seed(SaasInitialSeeder::class);
        $this->assertEquals('الجهة المخصّصة', SystemSetting::get('app_name'));
    }

    public function test_seeder_resets_plan_features_wholesale_on_reseed(): void
    {
        // The seeder deliberately replaces plan_features wholesale so
        // updates to the seeder (e.g. adding a new feature to Pro)
        // propagate. This test locks that behavior so a future
        // "preserve features" change gets caught.
        $this->seed(SaasInitialSeeder::class);
        $pro = Plan::where('slug', 'pro')->first();

        // Add a custom feature via the SuperAdmin UI.
        $pro->planFeatures()->create([
            'feature' => 'ميزة مخصّصة', 'included' => true, 'sort_order' => 99,
        ]);
        $this->assertDatabaseHas('plan_features', ['feature' => 'ميزة مخصّصة']);

        // Re-seeding wipes and rewrites — this is documented behavior.
        $this->seed(SaasInitialSeeder::class);
        $this->assertDatabaseMissing('plan_features', ['feature' => 'ميزة مخصّصة']);
    }
}
