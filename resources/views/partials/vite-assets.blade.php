@php
    use Illuminate\Foundation\Vite;
    use Illuminate\Support\Facades\Log;

    $entries = ['resources/css/app.css', 'resources/js/app.js'];
    $assetTags = null;

    try {
        $assetTags = app(Vite::class)($entries);
    } catch (Throwable $exception) {
        $manifestPath = public_path('build/manifest.json');
        if (is_file($manifestPath)) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            if (is_array($manifest)) {
                $cssFile = data_get($manifest, 'resources/css/app.css.file');
                $jsFile = data_get($manifest, 'resources/js/app.js.file');

                $parts = [];
                if (is_string($cssFile) && $cssFile !== '') {
                    $parts[] = '<link rel="stylesheet" href="' . e(asset('build/' . ltrim($cssFile, '/'))) . '">';
                }
                if (is_string($jsFile) && $jsFile !== '') {
                    $parts[] = '<script type="module" src="' . e(asset('build/' . ltrim($jsFile, '/'))) . '"></script>';
                }

                if ($parts !== []) {
                    $assetTags = implode(PHP_EOL, $parts);
                }
            }
        }

        if ($assetTags === null) {
            Log::warning('Vite asset fallback could not resolve build manifest', [
                'message' => $exception->getMessage(),
                'manifest_exists' => is_file(public_path('build/manifest.json')),
            ]);
        }
    }
@endphp

@if($assetTags)
{!! $assetTags !!}
@endif
