<?php

namespace App\Libraries\Api;

use App\Libraries\CustomFields;
use App\Models\CostReviewBatchModel;
use App\Models\CostReviewItemModel;

/**
 * Persistent review queue in front of PurchaseCostUpdate. An n8n flow (or any
 * caller) posts a batch of proposed actual costs; each item is resolved with
 * a dry run and stored for a human to confirm on the Invoice Review page,
 * instead of only being reported to a Teams/Slack channel.
 *
 * Confirming re-resolves + applies for real through PurchaseCostUpdate at
 * confirm time (not at intake time), so drift between intake and confirm -
 * a booking arriving late, a line already actualised another way - is caught
 * rather than blindly replayed.
 *
 * promise_date is staged per review item (cost_review_items.promise_date),
 * but its real home is the "Promise Date" custom field on purchase_invoice
 * (entity purchase_invoice, field_key promise_date) - the same field the
 * purchase invoice form already shows. It's per-invoice, not per-line, so
 * every review item matched to the same invoice pushes into one shared value.
 */
class PurchaseCostReview
{
    private CostReviewBatchModel $batches;
    private CostReviewItemModel $items;
    private CustomFields $customFields;

    public function __construct()
    {
        $this->batches      = model(CostReviewBatchModel::class);
        $this->items        = model(CostReviewItemModel::class);
        $this->customFields = new CustomFields();
    }

    /**
     * @param array<string,mixed> $body  source?, file_name?, file_url?, vendor?,
     *                                   window_days?, items[] (same shape as
     *                                   POST .../costs "costs[]"; "costs" accepted as alias)
     *
     * @return array{status:string, code:int, payload:array<string,mixed>}
     */
    public function intake(array $body): array
    {
        $rawItems = $body['items'] ?? $body['costs'] ?? null;
        if (! is_array($rawItems) || $rawItems === []) {
            return $this->err('Provide a non-empty "items" array.');
        }
        if (count($rawItems) > 2000) {
            return $this->err('At most 2000 items per batch.');
        }

        $dryBody = ['dry_run' => true, 'costs' => $rawItems];
        if (isset($body['window_days'])) {
            $dryBody['window_days'] = $body['window_days'];
        }

        $match = (new PurchaseCostUpdate())->apply($dryBody);
        if ($match['status'] === 'error') {
            return $match;
        }
        $results = $match['payload']['results'];

        $batchId = (int) $this->batches->insert([
            'source'          => trim((string) ($body['source'] ?? 'n8n')) ?: 'n8n',
            'file_name'       => $this->str($body['file_name'] ?? null),
            'file_url'        => $this->str($body['file_url'] ?? null),
            'vendor'          => $this->str($body['vendor'] ?? null),
            'item_count'      => count($rawItems),
            'confirmed_count' => 0,
            'status'          => 'pending',
        ], true);

        foreach (array_values($rawItems) as $ix => $raw) {
            $item = is_array($raw) ? $raw : [];
            $res  = $results[$ix] ?? ['status' => 'error', 'message' => 'No result.'];
            $this->items->insert($this->rowFromResult($item, $res, true, $batchId, $ix + 1));
        }

        return ['status' => 'ok', 'code' => 201, 'payload' => [
            'batch_id' => $batchId,
            'summary'  => $match['payload']['summary'],
        ]];
    }

