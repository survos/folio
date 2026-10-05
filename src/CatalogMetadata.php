<?php

declare(strict_types=1);
namespace Survos\Folio;

use Survos\DataContracts\Metadata\DatasetMetadata;
use Survos\DataContracts\Metadata\PropertyKey;

/** Catalog projection and paper-level search, with no database fan-out. */
final class CatalogMetadata
{
    public static function project(array $properties, array $fallback): array
    {
        foreach ([PropertyKey::LABEL => 'title', PropertyKey::DESCRIPTION => 'description', PropertyKey::CONTENT_TYPE => 'contentType', PropertyKey::ROW_COUNT => 'rowCount'] as $key => $field) {
            if (array_key_exists($key, $properties)) { $fallback[$field] = $properties[$key]; }
        }
        $fallback['tags'] = array_values(array_unique(array_merge($properties[PropertyKey::TAGS] ?? [], $fallback['tags'] ?? [])));
        $fallback['titleRecord'] = DatasetMetadata::titleRecord($properties);
        return $fallback;
    }

    public static function matches(array $entry, string $query): bool
    {
        if ($query === '') { return true; }
        $searchable = [$entry['datasetKey'], $entry['title'], $entry['description'], $entry['tags'], $entry['titleRecord']];
        $strings = [];
        array_walk_recursive($searchable, static function (mixed $value) use (&$strings): void {
            if (is_scalar($value)) { $strings[] = (string) $value; }
        });
        return mb_stripos(implode("\n", $strings), $query) !== false;
    }
}
