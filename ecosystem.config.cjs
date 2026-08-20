const php = '/usr/bin/php'; // which php
const cwd = '/var/www/api-checker.microcode.vn.ua';

module.exports = {
  apps: [
    {
      name: 'api-checker-queue',
      cwd,
      script: php,
      args: 'artisan sites:queue-work --tries=0 --timeout=90 --sleep=1',
      interpreter: 'none',
      autorestart: true,
      min_uptime: '10s',
      max_restarts: 10,
    },
    {
      name: 'api-checker-scheduler',
      cwd,
      script: php,
      args: 'artisan schedule:work',
      interpreter: 'none',
      autorestart: true,
      min_uptime: '10s',
      max_restarts: 10,
    },
  ],
};