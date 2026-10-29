<?php

namespace App\Http\Requests\Fleet;

use App\Enums\Fleet\FleetType;
use App\Enums\Fleet\InstallationRole;
use App\Enums\Fleet\VehicleKind;
use App\Models\Fleet\Vehicle;
use App\Models\Fleet\VehicleType;
use App\Rules\Renavam;
use App\Support\Brazil;
use App\Support\CurrentOrganization;
use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Validator;

/**
 * O veículo, com os implementos engatados e os rastreadores instalados.
 *
 * Proprietário, transportador, responsável, implementos e rastreadores
 * chegam pelo `uuid`. `trailers` e `devices`, quando vêm, são a lista
 * inteira — o que não estiver nela é desengatado ou retirado.
 */
class VehicleRequest extends FormRequest
{
    private VehicleType|false|null $type = false;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $input = [];

        if ($this->has('plate')) {
            $input['plate'] = Vehicle::normalizePlate($this->input('plate'));
        }

        if ($this->has('plate_state')) {
            $input['plate_state'] = mb_strtoupper(Text::plain($this->input('plate_state')));
        }

        if ($this->has('renavam')) {
            $input['renavam'] = Text::digits($this->input('renavam'));
        }

        if ($this->has('chassis')) {
            $input['chassis'] = preg_replace('/\s/', '', mb_strtoupper(Text::plain($this->input('chassis'))));
        }

