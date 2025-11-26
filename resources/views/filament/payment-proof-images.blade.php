@php
    use Illuminate\Support\Facades\Storage;

    $resolveUrl = static function (string $path): string {
        if ($path === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//', $path) || str_starts_with($path, '//')) {
            return $path;
        }

        // 优先使用 S3，未配置则回退到默认磁盘，再回退到 public
        if (config()->has('filesystems.disks.s3')) {
            return Storage::disk('s3')->url($path);
        }

        $defaultDisk = config('filesystems.default', 'public');
        if (config()->has("filesystems.disks.{$defaultDisk}")) {
            return Storage::disk($defaultDisk)->url($path);
        }

        return Storage::disk('public')->url($path);
    };
@endphp
<div class="space-y-3">
    @foreach(($images ?? []) as $path)
        <div>
            <img src="{{ $resolveUrl((string) $path) }}" alt="payment image" style="max-width: 100%; border-radius: 6px;" />
        </div>
    @endforeach
</div>
