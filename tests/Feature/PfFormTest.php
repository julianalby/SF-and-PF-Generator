<?php

namespace Tests\Feature;

use App\Models\PfRecord;
use Tests\TestCase;

class PfFormTest extends TestCase
{
    public function test_form_shows_all_four_inputs(): void
    {
        $this->actingAs($this->makeUser())->get('/pf/create')
            ->assertOk()
            ->assertSee('Generate Number PF')
            ->assertSee('Project Name')
            ->assertSee('SF Number')
            ->assertSee('VNID')
            ->assertSee('Customer Name')
            ->assertSee('name="submission_token"', false)
            ->assertDontSee('name="pf_number"', false)
            ->assertDontSee('name="user"', false);
    }

    public function test_first_pf_is_26090138_and_each_next_one_increments_by_exactly_one(): void
    {
        $user = $this->makeUser();
        $numbers = [];

        foreach (range(1, 4) as $ignored) {
            $this->actingAs($user)->post('/pf', $this->pfPayload());
            $numbers[] = PfRecord::latest('id')->value('pf_number');
        }

        $this->assertSame([26090138, 26090139, 26090140, 26090141], $numbers);
        $this->assertSame(26090142, $this->nextNumber('PF'));
    }

    public function test_only_project_name_is_required(): void
    {
        $this->actingAs($this->makeUser('Windy'))->post('/pf', $this->pfPayload())
            ->assertSessionHasNoErrors();

        $record = PfRecord::sole();
        $this->assertSame('Fiber Expansion Phase 1', $record->project_name);
        $this->assertNull($record->sf_number);
        $this->assertNull($record->vnid);
        $this->assertNull($record->customer_name);
        $this->assertSame('Windy', $record->user);
    }

    public function test_optional_fields_left_blank_are_stored_as_null(): void
    {
        $this->actingAs($this->makeUser())->post('/pf', $this->pfPayload(['sf_number' => '', 'vnid' => '  ', 'customer_name' => '']))
            ->assertSessionHasNoErrors();

        $record = PfRecord::sole();
        $this->assertNull($record->sf_number);
        $this->assertNull($record->vnid);
        $this->assertNull($record->customer_name);
    }

    public function test_optional_fields_are_stored_when_given(): void
    {
        $this->actingAs($this->makeUser())->post('/pf', $this->pfPayload([
            'sf_number' => '26090119',
            'vnid' => 'VN000123',
            'customer_name' => 'PT Contoh Jaya',
        ]));

        $record = PfRecord::sole();
        $this->assertSame('26090119', $record->sf_number);
        $this->assertSame('VN000123', $record->vnid);
        $this->assertSame('PT Contoh Jaya', $record->customer_name);
    }

    public function test_project_name_is_required_on_the_server(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/pf', $this->pfPayload(['project_name' => '']))
            ->assertSessionHasErrors('project_name');
        $this->actingAs($user)->post('/pf', $this->pfPayload(['project_name' => '   ']))
            ->assertSessionHasErrors('project_name');
        $this->actingAs($user)->post('/pf', array_diff_key($this->pfPayload(), ['project_name' => true]))
            ->assertSessionHasErrors('project_name');

        $this->assertSame(0, PfRecord::count());
        $this->assertSame(26090138, $this->nextNumber('PF'), 'a rejected form must not consume a number');
    }

    public function test_validation_message_names_the_field(): void
    {
        $this->actingAs($this->makeUser())->from('/pf/create')->followingRedirects()
            ->post('/pf', $this->pfPayload(['project_name' => '']))
            ->assertSee('The Project Name field is required.');
    }

    public function test_overlong_values_are_rejected(): void
    {
        $this->actingAs($this->makeUser())->post('/pf', $this->pfPayload([
            'project_name' => str_repeat('a', 256),
            'sf_number' => str_repeat('1', 101),
            'vnid' => str_repeat('b', 101),
            'customer_name' => str_repeat('c', 256),
        ]))->assertSessionHasErrors(['project_name', 'sf_number', 'vnid', 'customer_name']);

        $this->assertSame(0, PfRecord::count());
    }

    public function test_user_and_number_can_never_be_supplied_by_the_client(): void
    {
        $this->actingAs($this->makeUser('Windy'))->post('/pf', $this->pfPayload([
            'user' => 'Finance',
            'pf_number' => 1,
            'id' => 999,
        ]));

        $record = PfRecord::sole();
        $this->assertSame('Windy', $record->user);
        $this->assertSame(26090138, $record->pf_number);
        $this->assertSame(1, $record->id);
    }

    public function test_success_page_shows_the_generated_number(): void
    {
        $this->actingAs($this->makeUser())->followingRedirects()->post('/pf', $this->pfPayload())
            ->assertOk()
            ->assertSeeText('Project Form created successfully.')
            ->assertSeeText('PF No.: 26090138');
    }

    public function test_submitting_the_same_form_twice_creates_one_record_and_one_number(): void
    {
        $user = $this->makeUser();
        $payload = $this->pfPayload();

        $this->actingAs($user)->post('/pf', $payload);
        $this->actingAs($user)->post('/pf', $payload)->assertRedirect(route('pf.show', PfRecord::first()));

        $this->assertSame(1, PfRecord::count());
        $this->assertSame(26090139, $this->nextNumber('PF'));
    }

    public function test_missing_or_malformed_submission_token_is_rejected(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/pf', $this->pfPayload(['submission_token' => '123']))
            ->assertSessionHasErrors('submission_token');
        $this->actingAs($user)->post('/pf', array_diff_key($this->pfPayload(), ['submission_token' => true]))
            ->assertSessionHasErrors('submission_token');

        $this->assertSame(0, PfRecord::count());
    }

    public function test_the_sf_number_field_is_only_a_reference_and_need_not_exist(): void
    {
        $this->actingAs($this->makeUser())->post('/pf', $this->pfPayload(['sf_number' => '20240001']))
            ->assertSessionHasNoErrors();

        $this->assertSame('20240001', PfRecord::sole()->sf_number);
        $this->assertSame(26090139, $this->nextNumber('PF'));
        $this->assertSame(26090119, $this->nextNumber('SF'), 'the SF reference must not touch the SF sequence');
    }

    public function test_result_page_is_visible_to_its_owner_and_admins_only(): void
    {
        $owner = $this->makeUser('Windy');
        $this->actingAs($owner)->post('/pf', $this->pfPayload());
        $record = PfRecord::sole();

        $this->actingAs($owner)->get(route('pf.show', $record))->assertOk()->assertSeeText('PF No.: 26090138');
        $this->actingAs($this->makeUser('Alida'))->get(route('pf.show', $record))->assertForbidden();
        $this->actingAs($this->makeAdmin())->get(route('pf.show', $record))->assertOk();
    }

    public function test_user_entered_html_is_escaped_everywhere(): void
    {
        $payload = '<img src=x onerror=alert(1)>';

        $this->actingAs($this->makeUser())->followingRedirects()
            ->post('/pf', $this->pfPayload(['project_name' => $payload, 'customer_name' => $payload, 'sf_number' => $payload, 'vnid' => $payload]))
            ->assertOk()
            ->assertDontSee($payload, false)
            ->assertSee(e($payload), false);

        $this->actingAs($this->makeAdmin())->get('/admin/pf-records')
            ->assertOk()
            ->assertDontSee($payload, false)
            ->assertSee(e($payload), false);
    }
}
