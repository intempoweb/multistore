<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserAdminSectionsTest extends TestCase
{
    public function test_b2c_manager_can_edit_static_pages_without_super_admin_access(): void
    {
        $user = new User([
            'is_admin' => true,
            'admin_role' => 'b2c_manager',
        ]);

        $this->assertTrue($user->canAccessAdminSection('static_pages'));
        $this->assertTrue($user->canAccessAdminSection('storefront_seo'));
        $this->assertFalse($user->canAccessAdminSection('super'));
    }
}
