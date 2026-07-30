<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class ImportHeaderValidator
{
    /**
     * @param array<int,mixed> $headerRow
     */
    public function match(array $headerRow, ImportTemplateSchema $schema): ImportHeaderMatchResult
    {
        $aliases = $schema->aliases();
        $canonicalHeaders = [];
        $indexes = [];
        $unknownHeaders = [];

        foreach (array_values($headerRow) as $index => $rawHeader) {
            $rawLabel = trim((string) $rawHeader);
            $normalized = HeaderNormalizer::normalize($rawLabel);

            if ($normalized === '') {
                continue;
            }

            $canonical = $aliases[$normalized] ?? null;
            if ($canonical === null) {
                $unknownHeaders[] = $rawLabel;
                continue;
            }

            if (! array_key_exists($canonical, $indexes)) {
                $canonicalHeaders[] = $canonical;
                $indexes[$canonical] = $index;
            }
        }

        $missingRequired = array_values(array_filter(
            $schema->requiredColumns(),
            static fn (string $header) => ! array_key_exists($header, $indexes)
        ));

        return new ImportHeaderMatchResult(
            rawHeaders: array_map(static fn ($value) => trim((string) $value), array_values($headerRow)),
            canonicalHeaders: $canonicalHeaders,
            indexes: $indexes,
            unknownHeaders: array_values(array_filter($unknownHeaders, static fn (string $value) => $value !== '')),
            missingRequired: $missingRequired,
        );
    }

    public function ensureValid(ImportHeaderMatchResult $result, string $moduleLabel): void
    {
        if ($result->isValid()) {
            return;
        }

        $message = 'Header template ' . $moduleLabel . ' tidak sesuai.';
        if ($result->missingRequired !== []) {
            $message .= ' Kolom wajib yang belum ditemukan: ' . implode(', ', $result->missingRequired) . '.';
        }
        if ($result->unknownHeaders !== []) {
            $message .= ' Kolom yang tidak dikenali: ' . implode(', ', $result->unknownHeaders) . '.';
        }

        throw ValidationException::withMessages(['file' => $message]);
    }
}
