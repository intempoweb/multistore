<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttributeRequest;
use App\Http\Requests\UpdateAttributeRequest;
use App\Models\Attribute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AttributeController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $q = Attribute::query()
            ->with(['translations'])
            ->withCount('values')
            ->orderBy('sort_order')
            ->orderBy('code');

        if ($request->filled('code')) {
            $q->where('code', 'like', '%' . trim((string) $request->input('code')) . '%');
        }

        if ($request->filled('type')) {
            $q->where('type', trim((string) $request->input('type')));
        }

        if ($request->filled('is_filterable')) {
            $q->where(
                'is_filterable',
                filter_var($request->input('is_filterable'), FILTER_VALIDATE_BOOL)
            );
        }

        if ($request->filled('is_variant')) {
            $q->where(
                'is_variant',
                filter_var($request->input('is_variant'), FILTER_VALIDATE_BOOL)
            );
        }

        $attributes = $q->paginate(50)->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $attributes->items(),
                'meta' => [
                    'current_page' => $attributes->currentPage(),
                    'per_page' => $attributes->perPage(),
                    'total' => $attributes->total(),
                    'last_page' => $attributes->lastPage(),
                ],
            ]);
        }

        return view('admin.attributes.index', [
            'attributes' => $attributes,
            'filters' => [
                'code' => (string) $request->input('code', ''),
                'type' => (string) $request->input('type', ''),
                'is_filterable' => (string) $request->input('is_filterable', ''),
                'is_variant' => (string) $request->input('is_variant', ''),
            ],
        ]);
    }

    public function store(StoreAttributeRequest $request): RedirectResponse|JsonResponse
    {
        $data = $request->validated();

        $attribute = DB::transaction(function () use ($data) {
            $translations = $data['translations'] ?? [];
            unset($data['translations'], $data['ditta_cg18']);

            $attribute = Attribute::create($data);

            foreach ($translations as $tr) {
                $attribute->translations()->updateOrCreate(
                    ['locale' => $tr['locale']],
                    [
                        'label' => $tr['label'],
                        'help_text' => $tr['help_text'] ?? null,
                    ]
                );
            }

            return $attribute->load('translations');
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Attribute created',
                'data' => $attribute,
            ], 201);
        }

        return redirect()
            ->route('admin.attributes.show', $attribute)
            ->with('success', 'Attributo creato correttamente.');
    }

    public function show(Request $request, Attribute $attribute): View|JsonResponse
    {
        $attribute->load(['translations', 'values.translations']);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $attribute,
            ]);
        }

        return view('admin.attributes.show', [
            'attribute' => $attribute,
        ]);
    }

    public function update(UpdateAttributeRequest $request, Attribute $attribute): RedirectResponse|JsonResponse
    {
        $data = $request->validated();

        $attribute = DB::transaction(function () use ($attribute, $data) {
            $translations = $data['translations'] ?? [];
            unset($data['translations'], $data['ditta_cg18'], $data['code']);

            $attribute->update($data);

            foreach ($translations as $tr) {
                $attribute->translations()->updateOrCreate(
                    ['locale' => $tr['locale']],
                    [
                        'label' => $tr['label'],
                        'help_text' => $tr['help_text'] ?? null,
                    ]
                );
            }

            return $attribute->fresh()->load('translations');
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Attribute updated',
                'data' => $attribute,
            ]);
        }

        return redirect()
            ->route('admin.attributes.show', $attribute)
            ->with('success', 'Attributo aggiornato correttamente.');
    }

    public function destroy(Request $request, Attribute $attribute): RedirectResponse|JsonResponse
    {
        $attribute->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Attribute deleted',
            ]);
        }

        return redirect()
            ->route('admin.attributes.index')
            ->with('success', 'Attributo eliminato correttamente.');
    }
}