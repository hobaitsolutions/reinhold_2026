<?php

namespace hobaIT;




class ruleCondition
{

	public string $id;
	public string $type;
	public string $ruleId;
	public ?string $parentId;
	public value $value;

	/**
	 * @return string
	 */
	public function getId(): string
	{
		return $this->id;
	}

	/**
	 * @param string $id
	 */
	public function setId(string $id): void
	{
		$this->id = $id;
	}

	/**
	 * @return string
	 */
	public function getType(): string
	{
		return $this->type;
	}

	/**
	 * @param string $type
	 */
	public function setType(string $type): void
	{
		$this->type = $type;
	}

	/**
	 * @return string
	 */
	public function getRuleId(): string
	{
		return $this->ruleId;
	}

	/**
	 * @param string $ruleId
	 */
	public function setRuleId(string $ruleId): void
	{
		$this->ruleId = $ruleId;
	}

	/**
	 * @return string
	 */
	public function getParentId(): string
	{
		return $this->parentId;
	}

	/**
	 * @param ?string $parentId
	 */
	public function setParentId(?string $parentId = null): void
	{
		$this->parentId = $parentId;
	}

	public function listTypes(){
		//ein Hoch auf mich!!!
		var_dump(
			[
				'cartAlwaysValid',
				'cartCartAmount',
				'cartCartHasDeliveryFreeItem',
				'cartCartRuleS',
				'cartCartWeight',
				'cartGoodsCount',
				'cartGoodsPrice',
				'cartLineItemClearanceSale',
				'cartLineItemCreationDate',
				'cartLineItemCustomField',
				'cartLineItemDimensionHeight',
				'cartLineItemDimensionLength',
				'cartLineItemDimensionWeight',
				'cartLineItemDimensionWidth',
				'cartLineItemGroup',
				'cartLineItemInCategory',
				'cartLineItemIsNew',
				'cartLineItemListPrice',
				'cartLineItemOfManufacturer',
				'cartLineItemOfType',
				'cartLineItemPromoted',
				'cartLineItemProperty',
				'cartLineItemPurchasePrice',
				'cartLineItemReleaseDate',
				'cartLineItem',
				'cartLineItemS',
				'cartLineItemTag',
				'cartLineItemTaxation',
				'cartLineItemTotalPrice',
				'cartLineItemUnitPrice',
				'cartLineItemWithQuantity',
				'cartLineItemWrapper',
				'cartLineItemsInCartCount',
				'cartLineItemsInCart',
				'cartPaymentMethod',
				'cartShippingMethod',
				'customerBillingCountry',
				'customerBillingStreet',
				'customerBillingZipCode',
				'customerCustomerGroup',
				'customerCustomerNumber',
				'customerCustomerTag',
				'customerDaysSinceLastOrder',
				'customerDifferentAddresses',
				'customerIsCompany',
				'customerIsNewCustomer',
				'customerLastName',
				'customerOrderCount',
				'customerShippingCountry',
				'customerShippingStreet',
				'customerShippingZipCode',
			]
		);
	}

	/**
	 * @return array
	 */
	public function getValue(): array
	{
		return $this->value;
	}

	/**
	 * @param array $value
	 */
	public function setValue(value $value): void
	{
		$this->value = $value;
	}

}

