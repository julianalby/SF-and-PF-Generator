<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SfRecord;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SfRecordController extends Controller
{
    public function index(Request $request): View
    {
        $order = $request->query('order') === 'desc' ? 'desc' : 'asc';

        return view('admin.sf.index', [
            'records' => SfRecord::query()
                ->orderBy('id', $order)
                ->paginate((int) config('sfpf.per_page'))
                ->withQueryString(),
            'order' => $order,
        ]);
    }
}
