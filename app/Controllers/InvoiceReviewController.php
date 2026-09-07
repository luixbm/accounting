<?php

namespace App\Controllers;

use App\Libraries\Api\PurchaseCostReview;
use App\Libraries\CustomFields;
use App\Models\CostReviewBatchModel;
use App\Models\CostReviewItemModel;
use App\Models\JobModel;

/**
 * "Purchases -> Invoice Review": the human side of the n8n supplier-invoice
 * pipeline. Batches land here via POST /api/v1/purchase/lines/review, each
 * already resolved with a dry run; a person confirms (writes for real
 * through PurchaseCostUpdate) or leaves ambiguous/not-found lines for later.
 */
class InvoiceReviewController extends BaseController
{
    private CostReviewBatchModel $batches;
    private CostReviewItemModel $items;

    public function __construct()
    {
        $this->batches = model(CostReviewBatchModel::class);
        $this->items   = model(CostReviewItemModel::class);
    }

    private function guard(): bool
    {
        return user_can('journal.create');
    }

    private function deny()
    {
        return redirect()->to('purchases/review')->with('error', lang('Review.not_allowed'));
    }

    public const STATES = ['confirmed', 'matched', 'overbudget', 'pending', 'error'];

    /** The line's status key, mirroring the $badge() logic in the view. */
    public static function statusKey(array $it): string
    {
        if (! empty($it['confirmed_at'])) {
            return 'confirmed';
        }

        return match ($it['match_status']) {
            'would_apply', 'unchanged' => ! empty($it['over_budget']) ? 'overbudget' : 'matched',
            'ambiguous', 'not_found'   => 'pending',
            'applied'                  => 'confirmed',
            default                    => 'error',
        };
    }

    public function index()
    {
        if (! $this->guard()) {
            return $this->deny();
        }

        $status = $this->request->getGet('status') === 'all' ? 'all' : 'open';
        $q      = trim((string) $this->request->getGet('q'));
        $state  = in_array($this->request->getGet('state'), self::STATES, true) ? $this->request->getGet('state') : 'all';

        $b = $this->batches->orderBy('id', 'DESC');
        // A specific line-status filter reaches across every batch (confirmed
        // lines can sit in an otherwise fully-confirmed batch).
        if ($status === 'open' && $state === 'all') {
            $b->where('status !=', 'confirmed');
        }
        if ($q !== '') {
            $co             = active_company_id();
            $matchByVendor  = $this->batches->where('company_id', $co)
                ->groupStart()->like('vendor', $q)->orLike('file_name', $q)->groupEnd()
                ->findColumn('id') ?: [];
            $matchByParty   = $this->items->where('company_id', $co)
                ->groupStart()->like('party_name', $q)->orLike('description', $q)->groupEnd()
                ->findColumn('batch_id') ?: [];
            $matchingIds    = array_unique(array_merge($matchByVendor, $matchByParty));
            $b->whereIn('id', $matchingIds ?: [0]);
        }
        $batches          = $b->findAll(200);
        $confirmedBatches = count(array_filter($batches, static fn ($x) => ($x['status'] ?? '') === 'confirmed'));

        $itemsByBatch = [];
        $batchIds     = array_column($batches, 'id');
        if ($batchIds) {
            $rows = $this->items->whereIn('batch_id', $batchIds)->orderBy('line_no')->findAll();

            $jobIds = array_values(array_unique(array_filter(array_column($rows, 'job_id'))));
            $starts = [];
            if ($jobIds) {
                foreach (model(JobModel::class)->whereIn('id', $jobIds)->findAll() as $j) {
                    $starts[(int) $j['id']] = $j['start_date'];
                }
            }

            foreach ($rows as $it) {
                $it['arrival_date']              = $it['job_id'] ? ($starts[(int) $it['job_id']] ?? null) : null;
                $itemsByBatch[(int) $it['batch_id']][] = $it;
            }
        }

        // Line-level filtering: search matches a traveller/description, and the
        // status filter narrows to one badge. Batches with no surviving line drop.
        if ($q !== '' || $state !== 'all') {
            foreach ($itemsByBatch as $bid => $list) {
                $kept = array_values(array_filter($list, static function ($it) use ($q, $state) {
                    if ($q !== ''
                        && stripos((string) $it['party_name'], $q) === false
                        && stripos((string) $it['description'], $q) === false) {
                        return false;
                    }

                    return $state === 'all' || self::statusKey($it) === $state;
                }));
                if ($kept) {
                    $itemsByBatch[$bid] = $kept;
                } else {
                    unset($itemsByBatch[$bid]);
                }
            }
            $batches = array_values(array_filter($batches, static fn ($b) => isset($itemsByBatch[(int) $b['id']])));
        }

        // Current promise date per matched invoice, read from its custom field.
        $invoiceIds = [];
        foreach ($itemsByBatch as $list) {
            foreach ($list as $it) {
                if (! empty($it['invoice_id'])) {
                    $invoiceIds[(int) $it['invoice_id']] = true;
                }
            }
        }
        $promiseByInvoice = [];
        if ($invoiceIds) {
            foreach ((new CustomFields())->valuesForMany('purchase_invoice', array_keys($invoiceIds)) as $invId => $vals) {
                $promiseByInvoice[(int) $invId] = (string) ($vals['promise_date'] ?? '');
            }
        }

        return view('invoice_review/index', [
            'title'        => lang('Review.title'),
            'batches'      => $batches,
            'itemsByBatch' => $itemsByBatch,
            'promiseByInvoice' => $promiseByInvoice,
            'status'       => $status,
            'state'        => $state,
            'q'            => $q,
            'confirmedBatches' => $confirmedBatches,
        ]);
    }

