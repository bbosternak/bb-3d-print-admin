<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_admin_page_requires_authentication_and_there_is_no_public_registration(): void
    {
        foreach ([
            '/admin', '/admin/financial-dashboard', '/admin/products', '/admin/products/create',
            '/admin/filaments', '/admin/expenses', '/admin/sales',
            '/admin/application-settings', '/admin/cost-calculator',
            '/admin/pricing-calculator', '/admin/product-comparison',
        ] as $path) {
            $this->get($path)->assertRedirect('/admin/login');
        }
        $this->get('/admin/login')->assertOk()->assertSee('BB 3DPrint Admin');
        $this->get('/admin/register')->assertNotFound();
        $this->get('/')->assertRedirect('/admin');
    }

    public function test_provisioned_users_can_access_the_panel_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->actingAs(User::factory()->create())
            ->get('/admin')->assertRedirect('/admin/financial-dashboard');
    }
}
