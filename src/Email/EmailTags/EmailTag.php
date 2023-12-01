<?php
namespace GiveRecurring\Email\EmailTags;

/**
 * Class EmailTag
 * @package GiveRecurring\Email\EmailTags
 *
 * Extend this class when add new email tag.
 *
 * @since 1.11.5
 */
abstract class EmailTag {
	/**
	 * Get email tag id.
	 *
	 * @since 1.11.5
	 *
	 * @return string
	 */
	abstract public function getId();

	/**
	 * Get email tag description.
	 *
	 * @since 1.11.5
	 *
	 * @return string
	 */
	abstract public function getDescription();

	/**
	 * Get email tag description.
	 *
	 * @since 1.11.5
	 *
	 * @return string
	 */
	abstract public function getContext();

	/**
	 * Register email tag
	 *
	 * @since 1.11.5
	 */
	public function register(){
		give_add_email_tag([
			'tag'     => $this->getId(),
			'desc'    => $this->getDescription(),
			'func'    => [ $this, 'decode' ],
			'context' => $this->getContext(),
		]);
	}

	/**
	 * Decode email tag.
	 *
	 * @since 1.11.5
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	abstract public function decode( $args );

	/**
	 * Return email tag code.
	 *
	 * @since 1.11.5
	 *
	 * @return string
	 */
	public function getCode(){
		return sprintf(
			'{%1$s}',
			$this->getId()
		);
	}
}
