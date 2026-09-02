<?php

namespace App\Models;

class PurchasePaymentModel extends TenantModel
{
    protected $table         = 'purchase_payments';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields = [
        'payment_no', 'supplier_id', 'payment_date', 'bank_account_id',
        'currency_id', 'exchange_rate', 'amount', 'amount_base', 'reference',
        'status', 'journal_id', 'created_by', 'kind', 'unapplied', 'unapplied_base',
    ];

    public function listing(array $filters = [], int $perPage = 25)
    {
        $b = $this->select('purchase_payments.*, suppliers.name AS supplier_name, accounts.name AS bank_name')
            ->join('suppliers', 'suppliers.id = purchase_payments.supplier_id', 'left')
            ->join('accounts', 'accounts.id = purchase_payments.bank_account_id', 'left');
        if (! empty($filters['supplier_id'])) {
            $b->where('purchase_payments.supplier_id', $filters['supplier_id']);
        }
        if (! empty($filters['q'])) {
            $b->groupStart()->like('purchase_payments.payment_no', $filters['q'])
                ->orLike('purchase_payments.reference', $filters['q'])->groupEnd();
        }
        if (! empty($filters['kind'])) {
            if ($filters['kind'] === 'deposit_open') {
                $b->where('purchase_payments.kind', 'deposit')->where('purchase_payments.unapplied >', 0.005);
            } else {
                $b->where('purchase_payments.kind', $filters['kind']); // deposit | settlement
            }
        }

        return $b->orderBy('purchase_payments.payment_date', 'DESC')->orderBy('purchase_payments.id', 'DESC')
            ->paginate($perPage);
    }

    /**
     * Posted deposits for a supplier that still have money left to apply.
     *
     * @return list<array<string,mixed>>
     */
    public function unappliedDepositsFor(int $supplierId): array
    {
        if ($supplierId <= 0) {
            return [];
        }

        return $this->select('purchase_payments.*, cur.code AS currency_code')
            ->join('currencies cur', 'cur.id = purchase_payments.currency_id', 'left')
            ->where('purchase_payments.supplier_id', $supplierId)
            ->where('purchase_payments.kind', 'deposit')
            ->where('purchase_payments.status', 'posted')
            ->where('purchase_payments.unapplied >', 0.005)
            ->orderBy('purchase_payments.payment_date', 'ASC')
            ->findAll();
    }

    public function nextNo(): string
    {
        $stem = 'PP-' . date('ym') . '-';
        $row  = $this->like('payment_no', $stem, 'after')->orderBy('payment_no', 'DESC')->first();
        $seq  = $row ? (int) substr($row['payment_no'], -4) + 1 : 1;

        return $stem . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
