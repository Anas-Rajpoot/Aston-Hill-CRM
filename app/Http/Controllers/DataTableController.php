<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use App\Services\ModuleColumnService;

class DataTableController extends Controller
{
    public function index(Request $request, string $module)
    {
        $config = ModuleColumnService::module($module);
        abort_if(empty($config), 404);

        $model = $config['model'];
        $columns = $request->columns;
        $filters = $request->filters ?? [];
        $sort = $request->sort ?? [];

        $query = $model::select($columns);

        foreach ($filters as $field => $value) {
            if ($value === '' || !isset($config['columns'][$field])) continue;

            match ($config['columns'][$field]['filter']) {
                'text' => $query->where($field, 'like', "%$value%"),
                'select' => $query->where($field, $value),
                'date' => $query->whereDate($field, $value),
                default => null,
            };
        }

        return DataTables::of($query)
            ->order(function ($q) use ($sort) {
                if (!empty($sort)) {
                    $q->orderBy($sort['column'], $sort['direction']);
                }
            })
            ->make(true);
    }
}
