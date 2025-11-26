@php
    $fields = $fields ?? [];
    $record = $record ?? null;
    $labelMap = ['strong' => '强', 'medium' => '中', 'weak' => '弱'];
    $colorMap = [
        'strong' => 'bg-red-50 text-red-600 border-red-200',
        'medium' => 'bg-orange-50 text-orange-600 border-orange-200',
        'weak' => 'bg-amber-50 text-amber-600 border-amber-200',
        'default' => 'bg-gray-50 text-gray-500 border-gray-200',
    ];
@endphp

<div class="divide-y divide-gray-100 border border-gray-100 rounded-lg overflow-hidden bg-white">
    @foreach ($fields as $field => $label)
        @php
            $level = $record?->{$field} ?? null;
            $text = $labelMap[$level] ?? '未检出';
            $colorClass = $colorMap[$level] ?? $colorMap['default'];
        @endphp
        <div class="flex items-center justify-between px-4 py-2">
            <div class="text-sm text-gray-800">{{ $label }}</div>
            <span class="text-xs font-semibold px-3 py-1 border rounded-full {{ $colorClass }}">{{ $text }}</span>
        </div>
    @endforeach
</div>
