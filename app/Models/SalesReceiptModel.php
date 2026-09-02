<?php

namespace App\Models;

class SalesReceiptModel extends TenantModel
{
    protected $table         = 'sales_receipts';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields = [
        'receipt_no', 'customer_id', 'receipt_date', 'bank_account_id',
        'currency_id', 'exchange_rate', 'amount', 'amount_base', 'reference',
        'status', 'journal_id', 'created_by', 'kind', 'unapplied', 'unapplied_base',
    ];

    public function listing(array $filters = [], int $perPage = 25)
    {
        $b = $this->select('sales_receipts.*, customers.name AS customer_name, accounts.name AS bank_name')
            ->join('customers', 'customers.id = sales_receipts.customer_id', 'left')
            ->join('accounts', 'accounts.id = sales_receipts.bank_account_id', 'left');
        if (! empty($filters['customer_id'])) {
            $b->where('sales_receipts.customer_id', $filters['customer_id']);
        }
        if (! empty($filters['q'])) {
            $b->groupStart()->like('sales_receipts.receipt_no', $filters['q'])
                ->orLike('sales_receipts.reference', $filters['q'])->groupEnd();
        }
        if (! empty($filters['kind'])) {
            if ($filters['kind'] === 'deposit_open') {
                $b->where('sales_receipts.kind', 'deposit')->where('sales_receipts.unapplied >', 0.005);
            } else {
                $b->where('sales_receipts.kind', $filters['kind']); // deposit | settlement
            }
        }

        return $b->orderBy('sales_receipts.receipt_date', 'DESC')->orderBy('sales_receipts.id', 'DESC')
            ->paginate($perPage);
    }

    /**
     * Posted down payments for a customer that still have money left to apply.
     *
     * @return list<array<string,mixed>>
     */
    public function unappliedDepositsFor(int $customerId): array
    {
        if ($customerId <= 0) {
            return [];
        }

        return $this->select('sales_receipts.*, cur.code AS currency_code')
            ->join('currencies cur', 'cur.id = sales_receipts.currency_id', 'left')
            ->where('sales_receipts.customer_id', $customerId)
            ->where('sales_receipts.kind', 'deposit')
            ->where('sales_receipts.status', 'posted')
            ->where('sales_receipts.unapplied >', 0.005)
            ->orderBy('sales_receipts.receipt_date', 'ASC')
            ->findAll();
    }

    public function nextNo(): string
    {
        $stem = 'SR-' . date('ym') . '-';
        $row  = $this->like('receipt_no', $stem, 'after')->orderBy('receipt_no', 'DESC')->first();
        $seq  = $row ? (int) substr($row['receipt_no'], -4) + 1 : 1;

        return $stem . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
