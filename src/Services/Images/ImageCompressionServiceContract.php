<?php

namespace Condoedge\Utils\Services\Images;

use Illuminate\Http\UploadedFile;

interface ImageCompressionServiceContract
{
    /** True for raster mimes the service can re-encode (jpeg, png, webp, gif, bmp). */
    public function canCompress(mixed $file): bool;

    /**
     * Re-encode toward the byte budget. Returns the SAME instance if already
     * under budget (idempotency guard), else a NEW UploadedFile (test-mode)
     * pointing at a fresh temp file.
     */
    public function compress(UploadedFile $file, ?int $maxKb = null, ?int $maxWidth = null): UploadedFile;

    /**
     * compress() the named request file and swap the result into
     * request()->files so kompo's RequestData/FileHandler read the compressed
     * bytes. Returns the final UploadedFile, or null if absent/not compressible.
     */
    public function compressRequestFile(string $field, ?int $maxKb = null, ?int $maxWidth = null): ?UploadedFile;
}