<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerImpersonationToken;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCustomerImpersonationRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_impersonation_uses_global_route_for_selected_b2b_store_domain(): void
    {
        [$intempoStore, $fipellStore, $customer, $admin] = $this->createImpersonationFixtures();

        $response = $this
            ->actingAs($admin)
            ->withSession(['admin_store_id' => $fipellStore->id])
            ->withServerVariables(['HTTP_HOST' => $intempoStore->domain])
            ->post(route('admin.customers.login-as', $customer));

        $response->assertOk();
        $response->assertSee('Accesso a B2B FIPELL SERVICE in corso', false);
        $response->assertSee('https://fipell.it/customer-impersonation/', false);

        $token = CustomerImpersonationToken::query()->first();

        $this->assertNotNull($token);
        $this->assertSame($customer->id, (int) $token->customer_id);
        $this->assertSame($admin->id, (int) $token->admin_user_id);
        $this->assertSame($fipellStore->id, (int) $token->store_id);
        $this->assertNull($token->used_at);
    }

    public function test_customer_impersonation_token_logs_customer_into_selected_store_account_area(): void
    {
        [, $fipellStore, $customer, $admin] = $this->createImpersonationFixtures();

        $plainToken = Str::random(64);

        CustomerImpersonationToken::query()->create([
            'customer_id' => $customer->id,
            'admin_user_id' => $admin->id,
            'store_id' => $fipellStore->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this
            ->get('https://' . $fipellStore->domain . '/customer-impersonation/' . $plainToken);

        $response->assertRedirect('https://fipell.it/it/account');

        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertNotNull(CustomerImpersonationToken::query()->first()?->used_at);
    }

    private function createImpersonationFixtures(): array
    {
        $intempoStore = Store::query()->create([
            'ditta_cg18' => 1,
            'erp_site_code' => 1,
            'company_code' => 'INTEMPO',
            'site_code' => 'B2B',
            'domain' => 'intempodistribution.test',
            'name' => 'B2B INTEMPO',
            'is_b2b' => true,
            'theme' => 'intempodistribution',
            'is_active' => true,
        ]);

        $fipellStore = Store::query()->create([
            'ditta_cg18' => 3,
            'erp_site_code' => 1,
            'company_code' => 'FIPELL',
            'site_code' => 'B2B',
            'domain' => 'fipell.it',
            'name' => 'B2B FIPELL SERVICE',
            'is_b2b' => true,
            'theme' => 'fipell',
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'ditta_cg18' => 3,
            'tipocf_cg44' => 0,
            'clifor_cg44' => 3911,
            'ragsoanag_cg16' => 'CENTRO UFFICIO SCUOLA DI NARLINI MASSIMILIANO',
            'indemail_cg16' => 'cliente@example.test',
            'codrifalf_mg19' => 'PT',
            'account_origin' => 'erp',
            'is_active' => true,
        ]);

        $admin = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.test',
            'password' => 'password',
            'is_admin' => true,
            'admin_role' => 'super_admin',
        ]);

        return [$intempoStore, $fipellStore, $customer, $admin];
    }
}
