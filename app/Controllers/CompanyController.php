<?php

namespace App\Controllers;

use App\Models\CompanyModel;

class CompanyController extends BaseController
{
    private CompanyModel $companies;

    public function __construct()
    {
        $this->companies = model(CompanyModel::class);
    }

    private function guard(): bool
    {
        return user_can('settings.manage');
    }

    /** Switch the active company for this session (within the user's branches). */
    public function switch()
    {
        $id = (int) $this->request->getPost('company_id');
        $c  = $this->companies->find($id);
        if ($c && (int) $c['is_active'] === 1 && user_can_company($id)) {
            session()->set('active_company_id', $id);
        } elseif ($c && ! user_can_company($id)) {
            session()->setFlashdata('error', lang('App.not_allowed'));
        }

        return redirect()->to($this->request->getPost('return') ?: '/');
    }

    public function index()
    {
        if (! $this->guard()) {
            return redirect()->to('/')->with('error', 'Not allowed.');
        }

        return view('companies/index', [
            'title'     => 'Companies',
            'companies' => $this->companies->orderBy('code')->findAll(),
        ]);
    }

    public function new()
    {
        if (! $this->guard()) {
            return redirect()->to('companies')->with('error', 'Not allowed.');
        }

        return view('companies/form', [
            'title'      => 'New Company',
            'company'    => null,
            'currencies' => model(\App\Models\CurrencyModel::class)->where('is_active', 1)->orderBy('code')->findAll(),
        ]);
    }

    public function create()
    {
        if (! $this->guard()) {
            return redirect()->to('companies')->with('error', 'Not allowed.');
        }
        $id = $this->companies->insert($this->payload(), true);
        if (! $id) {
            return redirect()->back()->withInput()->with('errors', $this->companies->errors());
        }
        $this->handleLogo((int) $id);

        return redirect()->to('companies')->with('message', 'Company created. Add its chart of accounts, then switch to it.');
    }

    public function edit(int $id)
    {
        if (! $this->guard()) {
            return redirect()->to('companies')->with('error', 'Not allowed.');
        }
        $company = $this->companies->find($id);
        if (! $company) {
            return redirect()->to('companies')->with('error', 'Company not found.');
        }

        return view('companies/form', [
            'title'      => 'Edit ' . $company['name'],
            'company'    => $company,
            'currencies' => model(\App\Models\CurrencyModel::class)->where('is_active', 1)->orderBy('code')->findAll(),
        ]);
    }

    public function update(int $id)
    {
        if (! $this->guard()) {
            return redirect()->to('companies')->with('error', 'Not allowed.');
        }
        if (! $this->companies->find($id)) {
            return redirect()->to('companies')->with('error', 'Company not found.');
        }
        $data       = $this->payload();
        $data['id'] = $id; // lets is_unique's {id} placeholder exclude this row
        if (! $this->companies->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->companies->errors());
        }
        $this->handleLogo($id);

        return redirect()->to('companies')->with('message', 'Company updated.');
    }

    private function payload(): array
    {
        return [
            'code'          => trim((string) $this->request->getPost('code')),
            'name'          => trim((string) $this->request->getPost('name')),
            'legal_name'    => trim((string) $this->request->getPost('legal_name')) ?: null,
            'npwp'          => trim((string) $this->request->getPost('npwp')) ?: null,
            'address'       => trim((string) $this->request->getPost('address')) ?: null,
            'base_currency' => strtoupper(trim((string) $this->request->getPost('base_currency'))) ?: 'IDR',
            'parent_id'     => $this->request->getPost('parent_id') ?: null,
            'is_active'     => $this->request->getPost('is_active') !== null ? 1 : 0,
        ];
    }

    private function handleLogo(int $id): void
    {
        $company = $this->companies->find($id);
        if ($this->request->getPost('remove_logo')) {
            if ($company['logo_path'] && is_file(FCPATH . $company['logo_path'])) {
                @unlink(FCPATH . $company['logo_path']);
            }
            $this->companies->update($id, ['logo_path' => null]);

            return;
        }
        $file = $this->request->getFile('logo');
        if (! $file || ! $file->isValid() || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return;
        }
        $ext = strtolower($file->getExtension() ?: $file->getClientExtension());
        if (! in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'], true) || $file->getSize() > 2 * 1024 * 1024) {
            session()->setFlashdata('error', 'Logo must be an image under 2 MB.');

            return;
        }
        $dir = FCPATH . 'assets/branding';
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        if ($company['logo_path'] && is_file(FCPATH . $company['logo_path'])) {
            @unlink(FCPATH . $company['logo_path']);
        }
        $name = 'company-' . $id . '-' . time() . '.' . $ext;
        $file->move($dir, $name, true);
        $this->companies->update($id, ['logo_path' => 'assets/branding/' . $name]);
    }
}
