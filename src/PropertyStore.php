<?php

declare(strict_types=1);
namespace Survos\Folio;

use Survos\DataContracts\Metadata\PropertyKey;
use Survos\DataContracts\Metadata\PropertyValue;

/** Portable SQLite adapter: no Symfony or Doctrine dependency; reads never perform DDL. */
final readonly class PropertyStore
{
    public function __construct(private \PDO $pdo) {}

    /** @return array<string, PropertyValue> */
    public function read(): array
    {
        $properties = [];
        if ($this->exists('folio_property')) {
            foreach ($this->pdo->query('SELECT * FROM folio_property')->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $value = json_decode($row['value'], flags: JSON_THROW_ON_ERROR);
                PropertyKey::validate($row['key'], $value);
                $properties[$row['key']] = new PropertyValue($value, $row['source'], $row['owner'], $row['updated_at'], json_decode($row['provenance'], true, flags: JSON_THROW_ON_ERROR));
            }
        }
        if (($properties[PropertyKey::SCHEMA_VERSION]->value ?? 1) < 2 && $this->exists('folio')) {
            $legacy = $this->pdo->query('SELECT * FROM folio LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
            foreach ([PropertyKey::LABEL => 'label', PropertyKey::ROW_COUNT => 'row_count', PropertyKey::CONTENT_TYPE => 'content_type'] as $key => $column) {
                if (is_array($legacy) && array_key_exists($column, $legacy) && !isset($properties[$key])) {
                    $properties[$key] = PropertyValue::create($legacy[$column], 'import', 'legacy.folio', ['sourceRef' => 'folio.'.$column]);
                }
            }
        }
        return $properties;
    }

    public function migrate(): void
    {
        $ownTransaction = !$this->pdo->inTransaction();
        if ($ownTransaction) { $this->pdo->beginTransaction(); }
        try {
            $properties = $this->read();
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS folio_property ("key" VARCHAR(180) PRIMARY KEY NOT NULL, value TEXT NOT NULL, source VARCHAR(16) NOT NULL, owner VARCHAR(180) NOT NULL, updated_at VARCHAR(40) NOT NULL, provenance TEXT NOT NULL)');
            if (($properties[PropertyKey::SCHEMA_VERSION]->value ?? 1) < 2) {
                foreach ($properties as $key => $property) { $this->put($key, $property); }
                $this->put(PropertyKey::SCHEMA_VERSION, PropertyValue::create(2, 'build', 'folio.format'));
            }
            if ($ownTransaction) { $this->pdo->commit(); }
        } catch (\Throwable $e) {
            if ($ownTransaction && $this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw $e;
        }
    }

    public function put(string $key, PropertyValue $property): void
    {
        PropertyKey::validate($key, $property->value);
        $stmt = $this->pdo->prepare('INSERT INTO folio_property ("key", value, source, owner, updated_at, provenance) VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT("key") DO UPDATE SET value=excluded.value, source=excluded.source, owner=excluded.owner, updated_at=excluded.updated_at, provenance=excluded.provenance');
        $stmt->execute([$key, json_encode($property->value, JSON_THROW_ON_ERROR), $property->source, $property->owner, $property->updatedAt, json_encode($property->provenance, JSON_THROW_ON_ERROR)]);
    }

    public function values(): array
    {
        return array_map(static fn (PropertyValue $property): mixed => $property->value, $this->read());
    }

    private function exists(string $table): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
        $stmt->execute([$table]);
        return $stmt->fetchColumn() !== false;
    }
}
