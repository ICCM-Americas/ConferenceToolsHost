<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/** Feature tests for Host site settings (admin-editable nav links). */
#[TestDox('Host site settings (admin-editable nav links)')]
class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://example.com/my-link';

    /**
     * Each admin-editable nav-link setting: its form field name, its Setting
     * key, and its Setting accessor method.
     */
    public static function navLinks(): iterable
    {
        yield 'schedule link' => ['schedule_url', Setting::SCHEDULE_URL, 'scheduleUrl'];
        yield 'ICCM NAS link' => ['iccm_nas_url', Setting::ICCM_NAS_URL, 'iccmNasUrl'];
    }

    #[Test]
    #[DataProvider('navLinks')]
    #[TestDox('the $_dataName is hidden when no URL is stored')]
    public function link_hidden_when_unset(string $field, string $key, string $accessor): void
    {
        // No row stored, so the setting resolves to empty and the nav omits it.
        $this->assertSame('', Setting::{$accessor}());
    }

    #[Test]
    #[DataProvider('navLinks')]
    #[TestDox('an admin sees the settings form pre-filled with the stored $_dataName URL')]
    public function admin_sees_form_prefilled_with_stored_url(string $field, string $key, string $accessor): void
    {
        Setting::put($key, self::URL);

        $this->actingAs($this->createAdmin())->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee(__('admin.'.$field))
            ->assertSee(self::URL);
    }

    #[Test]
    #[DataProvider('navLinks')]
    #[TestDox('an admin can save a $_dataName URL and it drives the nav link')]
    public function admin_can_save_url(string $field, string $key, string $accessor): void
    {
        $this->actingAs($this->createAdmin())
            ->put(route('admin.settings.update'), [$field => self::URL])
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHas('status');

        $this->assertSame(self::URL, Setting::get($key));

        // The saved URL now backs the top-nav link.
        $this->actingAs($this->createAdmin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(self::URL);
    }

    #[Test]
    #[DataProvider('navLinks')]
    #[TestDox('clearing the $_dataName URL hides the nav link')]
    public function blank_url_hides_the_link(string $field, string $key, string $accessor): void
    {
        Setting::put($key, self::URL);

        $this->actingAs($this->createAdmin())
            ->put(route('admin.settings.update'), [$field => ''])
            ->assertRedirect(route('admin.settings.edit'));

        // Stored as an empty string (a deliberate "hide"), which the nav omits.
        $this->assertSame('', Setting::{$accessor}());

        $this->actingAs($this->createAdmin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(self::URL);
    }

    #[Test]
    #[DataProvider('navLinks')]
    #[TestDox('the $_dataName URL must be a valid URL')]
    public function url_must_be_valid(string $field, string $key, string $accessor): void
    {
        $this->actingAs($this->createAdmin())
            ->put(route('admin.settings.update'), [$field => 'not-a-url'])
            ->assertSessionHasErrors($field);
    }

    #[Test]
    #[TestDox('a non-admin cannot view or change site settings')]
    public function non_admin_is_forbidden(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->get(route('admin.settings.edit'))->assertForbidden();
        $this->actingAs($user)->put(route('admin.settings.update'), ['schedule_url' => self::URL])->assertForbidden();
    }

    #[Test]
    #[TestDox('a guest is redirected to login')]
    public function guest_redirected(): void
    {
        $this->get(route('admin.settings.edit'))->assertRedirect(route('login'));
    }

    #[Test]
    #[DataProvider('navLinks')]
    #[TestDox('reads are safe ($_dataName hidden) when the settings table is absent')]
    public function reads_are_safe_when_table_absent(string $field, string $key, string $accessor): void
    {
        // Simulate a database whose migrations have not been run: reads must not
        // fatal on the missing table, and the nav link simply stays hidden.
        Schema::dropIfExists('settings');

        $this->assertSame('', Setting::{$accessor}());
        $this->assertNull(Setting::get($key));
    }
}
