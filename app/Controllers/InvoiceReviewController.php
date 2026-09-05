<?php

namespace App\Controllers;

use App\Libraries\Api\PurchaseCostReview;
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

    public function index()
    {
        if (! $this->guard()) {
            return $this->deny();
        }

        $status = $this->request->getGet('status') === 'all' ? 'all' : 'open';
        $q      = trim((string) $this->request->getGet('q'));

        $b = $this->batches->orderBy('id', 'DESC');
        if ($status === 'open') {
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
        $batches = $b->findAll(200);

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

        return view('invoice_review/index', [
            'title'        => lang('Review.title'),
            'batches'      => $batches,
            'itemsByBatch' => $itemsByBatch,
            'status'       => $status,
            'q'            => $q,
        ]);
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
            ->findColumn('id') ?: [];

        return $this->backWith((new PurchaseCostReview())->confirm($ids, auth()->id()));
    }

    public function recheck(int $id)
    {
        if (! $this->guard()) {
            return $this->deny();
        }
        (new PurchaseCostReview())->recheck($id);

        return redirect()->back()->with('message', lang('Review.rechecked'));
    }

    public function promiseDate(int $id)
    {
        if (! $this->guard()) {
            return $this->deny();
        }
        (new PurchaseCostReview())->setPromiseDate($id, $this->request->getPost('promise_date'));

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
    private function backWith(array $r)
    {
        $n = count($r['confirmed']);
        if ($n === 0 && ! $r['failed']) {
            return redirect()->back()->with('error', lang('Review.nothing_to_confirm'));
        }
        $msg = lang('Review.confirmed_n', [$n]);
        if ($r['failed']) {
            return redirect()->back()->with('error', $msg . ' ' . lang('Review.failed_n', [count($r['failed'])]));
        }

        return redirect()->back()->with('message', $msg);
    }
}
