<?php

namespace Slowlyo\OwlAdmin\Services;

use Illuminate\Support\Arr;
use Slowlyo\OwlAdmin\Admin;
use Slowlyo\OwlAdmin\Models\AdminRole;
use Illuminate\Database\Eloquent\Builder;

/**
 * @method AdminRole getModel()
 * @method AdminRole|Builder query()
 */
class AdminRoleService extends AdminService
{
    public function __construct()
    {
        parent::__construct();

        $this->modelName = Admin::adminRoleModel();
    }

    public function searchable($query)
    {
        $name = request('name');
        $slug = request('slug');
        $createdAt = request('created_at');
        if ($createdAt) {
            $createdAt = safe_explode(',', $createdAt);
        }

        $query->where('slug', '<>', AdminRole::SuperAdministrator);

        $query->when($name, fn($query) => $query->where('name', 'like', '%' . $name . '%'));
        $query->when($slug, fn($query) => $query->where('slug', 'like', '%' . $slug . '%'));
        $query->when($createdAt, function ($query) use ($createdAt) {
            $query->whereBetween('created_at', [
                $createdAt[0] . ' 00:00:00',
                $createdAt[1] . ' 23:59:59'
            ]);
        });
    }

    public function getEditData($id)
    {
        $permission = parent::getEditData($id);

        $permission->load(['permissions']);

        return $permission;
    }

    public function store($data)
    {
        $this->checkRepeated($data);

        $columns = $this->getTableColumns();

        $model = $this->getModel();

        foreach ($data as $k => $v) {
            if (!in_array($k, $columns)) {
                continue;
            }

            $model->setAttribute($k, $v);
        }

        return $model->save();
    }

    public function update($primaryKey, $data)
    {
        $this->checkRepeated($data, $primaryKey);

        $columns = $this->getTableColumns();

        $model = $this->query()->whereKey($primaryKey)->first();

        foreach ($data as $k => $v) {
            if (!in_array($k, $columns)) {
                continue;
            }

            $model->setAttribute($k, $v);
        }

        return $model->save();
    }

    public function checkRepeated($data, $id = 0)
    {
        $query = $this->query()->when($id, fn($query) => $query->where('id', '<>', $id));

        admin_abort_if($query->clone()
            ->where('name', $data['name'])
            ->exists(), admin_trans('admin.admin_role.name_already_exists'));

        admin_abort_if($query->clone()
            ->where('slug', $data['slug'])
            ->exists(), admin_trans('admin.admin_role.slug_already_exists'));
    }

    public function savePermissions($primaryKey, $permissions)
    {
        $model = $this->query()->whereKey($primaryKey)->first();

        return $model->permissions()->sync(
            Arr::has($permissions, '0.id') ? Arr::pluck($permissions, 'id') : $permissions
        );
    }

    public function delete(string $ids)
    {
        $_ids   = explode(',', $ids);
        $exists = $this->query()
            ->whereIn($this->primaryKey(), $_ids)
            ->where('slug', AdminRole::SuperAdministrator)
            ->exists();

        admin_abort_if($exists, admin_trans('admin.admin_role.cannot_delete'));

        $used = $this->query()
            ->whereIn($this->primaryKey(), $_ids)
            ->has('users')
            ->exists();

        admin_abort_if($used, admin_trans('admin.admin_role.used'));


        return parent::delete($ids);
    }
}
