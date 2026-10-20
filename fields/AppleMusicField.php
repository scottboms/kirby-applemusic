<?php

namespace Scottboms\AppleMusic;

use Kirby\Form\Field\InputField;
use Kirby\Reflection\Attributes\Prop;

class AppleMusicField extends InputField
{
	/**
	 * Apple Music output format.
	 * Resolved from the plugin configuration.
	 */
	#[Prop(computed: true)]
	protected string $format = 'embed';

	/**
	 * Stored Apple Music URL or iframe HTML
	 */
	protected mixed $value = null;

	/**
	 * Preserve the original computed format behavior.
	 */
	public function format(): string
	{
		return option('scottboms.applemusic.format', 'embed');
	}

	/**
	 * Value passed to the Panel.
	 */
	public function toFormValue(): mixed
	{
		return parent::toFormValue();
	}

	/**
	 * Value written to the content file.
	 */
	public function toStoredValue(): mixed
	{
		return $this->toFormValue();
	}
}
