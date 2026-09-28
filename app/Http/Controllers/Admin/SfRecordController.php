<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesDatabaseFilters;
use App\Http\Controllers\Controller;
use App\Models\SfRecord;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SfRecordController extends Controller
{
    use HandlesDatabaseFilters;

    public function index(Request $request): View
    {
        $order = $request->query('order') === 'desc' ? 'desc' : 'asc';
        [$filters, $filterErrors] = $this->databaseFilters($request);

        return view('admin.sf.index', [
            'records' => SfRecord::query()
                ->filter($filters, 'sf_number')
                ->orderBy('id', $order)
                ->paginate((int) config('sfpf.per_page'))
                ->withQueryString(),
            'order' => $order,
            'filters' => $filters,
            'filterErrors' => $filterErrors,
            'usernames' => $this->allUsernames(),
            'hasFilters' => count(array_filter($filters)) > 0,
        ]);
    }
}
