<?php

namespace App\Controllers;

use App\Libraries\Accounting\Ledger;
use App\Libraries\CustomFields;
use CodeIgniter\Model;

/**
 * Shared CRUD + statement behaviour for Customers and Suppliers.
 */
abstract class PartyController extends BaseController
{
    /** @var 'customer'|'supplier' */
    protected string $kind;
    protected string $route;   // e.g. 'customers'
    protected string $label;   // e.g. 'Customer'

    protected CustomFields $cf;

    abstract protected function model(): Model;

    public function __construct()
    {
        $this->cf = new CustomFields();
    }

    private function guard(): bool
    {
        return user_can('masterdata.manage');
    }

    public function index()
    {
        $defs = $this->cf->defs($this->kind);
        $q    = trim((string) $this->request->getGet('q'));
        $cf   = [];
        foreach ((array) $this->request->getGet('cf') as $k => $v) {
            $cf[(string) $k] = is_scalar($v) ? trim((string) $v) : '';
        }

        $all  = $this->model()->orderBy('name', 'ASC')->findAll();
        $vals = $this->cf->valuesForMany($this->kind, array_column($all, 'id'));

        $rows = array_values(array_filter($all, static function ($r) use ($q, $cf, $vals, $defs) {
            if ($q !== '') {
                $hay = mb_strtolower(implode(' ', array_merge(
                    [$r['code'], $r['name'], $r['email'] ?? '', $r['phone'] ?? '', $r['npwp'] ?? '', $r['address'] ?? ''],
                    array_values($vals[$r['id']] ?? [])
                )));
                if (! str_contains($hay, mb_strtolower($q))) {
                    return false;
                }
            }
            foreach ($defs as $d) {
                $sel = $cf[$d['field_key']] ?? '';
                if ($sel === '') {
                    continue;
                }
                $rv = (string) ($vals[$r['id']][$d['field_key']] ?? '');
                if (in_array($d['type'], ['select', 'checkbox'], true)) {
                    if ($rv !== $sel) {
                        return false;
                    }
                } elseif (! str_contains(mb_strtolower($rv), mb_strtolower($sel))) {
                    return false;
                }
            }

            return true;
        }));

        return view('parties/index', [
            'title'  => $this->label . 's',
            'rows'   => $rows,
            'total'  => count($all),
            'route'  => $this->route,
            'label'  => $this->label,
            'cfDefs' => $defs,
            'cfVals' => $vals,
            'q'      => $q,
            'cf'     => $cf,
        ]);
    }

    public function new()
    {
        if (! $this->guard()) {
            return redirect()->to($this->route)->with('error', 'Not allowed.');
        }

        return view('parties/form', [
            'title'    => 'New ' . $this->label,
            'row'      => null,
            'route'    => $this->route,
            'label'    => $this->label,
            'cfDefs'   => $this->cf->defs($this->kind),
            'cfValues' => [],
        ]);
    }

    public function create()
    {
        if (! $this->guard()) {
            return redirect()->to($this->route)->with('error', 'Not allowed.');
        }
        $model = $this->model();
        $data  = $this->payload();
        if (! empty($data['code']) && $model->codeTaken($data['code'])) {
            return redirect()->back()->withInput()->with('errors', ['Code ' . $data['code'] . ' is already used in this company.']);
        }
        $cf    = (array) $this->request->getPost('cf');
        $cfErr = $this->cf->validate($this->kind, $cf);
        if ($cfErr) {
            return redirect()->back()->withInput()->with('errors', $cfErr);
        }
        if (! $model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $model->errors() ?: ['Insert failed.']);
        }
        $this->cf->save($this->kind, (int) $model->getInsertID(), $cf);

        return redirect()->to($this->route)->with('message', $this->label . ' created.');
    }

    public function edit(int $id)
    {
        if (! $this->guard()) {
            return redirect()->to($this->route)->with('error', 'Not allowed.');
        }
        $row = $this->model()->find($id);
        if (! $row) {
            return redirect()->to($this->route)->with('error', 'Not found.');
        }

        return view('parties/form', [
            'title'    => 'Edit ' . $row['name'],
            'row'      => $row,
            'route'    => $this->route,
            'label'    => $this->label,
            'cfDefs'   => $this->cf->defs($this->kind),
            'cfValues' => $this->cf->valuesFor($this->kind, $id),
        ]);
    }

    public function update(int $id)
    {
        if (! $this->guard()) {
            return redirect()->to($this->route)->with('error', 'Not allowed.');
        }
        $model = $this->model();
        if (! $model->find($id)) {
            return redirect()->to($this->route)->with('error', 'Not found.');
        }
        $data = $this->payload();
        if (! empty($data['code']) && $model->codeTaken($data['code'], $id)) {
            return redirect()->back()->withInput()->with('errors', ['Code ' . $data['code'] . ' is already used in this company.']);
        }
        $cf    = (array) $this->request->getPost('cf');
        $cfErr = $this->cf->validate($this->kind, $cf);
        if ($cfErr) {
            return redirect()->back()->withInput()->with('errors', $cfErr);
        }
        if (! $model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }
        $this->cf->save($this->kind, $id, $cf);

        return redirect()->to($this->route)->with('message', $this->label . ' updated.');
    }

    public function show(int $id)
    {
        $row = $this->model()->find($id);
        if (! $row) {
            return redirect()->to($this->route)->with('error', 'Not found.');
        }

        $from = $this->request->getGet('from') ?: date('Y-01-01');
        $to   = $this->request->getGet('to') ?: date('Y-m-d');

        $statement = (new Ledger())->partyStatement($this->kind, $id, $from, $to);

        return view('parties/statement', [
            'title'     => $row['name'] . ' — Statement',
            'row'       => $row,
            'route'     => $this->route,
            'label'     => $this->label,
            'kind'      => $this->kind,
            'from'      => $from,
            'to'        => $to,
            'statement' => $statement,
            'cfDefs'    => $this->cf->defs($this->kind),
            'cfValues'  => $this->cf->valuesFor($this->kind, $id),
        ]);
    }

    protected function payload(): array
    {
        return [
            'code'      => trim((string) $this->request->getPost('code')),
            'name'      => trim((string) $this->request->getPost('name')),
            'email'     => trim((string) $this->request->getPost('email')) ?: null,
            'phone'     => trim((string) $this->request->getPost('phone')) ?: null,
            'npwp'      => trim((string) $this->request->getPost('npwp')) ?: null,
            'address'   => trim((string) $this->request->getPost('address')) ?: null,
            'is_active' => $this->request->getPost('is_active') !== null ? 1 : 0,
        ];
    }
}
