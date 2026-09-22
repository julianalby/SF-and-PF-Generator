<?php

namespace Tests\Feature;

use App\Models\NumberSequence;
use App\Models\PfRecord;
use App\Models\SfRecord;
use App\Services\FormRecordService;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class SfFormTest extends TestCase
{
    public function test_form_shows_the_three_required_inputs(): void
    {
        $this->actingAs($this->makeUser())->get('/sf/create')
            ->assertOk()
            ->assertSee('Generate Number SF')
            ->assertSee('VNID')
            ->assertSee('Customer Name')
            ->assertSee('Service')
            ->assertSee('name="submission_token"', false)
            ->assertDontSee('name="sf_number"', false)
            ->assertDontSee('name="user"', false);
    }

    public function test_first_sf_is_26090119_and_each_next_one_increments_by_exactly_one(): void
    {
        $user = $this->makeUser('Windy');
        $numbers = [];

        foreach (range(1, 4) as $ignored) {
            $this->actingAs($user)->post('/sf', $this->sfPayload());
            $numbers[] = SfRecord::latest('id')->value('sf_number');
        }

        $this->assertSame([26090119, 26090120, 26090121, 26090122], $numbers);
        $this->assertSame(26090123, $this->nextNumber('SF'));
    }

    public function test_success_page_shows_the_generated_number(): void
    {
        $this->actingAs($this->makeUser())->followingRedirects()->post('/sf', $this->sfPayload())
            ->assertOk()
            ->assertSeeText('Service Form created successfully.')
            ->assertSeeText('SF No.: 26090119');
    }

    public function test_result_page_can_be_reloaded_without_re_submitting(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->post('/sf', $this->sfPayload())->assertRedirect(route('sf.show', 1));

        $this->get(route('sf.show', 1))->assertOk()->assertSeeText('SF No.: 26090119');
        $this->get(route('sf.show', 1))->assertOk()->assertSeeText('SF No.: 26090119');

        $this->assertSame(1, SfRecord::count());
    }

    public function test_record_stores_the_form_values_and_the_logged_in_username(): void
    {
        $this->actingAs($this->makeUser('Windy'))->post('/sf', $this->sfPayload());

        $record = SfRecord::sole();
        $this->assertSame('Windy', $record->user);
        $this->assertSame('VN000123', $record->vnid);
        $this->assertSame('PT Contoh Jaya', $record->customer_name);
        $this->assertSame('Dedicated Internet 100 Mbps', $record->service);
        $this->assertNotNull($record->created_at);
    }

    public function test_user_and_number_can_never_be_supplied_by_the_client(): void
    {
        $this->actingAs($this->makeUser('Windy'))->post('/sf', $this->sfPayload([
            'user' => 'Finance',
            'sf_number' => 1,
            'id' => 999,
            'created_at' => '2000-01-01 00:00:00',
        ]));

        $record = SfRecord::sole();
        $this->assertSame('Windy', $record->user);
        $this->assertSame(26090119, $record->sf_number);
        $this->assertSame(1, $record->id);
        $this->assertGreaterThan(2000, $record->created_at->year);
    }

    public function test_every_required_field_is_validated_on_the_server(): void
    {
        $user = $this->makeUser();

        foreach (['vnid', 'customer_name', 'service'] as $field) {
            $this->actingAs($user)->post('/sf', $this->sfPayload([$field => '']))
                ->assertSessionHasErrors($field);

            $this->actingAs($user)->post('/sf', array_diff_key($this->sfPayload(), [$field => true]))
                ->assertSessionHasErrors($field);
        }

        $this->assertSame(0, SfRecord::count());
        $this->assertSame(26090119, $this->nextNumber('SF'), 'a rejected form must not consume a number');
    }

    public function test_validation_message_names_the_field(): void
    {
        $this->actingAs($this->makeUser())->from('/sf/create')->followingRedirects()
            ->post('/sf', $this->sfPayload(['customer_name' => '']))
            ->assertSee('The Customer Name field is required.')
            ->assertSee('Generate Number SF');
    }

    public function test_whitespace_only_values_count_as_missing(): void
    {
        $this->actingAs($this->makeUser())->post('/sf', $this->sfPayload(['vnid' => '   ', 'service' => "\t "]))
            ->assertSessionHasErrors(['vnid', 'service']);

        $this->assertSame(0, SfRecord::count());
    }

    public function test_values_are_trimmed(): void
    {
        $this->actingAs($this->makeUser())->post('/sf', $this->sfPayload(['vnid' => '  VN9  ']));

        $this->assertSame('VN9', SfRecord::sole()->vnid);
    }

    public function test_overlong_values_are_rejected(): void
    {
        $this->actingAs($this->makeUser())->post('/sf', $this->sfPayload([
            'vnid' => str_repeat('a', 101),
            'customer_name' => str_repeat('b', 256),
            'service' => str_repeat('c', 256),
        ]))->assertSessionHasErrors(['vnid', 'customer_name', 'service']);

        $this->assertSame(0, SfRecord::count());
    }

    public function test_missing_or_malformed_submission_token_is_rejected(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/sf', $this->sfPayload(['submission_token' => 'not-a-uuid']))
            ->assertSessionHasErrors('submission_token');
        $this->actingAs($user)->post('/sf', array_diff_key($this->sfPayload(), ['submission_token' => true]))
            ->assertSessionHasErrors('submission_token');

        $this->assertSame(0, SfRecord::count());
    }

    public function test_submitting_the_same_form_twice_creates_one_record_and_one_number(): void
    {
        $user = $this->makeUser();
        $payload = $this->sfPayload();

        $first = $this->actingAs($user)->post('/sf', $payload);
        $second = $this->actingAs($user)->post('/sf', $payload);

        $this->assertSame(1, SfRecord::count());
        $this->assertSame(26090120, $this->nextNumber('SF'));
        $first->assertRedirect(route('sf.show', SfRecord::first()));
        $second->assertRedirect(route('sf.show', SfRecord::first()));
    }

    public function test_two_different_forms_get_two_different_numbers(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/sf', $this->sfPayload());
        $this->actingAs($user)->post('/sf', $this->sfPayload());

        $this->assertSame([26090119, 26090120], SfRecord::orderBy('id')->pluck('sf_number')->all());
    }

    public function test_someone_elses_token_is_refused(): void
    {
        $payload = $this->sfPayload();

        $this->actingAs($this->makeUser('Windy'))->post('/sf', $payload);
        $this->actingAs($this->makeUser('Alida'))->post('/sf', $payload)
            ->assertSessionHasErrors('submission_token');

        $this->assertSame(1, SfRecord::count());
        $this->assertSame('Windy', SfRecord::sole()->user);
    }

    public function test_sf_and_pf_sequences_are_completely_independent(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/sf', $this->sfPayload());
        $this->assertSame(26090138, $this->nextNumber('PF'), 'creating an SF must not touch the PF sequence');

        $this->actingAs($user)->post('/pf', $this->pfPayload());
        $this->assertSame(26090120, $this->nextNumber('SF'), 'creating a PF must not touch the SF sequence');

        $this->assertSame(26090119, SfRecord::sole()->sf_number);
        $this->assertSame(26090138, PfRecord::sole()->pf_number);
    }

    public function test_a_save_that_fails_does_not_consume_a_number(): void
    {
        $user = $this->makeUser();

        SfRecord::creating(function () {
            throw new RuntimeException('simulated failure while saving');
        });

        try {
            app(FormRecordService::class)->createSf($user, ['vnid' => 'V', 'customer_name' => 'C', 'service' => 'S'], (string) Str::uuid());
            $this->fail('the simulated failure should have propagated');
        } catch (RuntimeException) {
            // expected
        }

        SfRecord::flushEventListeners();

        $this->assertSame(0, SfRecord::count());
        $this->assertSame(26090119, $this->nextNumber('SF'), 'the rolled-back number must be handed out again');

        $this->actingAs($user)->post('/sf', $this->sfPayload());
        $this->assertSame(26090119, SfRecord::sole()->sf_number);
    }

    public function test_the_counter_is_authoritative_not_max_plus_one(): void
    {
        SfRecord::factory()->create(['sf_number' => 26090500]);
        NumberSequence::where('key', 'SF')->update(['next_number' => 26090119]);

        $this->actingAs($this->makeUser())->post('/sf', $this->sfPayload());

        $this->assertSame(26090119, SfRecord::where('id', '>', 1)->sole()->sf_number);
    }

    public function test_result_page_is_visible_to_its_owner_and_admins_only(): void
    {
        $owner = $this->makeUser('Windy');
        $this->actingAs($owner)->post('/sf', $this->sfPayload());
        $record = SfRecord::sole();

        $this->actingAs($owner)->get(route('sf.show', $record))->assertOk()->assertSeeText('SF No.: 26090119');
        $this->actingAs($this->makeUser('Alida'))->get(route('sf.show', $record))->assertForbidden();
        $this->actingAs($this->makeAdmin())->get(route('sf.show', $record))->assertOk();
    }

    public function test_user_entered_html_is_escaped_everywhere(): void
    {
        $payload = '<script>alert("xss")</script>';
        $user = $this->makeUser('Windy');

        $this->actingAs($user)->followingRedirects()
            ->post('/sf', $this->sfPayload(['customer_name' => $payload, 'service' => $payload, 'vnid' => $payload]))
            ->assertOk()
            ->assertDontSee($payload, false)
            ->assertSee(e($payload), false);

        $this->actingAs($this->makeAdmin())->get('/admin/sf-records')
            ->assertOk()
            ->assertDontSee($payload, false)
            ->assertSee(e($payload), false);
    }

    public function test_non_existing_result_page_is_a_404(): void
    {
        $this->actingAs($this->makeUser())->get('/sf/12345')->assertNotFound();
        $this->actingAs($this->makeUser('Other'))->get('/sf/abc')->assertNotFound();
    }
}
