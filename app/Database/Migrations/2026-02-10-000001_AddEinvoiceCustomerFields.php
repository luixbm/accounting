<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Buyer identification for LHDN MyInvois. Rather than dedicated columns these
 * reuse the custom-fields mechanism on the `customer` entity, so they show on
 * the customer form and can be edited without a schema change. Added for every
 * company; only companies with e-invoicing enabled actually use them.
 */
class AddEinvoiceCustomerFields extends Migration
{
    private array $fields = [
        ['einvoice_tin', 'MyInvois TIN', 'text', null, 'Buyer Tax Identification Number. Leave blank for general public (uses EI00000000010).', 90],
        ['einvoice_id_type', 'MyInvois ID type', 'select', "BRN\nNRIC\nPASSPORT\nARMY", 'Registration ID type that pairs with the ID number below.', 91],
        ['einvoice_id_value', 'MyInvois ID number', 'text', null, 'BRN / NRIC / passport / army number. "NA" if not available.', 92],
    ];

    public function up(): void
    {
        $now = date('Y-m-d H:i:s');
        $companies = $this->db->table('companies')->select('id')->get()->getResultArray();

        foreach ($companies as $c) {
            foreach ($this->fields as [$key, $label, $type, $options, $help, $sort]) {
                $exists = $this->db->table('custom_fields')
                    ->where(['company_id' => $c['id'], 'entity' => 'customer', 'field_key' => $key])
                    ->countAllResults();
                if ($exists) {
                    continue;
                }
                $this->db->table('custom_fields')->insert([
                    'company_id'  => $c['id'],
                    'entity'      => 'customer',
                    'field_key'   => $key,
                    'label'       => $label,
                    'type'        => $type,
                    'options'     => $options,
                    'help'        => $help,
                    'is_required' => 0,
                    'is_active'   => 1,
                    'show_in_list' => 0,
                    'sort_order'  => $sort,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $this->db->table('custom_fields')
            ->where('entity', 'customer')
            ->whereIn('field_key', ['einvoice_tin', 'einvoice_id_type', 'einvoice_id_value'])
            ->delete();
    }
}
