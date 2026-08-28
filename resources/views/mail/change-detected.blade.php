# {{ __('alerts.mail.heading') }}

{{ __('alerts.mail.body', ['site' => $rule->site?->name ?? '', 'endpoint' => $snapshot->address?->endpoint ?? '']) }}

**{{ __('alerts.mail.events') }}:** {{ implode(', ', $events) }}

**{{ __('alerts.mail.status') }}:** {{ $snapshot->status_code ?? '—' }}
