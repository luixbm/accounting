<?php

namespace App\Libraries;

use App\Models\CustomFieldModel;
use Config\Database;

/**
 * User-defined extra fields. Definitions live per company + entity; values are
 * stored in typed columns (value_text / value_num / value_date) so a future
 * feature (e.g. a payment list ordered by a "promise date" field) can filter
 * and sort on them directly, and so the API can set them by key.
 */
class CustomFields
{
    /** Entities that can carry custom fields. */
    public const ENTITIES = [
        'purchase_invoice' => 'Purchase Invoice',
        'sales_invoice'    => 'Sales Invoice',
        'purchase_payment' => 'Purchase Payment',
        'sales_receipt'    => 'Sales Receipt',
        'journal'          => 'Journal',
        'customer'         => 'Customer',
        'supplier'         => 'Supplier',
        'job'              => 'Job',
    ];

    private $db;
    private CustomFieldModel $fields;

    public function __construct()
    {
        $this->db     = Database::connect();
        $this->fields = model(CustomFieldModel::class);
    }

    /** @return list<array<string,mixed>> active field defs for the entity */
    public function defs(string $entity): array
    {
        return $this->fields->forEntity($entity, true);
    }

    public function hasAny(string $entity): bool
    {
        return $this->defs($entity) !== [];
    }

    /** @return array<string,string> field_key => raw value, for one record */
    public function valuesFor(string $entity, int $recordId): array
    {
        if ($recordId <= 0) {
            return [];
        }
        $rows = $this->db->table('custom_values')
            ->where('entity', $entity)->where('record_id', $recordId)
            ->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $out[$r['field_key']] = $this->pickValue($r);
        }

        return $out;
    }

    /**
     * @param list<int> $recordIds
     *
     * @return array<int,array<string,string>>
     */
    public function valuesForMany(string $entity, array $recordIds): array
    {
        $recordIds = array_values(array_filter(array_map('intval', $recordIds)));
        if (! $recordIds) {
            return [];
        }
        $rows = $this->db->table('custom_values')
            ->where('entity', $entity)->whereIn('record_id', $recordIds)
            ->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['record_id']][$r['field_key']] = $this->pickValue($r);
        }

        return $out;
    }

    /**
     * Persist values from a $_POST["cf"] map ( field_key => raw ).
     */
    public function save(string $entity, int $recordId, array $cf): void
    {
        $company = active_company_id();
        $now     = date('Y-m-d H:i:s');

        foreach ($this->defs($entity) as $def) {
            $key = $def['field_key'];
            $raw = $cf[$key] ?? ($def['type'] === 'checkbox' ? '0' : null);
            [$text, $num, $date] = self::cast($def['type'], $raw);

            $exists = $this->db->table('custom_values')
                ->where('entity', $entity)->where('record_id', $recordId)->where('field_key', $key)
                ->get()->getRowArray();

            $data = [
                'company_id' => $company,
                'value_text' => $text,
                'value_num'  => $num,
                'value_date' => $date,
                'updated_at' => $now,
            ];
            if ($exists) {
                $this->db->table('custom_values')->where('id', $exists['id'])->update($data);
            } else {
                $this->db->table('custom_values')->insert($data + [
                    'entity'     => $entity,
                    'record_id'  => $recordId,
                    'field_key'  => $key,
                    'created_at' => $now,
                ]);
            }
        }
    }

    public function deleteFor(string $entity, int $recordId): void
    {
        $this->db->table('custom_values')->where('entity', $entity)->where('record_id', $recordId)->delete();
    }

    /**
     * Validate required custom fields; returns list of error messages.
     *
     * @return list<string>
     */
    public function validate(string $entity, array $cf): array
    {
        $errors = [];
        foreach ($this->defs($entity) as $def) {
            if ((int) $def['is_required'] === 1) {
                $v = trim((string) ($cf[$def['field_key']] ?? ''));
                if ($v === '' || $v === '0' && $def['type'] === 'checkbox') {
                    $errors[] = $def['label'] . ' is required.';
                }
            }
        }

        return $errors;
    }

    /** @return array{0:?string,1:?float,2:?string} [text, num, date] */
    public static function cast(string $type, $raw): array
    {
        if ($raw === null || $raw === '') {
            return [$type === 'checkbox' ? '0' : null, null, null];
        }
        $raw = is_string($raw) ? trim($raw) : $raw;

        return match ($type) {
            'number'   => [null, (float) str_replace([',', ' '], '', (string) $raw), null],
            'date'     => [null, null, (($t = strtotime((string) $raw)) ? date('Y-m-d', $t) : null)],
            'checkbox' => [$raw ? '1' : '0', null, null],
            default    => [mb_substr((string) $raw, 0, 500), null, null],
        };
    }

    private function pickValue(array $row): string
    {
        if ($row['value_date'] !== null) {
            return (string) $row['value_date'];
        }
        if ($row['value_num'] !== null) {
            return rtrim(rtrim((string) $row['value_num'], '0'), '.');
        }

        return (string) ($row['value_text'] ?? '');
    }
}
