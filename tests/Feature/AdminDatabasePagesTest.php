<?php

namespace Tests\Feature;

use App\Models\PfRecord;
use App\Models\SfRecord;
use Tests\TestCase;

class AdminDatabasePagesTest extends TestCase
{
    public function test_sf_database_has_the_specified_columns_and_shows_the_records(): void
    {
        $user = $this->makeUser('Windy');
        $this->actingAs($user)->post('/sf', $this->sfPayload(['vnid' => 'VN-A', 'customer_name' => 'Alpha', 'service' => 'Broadband']));
        $this->actingAs($user)->post('/sf', $this->sfPayload(['vnid' => 'VN-B', 'customer_name' => 'Beta', 'service' => 'Metro']));

        $this->actingAs($this->makeAdmin())->get('/admin/sf-records')
            ->assertOk()
            ->assertSeeInOrder(['No', 'Create Date', 'User', 'SF Number', 'VNID', 'Customer Name', 'Service'])
            ->assertSeeInOrder(['26090119', 'VN-A', 'Alpha', 'Broadband'])
            ->assertSeeInOrder(['26090120', 'VN-B', 'Beta', 'Metro'])
            ->assertSee('Windy')
            ->assertSee('Total: 2 record(s)');
    }

    public function test_pf_database_has_the_specified_columns_and_shows_the_records(): void
    {
        $user = $this->makeUser('Windy');
        $this->actingAs($user)->post('/pf', $this->pfPayload(['project_name' => 'Project X', 'sf_number' => '26090119', 'vnid' => 'VN-A', 'customer_name' => 'Alpha']));
        $this->actingAs($user)->post('/pf', $this->pfPayload(['project_name' => 'Project Y']));

        $this->actingAs($this->makeAdmin())->get('/admin/pf-records')
            ->assertOk()
            ->assertSeeInOrder(['No', 'Create Date', 'User', 'PF Number', 'Project Name', 'SF Number', 'VNID', 'Customer Name'])
            ->assertSeeInOrder(['26090138', 'Project X', '26090119', 'VN-A', 'Alpha'])
            ->assertSeeInOrder(['26090139', 'Project Y'])
            ->assertSee('Total: 2 record(s)');
    }

    public function test_the_no_column_starts_at_one_for_each_table_independently(): void
    {
        $user = $this->makeUser();
        foreach (range(1, 3) as $ignored) {
            $this->actingAs($user)->post('/sf', $this->sfPayload());
        }
        foreach (range(1, 2) as $ignored) {
            $this->actingAs($user)->post('/pf', $this->pfPayload());
        }

        $admin = $this->makeAdmin();

        $sf = $this->actingAs($admin)->get('/admin/sf-records')->viewData('records');
        $this->assertSame([1, 2, 3], $sf->pluck('id')->all());
        $this->assertSame([26090119, 26090120, 26090121], $sf->pluck('sf_number')->all());

        $pf = $this->actingAs($admin)->get('/admin/pf-records')->viewData('records');
        $this->assertSame([1, 2], $pf->pluck('id')->all());
        $this->assertSame([26090138, 26090139], $pf->pluck('pf_number')->all());
    }

    public function test_the_user_column_shows_who_created_each_record(): void
    {
        $this->actingAs($this->makeUser('Windy'))->post('/sf', $this->sfPayload());
        $this->actingAs($this->makeUser('Alida'))->post('/sf', $this->sfPayload());

        $this->assertSame(['Windy', 'Alida'], SfRecord::orderBy('id')->pluck('user')->all());
    }

    public function test_pagination_and_sort_order(): void
    {
        config(['sfpf.per_page' => 2]);
        SfRecord::factory()->count(5)->create();
        $admin = $this->makeAdmin();

        $this->assertSame([1, 2], $this->actingAs($admin)->get('/admin/sf-records')->viewData('records')->pluck('id')->all());
        $this->assertSame([5], $this->actingAs($admin)->get('/admin/sf-records?page=3')->viewData('records')->pluck('id')->all());
        $this->assertSame([5, 4], $this->actingAs($admin)->get('/admin/sf-records?order=desc')->viewData('records')->pluck('id')->all());
        $this->assertSame([1, 2], $this->actingAs($admin)->get('/admin/sf-records?order=bogus')->viewData('records')->pluck('id')->all());

        $this->actingAs($admin)->get('/admin/sf-records')
            ->assertSee('Showing 1&ndash;2 of 5', false)
            ->assertSee('rel="next"', false);
    }

    public function test_empty_databases_show_a_friendly_message(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get('/admin/sf-records')->assertOk()->assertSee('No SF records yet.');
        $this->actingAs($admin)->get('/admin/pf-records')->assertOk()->assertSee('No PF records yet.');
    }

    public function test_pf_pagination_works_too(): void
    {
        config(['sfpf.per_page' => 2]);
        PfRecord::factory()->count(3)->create();

        $records = $this->actingAs($this->makeAdmin())->get('/admin/pf-records?page=2')->viewData('records');

        $this->assertSame([3], $records->pluck('id')->all());
    }
}
