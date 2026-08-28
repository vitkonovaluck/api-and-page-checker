<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

final class OrganizationMembers extends Component
{
    public string $email = '';

    public string $role = OrganizationRole::Operator->value;

    public function add(): void
    {
        $organization = $this->organization();
        $this->authorize('update', $organization);

        $validated = $this->validate([
            'email' => ['required', 'email'],
            'role' => ['required', 'string', Rule::in([OrganizationRole::Operator->value, OrganizationRole::Viewer->value])],
        ]);

        $member = User::query()->where('email', $validated['email'])->first();

        if ($member === null) {
            $this->addError('email', __('alerts.ui.member_not_found'));

            return;
        }

        if ($member->id === $organization->owner_user_id) {
            $this->addError('email', __('alerts.ui.member_is_owner'));

            return;
        }

        $organization->memberships()->updateOrCreate(
            ['user_id' => $member->id],
            ['role' => $validated['role']],
        );

        $this->reset(['email']);
        $this->role = OrganizationRole::Operator->value;
        $this->resetValidation();
    }

    public function remove(int $membershipId): void
    {
        $organization = $this->organization();
        $this->authorize('update', $organization);

        $membership = $organization->memberships()->whereKey($membershipId)->firstOrFail();

        if ($membership->user_id === $organization->owner_user_id) {
            abort(403);
        }

        $membership->delete();
    }

    public function render(): View
    {
        $organization = $this->organization();
        $memberships = $organization->memberships()
            ->with('user')
            ->orderBy('id')
            ->get();

        return view('livewire.settings.organization-members', [
            'organization' => $organization,
            'memberships' => $memberships,
            'canManage' => $this->currentUser()->can('update', $organization),
            'roleOptions' => [OrganizationRole::Operator, OrganizationRole::Viewer],
        ]);
    }

    private function organization(): Organization
    {
        $user = $this->currentUser();
        $organization = $user->personalOrganization()
            ?? $user->organizations()->orderBy('organizations.id')->first();

        abort_unless($organization instanceof Organization, 404);

        return $organization;
    }

    private function currentUser(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
