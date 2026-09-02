<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePurchases extends Migration
{
    public function up(): void
    {
        // --- purchase invoices (header)
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'internal_no'     => ['type' => 'VARCHAR', 'constraint' => 30],   // our running number
            'supplier_ref'    => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true], // supplier's invoice no
            'supplier_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'invoice_date'    => ['type' => 'DATE'],
            'due_date'        => ['type' => 'DATE', 'null' => true],
            'currency_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'exchange_rate'   => ['type' => 'DECIMAL', 'constraint' => '18,8', 'default' => 1],
            'description'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'subtotal'        => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0], // txn ccy
            'ppn_amount'      => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0],
            'pph_amount'      => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0], // withheld, reduces payable
            'total'           => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0], // subtotal + ppn - pph
            'total_base'      => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0], // IDR
            'paid_base'       => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0],
            'status'          => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'draft'], // draft|posted|partial|paid|void
            'journal_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_by'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'posted_by'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'posted_at'       => ['type' => 'DATETIME', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'internal_no']);
        $this->forge->addKey(['company_id', 'status']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('currency_id', 'currencies', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('purchase_invoices');

        // --- purchase invoice lines
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'invoice_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'line_no'     => ['type' => 'INT', 'constraint' => 4, 'default' => 1],
            'account_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'job_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'amount'      => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0],
            'amount_base' => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('invoice_id');
        $this->forge->addForeignKey('invoice_id', 'purchase_invoices', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('account_id', 'accounts', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('job_id', 'jobs', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('purchase_invoice_lines');

        // --- purchase payments (header)
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'company_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'payment_no'      => ['type' => 'VARCHAR', 'constraint' => 30],
            'supplier_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'payment_date'    => ['type' => 'DATE'],
            'bank_account_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'currency_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'exchange_rate'   => ['type' => 'DECIMAL', 'constraint' => '18,8', 'default' => 1],
            'amount'          => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0],
            'amount_base'     => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0],
            'reference'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'status'          => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'posted'], // posted|void
            'journal_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_by'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['company_id', 'payment_no']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('bank_account_id', 'accounts', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('purchase_payments');

        // --- payment -> invoice allocations
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'payment_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'invoice_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'amount_base' => ['type' => 'DECIMAL', 'constraint' => '20,2', 'default' => 0],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('payment_id');
        $this->forge->addKey('invoice_id');
        $this->forge->addForeignKey('payment_id', 'purchase_payments', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('invoice_id', 'purchase_invoices', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('purchase_payment_allocations');
    }

    public function down(): void
    {
        $this->forge->dropTable('purchase_payment_allocations', true);
        $this->forge->dropTable('purchase_payments', true);
        $this->forge->dropTable('purchase_invoice_lines', true);
        $this->forge->dropTable('purchase_invoices', true);
    }
}
