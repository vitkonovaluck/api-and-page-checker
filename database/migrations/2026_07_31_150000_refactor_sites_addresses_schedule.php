<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('base_url', 2048)->default('http://localhost')->after('name');
            $table->boolean('schedule_enabled')->default(false)->after('base_url');
            $table->string('schedule_interval', 16)->nullable()->after('schedule_enabled');
            $table->timestamp('schedule_last_run_at')->nullable()->after('schedule_interval');
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->string('endpoint', 2048)->default('/')->after('name');
            $table->boolean('schedule_enabled')->default(true)->after('endpoint');
        });

        $sites = DB::table('sites')->orderBy('id')->get();

        foreach ($sites as $site) {
            $addresses = DB::table('addresses')
                ->where('site_id', $site->id)
                ->orderBy('id')
                ->get();

            $baseUrl = 'http://localhost';

            if ($addresses->isNotEmpty()) {
                $parsed = parse_url((string) $addresses->first()->url);

                if (is_array($parsed) && ! empty($parsed['scheme']) && ! empty($parsed['host'])) {
                    $baseUrl = $parsed['scheme'].'://'.$parsed['host'];
                    if (! empty($parsed['port'])) {
                        $baseUrl .= ':'.$parsed['port'];
                    }
                }
            }

            DB::table('sites')->where('id', $site->id)->update(['base_url' => $baseUrl]);

            foreach ($addresses as $address) {
                $parsed = parse_url((string) $address->url);
                $endpoint = '/';

                if (is_array($parsed)) {
                    $path = $parsed['path'] ?? '/';
                    $query = isset($parsed['query']) ? '?'.$parsed['query'] : '';
                    $endpoint = ($path === '' ? '/' : $path).$query;
                }

                DB::table('addresses')->where('id', $address->id)->update([
                    'endpoint' => $endpoint,
                ]);
            }
        }

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropUnique(['site_id', 'url']);
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn('url');
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->unique(['site_id', 'endpoint']);
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropUnique(['site_id', 'endpoint']);
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->string('url', 2048)->default('http://localhost')->after('name');
        });

        $addresses = DB::table('addresses')
            ->join('sites', 'sites.id', '=', 'addresses.site_id')
            ->select('addresses.id', 'addresses.endpoint', 'sites.base_url')
            ->get();

        foreach ($addresses as $address) {
            $url = rtrim((string) $address->base_url, '/').'/'.ltrim((string) $address->endpoint, '/');
            DB::table('addresses')->where('id', $address->id)->update(['url' => $url]);
        }

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['endpoint', 'schedule_enabled']);
            $table->unique(['site_id', 'url']);
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn([
                'base_url',
                'schedule_enabled',
                'schedule_interval',
                'schedule_last_run_at',
            ]);
        });
    }
};
