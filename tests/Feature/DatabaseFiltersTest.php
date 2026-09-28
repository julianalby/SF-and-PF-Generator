<?php

namespace Tests\Feature;

use App\Models\PfRecord;
use App\Models\SfRecord;
use Tests\TestCase;

class DatabaseFiltersTest extends TestCase
{
    private function ids(string $url): array
    {
        return $this->actingAs($this->makeAdmin())->get($url)->assertOk()->viewData('records')->pluck('id')->all();
    }

    public function test_the_filter_bar_is_rendered_above_the_tables(): void
    {
        $admin = $this->makeAdmin();
        $this->makeUser('Windy');
        $this->makeUser('Alida');

        foreach (['sf' => 'SF', 'pf' => 'PF'] as $path => $label) {
            $this->actingAs($admin)->get("/admin/{$path}-records")
                ->assertOk()
                ->assertSee('name="date_from"', false)
                ->assertSee('name="date_to"', false)
                ->assertSee('placeholder="YYYY-MM-DD"', false)
                ->assertSee('data-date-picker', false)
                ->assertSee('name="user"', false)
                ->assertSee('name="number"', false)
                ->assertSee("Search {$label} Number")
                ->assertSeeInOrder(['All users', 'Alida', 'Finance', 'Windy'])
                ->assertSeeInOrder(['filter-bar', 'class="data"'], false);
        }
    }

    public function test_sf_date_range_is_inclusive_on_both_ends(): void
    {
        SfRecord::factory()->create(['created_at' => '2026-09-09 23:59:59']); // 1
        SfRecord::factory()->create(['created_at' => '2026-09-10 00:00:00']); // 2
        SfRecord::factory()->create(['created_at' => '2026-09-12 23:59:59']); // 3
        SfRecord::factory()->create(['created_at' => '2026-09-13 00:00:00']); // 4

        $this->assertSame([2, 3], $this->ids('/admin/sf-records?date_from=2026-09-10&date_to=2026-09-12'));
        $this->assertSame([2, 3, 4], $this->ids('/admin/sf-records?date_from=2026-09-10'));
        $this->assertSame([1, 2, 3], $this->ids('/admin/sf-records?date_to=2026-09-12'));
    }

    public function test_typed_dates_accept_day_first_and_slash_formats(): void
    {
        SfRecord::factory()->create(['created_at' => '2026-09-09 10:00:00']);
        SfRecord::factory()->create(['created_at' => '2026-09-10 10:00:00']);
        SfRecord::factory()->create(['created_at' => '2026-09-11 10:00:00']);

        $this->assertSame([2, 3], $this->ids('/admin/sf-records?date_from=10/09/2026'));
        $this->assertSame([1, 2], $this->ids('/admin/sf-records?date_to=2026/9/10'));
        $this->assertSame([2], $this->ids('/admin/sf-records?date_from=10-09-2026&date_to=10.09.2026'));
        $this->assertSame([1, 2, 3], $this->ids('/admin/sf-records?date_from=2026-02-31')); // impossible date: ignored
    }

    public function test_pf_date_range_works(): void
    {
        PfRecord::factory()->create(['created_at' => '2026-09-09 10:00:00']);
        PfRecord::factory()->create(['created_at' => '2026-09-10 10:00:00']);

        $this->assertSame([2], $this->ids('/admin/pf-records?date_from=2026-09-10&date_to=2026-09-10'));
    }

    public function test_user_filter_matches_exactly(): void
    {
        SfRecord::factory()->create(['user' => 'Windy']);
        SfRecord::factory()->create(['user' => 'Alida']);
        SfRecord::factory()->create(['user' => 'Windy']);
        PfRecord::factory()->create(['user' => 'Alida']);
        PfRecord::factory()->create(['user' => 'Windy']);

        $this->assertSame([1, 3], $this->ids('/admin/sf-records?user=Windy'));
        $this->assertSame([1], $this->ids('/admin/pf-records?user=Alida'));
        $this->assertSame([1, 2, 3], $this->ids('/admin/sf-records?user='));
    }

    public function test_number_search_is_a_partial_match(): void
    {
        SfRecord::factory()->create(['sf_number' => 26090119]);
        SfRecord::factory()->create(['sf_number' => 26090120]);
        SfRecord::factory()->create(['sf_number' => 26100119]);
        PfRecord::factory()->create(['pf_number' => 26090138]);
        PfRecord::factory()->create(['pf_number' => 26090139]);

        $this->assertSame([1], $this->ids('/admin/sf-records?number=26090119'));
        $this->assertSame([1, 2], $this->ids('/admin/sf-records?number=2609'));
        $this->assertSame([1, 3], $this->ids('/admin/sf-records?number=0119'));
        $this->assertSame([2], $this->ids('/admin/pf-records?number=139'));
        $this->assertSame([], $this->ids('/admin/sf-records?number=999'));
    }

    public function test_like_wildcards_typed_by_the_user_are_treated_literally(): void
    {
        SfRecord::factory()->create(['sf_number' => 26090119]);

        $this->assertSame([], $this->ids('/admin/sf-records?number=%25'));
        $this->assertSame([], $this->ids('/admin/sf-records?number=26_90119'));
    }

    public function test_filters_combine_and_survive_pagination_and_sorting(): void
    {
        config(['sfpf.per_page' => 2]);
        SfRecord::factory()->count(3)->create(['user' => 'Windy', 'created_at' => '2026-09-10 09:00:00']);
        SfRecord::factory()->create(['user' => 'Alida', 'created_at' => '2026-09-10 09:00:00']);
        SfRecord::factory()->create(['user' => 'Windy', 'created_at' => '2026-08-01 09:00:00']);

        $url = '/admin/sf-records?user=Windy&date_from=2026-09-01&date_to=2026-09-30';

        $this->assertSame([1, 2], $this->ids($url));
        $this->assertSame([3], $this->ids($url.'&page=2'));
        $this->assertSame([3, 2], $this->ids($url.'&order=desc'));

        $this->actingAs($this->makeAdmin())->get($url)
            ->assertSee('Total: 3 record(s)')
            ->assertSee('user=Windy', false); // pagination links keep the filters
    }

    public function test_invalid_input_is_ignored_and_reported_instead_of_breaking_the_page(): void
    {
        SfRecord::factory()->count(2)->create();
        $admin = $this->makeAdmin();

        $this->assertSame([1, 2], $this->ids('/admin/sf-records?date_from=not-a-date'));
        $this->assertSame([1, 2], $this->ids('/admin/sf-records?user[]=x&number[]=1'));

        $this->actingAs($admin)->get('/admin/sf-records?date_from=2026-09-12&date_to=2026-09-10')
            ->assertOk()
            ->assertSee('must not be before');
    }

    public function test_empty_result_with_filters_shows_a_matching_message(): void
    {
        SfRecord::factory()->create(['user' => 'Windy']);

        $this->actingAs($this->makeAdmin())->get('/admin/sf-records?user=Nobody')
            ->assertOk()
            ->assertSee('No SF records match the current filters.');
    }

    public function test_regular_users_still_cannot_reach_the_database_pages_with_filters(): void
    {
        $this->actingAs($this->makeUser())->get('/admin/sf-records?user=Windy')->assertForbidden();
        $this->actingAs($this->makeUser('Alida'))->get('/admin/pf-records?number=1')->assertForbidden();
    }
}
