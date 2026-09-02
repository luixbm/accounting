<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Two more columns on purchase-invoice lines for budget-vs-actual analysis:
 *
 *  - `budget_amount` freezes the original budget figure at creation time, so it
 *    survives the budget -> actual flip and stays available for variance reports
 *    ("which suppliers ran over budget, and why").
 *  - `cost_remark` is a free-text note explaining an over-budget actual.
 *
 * Existing lines are backfilled with budget_amount = current amount.
 */
class AddLineBudgetAndRemark extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('purchase_invoice_lines', [
            'budget_amount' => ['type' => 'DECIMAL', 'constraint' => '20,2', 'null' => true, 'after' => 'amount_base'],
            'cost_remark'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'supp_inv_date'],
        ]);
        $this->db->query('UPDATE purchase_invoice_lines SET budget_amount = amount WHERE budget_amount IS NULL');
    }

    public function down(): void
    {
        $this->forge->dropColumn('purchase_invoice_lines', ['budget_amount', 'cost_remark']);
    }
}
