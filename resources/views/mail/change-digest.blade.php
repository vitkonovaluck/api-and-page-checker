# {{ __('alerts.mail.digest_heading') }}

{{ __('alerts.mail.digest_body') }}

@foreach ($changes as $change)
- {{ $change['site'] }} {{ $change['endpoint'] }} (#{{ $change['snapshot_id'] }})
@endforeach
