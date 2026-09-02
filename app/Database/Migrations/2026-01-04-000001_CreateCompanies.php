<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCompanies extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'code'          => ['type' => 'VARCHAR', 'constraint' => 20],
            'name'          => ['type' => 'VARCHAR', 'constraint' => 150],
            'legal_name'    => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'npwp'          => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'address'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'base_currency' => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'IDR'],
            'logo_path'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            // optional grouping parent for consolidation
            'parent_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'is_active'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('companies');

        // Seed the first company from the existing single-company settings.
        $name = 'Company 1';
        $row  = $this->db->table('settings')->where('key', 'Accounting.companyName')->get()->getRowArray();
        if ($row && ! empty($row['value'])) {
            $name = $row['value'];
        }
        $this->db->table('companies')->insert([
            'code'          => 'C1',
            'name'          => $name,
            'base_currency' => 'IDR',
            'is_active'     => 1,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
        $companyId = (int) $this->db->insertID();

        // --- add company_id to every tenant-scoped table, backfilled to company 1
        $scoped = [
            'accounts'        => 'code',
            'customers'       => 'code',
            'suppliers'       => 'code',
            'jobs'            => 'code',
            'journals'        => 'journal_no',
            'journal_lines'   => null,
            'fiscal_periods'  => null,
            'import_batches'  => null,
            'account_aliases' => 'source_label',
        ];

        foreach ($scoped as $table => $uniqueCol) {
            $this->forge->addColumn($table, [
                'company_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'first' => true],
            ]);
            $this->db->query("UPDATE `{$table}` SET `company_id` = {$companyId}");
            $this->db->query("ALTER TABLE `{$table}` MODIFY `company_id` INT(11) UNSIGNED NOT NULL");
            $this->db->query("ALTER TABLE `{$table}` ADD INDEX `{$table}_company_id_idx` (`company_id`)");
            $this->db->query("ALTER TABLE `{$table}` ADD CONSTRAINT `{$table}_company_id_fk`
                FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
        }

        // --- rework single-column unique keys to be per-company
        $this->swapUnique('accounts', 'code', ['company_id', 'code']);
        $this->swapUnique('customers', 'code', ['company_id', 'code']);
        $this->swapUnique('suppliers', 'code', ['company_id', 'code']);
        $this->swapUnique('jobs', 'code', ['company_id', 'code']);
        $this->swapUnique('journals', 'journal_no', ['company_id', 'journal_no']);
        $this->swapUnique('account_aliases', 'source_label', ['company_id', 'source_label']);

        // fiscal_periods had unique(year, month)
        $this->dropIndexIfExists('fiscal_periods', 'year');
        $this->db->query('ALTER TABLE `fiscal_periods` ADD UNIQUE KEY `fiscal_periods_company_period` (`company_id`,`year`,`month`)');

        // journal_lines.company_id backfill from parent journal (was set to 1 above; fix any mismatch)
        $this->db->query('UPDATE `journal_lines` jl JOIN `journals` j ON j.id = jl.journal_id SET jl.company_id = j.company_id');
    }

    public function down(): void
    {
        $tables = ['accounts', 'customers', 'suppliers', 'jobs', 'journals', 'journal_lines', 'fiscal_periods', 'import_batches', 'account_aliases'];
        foreach ($tables as $t) {
            try {
                $this->db->query("ALTER TABLE `{$t}` DROP FOREIGN KEY `{$t}_company_id_fk`");
            } catch (\Throwable $e) {
            }
            try {
                $this->forge->dropColumn($t, 'company_id');
            } catch (\Throwable $e) {
            }
        }
        $this->forge->dropTable('companies', true);
    }

    private function swapUnique(string $table, string $oldCol, array $newCols): void
    {
        $this->dropIndexIfExists($table, $oldCol);
        $name = $table . '_' . implode('_', $newCols) . '_uniq';
        $cols = '`' . implode('`,`', $newCols) . '`';
        $this->db->query("ALTER TABLE `{$table}` ADD UNIQUE KEY `{$name}` ({$cols})");
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        $exists = $this->db->query(
            "SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE()
             AND table_name = ? AND index_name = ? LIMIT 1",
            [$table, $index]
        )->getRowArray();
        if ($exists) {
            $this->db->query("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }
    }
}