        $this->merge($input);
    }

    public function rules(): array
    {
        $year = (int) date('Y');
        $isTrailer = fn () => $this->kind() !== VehicleKind::MotorVehicle;

        return [
            'vehicle_type_id' => ['required', 'integer', 'exists:vehicle_types,id'],
            'body_type_id' => ['nullable', 'integer', 'exists:body_types,id'],
            'fleet_type' => ['required', Rule::enum(FleetType::class)],

            'plate' => ['required', 'regex:/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/'],
            'plate_state' => ['required', Rule::in(Brazil::STATES)],
            'renavam' => ['required', new Renavam],
            'chassis' => ['required', 'regex:/^[A-HJ-NPR-Z0-9]{17}$/'],
            'registration_expires_on' => ['required', 'date'],

            'brand' => ['required', 'string', 'max:60'],
            'model' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:40'],
            'manufacture_year' => ['required', 'integer', 'min:1950', 'max:'.($year + 1)],
            'model_year' => ['required', 'integer', 'gte:manufacture_year', 'max:'.($year + 2)],

            'axle_count' => ['required', 'integer', 'min:1', 'max:12'],
            'tare_weight_kg' => ['required', 'integer', 'min:1', 'max:100000'],
            'load_capacity_kg' => ['nullable', 'integer', 'min:0', 'max:200000'],
            'gross_weight_kg' => ['nullable', 'integer', 'gte:tare_weight_kg', 'max:300000'],
            'max_traction_weight_kg' => [Rule::excludeIf($isTrailer), 'nullable', 'integer', 'min:0', 'max:300000'],
            'volume_capacity_m3' => ['nullable', 'numeric', 'min:0', 'max:999999'],

            'owner' => ['required', 'uuid', $this->existsInCompany('vehicle_owners')],
            'carrier' => ['nullable', 'uuid', $this->existsInCompany('carriers')],
            'responsible' => ['required', 'uuid', $this->existsInCompany('vehicle_responsibles')],

            'trailers' => [Rule::excludeIf($isTrailer), 'sometimes', 'array', 'max:3'],
            'trailers.*' => ['uuid', 'distinct', $this->existsInCompany('vehicles')],

            'devices' => ['sometimes', 'array', 'max:5'],
            'devices.*.device' => ['required', 'uuid', 'distinct', $this->existsInCompany('devices')],
            'devices.*.role' => ['required', Rule::enum(InstallationRole::class)],
        ];
    }

    /**
     * O que depende de outra linha: o implemento escolhido ser mesmo
     * implemento, e não o próprio veículo; um rastreador principal só.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $trailers = $this->input('trailers');

                if ($this->kind() === VehicleKind::MotorVehicle && is_array($trailers) && $trailers !== []) {
                    $chosen = Vehicle::query()->with('vehicleType')->whereIn('uuid', $trailers)->get()->keyBy('uuid');

                    foreach (array_values($trailers) as $index => $uuid) {
                        if ($uuid === $this->route('uuid')) {
                            $validator->errors()->add("trailers.{$index}", 'Um veículo não engata nele mesmo');
                        } elseif (($chosen[$uuid] ?? null)?->kind() === VehicleKind::MotorVehicle) {
                            $validator->errors()->add("trailers.{$index}", "{$chosen[$uuid]->plate} não é implemento");
                        }
                    }
                }

                $primaries = collect($this->input('devices', []))
                    ->where('role', InstallationRole::Primary->value)
                    ->count();

                if ($primaries > 1) {
                    $validator->errors()->add('devices', 'Escolha um rastreador principal só');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_type_id.required' => 'Selecione o tipo do veículo',
            'vehicle_type_id.exists' => 'Selecione um tipo da lista',
            'body_type_id.exists' => 'Selecione uma carroceria da lista',
            'fleet_type.required' => 'Informe a frota',
            'fleet_type.enum' => 'A frota é inválida',
            'plate.required' => 'A placa é obrigatória',
            'plate.regex' => 'Informe uma placa válida, como ABC1D23 ou ABC1234',
            'plate_state.required' => 'Informe a UF',
            'plate_state.in' => 'A UF da placa é inválida',
            'renavam.required' => 'O RENAVAM é obrigatório',
            'chassis.required' => 'O chassi é obrigatório',
            'chassis.regex' => 'O chassi tem 17 letras e números, sem I, O e Q',
            'registration_expires_on.required' => 'Informe a validade do CRLV',
            'registration_expires_on.date' => 'A validade do CRLV é inválida',
            'brand.required' => 'A marca é obrigatória',
            'model.required' => 'O modelo é obrigatório',
            'manufacture_year.required' => 'Informe o ano de fabricação',
            'manufacture_year.min' => 'O ano de fabricação é inválido',
            'manufacture_year.max' => 'O ano de fabricação é inválido',
            'model_year.required' => 'Informe o ano do modelo',
            'model_year.gte' => 'O ano do modelo não pode ser anterior ao de fabricação',
            'model_year.max' => 'O ano do modelo é inválido',
            'axle_count.required' => 'Informe o número de eixos',
            'axle_count.min' => 'O número de eixos é inválido',
            'axle_count.max' => 'O número de eixos é inválido',
            'tare_weight_kg.required' => 'Informe a tara',
            'tare_weight_kg.min' => 'A tara deve ser maior que zero',
            'gross_weight_kg.gte' => 'O PBT não pode ser menor que a tara',
            'owner.required' => 'Selecione o proprietário',
            'owner.exists' => 'Selecione um proprietário cadastrado',
            'carrier.exists' => 'Selecione um transportador cadastrado',
            'responsible.required' => 'Selecione o responsável',
            'responsible.exists' => 'Selecione um responsável cadastrado',
            'trailers.max' => 'Um veículo leva no máximo 3 implementos',
            'trailers.*.distinct' => 'O mesmo implemento foi escolhido duas vezes',
            'trailers.*.exists' => 'Selecione um implemento cadastrado',
            'devices.max' => 'Um veículo leva no máximo 5 rastreadores',
            'devices.*.device.distinct' => 'O mesmo rastreador foi escolhido duas vezes',
            'devices.*.device.exists' => 'Selecione um rastreador cadastrado',
            'devices.*.role.required' => 'Informe se o rastreador é principal ou reserva',
            'devices.*.role.enum' => 'A função do rastreador é inválida',
        ];
    }

    private function kind(): ?VehicleKind
    {
        if ($this->type === false) {
            $this->type = VehicleType::query()->find($this->integer('vehicle_type_id') ?: null);
        }

        return $this->type?->kind;
    }

    private function existsInCompany(string $table): Exists
    {
        return Rule::exists($table, 'uuid')
            ->where('company_id', app(CurrentOrganization::class)->id())
            ->whereNull('deleted_at');
    }
}
