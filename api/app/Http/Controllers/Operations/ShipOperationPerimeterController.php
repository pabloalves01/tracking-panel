<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\Operation\ShipOperationPerimeter;
use Illuminate\Http\Request;
use MatanYadaev\EloquentSpatial\Objects\Polygon;

class ShipOperationPerimeterController extends Controller
{
    public function create(Request $request)
    {
        $shipOperationId = $request->input('ship_operation_id');

        $shipOperationPerimeter = ShipOperationPerimeter::create([
            'ship_operation_id' => $shipOperationId,
            'perimeter' => Polygon::fromArray($request->input('perimeter')),
        ]);

        return response()->json($shipOperationPerimeter, 201);
    }

    public function get(Request $request, $shipOperationId)
    {
        $shipOperationPerimeters = ShipOperationPerimeter::where('ship_operation_id', $shipOperationId)->get();

        return response()->json($shipOperationPerimeters);
    }
}
