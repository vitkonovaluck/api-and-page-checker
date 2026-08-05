module.exports = {
    apps: [
        {
            name: 'api-checker-queue',
            cwd: '/var/www/api-checker.microcode.vn.ua',
            script: 'artisan',
            args: 'queue:work database --queue=default --sleep=3 --tries=3 --timeout=180 --max-time=3600',
            interpreter: 'php',
            exec_mode: 'fork',
            instances: 1,
            watch: false,
            autorestart: true,
            max_memory_restart: '512M',
        },
        {
            name: 'api-checker-scheduler',
            cwd: '/var/www/api-checker.microcode.vn.ua',
            script: 'artisan',
            args: 'schedule:run',
            interpreter: 'php',
            exec_mode: 'fork',
            instances: 1,
            cron_restart: '* * * * *',
            autorestart: false,
            watch: false,
        },
    ],
};