    /**
     * Drop every fully-confirmed batch (and its items) from the staging queue.
     * The confirmed items already wrote their effect to the real invoices, so
     * this only tidies the list.
     */
    public function clearConfirmed()
    {
        if (! $this->guard()) {
            return $this->deny();
        }

        $ids = $this->batches->where('company_id', active_company_id())->where('status', 'confirmed')->findColumn('id') ?: [];
        if ($ids) {
            $this->items->whereIn('batch_id', $ids)->delete();
            $this->batches->whereIn('id', $ids)->delete();
        }

        return redirect()->to('purchases/review')->with('message', lang('Review.confirmed_cleared', [count($ids)]));
    }

    /**
     * Remove a batch from the review queue. This only tidies the staging
     * list - items already confirmed already wrote their effect to the real
     * purchase invoice, and deleting the batch here does not touch that.
     */
    public function deleteBatch(int $batchId)
    {
        if (! $this->guard()) {
            return $this->deny();
        }

        $batch = $this->batches->find($batchId);
        if (! $batch) {
            return redirect()->back()->with('error', lang('Review.not_allowed'));
        }
        $this->items->where('batch_id', $batchId)->delete();
        $this->batches->delete($batchId);

        return redirect()->back()->with('message', lang('Review.batch_deleted'));
    }

    public function confirmItem(int $id)
    {
        if (! $this->guard()) {
            return $this->deny();
        }

        // An over-budget line whose amount still differs from the supplier's is
        // never auto-applied - send the user to the invoice to decide.
        $it = $this->items->find($id);
        if ($it && empty($it['confirmed_at']) && ! empty($it['over_budget'])
            && $it['match_status'] === 'would_apply' && ! empty($it['invoice_id'])) {
            return redirect()->to('purchases/' . (int) $it['invoice_id'] . '/edit')->with('message', lang('Review.overbudget_open', [
                $it['description'] ?: ('#' . $it['line_no']),
                money((float) ($it['requested_amount'] ?? 0)),
                money((float) ($it['matched_budget'] ?? 0)),
            ]));
        }

        return $this->backWith((new PurchaseCostReview())->confirm([$id], auth()->id()));
    }

    public function confirmBatch(int $batchId)
    {
        if (! $this->guard()) {
            return $this->deny();
        }

        $ids = $this->items
            ->where('company_id', active_company_id())
            ->where('batch_id', $batchId)
            ->where('confirmed_at IS NULL')
            ->whereIn('match_status', ['would_apply', 'unchanged'])
            ->groupStart()->where('over_budget', 0)->orWhere('match_status', 'unchanged')->groupEnd()
            ->findColumn('id') ?: [];

        $r    = (new PurchaseCostReview())->confirm($ids, auth()->id());
        $left = $this->items
            ->where('company_id', active_company_id())
            ->where('batch_id', $batchId)
            ->where('confirmed_at IS NULL')
            ->where('over_budget', 1)
            ->where('match_status', 'would_apply')
            ->countAllResults();

        return $this->backWith($r, $left > 0 ? lang('Review.overbudget_left_n', [$left]) : '');
    }

    public function recheck(int $id)
    {
        if (! $this->guard()) {
            return $this->deny();
        }
        (new PurchaseCostReview())->recheck($id);

        return redirect()->back()->with('message', lang('Review.rechecked'));
    }

    /** Set the promise / planned-payment date for one purchase invoice in a batch. */
    public function invoicePromiseDate(int $batchId, int $invoiceId)
    {
        if (! $this->guard()) {
            return $this->deny();
        }
        $has = $this->items
            ->where('company_id', active_company_id())
            ->where('batch_id', $batchId)
            ->where('invoice_id', $invoiceId)
            ->countAllResults();
        if ($has === 0) {
            return redirect()->back()->with('error', lang('Review.not_allowed'));
        }

        (new PurchaseCostReview())->setInvoicePromiseDate($batchId, $invoiceId, $this->request->getPost('promise_date'));

        return redirect()->back()->with('message', lang('Review.promise_saved'));
    }

    public function recheckBatch(int $batchId)
    {
        if (! $this->guard()) {
            return $this->deny();
        }

        $ids = $this->items
            ->where('company_id', active_company_id())
            ->where('batch_id', $batchId)
            ->where('confirmed_at IS NULL')
            ->findColumn('id') ?: [];

        $n = (new PurchaseCostReview())->recheckMany($ids);

        return redirect()->back()->with('message', lang('Review.rechecked_n', [$n]));
    }

    /** @param array{confirmed: list<int>, failed: array<int,string>} $r */
    private function backWith(array $r, string $extra = '')
    {
        $n = count($r['confirmed']);
        if ($n === 0 && ! $r['failed'] && $extra === '') {
            return redirect()->back()->with('error', lang('Review.nothing_to_confirm'));
        }
        $msg = trim(($n > 0 ? lang('Review.confirmed_n', [$n]) : '') . ' ' . $extra);
        if ($r['failed']) {
            return redirect()->back()->with('error', trim($msg . ' ' . lang('Review.failed_n', [count($r['failed'])])));
        }

        return redirect()->back()->with('message', $msg);
    }
}