    /**
     * Confirm a set of review items: re-resolve + write for real through
     * PurchaseCostUpdate, update each item's stored result. Items already
     * confirmed (or belonging to another company) are silently skipped.
     * Whatever promise_date is currently stored on the item (its intake
     * default, or a later edit) rides along in the payload so it lands on
     * the real purchase_invoice_lines row.
     *
     * @param list<int> $itemIds
     *
     * @return array{confirmed: list<int>, failed: array<int,string>}
     */
    public function confirm(array $itemIds, ?int $userId): array
    {
        $rows = [];
        foreach (array_unique(array_map('intval', $itemIds)) as $id) {
            $row = $this->items->find($id);
            if ($row && empty($row['confirmed_at'])) {
                $rows[] = $row;
            }
        }
        if (! $rows) {
            return ['confirmed' => [], 'failed' => []];
        }

        $payloads = array_map(static fn ($row) => json_decode((string) $row['payload'], true) ?: [], $rows);
        $apply    = (new PurchaseCostUpdate())->apply(['costs' => $payloads]);
        $results  = $apply['payload']['results'] ?? [];

        $confirmed = [];
        $failed    = [];
        foreach ($rows as $ix => $row) {
            $res  = $results[$ix] ?? ['status' => 'error', 'message' => 'No result.'];
            $data = $this->rowFromResult($payloads[$ix], $res, false);

            $ok = in_array($res['status'] ?? '', ['applied', 'unchanged'], true);
            if ($ok) {
                $data['confirmed_at'] = date('Y-m-d H:i:s');
                $data['confirmed_by'] = $userId;
                $confirmed[]          = (int) $row['id'];

                $invoiceId = $res['invoice']['id'] ?? $row['invoice_id'] ?? null;
                if ($invoiceId && ! empty($row['promise_date'])) {
                    $this->pushPromiseDate((int) $invoiceId, $row['promise_date']);
                }
            } else {
                $failed[(int) $row['id']] = $res['message'] ?? ('Could not apply (status: ' . ($res['status'] ?? 'error') . ').');
            }
            $this->items->update((int) $row['id'], $data);
        }

        foreach (array_unique(array_column($rows, 'batch_id')) as $bid) {
            $this->refreshBatch((int) $bid);
        }

        return ['confirmed' => $confirmed, 'failed' => $failed];
    }

    /** Re-run the dry-run match for one stored, unconfirmed item. */
    public function recheck(int $itemId): bool
    {
        return $this->recheckMany([$itemId]) > 0;
    }

    /**
     * Re-run the dry-run match for a whole batch's unconfirmed items in one
     * request - e.g. after a matching-engine fix or a supplier record edit,
     * so a multi-hundred-line batch doesn't need re-checking one row at a time.
     * Never touches promise_date - that's only ever set at intake or by an
     * explicit edit.
     *
     * @param list<int> $itemIds
     *
     * @return int number of items updated
     */
    public function recheckMany(array $itemIds): int
    {
        $rows = [];
        foreach (array_unique(array_map('intval', $itemIds)) as $id) {
            $row = $this->items->find($id);
            if ($row && empty($row['confirmed_at'])) {
                $rows[] = $row;
            }
        }
        if (! $rows) {
            return 0;
        }

        $payloads = array_map(static fn ($row) => json_decode((string) $row['payload'], true) ?: [], $rows);
        $match    = (new PurchaseCostUpdate())->apply(['dry_run' => true, 'costs' => $payloads]);
        $results  = $match['payload']['results'] ?? [];

        foreach ($rows as $ix => $row) {
            $res  = $results[$ix] ?? ['status' => 'error'];
            $data = $this->rowFromResult($payloads[$ix], $res, false);
            $this->items->update((int) $row['id'], $data);
        }

        return count($rows);
    }

    /**
     * Set (or clear, with a blank date) the planned-payment date for ONE
     * purchase invoice from the review queue. Writes the invoice's "Promise
     * Date" custom field and mirrors the value onto every review line of that
     * invoice in the batch for display.
     */
    public function setInvoicePromiseDate(int $batchId, int $invoiceId, ?string $date): bool
    {
        $norm = ($date !== null && trim($date) !== '') ? $this->date($date) : null;

        $this->pushPromiseDate($invoiceId, $norm ?? '');
        $this->items
            ->where('company_id', active_company_id())
            ->where('batch_id', $batchId)
            ->where('invoice_id', $invoiceId)
            ->set('promise_date', $norm)
            ->update();

        return true;
    }

