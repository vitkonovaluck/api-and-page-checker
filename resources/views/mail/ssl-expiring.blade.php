# {{ __('alerts.mail.ssl_heading') }}

{{ __('alerts.mail.ssl_body', ['site' => $site->name, 'days' => $daysLeft, 'host' => $site->base_url]) }}
