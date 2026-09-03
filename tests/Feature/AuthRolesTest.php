<?php

namespace Tests\Feature;

use App\Filament\Pages\PlaybackSettings;
use App\Filament\Resources\CameraResource;
use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\UserResource;
use App\Models\Camera;
use App\Models\Category;
use App\Models\User;
use Filament\Auth\Pages\EditProfile;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AuthRolesTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(array $overrides = []): User
    {
        return User::factory()->admin()->create($overrides);
    }

    private function makeOperator(array $overrides = []): User
    {
        return User::factory()->operator()->create($overrides);
    }

    public function test_panel_access_is_allowlisted_to_admin_and_operator(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertTrue($this->makeAdmin()->canAccessPanel($panel));
        $this->assertTrue($this->makeOperator()->canAccessPanel($panel));

        $outsider = User::factory()->create();
        $outsider->forceFill(['role' => 'viewer'])->save();

        $this->assertFalse($outsider->canAccessPanel($panel));

        $roleless = new User();
        $roleless->role = null;

        $this->assertFalse($roleless->canAccessPanel($panel));
    }

    public function test_role_is_not_mass_assignable(): void
    {
        $user = User::create([
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
        ]);

        // Crafted `role=admin` is ignored — new users default to operator.
        $this->assertSame(User::ROLE_OPERATOR, $user->fresh()->role);
    }

    public function test_camera_resource_allows_admin_and_operator_full_access(): void
    {
        $camera = Camera::factory()->create();

        foreach (['admin' => $this->makeAdmin(), 'operator' => $this->makeOperator()] as $role => $user) {
            $this->actingAs($user);

            $this->assertTrue(CameraResource::canViewAny(), "{$role} can view cameras");
            $this->assertTrue(CameraResource::canCreate(), "{$role} can create cameras");
            $this->assertTrue(CameraResource::canEdit($camera), "{$role} can edit cameras");
            $this->assertTrue(CameraResource::canDelete($camera), "{$role} can delete cameras");
            $this->assertTrue(CameraResource::canDeleteAny(), "{$role} can bulk delete cameras");
        }
    }

    public function test_guests_cannot_manage_cameras(): void
    {
        $camera = Camera::factory()->create();

        $this->assertFalse(CameraResource::canViewAny());
        $this->assertFalse(CameraResource::canCreate());
        $this->assertFalse(CameraResource::canEdit($camera));
        $this->assertFalse(CameraResource::canDelete($camera));
        $this->assertFalse(CameraResource::canDeleteAny());
    }

    public function test_operator_is_forbidden_from_admin_only_areas(): void
    {
        $this->actingAs($this->makeOperator());

        // Users stay admin-only.
        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(UserResource::canCreate());
        $this->assertFalse(UserResource::canDeleteAny());

        // Categories: view/create/edit allowed, delete admin-only.
        $this->assertTrue(CategoryResource::canViewAny());
        $this->assertTrue(CategoryResource::canCreate());
        $this->assertFalse(CategoryResource::canDeleteAny());

        // Playback settings: full access for operators.
        $this->assertTrue(PlaybackSettings::canAccess());
    }

    public function test_operator_cannot_delete_categories(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->makeOperator());

        $this->assertTrue(CategoryResource::canViewAny());
        $this->assertTrue(CategoryResource::canCreate());
        $this->assertTrue(CategoryResource::canEdit($category));
        $this->assertFalse(CategoryResource::canDelete($category));
        $this->assertFalse(CategoryResource::canDeleteAny());
    }

    public function test_admin_can_delete_categories(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->makeAdmin());

        $this->assertTrue(CategoryResource::canDelete($category));
        $this->assertTrue(CategoryResource::canDeleteAny());
    }

    public function test_admin_can_access_admin_only_areas(): void
    {
        $this->actingAs($this->makeAdmin());

        $this->assertTrue(UserResource::canViewAny());
        $this->assertTrue(UserResource::canCreate());
        $this->assertTrue(UserResource::canDeleteAny());
        $this->assertTrue(CategoryResource::canViewAny());
        $this->assertTrue(CategoryResource::canCreate());
        $this->assertTrue(CategoryResource::canDeleteAny());
        $this->assertTrue(PlaybackSettings::canAccess());
    }

    public function test_operator_can_delete_camera(): void
    {
        $operator = $this->makeOperator();
        $this->actingAs($operator);

        $camera = Camera::factory()->create();

        $this->assertTrue(CameraResource::canDelete($camera));

        $camera->delete();

        $this->assertDatabaseMissing('cameras', ['id' => $camera->id]);
    }

    public function test_operator_cannot_escalate_role(): void
    {
        $operator = $this->makeOperator();

        $this->assertSame(User::ROLE_OPERATOR, UserResource::resolveAssignedRole($operator, User::ROLE_ADMIN));
        $this->assertSame(User::ROLE_OPERATOR, UserResource::resolveAssignedRole(null, User::ROLE_ADMIN));
        $this->assertSame(User::ROLE_OPERATOR, UserResource::resolveAssignedRole($operator, 'superadmin'));
    }

    public function test_admin_can_assign_roles_explicitly(): void
    {
        $admin = $this->makeAdmin();

        $this->assertSame(User::ROLE_ADMIN, UserResource::resolveAssignedRole($admin, User::ROLE_ADMIN));
        $this->assertSame(User::ROLE_OPERATOR, UserResource::resolveAssignedRole($admin, User::ROLE_OPERATOR));
        $this->assertSame(User::ROLE_OPERATOR, UserResource::resolveAssignedRole($admin, 'superadmin'));
        $this->assertSame(User::ROLE_OPERATOR, UserResource::resolveAssignedRole($admin, null));
    }

    public function test_self_delete_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin);

        $this->assertFalse(UserResource::canDelete($admin));
        $this->assertSame(
            'You cannot delete your own account.',
            UserResource::deletionRejectionReason($admin, $admin)
        );

        $this->assertFalse((bool) $admin->delete());
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_last_admin_delete_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $operator = $this->makeOperator();
        $this->actingAs($operator);

        $this->assertSame(
            'You cannot delete the last admin account.',
            UserResource::deletionRejectionReason($operator, $admin)
        );

        $this->assertFalse((bool) $admin->delete());
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_non_last_admin_delete_is_allowed(): void
    {
        $keeper = $this->makeAdmin();
        $leaver = $this->makeAdmin();
        $this->actingAs($keeper);

        $this->assertNull(UserResource::deletionRejectionReason($keeper, $leaver));
        $this->assertTrue(UserResource::canDelete($leaver));

        $this->assertTrue((bool) $leaver->delete());
        $this->assertDatabaseMissing('users', ['id' => $leaver->id]);
        $this->assertDatabaseHas('users', ['id' => $keeper->id]);
    }

    public function test_operator_gets_403_on_user_pages(): void
    {
        $this->actingAs($this->makeOperator());

        $this->assertSame(403, $this->get('/admin/users')->status());
    }

    public function test_operator_can_open_category_and_playback_pages(): void
    {
        $this->actingAs($this->makeOperator());

        $this->assertSame(200, $this->get('/admin/categories')->status());
        $this->assertSame(200, $this->get('/admin/playback-settings')->status());
    }

    public function test_profile_page_accessible_to_admin_and_operator(): void
    {
        foreach (['admin' => $this->makeAdmin(), 'operator' => $this->makeOperator()] as $role => $user) {
            $this->actingAs($user);

            $this->assertSame(200, $this->get('/admin/profile')->status(), "{$role} can open profile");
        }
    }

    public function test_profile_update_changes_own_name(): void
    {
        $operator = $this->makeOperator();
        $this->actingAs($operator);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'name' => 'New Operator Name',
                'email' => $operator->email,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('New Operator Name', $operator->fresh()->name);
    }

    public function test_profile_password_change_requires_current_password(): void
    {
        $operator = $this->makeOperator([
            'password' => Hash::make('current-password-123'),
        ]);
        $this->actingAs($operator);

        // Without the current password the change must not go through.
        Livewire::test(EditProfile::class)
            ->fillForm([
                'name' => $operator->name,
                'email' => $operator->email,
                'password' => 'brand-new-password-456',
                'passwordConfirmation' => 'brand-new-password-456',
            ])
            ->call('save')
            ->assertHasFormErrors(['currentPassword']);

        $this->assertTrue(Hash::check('current-password-123', $operator->fresh()->password));

        // With the current password the change succeeds.
        Livewire::test(EditProfile::class)
            ->fillForm([
                'name' => $operator->name,
                'email' => $operator->email,
                'password' => 'brand-new-password-456',
                'passwordConfirmation' => 'brand-new-password-456',
                'currentPassword' => 'current-password-123',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('brand-new-password-456', $operator->fresh()->password));
    }

    public function test_admin_can_open_user_pages(): void
    {
        $this->actingAs($this->makeAdmin());

        $this->assertSame(200, $this->get('/admin/users')->status());
    }

    public function test_operator_can_open_camera_pages(): void
    {
        $this->actingAs($this->makeOperator());

        $this->assertSame(200, $this->get('/admin/cameras')->status());
    }
}
