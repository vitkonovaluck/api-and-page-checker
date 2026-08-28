<?php

declare(strict_types=1);

namespace App\Livewire\Sites;

use App\Enums\AlertEvent;
use App\Models\AlertRule;
use App\Models\NotificationChannel;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

final class AlertRules extends Component
{
    public Site $site;

    public mixed $notificationChannelId = null;

    public mixed $addressId = null;

    /** @var list<string> */
    public array $events = [AlertEvent::BodyChanged->value];

    public int $minConsecutive = 1;

    public int $cooldownMinutes = 0;

    public bool $notifyOnManual = false;

    public bool $digestValueChanges = false;

    public function mount(Site $site): void
    {
        $this->authorize('view', $site);
        $this->site = $site;
    }

    public function create(): void
    {
        $this->authorize('update', $this->site);

        $validated = $this->validate([
            'notificationChannelId' => [
                'required',
                'integer',
                Rule::exists('notification_channels', 'id')->where('user_id', Auth::id()),
            ],
            'addressId' => [
                'nullable',
                'integer',
                Rule::exists('addresses', 'id')->where('site_id', $this->site->id),
            ],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::enum(AlertEvent::class)],
            'minConsecutive' => ['required', 'integer', 'min:1', 'max:20'],
            'cooldownMinutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'notifyOnManual' => ['boolean'],
            'digestValueChanges' => ['boolean'],
        ]);

        $this->site->alertRules()->create([
            'notification_channel_id' => $validated['notificationChannelId'],
            'address_id' => $validated['addressId'] ?? null,
            'events' => $validated['events'],
            'min_consecutive' => $validated['minConsecutive'],
            'cooldown_minutes' => $validated['cooldownMinutes'],
            'notify_on_manual' => (bool) $this->notifyOnManual,
            'digest_value_changes' => (bool) $this->digestValueChanges,
        ]);

        $this->reset(['addressId', 'notifyOnManual', 'digestValueChanges']);
        $this->events = [AlertEvent::BodyChanged->value];
        $this->minConsecutive = 1;
        $this->cooldownMinutes = 0;
        $this->resetValidation();
    }

    public function delete(int $ruleId): void
    {
        $this->authorize('update', $this->site);

        AlertRule::query()
            ->where('site_id', $this->site->id)
            ->whereKey($ruleId)
            ->delete();
    }

    public function render(): View
    {
        $user = $this->currentUser();
        $this->site->loadMissing(['addresses' => fn ($q) => $q->orderBy('id')]);

        return view('livewire.sites.alert-rules', [
            'rules' => $this->site->alertRules()->with(['notificationChannel', 'address'])->orderByDesc('id')->get(),
            'channels' => NotificationChannel::query()
                ->where('user_id', $user->id)
                ->where('is_enabled', true)
                ->orderBy('id')
                ->get(),
            'eventOptions' => AlertEvent::cases(),
            'canUpdate' => $user->can('update', $this->site),
        ]);
    }

    private function currentUser(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
