<?php

namespace App\Http\Controllers\Fleet;

use App\Enums\Fleet\VehicleKind;
use App\Http\Controllers\Controller;
use App\Http\Resources\Fleet\CatalogItemResource;
use App\Services\Fleet\VehicleCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Marcas e modelos de veículo, para o formulário — com o que faltar
 * acrescentado ali mesmo.
 */
class VehicleBrandController extends Controller
{
    public function __construct(private readonly VehicleCatalogService $catalog) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate(['kind' => ['required', Rule::enum(VehicleKind::class)]]);

        return CatalogItemResource::collection($this->catalog->brands(VehicleKind::from($data['kind'])));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::enum(VehicleKind::class)],
            'name' => ['required', 'string', 'max:60'],
        ], [
            'name.required' => 'Informe o nome da marca',
            'name.max' => 'O nome da marca deve ter no máximo 60 caracteres',
        ]);

        $brand = $this->catalog->addBrand(VehicleKind::from($data['kind']), $data['name']);

        return response()->json([
            'message' => 'Marca adicionada',
            'data' => CatalogItemResource::make($brand),
        ], $brand->wasRecentlyCreated ? 201 : 200);
    }

    public function models(int $brand): AnonymousResourceCollection
    {
        return CatalogItemResource::collection($this->catalog->models($this->catalog->findBrand($brand)));
    }

    public function storeModel(Request $request, int $brand): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80']], [
            'name.required' => 'Informe o nome do modelo',
            'name.max' => 'O nome do modelo deve ter no máximo 80 caracteres',
        ]);

        $model = $this->catalog->addModel($this->catalog->findBrand($brand), $data['name']);

        return response()->json([
            'message' => 'Modelo adicionado',
            'data' => CatalogItemResource::make($model),
        ], $model->wasRecentlyCreated ? 201 : 200);
    }
}
