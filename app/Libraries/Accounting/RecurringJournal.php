<?php

namespace App\Libraries\Accounting;

use App\Models\CurrencyModel;
use App\Models\ExchangeRateModel;
use App\Models\JournalModel;
use App\Models\RecurringJournalLineModel;
use App\Models\RecurringJournalModel;

/**
 * Turns a recurring journal template into a DRAFT general journal for review.
 * Nothing is posted here - the caller sends the user to the journal editor.
 */
class RecurringJournal
{
    /**
     * @return array{ok: bool, id?: int, errors?: list<string>}
     */
    public static function generate(int $templateId, ?string $entryDate = null): array
    {
        $templates = model(RecurringJournalModel::class);
        $tpl       = $templates->find($templateId);
        if (! $tpl) {
            return ['ok' => false, 'errors' => ['Template not found.']];
        }
        if ((int) $tpl['is_active'] !== 1) {
            return ['ok' => false, 'errors' => ['This template is inactive.']];
        }
        if (! in_array($tpl['source'], JournalModel::MANUAL_SOURCES, true)) {
            return ['ok' => false, 'errors' => ['This template has an invalid source.']];
        }

        $lines = model(RecurringJournalLineModel::class)->forTemplate($templateId);
        if ($lines === []) {
            return ['ok' => false, 'errors' => ['Add at least one line to the template first.']];
        }

        $entryDate = $entryDate ?: ($tpl['next_date'] ?: date('Y-m-d'));
        $ts        = strtotime($entryDate);
        $desc      = strtr($tpl['description'], [
            '{month}' => date('F', $ts),
            '{year}'  => date('Y', $ts),
            '{date}'  => date('Y-m-d', $ts),
        ]);

        $currencyId = (int) $tpl['currency_id'];
        $rate       = model(CurrencyModel::class)->isBase($currencyId)
            ? 1.0
            : model(ExchangeRateModel::class)->rateFor($currencyId, $entryDate);

        $rawLines = array_map(static fn ($l) => [
            'account_id'  => (int) $l['account_id'],
            'memo'        => $l['memo'],
            'debit'       => (float) $l['debit'],
            'credit'      => (float) $l['credit'],
            'customer_id' => $l['customer_id'] ?: null,
            'supplier_id' => $l['supplier_id'] ?: null,
            'job_id'      => $l['job_id'] ?: null,
        ], $lines);

        $res = (new JournalPoster())->save([
            'entry_date'    => $entryDate,
            'reference'     => $tpl['reference'],
            'description'   => $desc,
            'source'        => $tpl['source'],
            'currency_id'   => $currencyId,
            'exchange_rate' => $rate,
        ], $rawLines);

        if (! $res['ok']) {
            return $res;
        }

        $templates->update($templateId, [
            'last_generated_on' => $entryDate,
            'last_journal_id'   => (int) $res['id'],
        ]);
        if ($tpl['frequency'] !== 'manual') {
            $templates->advance($templateId);
        }

        return ['ok' => true, 'id' => (int) $res['id']];
    }
}
