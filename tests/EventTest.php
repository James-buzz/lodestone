<?php

namespace Lodestone\Tests;

use Lodestone\Components\Button;
use Lodestone\Tests\Fixtures\Item;
use Lodestone\Tests\Fixtures\ItemsPage;
use LogicException;

/**
 * The event path is where Lodestone's security claims live: a button that isn't on the rebuilt page
 * can't be clicked, rows come through the table's own query, and hidden fields are dropped.
 */
final class EventTest extends TestCase
{
    public function test_hidden_button_is_404(): void
    {
        $this->post('/admin/items/_event/buttons.hidden')->assertNotFound();

        $this->assertSame([], ItemsPage::$calls);
    }

    public function test_disabled_header_button_gets_a_danger_toast_and_no_callback(): void
    {
        $this->from('/admin')->post('/admin/items/_event/buttons.locked')
            ->assertRedirect('/admin')
            ->assertSessionHas('lodestone.toast.tone', 'danger');

        $this->assertSame([], ItemsPage::$calls);
    }

    public function test_row_outside_the_tables_query_is_a_validation_error(): void
    {
        $archived = Item::create(['status' => 'archived']);

        $this->from('/admin')->post('/admin/items/_event/items.row.touch', ['ids' => [$archived->id]])
            ->assertRedirect('/admin')
            ->assertSessionHasErrors('ids');

        $this->assertSame([], ItemsPage::$calls);
    }

    public function test_row_failing_visible_gets_a_danger_toast_and_no_callback(): void
    {
        $closed = Item::create(['status' => 'closed']);

        $this->from('/admin')->post('/admin/items/_event/items.row.touch', ['ids' => [$closed->id]])
            ->assertRedirect('/admin')
            ->assertSessionHas('lodestone.toast.tone', 'danger');

        $this->assertSame([], ItemsPage::$calls);
    }

    public function test_row_passing_visible_runs_the_callback(): void
    {
        $open = Item::create(['status' => 'open']);

        $this->from('/admin')->post('/admin/items/_event/items.row.touch', ['ids' => [$open->id]])->assertRedirect('/admin');

        $this->assertSame([['touch', $open->id]], ItemsPage::$calls);
    }

    public function test_ui_visit_redirects_to_the_page_instead_of_back(): void
    {
        $this->from('/elsewhere')->post('/admin/items/_event/buttons.goto')->assertRedirect('/admin');
    }

    public function test_form_submit_drops_a_field_hidden_by_its_condition(): void
    {
        $this->from('/admin')->post('/admin/items/_event/form.note', ['reason' => 'weather', 'details' => 'should be dropped'])
            ->assertRedirect('/admin')
            ->assertSessionHasNoErrors();

        $this->assertSame([['note', ['reason' => 'weather']]], ItemsPage::$calls);
    }

    public function test_collection_parameter_on_a_row_button_throws(): void
    {
        $open = Item::create(['status' => 'open']);

        $this->withoutExceptionHandling();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('asks for Collection $items');

        $this->post('/admin/items/_event/items.row.many', ['ids' => [$open->id]]);
    }

    public function test_can_without_a_record_or_model_throws(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("can('create', Model::class)");

        Button::make('new')->can('create')->click(fn () => null)->isVisibleFor(null);
    }

    public function test_a_custom_nodes_targets_get_working_event_urls(): void
    {
        $card = collect($this->get('/admin/items', ['X-Lodestone-Modal' => '1'])->assertOk()->json('components'))->firstWhere('type', 'card');

        $this->assertStringEndsWith('/admin/items/_event/card.ping', $card['button']['url']);

        $this->from('/admin')->post($card['button']['url'])->assertRedirect('/admin');

        $this->assertSame(['ping'], ItemsPage::$calls);
    }
}
