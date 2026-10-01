<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateInvoiceTemplateRequest;
use App\Models\InvoiceTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceTemplateController extends Controller
{
    private function current(): InvoiceTemplate
    {
        return InvoiceTemplate::orderBy('id')->firstOrFail();
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $this->current()]);
    }

    public function update(UpdateInvoiceTemplateRequest $request): JsonResponse
    {
        $data = $request->validated();
        if ($request->has('show_npwp')) {
            $data['show_npwp'] = $request->boolean('show_npwp');
        }
        if ($request->has('show_outlet_phone')) {
            $data['show_outlet_phone'] = $request->boolean('show_outlet_phone');
        }
        unset($data['logo']);

        // Store the new logo before touching the row so a failed upload never
        // leaves a partially-updated template or drops the prior logo_path.
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        $template = $this->current();
        $template->fill($data);
        $template->save();
        $template->refresh();

        return response()->json(['status' => 'success', 'data' => $template]);
    }
}
