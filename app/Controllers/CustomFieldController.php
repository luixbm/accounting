<?php

namespace App\Controllers;

use App\Libraries\CustomFields;
use App\Models\CustomFieldModel;

class CustomFieldController extends BaseController
{
    private CustomFieldModel $model;

    public function __construct()
    {
        $this->model = model(CustomFieldModel::class);
    }

    private function guard(): bool
    {
        return user_can('settings.manage');
    }

    public function index()
    {
        if (! $this->guard()) {
            return redirect()->to('/')->with('error', 'Not allowed.');
        }
        $entity = $this->request->getGet('entity') ?: array_key_first(CustomFields::ENTITIES);
        if (! isset(CustomFields::ENTITIES[$entity])) {
            $entity = array_key_first(CustomFields::ENTITIES);
        }

        return view('custom_fields/index', [
            'title'    => 'Custom Fields',
            'entities' => CustomFields::ENTITIES,
            'entity'   => $entity,
            'fields'   => $this->model->forEntity($entity, false),
        ]);
    }

    public function new()
    {
        if (! $this->guard()) {
            return redirect()->to('custom-fields')->with('error', 'Not allowed.');
        }
        $entity = $this->request->getGet('entity') ?: array_key_first(CustomFields::ENTITIES);

        return view('custom_fields/form', [
            'title'    => 'New Custom Field',
            'entities' => CustomFields::ENTITIES,
            'field'    => ['entity' => $entity, 'type' => 'text', 'is_active' => 1],
        ]);
    }

    public function create()
    {
        if (! $this->guard()) {
            return redirect()->to('custom-fields')->with('error', 'Not allowed.');
        }
        $data = $this->payload();
        if (! $this->model->insert($data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }

        return redirect()->to('custom-fields?entity=' . $data['entity'])->with('message', 'Custom field added.');
    }

    public function edit(int $id)
    {
        if (! $this->guard()) {
            return redirect()->to('custom-fields')->with('error', 'Not allowed.');
        }
        $field = $this->model->find($id);
        if (! $field) {
            return redirect()->to('custom-fields')->with('error', 'Field not found.');
        }

        return view('custom_fields/form', [
            'title'    => 'Edit ' . $field['label'],
            'entities' => CustomFields::ENTITIES,
            'field'    => $field,
        ]);
    }

    public function update(int $id)
    {
        if (! $this->guard()) {
            return redirect()->to('custom-fields')->with('error', 'Not allowed.');
        }
        $field = $this->model->find($id);
        if (! $field) {
            return redirect()->to('custom-fields')->with('error', 'Field not found.');
        }
        $data = $this->payload();
        // field_key + entity are immutable once created (values are keyed on them)
        unset($data['field_key'], $data['entity']);
        if (! $this->model->update($id, $data)) {
            return redirect()->back()->withInput()->with('errors', $this->model->errors());
        }

        return redirect()->to('custom-fields?entity=' . $field['entity'])->with('message', 'Custom field updated.');
    }

    public function toggle(int $id)
    {
        if (! $this->guard()) {
            return redirect()->to('custom-fields')->with('error', 'Not allowed.');
        }
        $field = $this->model->find($id);
        if ($field) {
            $this->model->update($id, ['is_active' => $field['is_active'] ? 0 : 1]);
        }

        return redirect()->to('custom-fields?entity=' . ($field['entity'] ?? ''))->with('message', 'Field toggled.');
    }

    private function payload(): array
    {
        $key = strtolower(trim((string) $this->request->getPost('field_key')));
        $key = preg_replace('/[^a-z0-9_]+/', '_', $key);

        return [
            'entity'       => $this->request->getPost('entity'),
            'field_key'    => $key,
            'label'        => trim((string) $this->request->getPost('label')),
            'type'         => $this->request->getPost('type'),
            'options'      => trim((string) $this->request->getPost('options')) ?: null,
            'help'         => trim((string) $this->request->getPost('help')) ?: null,
            'is_required'  => $this->request->getPost('is_required') ? 1 : 0,
            'is_active'    => $this->request->getPost('is_active') ? 1 : 0,
            'show_in_list' => $this->request->getPost('show_in_list') ? 1 : 0,
            'sort_order'   => (int) $this->request->getPost('sort_order'),
        ];
    }
}
