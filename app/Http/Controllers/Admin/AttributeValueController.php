<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttributeValueRequest;
use App\Http\Requests\UpdateAttributeValueRequest;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AttributeValueController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $q = AttributeValue::query()
            ->with(['attribute.translations', 'translations', 'mediaAssets'])
            ->withCount('products')
            ->orderBy('attribute_id')
            ->orderBy('sort_order')
            ->orderBy('value_code');

        if ($request->filled('attribute_id')) {
            $q->where('attribute_id', (int) $request->input('attribute_id'));
        }

        if ($request->filled('value_code')) {
            $q->where('value_code', 'like', '%' . trim((string) $request->input('value_code')) . '%');
        }

        $values = $q->paginate(50)->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $values->items(),
                'meta' => [
                    'current_page' => $values->currentPage(),
                    'per_page' => $values->perPage(),
                    'total' => $values->total(),
                    'last_page' => $values->lastPage(),
                ],
            ]);
        }

        return view('admin.attribute-values.index', [
            'values' => $values,
            'attributes' => Attribute::query()->orderBy('sort_order')->orderBy('code')->get(),
            'filters' => [
                'attribute_id' => (string) $request->input('attribute_id', ''),
                'value_code' => (string) $request->input('value_code', ''),
            ],
        ]);
    }

    public function store(StoreAttributeValueRequest $request): RedirectResponse|JsonResponse
    {
        $data = $request->validated();

        $value = DB::transaction(function () use ($data) {
            $translations = $data['translations'] ?? [];
            unset($data['translations']);

            $attribute = Attribute::query()
                ->where('id', $data['attribute_id'])
                ->firstOrFail();

            $value = AttributeValue::create([
                'attribute_id' => $attribute->id,
                'value_code' => $data['value_code'],
                'sort_order' => $data['sort_order'] ?? 0,
                'erp_lastchange' => $data['erp_lastchange'] ?? null,
            ]);

            foreach ($translations as $tr) {
                $value->translations()->updateOrCreate(
                    ['locale' => $tr['locale']],
                    ['label' => $tr['label']]
                );
            }

            return $value->load(['attribute', 'translations', 'mediaAssets']);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Attribute value created',
                'data' => $value,
            ], 201);
        }

        return redirect()
            ->route('admin.attribute-values.show', $value)
            ->with('success', 'Valore attributo creato correttamente.');
    }

    public function show(Request $request, AttributeValue $attributeValue): View|JsonResponse
    {
        $attributeValue->load(['attribute.translations', 'translations', 'mediaAssets', 'products']);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $attributeValue,
            ]);
        }

        return view('admin.attribute-values.show', [
            'value' => $attributeValue,
        ]);
    }

    public function update(UpdateAttributeValueRequest $request, AttributeValue $attributeValue): RedirectResponse|JsonResponse
    {
        $data = $request->validated();

        $attributeValue = DB::transaction(function () use ($attributeValue, $data) {
            $translations = $data['translations'] ?? [];
            unset($data['translations'], $data['attribute_id'], $data['value_code']);

            $attributeValue->update($data);

            foreach ($translations as $tr) {
                $attributeValue->translations()->updateOrCreate(
                    ['locale' => $tr['locale']],
                    ['label' => $tr['label']]
                );
            }

            return $attributeValue->fresh()->load(['attribute', 'translations', 'mediaAssets']);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Attribute value updated',
                'data' => $attributeValue,
            ]);
        }

        return redirect()
            ->route('admin.attribute-values.show', $attributeValue)
            ->with('success', 'Valore attributo aggiornato correttamente.');
    }

    public function destroy(Request $request, AttributeValue $attributeValue): RedirectResponse|JsonResponse
    {
        $attributeValue->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Attribute value deleted',
            ]);
        }

        return redirect()
            ->route('admin.attribute-values.index')
            ->with('success', 'Valore attributo eliminato correttamente.');
    }
}