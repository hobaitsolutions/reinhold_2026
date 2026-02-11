<?php

namespace hobaIT;

class ruleCustomFrields
{
	public string $custom_rule_personennr = '';

	/**
	 * @return string
	 */
	public function getCustomRulePersonennr(): string
	{
		return $this->custom_rule_personennr;
	}

	/**
	 * @param string $custom_rule_personennr
	 */
	public function setCustomRulePersonennr(string $custom_rule_personennr): void
	{
		$this->custom_rule_personennr = $custom_rule_personennr;
	}
}