    /**
     * Merge the promise date into the invoice's custom fields without
     * disturbing any other custom field already set on it - the underlying
     * save() call expects the full field_key => value map. A blank date
     * clears it.
     */
    private function pushPromiseDate(int $invoiceId, string $date): void
    {
        if (! $this->customFields->hasAny('purchase_invoice')) {
            return;
        }
        $values                 = $this->customFields->valuesFor('purchase_invoice', $invoiceId);
        $values['promise_date'] = $date;
        $this->customFields->save('purchase_invoice', $invoiceId, $values);
    }

    /** Recompute a batch's confirmed_count/status after its items change. */
    private function refreshBatch(int $batchId): void
    {
        $total     = $this->items->where('batch_id', $batchId)->countAllResults();
        $confirmed = $this->items->where('batch_id', $batchId)->where('confirmed_at IS NOT NULL')->countAllResults();
        $this->batches->update($batchId, [
            'confirmed_count' => $confirmed,
            'status'          => ($total > 0 && $confirmed >= $total) ? 'confirmed' : 'pending',
            'confirmed_at'    => ($total > 0 && $confirmed >= $total) ? date('Y-m-d H:i:s') : null,
        ]);
    }

    /**
     * Build a cost_review_items row from an input item + its
     * PurchaseCostUpdate result. $isIntake adds the batch-only columns
     * (payload, party_name, description, requested_amount, and the initial
     * promise_date default); confirm/recheck pass false and never touch
     * promise_date, so a user's edit survives every re-match.
     *
     * @param array<string,mixed> $item
     * @param array<string,mixed> $res
     *
     * @return array<string,mixed>
     */
    private function rowFromResult(array $item, array $res, bool $isIntake, ?int $batchId = null, ?int $lineNo = null): array
    {
        // the matched line's own service_date is authoritative once matched;
        // fall back to what was submitted when nothing matched yet
        $serviceDate = $this->date($res['service_date'] ?? ($item['service_date'] ?? null));

        $row = [
            'match_type'          => $res['match'] ?? null,
            'match_status'        => $res['status'] ?? 'error',
            'match_message'       => $res['message'] ?? null,
            'matched_budget'      => isset($res['budget']) ? (float) $res['budget'] : null,
            'variance'            => isset($res['variance']) ? (float) $res['variance'] : null,
            'over_budget'         => ! empty($res['over_budget']) ? 1 : 0,
            'invoice_id'          => $res['invoice']['id'] ?? null,
            'invoice_internal_no' => $res['invoice']['internal_no'] ?? null,
            'line_id'             => $res['line_id'] ?? null,
            'job_id'              => $res['job_id'] ?? null,
            'service_date'        => $serviceDate,
        ];

        if ($isIntake) {
            $cost = $item['cost'] ?? $item['amount'] ?? null;
            $row  = [
                'batch_id'         => $batchId,
                'line_no'          => $lineNo,
                'payload'          => json_encode($item),
                'party_name'       => $this->str($item['party_name'] ?? null),
                'description'      => $this->str($item['description'] ?? null),
                'requested_amount' => is_numeric($cost) ? (float) $cost : null,
                // no default - the payer sets it per purchase invoice in Review
                'promise_date'     => null,
            ] + $row;
        }

        return $row;
    }

    private function str($v): ?string
    {
        $s = trim((string) ($v ?? ''));

        return $s !== '' ? $s : null;
    }

    private function date($v): ?string
    {
        if (! is_string($v) && ! is_numeric($v)) {
            return null;
        }
        $s = trim((string) $v);

        return $s !== '' && ($t = strtotime($s)) ? date('Y-m-d', $t) : null;
    }

    /** @return array{status:string, code:int, payload:array<string,mixed>} */
    private function err(string $msg): array
    {
        return ['status' => 'error', 'code' => 422, 'payload' => ['message' => $msg]];
    }
}
