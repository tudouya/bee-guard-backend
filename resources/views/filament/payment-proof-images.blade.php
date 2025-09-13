@php
    use Illuminate\Support\Facades\Storage;
@endphp
<div class="space-y-3">
    @foreach(($images ?? []) as $path)
        <div>
            <img src="{{ Storage::disk('public')->url($path) }}" alt="payment image" style="max-width: 100%; border-radius: 6px;" />
        </div>
    @endforeach
</div>
