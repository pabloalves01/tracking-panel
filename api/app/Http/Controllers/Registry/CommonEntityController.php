<?php

namespace App\Http\Controllers\Registry;

use App\Http\Controllers\Controller;
use App\Models\Registry\CommonEntity;
use Illuminate\Http\JsonResponse;

class CommonEntityController extends Controller
{
    /**
     * Get a common entity by document.
     *
     * @param string $document
     * @return JsonResponse
     */
    public function byDocument(string $document)
    {
        $entity = CommonEntity::query()
            ->withExists(['supplier', 'employee', 'driver', 'vehicleOwner', 'vehicleResponsible', 'fuelStation'])
            ->where('cpf_cnpj', $document)
            ->first();

        return response()->json(['data' => $entity]);
    }
}
