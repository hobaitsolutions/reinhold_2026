<?php

namespace hobaIT;

class image
{
	public string $mediaId;

	/**
	 * @return string
	 */
	public function getMediaId(): string
	{
		return $this->mediaId;
	}

	/**
	 * @param string $mediaId
	 */
	public function setMediaId(string $mediaId): void
	{
		$this->mediaId = $mediaId;
	}

	public function __construct(string $image)
	{
		$this->mediaId = $image;
	}
}
