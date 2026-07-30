<?php

return [
    'max_execution_time' => env('HR_EXPORT_MAX_EXECUTION_TIME', 120),
    'memory_limit' => env('HR_EXPORT_MEMORY_LIMIT', '512M'),
    'dompdf_dpi' => env('HR_EXPORT_DOMPDF_DPI', 96),
    'cv_pdf_preview_enabled' => env('HR_EXPORT_CV_PREVIEW_ENABLED', true),
    'cv_pdf_preview_max_pages' => env('HR_EXPORT_CV_MAX_PAGES', 2),
    'cv_pdf_preview_resolution' => env('HR_EXPORT_CV_DPI', 96),
    'cv_pdf_preview_quality' => env('HR_EXPORT_CV_QUALITY', 70),
    'cv_pdf_preview_max_file_bytes' => env('HR_EXPORT_CV_MAX_FILE_BYTES', 5242880),
];
