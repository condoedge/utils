<?php

namespace Condoedge\Utils\Services\Images;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Facades\Image;

class InterventionImageCompressionService implements ImageCompressionServiceContract
{
	protected const PNG_SHRINK_ATTEMPTS = 4;
	protected const PNG_SHRINK_RATIO = 0.8;

	public function canCompress(mixed $file): bool
	{
		if (!($file instanceof UploadedFile)) {
			return false;
		}

		return in_array(strtolower((string) $file->getMimeType()), [
			'image/jpeg',
			'image/jpg',
			'image/png',
			'image/webp',
			'image/gif',
			'image/bmp',
			'image/x-ms-bmp',
		], true);
	}

	public function compress(UploadedFile $file, ?int $maxKb = null, ?int $maxWidth = null): UploadedFile
	{
		if (!$this->canCompress($file)) {
			return $file;
		}

		$maxBytes = $this->resolveMaxBytes($maxKb);

		if (($file->getSize() ?? 0) <= $maxBytes) {
			return $file;
		}

		$realPath = $file->getRealPath();

		if (!$realPath || !is_file($realPath)) {
			throw new \RuntimeException('The uploaded image cannot be read.');
		}

		$this->guardMegapixels($realPath);

		$bytes = file_get_contents($realPath);

		if ($bytes === false) {
			throw new \RuntimeException('The uploaded image bytes cannot be read.');
		}

		$image = Image::make($bytes)->orientate();

		$image->resize($maxWidth ?? $this->defaultMaxWidth(), null, function ($constraint) {
			$constraint->aspectRatio();
			$constraint->upsize();
		});

		$format = $this->resolveOutputFormat($file);
		$encoded = $this->encodeUnderTargetSize($image, $format, $maxBytes);

		$tmpPath = tempnam(sys_get_temp_dir(), 'img-cmpr-');

		if ($tmpPath === false) {
			throw new \RuntimeException('Could not create a temporary image file.');
		}

		file_put_contents($tmpPath, $encoded);

		$nameWithoutExtension = pathinfo($file->getClientOriginalName() ?: 'image', PATHINFO_FILENAME) ?: 'image';
		$normalizedExtension = $format === 'jpeg' ? 'jpg' : $format;

		return new UploadedFile(
			$tmpPath,
			$nameWithoutExtension . '.' . $normalizedExtension,
			$this->mimeFromFormat($format),
			null,
			true,
		);
	}

	public function compressRequestFile(string $field, ?int $maxKb = null, ?int $maxWidth = null): ?UploadedFile
	{
		$file = request()->file($field);

		if (!($file instanceof UploadedFile) || !$this->canCompress($file)) {
			return null;
		}

		$compressed = $this->compress($file, $maxKb, $maxWidth);

		if ($compressed !== $file) {
			request()->files->set($field, $compressed);
		}

		return $compressed;
	}

	protected function guardMegapixels(string $realPath): void
	{
		$imageSize = @getimagesize($realPath);

		if (!is_array($imageSize)) {
			throw new \RuntimeException('The image dimensions cannot be read.');
		}

		$width = (int) ($imageSize[0] ?? 0);
		$height = (int) ($imageSize[1] ?? 0);
		$pixels = $width * $height;

		if ($pixels <= 0) {
			throw new \RuntimeException('The image has invalid dimensions.');
		}

		if ($pixels > ($this->maxMegapixels() * 1_000_000)) {
			throw new \RuntimeException('IMAGE_TOO_LARGE_TO_PROCESS');
		}
	}

	protected function encodeUnderTargetSize($image, string $format, int $maxBytes): string
	{
		if (in_array($format, ['jpg', 'jpeg', 'webp'], true)) {
			$encoded = '';

			foreach ($this->qualityLadder() as $quality) {
				$encoded = (string) $image->encode($format, (int) $quality);

				if (strlen($encoded) <= $maxBytes) {
					return $encoded;
				}
			}

			return $encoded;
		}

		$encoded = (string) $image->encode($format, 9);

		for ($attempt = 0; $attempt < static::PNG_SHRINK_ATTEMPTS && strlen($encoded) > $maxBytes; $attempt++) {
			$image = $image->resize((int) max(1, floor($image->width() * static::PNG_SHRINK_RATIO)), null, function ($constraint) {
				$constraint->aspectRatio();
			});

			$encoded = (string) $image->encode($format, 9);
		}

		return $encoded;
	}

	protected function resolveOutputFormat(UploadedFile $file): string
	{
		$originalExtension = strtolower((string) $file->getClientOriginalExtension());
		$mime = strtolower((string) $file->getMimeType());

		if ($originalExtension === 'gif' || $mime === 'image/gif') {
			return 'png';
		}

		if (in_array($originalExtension, ['png', 'webp'], true)) {
			return $originalExtension;
		}

		if ($mime === 'image/png') {
			return 'png';
		}

		if ($mime === 'image/webp') {
			return 'webp';
		}

		return 'jpg';
	}

	protected function mimeFromFormat(string $format): string
	{
		return match ($format) {
			'jpg', 'jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'webp' => 'image/webp',
			default => 'image/jpeg',
		};
	}

	protected function resolveMaxBytes(?int $maxKb = null): int
	{
		return (($maxKb ?? $this->targetMaxKb()) * 1024);
	}

	protected function targetMaxKb(): int
	{
		return (int) config('kompo-files.image-compression.target-max-kb', 2048);
	}

	protected function defaultMaxWidth(): int
	{
		return (int) config('kompo-files.image-compression.max-width', 2000);
	}

	protected function maxMegapixels(): int
	{
		return (int) config('kompo-files.image-compression.max-megapixels', 60);
	}

	protected function qualityLadder(): array
	{
		return config('kompo-files.image-compression.quality-ladder', [85, 75, 65, 55, 45]);
	}
}

