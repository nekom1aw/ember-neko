<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_the_map_page_exposes_locations(): void
    {
        $locationId = DB::table('titik_lokasi')->insertGetId([
            'desa' => 'Desa Contoh',
            'latitude' => -2.56422,
            'longitude' => 102.77008,
            'confidence' => 'high',
        ]);

        $response = $this->get('/map');

        $response
            ->assertOk()
            ->assertSee('id="ember-map"', false)
            ->assertSee('-2.56422', false)
            ->assertSee('102.77008', false)
            ->assertSee('detail_url', false)
            ->assertSee('locations\\/1?lang=id', false)
            ->assertSee('id="map-detail-panel"', false)
            ->assertSee('id="map-detail-close"', false)
            ->assertSee('id="map-drilldown-control"', false)
            ->assertSee('id="map-boundary-breadcrumb"', false)
            ->assertSee('Klik provinsi untuk melihat kabupaten/kota.')
            ->assertSee('Buka detail lengkap')
            ->assertSee('h-[calc(100vh-6.5rem)]', false)
            ->assertDontSee('<footer', false);

        $this->get(route('user.locations.show', ['id' => $locationId, 'lang' => 'id']))
            ->assertOk()
            ->assertSee('Desa Contoh')
            ->assertSee('Confidence')
            ->assertSee('Tinggi')
            ->assertSee('id="location-detail-map"', false)
            ->assertSee('Buka di OpenStreetMap');
    }

    public function test_the_public_dashboard_supports_english_and_content_navigation(): void
    {
        $this->get('/?lang=en')
            ->assertOk()
            ->assertSee('images/ember-hero-v2.jpg', false)
            ->assertSee('Early Monitoring for Burning Environment Response')
            ->assertSee('Open interactive map')
            ->assertSee('Methodology')
            ->assertSee(route('user.about', ['lang' => 'en']), false)
            ->assertSee(route('user.team', ['lang' => 'en']), false)
            ->assertSee('id="back-to-top"', false)
            ->assertSee('header class="sticky', false)
            ->assertSee('Recently added data');
    }

    public function test_public_search_finds_a_location_and_has_no_cms_button(): void
    {
        DB::table('titik_lokasi')->insert([
            'desa' => 'Suka Maju',
            'kecamatan' => 'Contoh',
            'latitude' => -2.92494,
            'longitude' => 104.68752,
        ]);

        $this->get('/search?q=Suka+Maju&lang=id')
            ->assertOk()
            ->assertSee('Suka Maju')
            ->assertSee('Hasil pencarian')
            ->assertDontSee('>CMS<', false);
    }

    public function test_map_year_filter_uses_the_available_date_range(): void
    {
        DB::table('titik_lokasi')->insert([
            [
                'latitude' => -2.56422,
                'longitude' => 102.77008,
                'date' => '2022-06-15',
            ],
            [
                'latitude' => 4.90892,
                'longitude' => 97.47369,
                'date' => '2024-09-20',
            ],
        ]);

        $this->get('/map?lang=id')
            ->assertOk()
            ->assertSee('id="map-year-range"', false)
            ->assertSee('data-year-values="2022,2024"', false)
            ->assertSee('>Semua<', false)
            ->assertSee('2 lokasi');
    }

    public function test_public_statistics_groups_location_statuses_by_year(): void
    {
        DB::table('titik_lokasi')->insert([
            ['latitude' => -2.5, 'longitude' => 102.7, 'date' => '2024-02-01', 'confidence' => 'high'],
            ['latitude' => -2.6, 'longitude' => 102.8, 'date' => '2024-03-01', 'confidence' => 'low'],
            ['latitude' => -2.7, 'longitude' => 102.9, 'date' => '2025-04-01', 'confidence' => null],
        ]);

        $this->get(route('user.statistics', ['lang' => 'id']))
            ->assertOk()
            ->assertSee('Statistik Tahunan')
            ->assertSee('2024')
            ->assertSee('2025')
            ->assertSee('Tinggi')
            ->assertSee('Belum dinilai');

        $this->get(route('user.statistics', ['lang' => 'en']))
            ->assertOk()
            ->assertSee('Annual Statistics')
            ->assertSee('Unrated');
    }
}
