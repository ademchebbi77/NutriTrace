{{-- Expected output and resources for the inputs of a transformation, as computed by the AI. --}}
@isset($expected['output_min'])
    <div class="small text-gray-700">{{ __('ai.transformation.expected_output', [
        'min' => number_format($expected['output_min'], 1, ',', ' '),
        'max' => number_format($expected['output_max'], 1, ',', ' '),
        'unit' => $expected['output_unit'] ?? '',
    ]) }}</div>
    <div class="small text-gray-600 mb-2">{{ __('ai.transformation.expected_resources', [
        'emin' => number_format($expected['energy_min'], 0, ',', ' '),
        'emax' => number_format($expected['energy_max'], 0, ',', ' '),
        'wmin' => number_format($expected['water_min'], 0, ',', ' '),
        'wmax' => number_format($expected['water_max'], 0, ',', ' '),
    ]) }}</div>
@endisset
