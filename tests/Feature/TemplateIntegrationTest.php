<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_home_page_renders_with_public_layout(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('masthead', false)
            ->assertSee(__('public.home.hero'))
            ->assertSee(__('ui.nav.login'));
    }

    public function test_back_office_pages_render_with_the_sb_admin_layout(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/consommateur')
            ->assertOk()
            ->assertSee('id="accordionSidebar"', false)
            ->assertSee('id="sidebarToggleTop"', false)
            ->assertSee('sticky-footer', false)
            ->assertSee('<li class="nav-item active">', false)
            ->assertSee(__('menu.dashboard'));
    }

    public function test_auth_pages_render_with_the_sb_admin_card_layout(): void
    {
        foreach (['/login', '/register', '/forgot-password'] as $page) {
            $this->get($page)
                ->assertOk()
                ->assertSee('bg-gradient-primary', false)
                ->assertSee('form-control-user', false);
        }
    }

    public function test_unknown_pages_show_the_french_404_page(): void
    {
        $this->get('/page-inexistante')
            ->assertNotFound()
            ->assertSee(__('account.errors.404_title'));
    }
}
