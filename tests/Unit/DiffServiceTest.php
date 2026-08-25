<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\DiffChangeType;
use App\Models\Snapshot;
use App\Services\DiffService;
use Tests\TestCase;

class DiffServiceTest extends TestCase
{
    public function test_slug_list_reorder_is_reported_as_reordered(): void
    {
        $cetus = $this->sto('cetus', 'СТО Cetus');
        $avtoMotiv = $this->sto('avto-motiv', 'СТО Авто-Мотив');
        $bitstop = $this->sto('bitstop-kiev', 'СТО Bitstop - Kiev');

        $diff = $this->compareJson(
            ['address' => [$cetus, $avtoMotiv, $bitstop]],
            ['address' => [$avtoMotiv, $bitstop, $cetus]],
        );

        $this->assertTrue($diff['has_changes']);
        $this->assertSame(
            [
                [
                    'path' => 'address',
                    'type' => DiffChangeType::Reordered->value,
                    'old' => ['cetus', 'avto-motiv', 'bitstop-kiev'],
                    'new' => ['avto-motiv', 'bitstop-kiev', 'cetus'],
                ],
            ],
            $diff['body']['changes'],
        );
    }

    public function test_slug_list_field_change_uses_slug_path_after_reorder(): void
    {
        $cetus = $this->sto('cetus', 'СТО Cetus');
        $avtoMotiv = $this->sto('avto-motiv', 'СТО Авто-Мотив');
        $updatedCetus = $this->sto('cetus', 'СТО Cetus Kyiv');

        $diff = $this->compareJson(
            ['address' => [$cetus, $avtoMotiv]],
            ['address' => [$avtoMotiv, $updatedCetus]],
        );

        $this->assertContains(
            [
                'path' => 'address[slug=cetus].name',
                'type' => DiffChangeType::Changed->value,
                'old' => 'СТО Cetus',
                'new' => 'СТО Cetus Kyiv',
            ],
            $diff['body']['changes'],
        );
        $this->assertContains(
            [
                'path' => 'address',
                'type' => DiffChangeType::Reordered->value,
                'old' => ['cetus', 'avto-motiv'],
                'new' => ['avto-motiv', 'cetus'],
            ],
            $diff['body']['changes'],
        );
        $this->assertCount(2, $diff['body']['changes']);
    }

    public function test_slug_list_added_and_removed_items_are_matched_by_slug(): void
    {
        $cetus = $this->sto('cetus', 'СТО Cetus');
        $avtoMotiv = $this->sto('avto-motiv', 'СТО Авто-Мотив');
        $bitstop = $this->sto('bitstop-kiev', 'СТО Bitstop - Kiev');

        $diff = $this->compareJson(
            ['address' => [$cetus, $avtoMotiv]],
            ['address' => [$cetus, $bitstop]],
        );

        $this->assertSame(
            [
                [
                    'path' => 'address[slug=avto-motiv]',
                    'type' => DiffChangeType::Removed->value,
                    'old' => $avtoMotiv,
                    'new' => null,
                ],
                [
                    'path' => 'address[slug=bitstop-kiev]',
                    'type' => DiffChangeType::Added->value,
                    'old' => null,
                    'new' => $bitstop,
                ],
            ],
            $diff['body']['changes'],
        );
    }

    public function test_appending_item_is_not_reported_as_reorder(): void
    {
        $cetus = $this->sto('cetus', 'СТО Cetus');
        $avtoMotiv = $this->sto('avto-motiv', 'СТО Авто-Мотив');

        $diff = $this->compareJson(
            ['address' => [$cetus]],
            ['address' => [$cetus, $avtoMotiv]],
        );

        $this->assertSame(
            [
                [
                    'path' => 'address[slug=avto-motiv]',
                    'type' => DiffChangeType::Added->value,
                    'old' => null,
                    'new' => $avtoMotiv,
                ],
            ],
            $diff['body']['changes'],
        );
    }

    public function test_unique_scalar_list_reorder_is_reported_as_reordered(): void
    {
        $diff = $this->compareJson(
            ['tags' => ['glass', 'tuning', 'alarm']],
            ['tags' => ['tuning', 'alarm', 'glass']],
        );

        $this->assertSame(
            [
                [
                    'path' => 'tags',
                    'type' => DiffChangeType::Reordered->value,
                    'old' => ['glass', 'tuning', 'alarm'],
                    'new' => ['tuning', 'alarm', 'glass'],
                ],
            ],
            $diff['body']['changes'],
        );
    }

    public function test_identical_object_list_without_identity_key_is_reported_as_reordered(): void
    {
        $first = ['name' => 'СТО Cetus', 'lat' => 50.45];
        $second = ['name' => 'СТО Авто-Мотив', 'lat' => 50.41];

        $diff = $this->compareJson(
            ['address' => [$first, $second]],
            ['address' => [$second, $first]],
        );

        $this->assertSame(
            [
                [
                    'path' => 'address',
                    'type' => DiffChangeType::Reordered->value,
                    'old' => [$first, $second],
                    'new' => [$second, $first],
                ],
            ],
            $diff['body']['changes'],
        );
    }

    public function test_duplicate_slugs_fall_back_to_index_diff(): void
    {
        $first = $this->sto('cetus', 'СТО Cetus');
        $duplicate = $this->sto('cetus', 'СТО Cetus Duplicate');
        $changed = $this->sto('cetus', 'СТО Cetus Changed');

        $diff = $this->compareJson(
            ['address' => [$first, $duplicate]],
            ['address' => [$first, $changed]],
        );

        $this->assertSame(
            [
                [
                    'path' => 'address[1].name',
                    'type' => DiffChangeType::Changed->value,
                    'old' => 'СТО Cetus Duplicate',
                    'new' => 'СТО Cetus Changed',
                ],
            ],
            $diff['body']['changes'],
        );
    }

    public function test_object_field_changes_without_list_still_use_key_paths(): void
    {
        $diff = $this->compareJson(
            ['version' => 1, 'name' => 'old'],
            ['version' => 2, 'name' => 'new'],
        );

        $paths = array_column($diff['body']['changes'], 'path');

        $this->assertContains('version', $paths);
        $this->assertContains('name', $paths);
    }

    /**
     * @return array<string, mixed>
     */
    private function sto(string $slug, string $name): array
    {
        return [
            'name' => $name,
            'slug' => $slug,
            'lat' => 50.45,
            'lng' => 30.52,
        ];
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array<string, mixed>
     */
    private function compareJson(array $old, array $new): array
    {
        $oldBody = json_encode($old, JSON_UNESCAPED_UNICODE);
        $newBody = json_encode($new, JSON_UNESCAPED_UNICODE);

        $previous = new Snapshot([
            'status_code' => 200,
            'headers' => [],
            'body' => $oldBody,
            'response_time_ms' => 10,
        ]);
        $current = new Snapshot([
            'status_code' => 200,
            'headers' => [],
            'body' => $newBody,
            'response_time_ms' => 10,
        ]);

        return app(DiffService::class)->compare($previous, $current);
    }
}
