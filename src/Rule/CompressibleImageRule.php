<?php

namespace Condoedge\Utils\Rule;

use Closure;
use Condoedge\Utils\Services\Images\ImageCompressionServiceContract;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class CompressibleImageRule implements ValidationRule
{
	protected int $maxFinalKb;
	protected int $maxIncomingKb;
	protected int $maxWidth;

	public function __construct(?int $maxFinalKb = null, ?int $maxIncomingKb = null, ?int $maxWidth = null)
	{
		$this->maxFinalKb = $maxFinalKb ?? (int) config('kompo-files.image-compression.target-max-kb', 2048);
		$this->maxIncomingKb = $maxIncomingKb ?? (int) config('kompo-files.image-compression.incoming-max-kb', 20480);
		$this->maxWidth = $maxWidth ?? (int) config('kompo-files.image-compression.max-width', 2000);
	}

	/**
	 * This rule intentionally mutates request()->files when compression occurs.
	 * The swap ensures downstream Kompo upload/storage reads the compressed bytes.
	 *
	 * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
	 */
	public function validate(string $attribute, mixed $value, Closure $fail): void
	{
		if (!($value instanceof UploadedFile)) {
			return;
		}

		if (($value->getSize() ?? 0) > ($this->maxIncomingKb * 1024)) {
			$fail(__('utils.image-too-large-to-process', ['max' => $this->toDisplayMb($this->maxIncomingKb)]));

			return;
		}

		$compressionService = app(ImageCompressionServiceContract::class);

		if (!$compressionService->canCompress($value)) {
			if (($value->getSize() ?? 0) > ($this->maxFinalKb * 1024)) {
				$fail(__('utils.image-could-not-be-compressed', ['max' => $this->toDisplayMb($this->maxFinalKb)]));
			}

			return;
		}

		try {
			$compressed = $compressionService->compressRequestFile($attribute, $this->maxFinalKb, $this->maxWidth);

			if ($compressed && ($compressed->getSize() ?? 0) > ($this->maxFinalKb * 1024)) {
				$fail(__('utils.image-could-not-be-compressed', ['max' => $this->toDisplayMb($this->maxFinalKb)]));
			}
		} catch (\Throwable $exception) {
			if ($exception->getMessage() === 'IMAGE_TOO_LARGE_TO_PROCESS') {
				$fail(__('utils.image-too-large-to-process', ['max' => $this->toDisplayMb($this->maxIncomingKb)]));

				return;
			}

			$fail(__('utils.image-could-not-be-compressed', ['max' => $this->toDisplayMb($this->maxFinalKb)]));
		}
	}

	protected function toDisplayMb(int $kb): string
	{
		$mb = round($kb / 1024, 2);

		return rtrim(rtrim((string) $mb, '0'), '.');
	}
}
