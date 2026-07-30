<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\ApplicationFormUploadLimit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ApplicationFormTemporaryUploadService
{
    private const METADATA_DISK = 'local';
    private const METADATA_DIRECTORY = 'application-form-temp';

    private const FIELD_DEFINITIONS = [
        'photo_ktp_file' => [
            'label' => 'pas foto',
            'final_directory' => 'applicants/photos',
            'temporary_directory' => 'applicants/tmp/photos',
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
            'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
            'max_kb' => 4096,
        ],
        'scan_ktp_file' => [
            'label' => 'scan KTP',
            'final_directory' => 'applicants/ktp',
            'temporary_directory' => 'applicants/tmp/ktp',
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
            'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
            'max_kb' => 4096,
        ],
        'cv_file' => [
            'label' => 'CV',
            'final_directory' => 'applicants/cv',
            'temporary_directory' => 'applicants/tmp/cv',
            'allowed_extensions' => ['pdf'],
            'allowed_mimes' => ['application/pdf'],
            'max_kb' => 5120,
        ],
    ];

    public static function supportedFields(): array
    {
        return array_keys(self::FIELD_DEFINITIONS);
    }

    public static function fieldDefinition(string $field): array
    {
        if (! isset(self::FIELD_DEFINITIONS[$field])) {
            throw new RuntimeException('Field upload application form tidak dikenali.');
        }

        return self::FIELD_DEFINITIONS[$field];
    }

    public function storeTemporaryUpload(UploadedFile $file, string $field, int $userId, array $logContext = []): array
    {
        $definition = self::fieldDefinition($field);
        Log::info('Application form temporary upload validation started', $logContext + [
            'field' => $field,
            'upload_file' => $this->fileDiagnostics($file),
            'field_definition' => $this->definitionDiagnostics($definition),
        ]);

        $this->assertFileUploadIsHealthy($file, $definition['label']);

        $sizeBytes = (int) ($file->getSize() ?? 0);
        if ($sizeBytes > ((int) $definition['max_kb'] * 1024)) {
            $message = 'Ukuran ' . $definition['label'] . ' melebihi batas ' . $this->formatKilobytes((int) $definition['max_kb']) . '.';
            Log::warning('Application form temporary upload rejected by size rule', $logContext + [
                'field' => $field,
                'reason' => 'file_too_large',
                'message' => $message,
                'upload_file' => $this->fileDiagnostics($file),
                'field_definition' => $this->definitionDiagnostics($definition),
            ]);
            throw new RuntimeException($message);
        }

        $prepared = $this->prepareStorableFile($file, $definition, $logContext);
        $token = Str::lower(Str::random(40));
        $storedRelativePath = trim($definition['temporary_directory'], '/') . '/' . $token . '.' . $prepared['extension'];

        try {
            $written = Storage::disk('public')->put($storedRelativePath, $prepared['binary']);
        } catch (Throwable $exception) {
            Log::error('Application form temporary upload public storage exception', $logContext + [
                'field' => $field,
                'reason' => 'public_storage_exception',
                'disk' => 'public',
                'target_path' => $storedRelativePath,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
                'field_definition' => $this->definitionDiagnostics($definition),
            ]);

            throw new RuntimeException('Server gagal menyimpan file ' . $definition['label'] . '. Silakan coba lagi atau hubungi HRD.');
        }

        if (! $written) {
            Log::error('Application form temporary upload public storage returned false', $logContext + [
                'field' => $field,
                'reason' => 'public_storage_failed',
                'disk' => 'public',
                'target_path' => $storedRelativePath,
                'field_definition' => $this->definitionDiagnostics($definition),
            ]);
            throw new RuntimeException('Server gagal menyimpan file ' . $definition['label'] . '. Silakan coba lagi atau hubungi HRD.');
        }

        $metadata = [
            'token' => $token,
            'user_id' => $userId,
            'field' => $field,
            'label' => $definition['label'],
            'stored_path' => $storedRelativePath,
            'mime' => $prepared['mime'],
            'extension' => $prepared['extension'],
            'size_bytes' => strlen($prepared['binary']),
            'original_name' => $file->getClientOriginalName(),
            'normalized_name' => $prepared['normalized_name'],
            'source' => $prepared['source'],
            'created_at' => now()->toIso8601String(),
        ];

        try {
            $metadataWritten = Storage::disk(self::METADATA_DISK)->put($this->metadataPath($token), json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedRelativePath);
            Log::error('Application form temporary upload metadata storage exception', $logContext + [
                'field' => $field,
                'reason' => 'metadata_storage_exception',
                'disk' => self::METADATA_DISK,
                'target_path' => $this->metadataPath($token),
                'temporary_path_deleted' => $storedRelativePath,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException('Server gagal mencatat token upload ' . $definition['label'] . '. Silakan coba lagi atau hubungi HRD.');
        }

        if (! $metadataWritten) {
            Storage::disk('public')->delete($storedRelativePath);
            Log::error('Application form temporary upload metadata storage returned false', $logContext + [
                'field' => $field,
                'reason' => 'metadata_storage_failed',
                'disk' => self::METADATA_DISK,
                'target_path' => $this->metadataPath($token),
                'temporary_path_deleted' => $storedRelativePath,
            ]);

            throw new RuntimeException('Server gagal mencatat token upload ' . $definition['label'] . '. Silakan coba lagi atau hubungi HRD.');
        }

        Log::info('Application form temporary upload stored', $logContext + [
            'field' => $field,
            'token' => $token,
            'stored_path' => $storedRelativePath,
            'temporary_source' => $prepared['source'],
            'normalized_name' => $prepared['normalized_name'],
            'normalized_mime' => $prepared['mime'],
            'normalized_extension' => $prepared['extension'],
            'size_bytes' => $metadata['size_bytes'],
        ]);

        return $metadata + [
            'preview_url' => $this->publicUrl($storedRelativePath),
        ];
    }

    public function promoteTemporaryUpload(string $token, string $field, int $userId, array $logContext = []): string
    {
        $metadata = $this->findTemporaryUpload($token, $field, $userId);
        if ($metadata === null) {
            throw new RuntimeException('Dokumen sementara untuk ' . self::fieldDefinition($field)['label'] . ' tidak ditemukan. Silakan unggah ulang file tersebut.');
        }

        $definition = self::fieldDefinition($field);
        $finalPath = trim($definition['final_directory'], '/') . '/' . Str::uuid()->toString() . '.' . $metadata['extension'];

        if (! Storage::disk('public')->exists((string) $metadata['stored_path'])) {
            $this->deleteMetadata($token);
            throw new RuntimeException('File sementara untuk ' . $definition['label'] . ' sudah tidak tersedia di server. Silakan unggah ulang.');
        }

        $moved = Storage::disk('public')->move((string) $metadata['stored_path'], $finalPath);
        if (! $moved) {
            throw new RuntimeException('Dokumen ' . $definition['label'] . ' gagal dipindahkan ke penyimpanan final.');
        }

        $this->deleteMetadata($token);

        Log::info('Application form temporary upload promoted', $logContext + [
            'field' => $field,
            'token' => $token,
            'temporary_path' => $metadata['stored_path'],
            'final_path' => $finalPath,
        ]);

        return $finalPath;
    }

    public function discardTemporaryUpload(?string $token, string $field, int $userId, array $logContext = []): void
    {
        if (! is_string($token) || trim($token) === '') {
            return;
        }

        $metadata = $this->findTemporaryUpload($token, $field, $userId);
        if ($metadata === null) {
            return;
        }

        if (Storage::disk('public')->exists((string) $metadata['stored_path'])) {
            Storage::disk('public')->delete((string) $metadata['stored_path']);
        }

        $this->deleteMetadata($token);

        Log::info('Application form temporary upload discarded', $logContext + [
            'field' => $field,
            'token' => $token,
        ]);
    }

    public function findTemporaryUpload(?string $token, ?string $field = null, ?int $userId = null): ?array
    {
        if (! is_string($token) || trim($token) === '') {
            return null;
        }

        $metadataPath = $this->metadataPath($token);
        if (! Storage::disk(self::METADATA_DISK)->exists($metadataPath)) {
            return null;
        }

        $decoded = json_decode((string) Storage::disk(self::METADATA_DISK)->get($metadataPath), true);
        if (! is_array($decoded)) {
            return null;
        }

        if ($field !== null && (string) ($decoded['field'] ?? '') !== $field) {
            return null;
        }

        if ($userId !== null && (int) ($decoded['user_id'] ?? 0) !== $userId) {
            return null;
        }

        $decoded['preview_url'] = $this->publicUrl((string) ($decoded['stored_path'] ?? ''));

        return $decoded;
    }

    public function canConvertHeic(): bool
    {
        if (! class_exists(\Imagick::class)) {
            return false;
        }

        try {
            $formats = array_map('strtoupper', \Imagick::queryFormats());
            return in_array('HEIC', $formats, true) || in_array('HEIF', $formats, true);
        } catch (Throwable) {
            return false;
        }
    }

    public function likelyOversizeRequest(int|string|null $contentLength): bool
    {
        $effective = ApplicationFormUploadLimit::effectiveBytes();
        $content = (int) $contentLength;

        return $effective > 0 && $content > 0 && $content > $effective;
    }

    private function prepareStorableFile(UploadedFile $file, array $definition, array $logContext): array
    {
        $originalName = trim((string) $file->getClientOriginalName());
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $clientMime = strtolower((string) $file->getClientMimeType());
        $detectedMime = strtolower((string) $file->getMimeType());

        if ($this->isHeicLike($extension, $clientMime, $detectedMime)) {
            return $this->convertHeicFile($file, $definition, $logContext);
        }

        $hasAllowedExtension = $extension !== '' && in_array($extension, $definition['allowed_extensions'], true);
        $hasAllowedMime = ($clientMime !== '' && in_array($clientMime, $definition['allowed_mimes'], true))
            || ($detectedMime !== '' && in_array($detectedMime, $definition['allowed_mimes'], true));

        if (! $hasAllowedExtension && ! $hasAllowedMime) {
            $message = 'Tipe file ' . $definition['label'] . ' tidak didukung. Gunakan ' . $this->allowedFormatText($definition) . '.';
            Log::warning('Application form temporary upload rejected by type rule', $logContext + [
                'reason' => 'unsupported_file_type',
                'message' => $message,
                'upload_file' => $this->fileDiagnostics($file),
                'field_definition' => $this->definitionDiagnostics($definition),
            ]);
            throw new RuntimeException($message);
        }

        $binary = file_get_contents($file->getRealPath());
        if ($binary === false) {
            Log::warning('Application form temporary upload failed reading tmp file', $logContext + [
                'reason' => 'tmp_file_read_failed',
                'upload_file' => $this->fileDiagnostics($file),
                'field_definition' => $this->definitionDiagnostics($definition),
            ]);
            throw new RuntimeException('File ' . $definition['label'] . ' gagal dibaca dari upload sementara.');
        }

        $normalizedExtension = $extension !== '' ? $extension : $this->guessExtensionFromMime($detectedMime ?: $clientMime);
        if ($normalizedExtension === '') {
            $normalizedExtension = 'bin';
        }

        return [
            'binary' => $binary,
            'mime' => $detectedMime !== '' ? $detectedMime : ($clientMime !== '' ? $clientMime : 'application/octet-stream'),
            'extension' => $normalizedExtension,
            'normalized_name' => $this->normalizedFilename($originalName, $normalizedExtension),
            'source' => 'direct_upload',
        ];
    }

    private function convertHeicFile(UploadedFile $file, array $definition, array $logContext): array
    {
        if (! $this->canConvertHeic()) {
            throw new RuntimeException('Server belum mendukung konversi HEIC/HEIF. Simpan file iPhone ke JPG, PNG, WEBP, atau PDF lalu unggah kembali.');
        }

        try {
            $imagick = new \Imagick();
            $imagick->readImage($file->getRealPath());
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality(88);
            $binary = $imagick->getImagesBlob();
            $imagick->clear();
            $imagick->destroy();
        } catch (Throwable $exception) {
            Log::warning('Application form HEIC conversion failed', $logContext + [
                'field' => array_search($definition, self::FIELD_DEFINITIONS, true) ?: 'unknown',
                'original_name' => $file->getClientOriginalName(),
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException('File HEIC/HEIF terdeteksi tetapi gagal dikonversi di server. Simpan ke JPG, PNG, WEBP, atau PDF lalu unggah kembali.');
        }

        if (! in_array('image/jpeg', $definition['allowed_mimes'], true)) {
            throw new RuntimeException('Format hasil konversi HEIC tidak sesuai untuk dokumen ini.');
        }

        return [
            'binary' => $binary,
            'mime' => 'image/jpeg',
            'extension' => 'jpg',
            'normalized_name' => $this->normalizedFilename($file->getClientOriginalName(), 'jpg'),
            'source' => 'heic_converted',
        ];
    }

    private function assertFileUploadIsHealthy(UploadedFile $file, string $label): void
    {
        if (! $file->isValid()) {
            $message = match ($file->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran ' . $label . ' melebihi batas upload server.',
                UPLOAD_ERR_PARTIAL => ucfirst($label) . ' gagal terunggah sepenuhnya. Periksa koneksi internet lalu coba lagi.',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'Upload ' . $label . ' gagal diproses di server.',
                default => 'Upload ' . $label . ' gagal diproses.',
            };

            throw new RuntimeException($message);
        }
    }

    private function fileDiagnostics(UploadedFile $file): array
    {
        return [
            'original_name' => $file->getClientOriginalName(),
            'client_extension' => strtolower((string) $file->getClientOriginalExtension()),
            'client_mime' => $file->getClientMimeType(),
            'detected_mime' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'upload_error' => $file->getError(),
            'is_valid' => $file->isValid(),
        ];
    }

    private function definitionDiagnostics(array $definition): array
    {
        return [
            'label' => $definition['label'] ?? null,
            'allowed_extensions' => $definition['allowed_extensions'] ?? [],
            'allowed_mimes' => $definition['allowed_mimes'] ?? [],
            'max_kb' => $definition['max_kb'] ?? null,
            'max_bytes' => isset($definition['max_kb']) ? ((int) $definition['max_kb'] * 1024) : null,
            'temporary_directory' => $definition['temporary_directory'] ?? null,
            'final_directory' => $definition['final_directory'] ?? null,
            'disk' => 'public',
        ];
    }

    private function allowedFormatText(array $definition): string
    {
        $extensions = array_map(static fn (string $extension): string => strtoupper($extension), $definition['allowed_extensions'] ?? []);

        return implode(', ', array_unique($extensions));
    }

    private function formatKilobytes(int $kilobytes): string
    {
        if ($kilobytes >= 1024) {
            return rtrim(rtrim(number_format($kilobytes / 1024, 1, ',', '.'), '0'), ',') . 'MB';
        }

        return $kilobytes . 'KB';
    }

    private function isHeicLike(string $extension, string $clientMime, string $detectedMime): bool
    {
        if (in_array($extension, ['heic', 'heif'], true)) {
            return true;
        }

        foreach ([$clientMime, $detectedMime] as $mime) {
            if ($mime !== '' && (str_contains($mime, 'heic') || str_contains($mime, 'heif'))) {
                return true;
            }
        }

        return false;
    }

    private function guessExtensionFromMime(string $mime): string
    {
        return match (strtolower($mime)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => '',
        };
    }

    private function normalizedFilename(string $originalName, string $fallbackExtension): string
    {
        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $base = trim((string) Str::of($base)->ascii()->replaceMatches('/[^A-Za-z0-9\-_]+/', '-')->trim('-'));
        if ($base === '') {
            $base = 'document';
        }

        return $base . '.' . $fallbackExtension;
    }

    private function metadataPath(string $token): string
    {
        return trim(self::METADATA_DIRECTORY, '/') . '/' . $token . '.json';
    }

    private function deleteMetadata(string $token): void
    {
        Storage::disk(self::METADATA_DISK)->delete($this->metadataPath($token));
    }

    private function publicUrl(string $storedPath): string
    {
        return Storage::disk('public')->url($storedPath);
    }
}
