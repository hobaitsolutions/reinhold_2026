<?php

namespace Reinhold\Twig;

use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;


const BASEDIR = '/var/www/vhosts/reinhold-sohn-hygiene.de/staging.reinhold-sohn-hygiene.de/public/';

class Common extends AbstractExtension
{
	private EntityRepository $productRepo;
	private Connection $connection;
	private EntityCollection $products;


	public function __construct(EntityRepository $productRepo, Connection $connection)
	{
		$this->productRepo = $productRepo;
		$this->connection  = $connection;
	}


	/**
	 * Returns an array of Twig filters used within the application.
	 *
	 * Each filter is an instance of `TwigFilter` and connects a specified name with its corresponding
	 * method in the current class, enabling customized transformations or processing within Twig templates.
	 *
	 * These filters serve a variety of purposes, including:
	 * - Formatting hazard information.
	 * - Manipulating purchase unit data.
	 * - Generating formatted versions of pictograms and product representations.
	 * - Modifying strings (e.g., highlighting, cutting lines, or replacing content with links).
	 * - Fetching related product data, such as linked products, replacements, and article matches.
	 * - Displaying manufacturer or contact information in a specific format.
	 *
	 * @return TwigFilter[] An array of Twig filters where each filter is mapped to a method for processing data.
	 */
	public function getFilters()
	{
		return [
			new TwigFilter('hazards', [$this, 'hazards']),
			new TwigFilter('purchaseunits', [$this, 'purchaseunits']),
			new TwigFilter('pictograms', [$this, 'pictograms']),
			new TwigFilter('getNameWithoutUnit', [$this, 'getNameWithoutUnit']),
			new TwigFilter('getUnit', [$this, 'getUnit']),
			new TwigFilter('cutlines', [$this, 'cutLines']),
			new TwigFilter('displayContact', [$this, 'displayContact']),
			new TwigFilter('highlightFirst', [$this, 'highlightFirst']),
			new TwigFilter('replaceWithLinks', [$this, 'replaceWithLinks']),
			new TwigFilter('getLinkedProducts', [$this, 'getLinkedProducts']),
			new TwigFilter('getReplacement', [$this, 'getReplacement']),
			new TwigFilter('hazardDescription', [$this, 'hazardDescription']),
			new TwigFilter('getDSArticleMatch', [$this, 'getDSArticleMatch']),
			new TwigFilter('printManufacturerData', [$this, 'printManufacturerData']),
		];
	}

	/**
	 * Represents hazards information which could be null or a string value.
	 *
	 * @param string|null $hazards
	 * @param bool|null   $hover
	 *
	 * @return string
	 */
	public function hazards(?string $hazards, ?bool $hover = true): string
	{
		if (empty($hazards)) return '';
		$string  = '';
		$hazards = json_decode($hazards);

		if (!empty($hazards))
		{
			$string .= '<div class="product-hazards"><h4>Gefahrenhinweise</h4><div class="hazards">';
			foreach ($hazards as $hazard)
			{
				$string .= self::getHazardSymbol(hazard: $hazard, hover: $hover); //attention, named arguments
			}
			$string .= '</div></div>';
		}

		return $string;
	}


	/**
	 * Retrieves a hazard symbol based on the provided identifier and constructs a formatted representation.
	 *
	 * Based on the input hazard code and type (either `svg` or `gif`), this method selects
	 * the corresponding symbol file from a predefined set. The symbol image path is wrapped and returned
	 * if a match is found. If no matching symbol exists, the method processes the hazard code through a
	 * custom handler and creates a formatted string representing the hazard description.
	 *
	 * @param string      $hazard The hazard identifier code (e.g., "GHS01", "GHS02").
	 * @param string      $path   The base directory path where symbol files are located.
	 *                            Defaults to '/artikelbilder/Gefahrenpiktogramme/'.
	 * @param bool|null   $hover  Defines whether the symbol is hoverable; defaults to true.
	 * @param string|null $type   The file type of the hazard symbol, either `svg` or `gif`.
	 *                            Defaults to 'svg'.
	 *
	 * @return string The HTML or formatted string representation of the hazard symbol or description.
	 */
	protected function getHazardSymbol(string $hazard, string $path = '/artikelbilder/Gefahrenpiktogramme/', ?bool $hover = true, ?string $type = 'svg'): string
	{
		if ('svg' == $type)
		{
			$symbols = [
				"GHS01" => "GHS01-pictogram-explos.svg",
				"GHS02" => "GHS02-pictogram-flamme.svg",
				"GHS03" => "GHS03-pictogram-rondflam.svg",
				"GHS04" => "GHS04-pictogram-bottle.svg",
				"GHS05" => "GHS05-pictogram-acid.svg",
				"GHS06" => "GHS06-pictogram-skull.svg",
				"GHS07" => "GHS07-pictogram-exclam.svg",
				"GHS08" => "GHS08-pictogram-silhouette.svg",
				"GHS09" => "GHS09-pictogram-pollu.svg",
				"GHS"   => "GHS-pictogram-pollu.svg"
			];
		}
		else
		{
			$symbols = [
				"GHS01" => "GHS01_explos.gif",
				"GHS02" => "GHS02_flamme.gif",
				"GHS03" => "GHS03_rondflam.gif",
				"GHS04" => "GHS04_bottle.gif",
				"GHS05" => "GHS05_acid_red.gif",
				"GHS06" => "GHS06_skull.gif",
				"GHS07" => "GHS07_exclam.gif",
				"GHS08" => "GHS08_silhouete.gif",
				"GHS09" => "GHS09_Aquatic-pollut-red.gif",
			];
		}

		if (array_key_exists($hazard, $symbols))
		{
			return self::wrapImage(name: $symbols[$hazard], path: $path, hover: $hover);
		}
		else if ($hazard == 'Gefahr')
		{
			return '<h3 class="mr-2 border-1 border-danger" >' . $hazard . '</h3>';
		}
		else if (strtolower($hazard) == 'enthält'){
			return '<div class="hazard-text">' . $hazard . ':</div>';
		}
		else
		{
			return '<span class="hazard-text">' . $hazard . '</span>';
		}
	}

	/**
	 * Wraps the provided image name and path into an HTML `<img>` tag.
	 *
	 * This method constructs an HTML image element using the specified image name and an optional
	 * base path. It also sets a translation-aware `title` attribute for the image, where spaces in
	 * the image name are replaced with underscores for compatibility with translation filters.
	 *
	 * @param string      $name The name of the image file to be used in the image source.
	 * @param string|null $path The optional base path for the image file, defaulting to '/artikelbilder/artikel/'.
	 *
	 * @return string The generated HTML `<img>` tag with the provided source, `alt`, and `title` attributes.
	 */
	protected function wrapImage(string $name, ?string $path = '/artikelbilder/artikel/', $hover = false): string
	{
		if ($hover)
		{
			return '<img src="' . $path . $name . '" alt="" title=\'{{"' . str_replace(' ', '_', $name) . '" | trans}}\' />';
		}
		else
		{
			return '<div class="hazard"><img src="' . $path . $name . '" alt=""/><div class="description">{{"' . str_replace(' ', '_', $name) . '" | trans}}</div></div>';
		}
	}

	/**
	 * Calculates and formats the total purchase units based on the provided count and unit input.
	 *
	 * The function parses and extracts numeric values from the given count and unit strings,
	 * performs a multiplication to calculate the total, and appends the unit label.
	 * Non-numeric elements in the unit string are retained as part of the final result.
	 * If either the count or unit is empty, a default concatenated string is returned.
	 *
	 * @param string|null $count The quantity of the item to be calculated; can be null or a string representation of a number.
	 * @param string|null $unit  The unit of measure, which may include numeric and non-numeric components; can be null.
	 *
	 * @return string A string representing the total calculated units, formatted with the numeric total and unit label.
	 */
	public function purchaseunits(?string $count, ?string $unit)
	{
		if (empty($count) || empty($unit)) return $count . ' × ' . $unit;
		$count = str_replace(',', '.', $count);
		@preg_match('!\d+\.*\d*!', $count, $number1);
		$number1 = (float) $number1[0];
		@preg_match('!\d+\.*\d*!', $unit, $number2);
		@$number2 = (float) $number2[0];
		if (empty($number2))
		{
			$number2 = 1;
		}
		$unit = preg_replace('!\d+(\.,)*\d*!', '', $unit);
		$all  = $number1 * $number2;
		if ($all > 1) $all = (int) $all;

		return str_replace('  ', ' ', $all . ' ' . $unit);
	}

	public function pictograms(/**
	 *
	 */ /**
	 */ /**
	 **/ /**
	 * @*/ /**
	 * @var
	 */ /**
	 * @var string
	 */ /**
	 * @var string $
	 */ /**
	 * @var string $p
	 */ /**
	 * @var string $pict
	 */ /**
	 * @var string $pictos
	 */ /**
	 * @var string $pictos A
	 */ /**
	 * @var string $pictos A variable
	 */ /**
	 * @var string $pictos A variable to
	 */ /**
	 * @var string $pictos A variable to store
	 */ /**
	 * @var string $pictos A variable to store pict
	 */ /**
	 * @var string $pictos A variable to store pictographic
	 */ /**
	 * @var string $pictos A variable to store pictographic representation
	 */ /**
	 * @var string $pictos A variable to store pictographic representation or
	 */ /**
	 * @var string $pictos A variable to store pictographic representation or related
	 */ /**
	 * @var string $pictos A variable to store pictographic representation or related data
	 */ /**
	 * @var string $pictos A variable to store pictographic representation or related data.
	 */ /**
	 * @var string $pictos A variable to store pictographic representation or related data.
	 */ string $pictos)
	{
		$string = '';
		if (!empty($pictos))
		{
			$all    = explode(',', $pictos);
			$length = count($all);
			for ($i = 0; $i < $length; ++$i)
			{
				$current = current($all); //fix ,5 picto


				$next = next($all);
				if (strlen($next) == 1)
				{
					$current .= ',' . $next;
				}
				$string .= self::getPictoImage($current);
			}
		}

		return new \Twig\Markup($string, 'UTF-8');
	}

	/**
	 * Generates an HTML image tag for a pictogram based on the provided name.
	 *
	 * This method dynamically creates an <img> element for a specific pictogram, adjusting
	 * the "order" style property based on whether the name contains the substring "pH-Wert".
	 * The image source is generated by replacing certain parts of the provided name, ensuring
	 * the correct file path for the pictogram. Additionally, a translatable title attribute
	 * is included in the generated image tag.
	 *
	 * @param string $name The name of the pictogram used to determine the image source and attributes.
	 *
	 * @return string The generated HTML string representing the pictogram image.
	 */
	protected static function getPictoImage(string $name): string
	{
		$order = '1';
		if (strstr($name, 'pH-Wert'))
		{
			$order = 0;
		}

		return '<img style="order:' . $order . '" src="/artikelbilder/Piktogramme/Piktogramm_' . str_replace('pH-Wert', 'pH-Wert ', $name) . '.jpg" alt="" title="{{"' . str_replace(' ', '_', $name) . '" | trans}}"/>';
	}

	/**
	 * Extracts the name component from a given string, excluding any unit information.
	 *
	 * This method processes the input string using a utility function to separate the
	 * name and unit. It then retrieves and returns only the name portion of the string.
	 *
	 * @param string $name The input string containing a name and potentially a unit.
	 *
	 * @return string The name portion of the input string, with the unit excluded.
	 */
	public function getNameWithoutUnit(string $name): string
	{
		return (self::splitName($name)['name']);
	}

	/**
	 * Retrieves the unit information from a given name.
	 *
	 * This method extracts and returns the 'package' value from the name by processing it through
	 * a static method. The input string is expected to be structured in a way that allows accurate
	 * parsing and retrieval of the relevant 'package' information.
	 *
	 * @param string $name The input string containing the name from which the unit information is extracted.
	 *
	 * @return string The extracted unit 'package' value from the given name.
	 */
	public function getUnit(string $name): string
	{
		return self::splitName($name)['package'];

	}

	/**
	 * Splits a given name string into separate components based on a delimiter.
	 *
	 * The method separates the last segment of the string, referred to as the "package",
	 * from the rest of the string. The delimiter used for splitting is the pipe character (`|`).
	 * The remaining part of the string, excluding the last segment, is grouped together
	 * and returned along with the extracted "package".
	 *
	 * @param string $name The input string to be split, containing segments separated by `|`.
	 *
	 * @return array An associative array with two keys:
	 *               - 'name': The string with all segments except the last, recombined with `|`.
	 *               - 'package': The last segment of the input string.
	 */
	protected static function splitName(string $name): array
	{
		$array   = explode('|', $name);
		$package = array_pop($array);

		return ['name' => implode('|', $array), 'package' => $package];
	}

	/**
	 * Removes the first two lines from the given text and returns the remaining lines.
	 *
	 * This method processes a string by splitting it into lines, skipping the first
	 * two, and reassembling the rest into a single string with newline characters
	 * separating each line.
	 *
	 * @param string $text The input text to process, containing multiple lines separated by newline characters.
	 *
	 * @return string The modified text after removing the first two lines.
	 */
	public function cutLines(string $text)
	{
		return implode("\n", array_slice(explode("\n", $text), 2));
	}

	/**
	 * Generates an HTML representation of customer contact information.
	 *
	 * This method processes the provided contact object to extract and display relevant customer
	 * contact details. If the contact is invalid or the required custom field is missing,
	 * an empty HTML string is returned. When valid contact data exists, it returns a structured
	 * HTML output containing the customer's name, email link, phone numbers, and optionally a
	 * contact image.
	 *
	 * The email and phone numbers are formatted for display, and multiple phone numbers (if present)
	 * are separated into separate lines.
	 *
	 * @param object|null $contact  The contact object containing custom fields, including
	 *                              serialized customer contact information; can be null.
	 *
	 * @return \Twig\Markup The formatted contact information as an HTML string, or an empty
	 *                      HTML string if no valid contact data is provided.
	 */
	public function displayContact($contact)
	{
		if (empty($contact) || empty($contact->customFields['custom_customer_contact']))
		{
			return new \Twig\Markup('', 'UTF-8');
		}
		$contact = json_decode($contact->customFields['custom_customer_contact']);

		if (!empty($contact->name))
		{
			$html = self::getContactImage($contact->image) . '<br><br>
		  <div class="font-weight-bold">
		  ' . $contact->name . '
          </div>
        <div class="mail">
            <a href="' . $contact->mail . '">E-Mail schreiben</a>
        </div>
        <div class="tel">
            ' . str_replace('|', '<br>', $contact->tel) . '
        </div>';
		}

		return new \Twig\Markup($html, 'UTF-8');
	}

	/**
	 * Retrieves the most recent contact image from a specific directory structure.
	 *
	 * If a valid image name is provided, this method searches in the designated employee image
	 * directory to find image files matching specified extensions (e.g., jpg, png, gif). The images
	 * are sorted by their modification time, and the latest image is returned as an HTML `<img>` tag.
	 * If no suitable image is found, or if the input is empty, an empty string will be returned.
	 *
	 * @param mixed $image The name of the image to be searched; can be any value.
	 *
	 * @return string An HTML `<img>` tag pointing to the most recent image, or an empty string
	 *                if no image is found or input is invalid.
	 */
	public function getContactImage($image)
	{
		if (!empty($image))
		{
			$dir = BASEDIR . '/artikelbilder/Mitarbeiter/' . $image . '/';
			if (file_exists($dir))
			{

				$files = glob($dir . '*.{jpg,png,gif,JPG,PNG,GIF}', GLOB_BRACE);
				usort($files, function ($a, $b) {
					if (filemtime($a) === filemtime($b)) return 0;

					return filemtime($a) < filemtime($b) ? -1 : 1;
				});
				$files = array_reverse($files);
				if (!empty($files))
				{
					$imageFile = str_replace(BASEDIR, '', $files[0]);

					return '<img src="' . $imageFile . '" alt="' . $image . '" />';
				}
			}
		}
		else
		{
			$image = '';
		}

		return $image;
	}

	/**
	 * Wraps the first line of the provided HTML content in an <h2> tag.
	 *
	 * The method splits the given input into lines, applies an <h2> wrapper
	 * to the first line, and then reassembles the content into a single HTML string.
	 *
	 * @param string $html The HTML content to be processed.
	 *
	 * @return \Twig\Markup The modified HTML content with the first line highlighted.
	 */
	public function highlightFirst($html)
	{

		$html    = explode("\n", $html);
		$html[0] = '<h2>' . $html[0] . '</h2>';

		return new \Twig\Markup(implode("\n", $html), 'UTF-8');
	}


	/**
	 * Retrieves a collection of products based on an array of product numbers.
	 *
	 * This method uses a criteria object to filter products by the provided product numbers
	 * and includes associated data such as cover images and thumbnails. The results are
	 * fetched from the product repository within the default execution context.
	 *
	 * @param array $productNumbers A list of product numbers used to filter the products.
	 *
	 * @return \Shopware\Core\Framework\DataAbstractionLayer\EntityCollection A collection of products
	 *                                                                         matching the given criteria.
	 */
	public function getProductsByProductNumbers(array $productNumbers)
	{
		$productsCriteria = new Criteria();
		$productsCriteria->addFilter(new EqualsAnyFilter('productNumber', $productNumbers));
		$productsCriteria->addAssociation('cover');
		$productsCriteria->addAssociation('thumbnails');

		return $this->productRepo->search($productsCriteria, Context::createDefaultContext())->getEntities();
	}

	/**
	 * Retrieves a collection of products based on an array of product numbers, excluding their images.
	 *
	 * This method filters the products using the provided product numbers and queries the product repository.
	 * It uses a default context to execute the search and returns the resulting set of product entities.
	 *
	 * @param array $productNumbers An array of product numbers used to filter the products.
	 *
	 * @return \Shopware\Core\Framework\DataAbstractionLayer\EntityCollection A collection of product entities matching the provided product numbers.
	 */
	public function getProductsByProductNumbersNoImages(array $productNumbers)
	{
		$productsCriteria = new Criteria();
		$productsCriteria->addFilter(new EqualsAnyFilter('productNumber', $productNumbers));

		return $this->productRepo->search($productsCriteria, Context::createDefaultContext())->getEntities();
	}

	/**
	 * Retrieves a product entity based on the provided product ID.
	 *
	 * This method constructs a criteria object to filter products by the given ID
	 * and performs a search in the product repository. The first matching product
	 * entity is returned, or null if no product matches the specified ID.
	 *
	 * @param string $id The unique identifier of the product to be retrieved.
	 *
	 * @return mixed|null The first matching product entity if found, or null if no product
	 *                    with the specified ID exists.
	 */
	public function getProductById(string $id)
	{
		$productsCriteria = new Criteria();
		$productsCriteria->addFilter(new EqualsFilter('id', $id));

		return $this->productRepo->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();
	}

	/**
	 * Retrieves and formats a link to a DS article that matches the given product number.
	 *
	 * Based on the provided product number, the method constructs a query to find product entries
	 * where the name matches the format "DS-Gebühr zu {productNumber}". If a matching product is found,
	 * its details are retrieved and used to generate an HTML link to the product's details page.
	 * If no match is found, an empty string is returned.
	 *
	 * @param string $productNumber The product number used to search for a corresponding DS article.
	 *
	 * @return string An HTML link to the matching DS article or an empty string if no match is found.
	 * @throws Exception
	 */
	public function getDSArticleMatch(string $productNumber)
	{
		$name = "DS-Gebühr zu " . $productNumber;
		$res  = $this->connection->query('SELECT lower(hex(product_id)) as id FROM product_translation WHERE name LIKE "' . $name . '%"')->fetchAll();
		if (!empty($res))
		{
			$p = self::getProductById($res[0]['id']);

			return "<a href=\"{{ seoUrl('frontend.detail.page', {'productId':'" . $p->getId() . "'}) }}\" title='" . ($p->getName()) . "'>" . $p->getProductNumber() . ' - ' . $p->getName() . "</a>";
		}
		else return '';
	}

	/**
	 * Replaces a product number with a hyperlink pointing to the respective product page or
	 * search result, based on the format of the provided product number.
	 *
	 * If the product number does not contain a hyphen ('-'), the function attempts to retrieve
	 * the corresponding product information. If found, a link is generated using the product's ID.
	 * Optionally, the product's name can be appended to the link text if $useNames is true.
	 *
	 * For product numbers containing a hyphen, a generic search link is created instead, where the
	 * part of the product number before the hyphen is used as the search query.
	 *
	 * If no specific conditions are met, the raw product number is returned as-is.
	 *
	 * @param array $productNumber An array that is expected to contain at least two elements,
	 *                             where the second element is used for determining the hyperlink.
	 * @param bool  $useNames      Optional. Whether to include the product name in the link text.
	 *
	 * @return string The product number as a hyperlink if conditions are met, or as plain text otherwise.
	 */
	public function replaceWithLink($productNumber, bool $useNames = false)
	{
		//$productNumber = array(2) { [0]=> string(16) "||85027-Nuance||" [1]=> string(12) "85027-Nuance" }
		if (!strstr($productNumber[1], '-'))
		{
			$p = (self::getProductsByProductNumbersNoImages([$productNumber[1]]))->first();
			if (!empty($p))
			{
				$text = $p->getProductNumber();
				if ($useNames)
				{
					$text .= ' ' . $p->name;
				}

				return "<a href=\"{{ seoUrl('frontend.detail.page', {'productId':'" . $p->getId() . "'}) }}\" title='" . ($p->getName()) . "'>" . $text . "</a>";
			}
		}
		else
		{
			return "<a href=\"/search?search=" . explode('-', $productNumber[1])[0] . "\">" . $productNumber[1] . "</a>";
		}

		return $productNumber[1];
	}

	/**
	 * Replaces placeholders in the given HTML string with corresponding links.
	 *
	 * This method identifies placeholders enclosed within double pipes (`||example||`) in the provided
	 * HTML content using a regular expression. It then replaces these placeholders with HTML links
	 * using a callback function. The transformation logic for the replacement is defined within
	 * the `replaceWithLink` method of the `\Reinhold\Twig\Common` class.
	 *
	 * @param string $html The HTML string containing placeholders to be replaced.
	 *
	 * @return string The modified HTML string with placeholders replaced by links.
	 */
	public function replaceWithLinks($html)
	{
		$regex = '/\|\|(.*?)\|\|/';
//		$html  = nl2br($html); @todo possibly dangerous - check if this makes a difference
		preg_match_all($regex, $html, $matches);
		$html = preg_replace_callback(
			$regex,
			['\Reinhold\Twig\Common', 'replaceWithLink'],
			$html);

		return ($html);
	}

	/**
	 * Extracts product numbers from the given HTML string and generates a list of linked products.
	 *
	 * This method searches the input HTML for patterns encapsulated by double vertical bars ("||")
	 * using a regular expression. For each match, a product number is extracted and passed to the
	 * `replaceWithLink` method to generate an individual link. All links are then assembled into a
	 * structured HTML unordered list. If no matches are found, an empty string is returned.
	 *
	 * @param string $html The HTML string containing product number placeholders.
	 *
	 * @return string The formatted HTML list of linked products, or an empty string if no matches are found.
	 */
	public function getLinkedProducts($html)
	{
		$regex = '/\|\|(.*?)\|\|/';
		$links = [];
		preg_match_all($regex, $html, $matches);
		foreach ($matches[1] as $pNumber)
		{ // matches[0] => ["||1234||"] $matches[1] => ["1234"]
			$links[] = self::replaceWithLink(['', $pNumber], true);
		}
		if (!empty($links))
		{
			return ('<ul class="related"><li>' . implode('</li><li>', $links) . '</li></ul>');

		}

		return '';
	}


	/**
	 * Parses the provided HTML content to identify replacement markers and generates a formatted
	 * HTML string indicating the replacements.
	 *
	 * The method searches for patterns in the format `||value||` within the input HTML using
	 * regular expressions and processes each matched value. For every match found, it generates
	 * a replacement link utilizing the `replaceWithLink` method. If any replacements are identified,
	 * they are concatenated into a formatted HTML block. Otherwise, an empty string is returned.
	 *
	 * @param string $html The HTML content containing potential replacement markers.
	 *
	 * @return string A formatted HTML string displaying the replacements, or an empty string if no matches are found.
	 */
	public function getReplacement($html)
	{
		$regex = '/\|\|(.*?)\|\|/';
		$links = [];
		preg_match_all($regex, $html, $matches);
		foreach ($matches[1] as $pNumber)
		{ // matches[0] => ["||1234||"] $matches[1] => ["1234"]
			$links[] = self::replaceWithLink(['', $pNumber]);
		}
		if (!empty($links))
		{
			return '<br><br><strong>Ersetzt durch: </strong>' . implode(', ', $links);
		}

		return '';
	}


	/**
	 * Retrieves a hazard description based on the provided hazard number.
	 *
	 * If the input is null or empty, an empty string is returned. If the hazard number
	 * is a JSON array, it will be flattened into a single space-delimited string. The
	 * description corresponding to the hazard number is then retrieved from a predefined
	 * list of descriptions stored in a JSON file or hardcoded values. If no matching entry
	 * is found, the method returns an empty string.
	 *
	 * @param string|null $hazardNumber The hazard number to look up; can be null or a JSON array.
	 *
	 * @return string The hazard description if found, otherwise an empty string.
	 */
	public function hazardDescription(?string $hazardNumber, ?bool $printDescription = false)
	{
		if (empty($hazardNumber))
		{
			return '';
		}

		if (is_array(json_decode($hazardNumber)))
		{
			$hazardNumber = implode(' ', json_decode($hazardNumber));
		}

		$hazardsJson = json_decode(file_get_contents(BASEDIR . "/hazard_descriptions.json"), true);
		$hazards     = [
			"H200"                  => "Instabil, explosiv",
			"H201"                  => "Explosiv, Gefahr der Massenexplosion.",
			"H202"                  => "Explosiv; große Gefahr durch Splitter, Spreng- und Wurfstücke.",
			"H203"                  => "Explosiv; Gefahr durch Feuer, Luftdruck oder Splitter, Spreng- und Wurfstücke.",
			"H204"                  => "Gefahr durch Feuer oder Splitter, Spreng- und Wurfstücke.",
			"H205"                  => "Gefahr der Massenexplosion bei Feuer.",
			"H206"                  => "Gefahr durch Feuer, Druckstoß oder Sprengstücke; erhöhte Explosionsgefahr, wenn das Desensibilisierungsmittel reduziert wird.",
			"H207"                  => "Gefahr durch Feuer oder Sprengstücke; erhöhte Explosionsgefahr, wenn das Desensibilisierungsmittel reduziert wird.",
			"H208"                  => "Gefahr durch Feuer; erhöhte Explosionsgefahr, wenn das Desensibilisierungsmittel reduziert wird.",
			"H209"                  => "Explosiv.",
			"H210"                  => "Sehr empfindlich.",
			"H211"                  => "Kann empfindlich sein.",
			"H220"                  => "Extrem entzündbares Gas.",
			"H221"                  => "Entzündbares Gas.",
			"H222"                  => "Extrem entzündbares Aerosol.",
			"H223"                  => "Entzündbares Aerosol.",
			"H224"                  => "Flüssigkeit und Dampf extrem entzündbar.",
			"H225"                  => "Flüssigkeit und Dampf leicht entzündbar.",
			"H226"                  => "Flüssigkeit und Dampf entzündbar.",
			"H227"                  => "Brennbare Flüssigkeit.",
			"H228"                  => "Entzündbarer Feststoff.",
			"H229"                  => "Behälter steht unter Druck: kann bei Erwärmung bersten.",
			"H230"                  => "Kann auch in Abwesenheit von Luft explosionsartig reagieren.",
			"H231"                  => "Kann auch in Abwesenheit von Luft bei erhöhtem Druck und/oder erhöhter Temperatur explosionsartig reagieren.",
			"H232"                  => "Kann sich bei Kontakt mit Luft spontan entzünden.",
			"H240"                  => "Erwärmung kann Explosion verursachen.",
			"H241"                  => "Erwärmung kann Brand oder Explosion verursachen.",
			"H242"                  => "Erwärmung kann Brand verursachen.",
			"H250"                  => "Entzündet sich in Berührung mit Luft von selbst.",
			"H251"                  => "Selbsterhitzungsfähig; kann in Brand geraten.",
			"H252"                  => "In großen Mengen selbsterhitzungsfähig; kann in Brand geraten.",
			"H260"                  => "In Berührung mit Wasser entstehen entzündbare Gase, die sich spontan entzünden können.",
			"H261"                  => "In Berührung mit Wasser entstehen entzündbare Gase.",
			"H270"                  => "Kann Brand verursachen oder verstärken; Oxidationsmittel.",
			"H271"                  => "Kann Brand oder Explosion verursachen; starkes Oxidationsmittel.",
			"H272"                  => "Kann Brand verstärken; Oxidationsmittel.",
			"H280"                  => "Enthält Gas unter Druck; kann bei Erwärmung explodieren.",
			"H281"                  => "Enthält tiefgekühltes Gas; kann Kälteverbrennungen oder -verletzungen verursachen.",
			"H282"                  => "Extrem entzündbare Chemikalie unter Druck; kann bei Erwärmung explodieren.",
			"H283"                  => "Entzündbare Chemikalie unter Druck; kann bei Erwärmung explodieren.",
			"H284"                  => "Chemikalie unter Druck; kann bei Erwärmung explodieren.",
			"H290"                  => "Kann gegenüber Metallen korrosiv sein.",
			"H300"                  => "Lebensgefahr bei Verschlucken.",
			"H301"                  => "Giftig bei Verschlucken.",
			"H302"                  => "Gesundheitsschädlich bei Verschlucken.",
			"H303"                  => "Kann bei Verschlucken gesundheitsschädlich sein.",
			"H304"                  => "Kann bei Verschlucken und Eindringen in die Atemwege tödlich sein.",
			"H305"                  => "Kann bei Verschlucken und Eindringen in die Atemwege gesundheitsschädlich sein.",
			"H310"                  => "Lebensgefahr bei Hautkontakt.",
			"H311"                  => "Giftig bei Hautkontakt.",
			"H312"                  => "Gesundheitsschädlich bei Hautkontakt.",
			"H313"                  => "Kann bei Hautkontakt gesundheitsschädlich sein.",
			"H314"                  => "Verursacht schwere Verätzungen der Haut und schwere Augenschäden.",
			"H315"                  => "Verursacht Hautreizungen.",
			"H316"                  => "Verursacht leichte Hautreizungen.",
			"H317"                  => "Kann allergische Hautreaktionen verursachen.",
			"H318"                  => "Verursacht schwere Augenschäden.",
			"H319"                  => "Verursacht schwere Augenreizung.",
			"H320"                  => "Verursacht Augenreizung.",
			"H330"                  => "Lebensgefahr bei Einatmen.",
			"H331"                  => "Giftig bei Einatmen.",
			"H332"                  => "Gesundheitsschädlich bei Einatmen.",
			"H333"                  => "Kann bei Einatmen gesundheitsschädlich sein.",
			"H334"                  => "Kann bei Einatmen Allergie, asthmaartige Symptome oder Atembeschwerden verursachen.",
			"H335"                  => "Kann die Atemwege reizen.",
			"H336"                  => "Kann Schläfrigkeit und Benommenheit verursachen.",
			"H340"                  => "Kann genetische Defekte verursachen (Expositionsweg angeben, sofern schlüssig belegt ist, dass diese Gefahr bei keinem anderen Expositionsweg besteht).",
			"H341"                  => "Kann vermutlich genetische Defekte verursachen (Expositionsweg angeben, sofern schlüssig belegt ist, dass diese Gefahr bei keinem anderen Expositionsweg besteht).",
			"H350"                  => "Kann Krebs erzeugen (Expositionsweg angeben, sofern schlüssig belegt ist, dass diese Gefahr bei keinem anderen Expositionsweg besteht).",
			"H350i"                 => "Kann bei Einatmen Krebs erzeugen.",
			"H351"                  => "Kann vermutlich Krebs erzeugen (Expositionsweg angeben, sofern schlüssig belegt ist, dass diese Gefahr bei keinem anderen Expositionsweg besteht).",
			"H360"                  => "Kann die Fruchtbarkeit beeinträchtigen oder das Kind im Mutterleib schädigen (konkrete Wirkung angeben, sofern bekannt) (Expositionsweg angeben, sofern schlüssig belegt ist, dass die Gefahr bei keinem anderen Expositionsweg besteht).",
			"H360F"                 => "Kann die Fruchtbarkeit beeinträchtigen.",
			"H360D"                 => "Kann das Kind im Mutterleib schädigen.",
			"H360FD"                => "Kann die Fruchtbarkeit beeinträchtigen. Kann das Kind im Mutterleib schädigen.",
			"H360Fd"                => "Kann die Fruchtbarkeit beeinträchtigen. Kann vermutlich das Kind im Mutterleib schädigen.",
			"H360Df"                => "Kann das Kind im Mutterleib schädigen. Kann vermutlich die Fruchtbarkeit beeinträchtigen.",
			"H361"                  => "Kann vermutlich die Fruchtbarkeit beeinträchtigen oder das Kind im Mutterleib schädigen (konkrete Wirkung angeben, sofern bekannt) (Expositionsweg angeben, sofern schlüssig belegt ist, dass die Gefahr bei keinem anderen Expositionsweg besteht).",
			"H361f"                 => "Kann vermutlich die Fruchtbarkeit beeinträchtigen.",
			"H361d"                 => "Kann vermutlich das Kind im Mutterleib schädigen.",
			"H361fd"                => "Kann vermutlich die Fruchtbarkeit beeinträchtigen. Kann vermutlich das Kind im Mutterleib schädigen.",
			"H362"                  => "Kann Säuglinge über die Muttermilch schädigen.",
			"H370"                  => "Schädigt die Organe (oder alle betroffenen Organe nennen, sofern bekannt) (Expositionsweg angeben, sofern schlüssig belegt ist, dass diese Gefahr bei keinem anderen Expositionsweg besteht).",
			"H371"                  => "Kann die Organe schädigen (oder alle betroffenen Organe nennen, sofern bekannt) (Expositionsweg angeben, sofern schlüssig belegt ist, dass diese Gefahr bei keinem anderen Expositionsweg besteht).",
			"H372"                  => "Schädigt die Organe (alle betroffenen Organe nennen) bei längerer oder wiederholter Exposition (Expositionsweg angeben, wenn schlüssig belegt ist, dass diese Gefahr bei keinem anderen Expositionsweg besteht).",
			"H373"                  => "Kann die Organe schädigen (alle betroffenen Organe nennen, sofern bekannt) bei längerer oder wiederholter Exposition (Expositionsweg angeben, wenn schlüssig belegt ist, dass diese Gefahr bei keinem anderen Expositionsweg besteht).",
			"H300+H310"             => "Lebensgefahr bei Verschlucken oder Hautkontakt.",
			"H300+H330"             => "Lebensgefahr bei Verschlucken oder Einatmen.",
			"H310+H330"             => "Lebensgefahr bei Hautkontakt oder Einatmen.",
			"H300+H310+H330"        => "Lebensgefahr bei Verschlucken, Hautkontakt oder Einatmen.",
			"H301+H311"             => "Giftig bei Verschlucken oder Hautkontakt.",
			"H301+H331"             => "Giftig bei Verschlucken oder Einatmen.",
			"H311+H331"             => "Giftig bei Hautkontakt oder Einatmen.",
			"H301+H311+H331"        => "Giftig bei Verschlucken, Hautkontakt oder Einatmen.",
			"H302+H312"             => "Gesundheitsschädlich bei Verschlucken oder Hautkontakt.",
			"H302+H332"             => "Gesundheitsschädlich bei Verschlucken oder Einatmen.",
			"H312+H332"             => "Gesundheitsschädlich bei Hautkontakt oder Einatmen.",
			"H302+H312+H332"        => "Gesundheitsschädlich bei Verschlucken, Hautkontakt oder Einatmen.",
			"H400"                  => "Sehr giftig für Wasserorganismen.",
			"H401"                  => "Giftig für Wasserorganismen.",
			"H402"                  => "Schädlich für Wasserorganismen.",
			"H410"                  => "Sehr giftig für Wasserorganismen mit langfristiger Wirkung.",
			"H411"                  => "Giftig für Wasserorganismen, mit langfristiger Wirkung.",
			"H412"                  => "Schädlich für Wasserorganismen, mit langfristiger Wirkung.",
			"H413"                  => "Kann für Wasserorganismen schädlich sein, mit langfristiger Wirkung.",
			"H420"                  => "Schädigt die öffentliche Gesundheit und die Umwelt durch Ozonabbau in der äußeren Atmosphäre.",
			"EUH001"                => "In trockenem Zustand explosiv., durch H206 – H208 ersetzt",
			"EUH006"                => "Mit und ohne Luft explosionsfähig., durch H230/P420 ersetzt",
			"EUH014"                => "Reagiert heftig mit Wasser.",
			"EUH018"                => "Kann bei Verwendung explosionsfähige / entzündbare Dampf /Luft-Gemische bilden.",
			"EUH019"                => "Kann explosionsfähige Peroxide bilden.",
			"EUH029"                => "Entwickelt bei Berührung mit Wasser giftige Gase.",
			"EUH031"                => "Entwickelt bei Berührung mit Säure giftige Gase.",
			"EUH032"                => "Entwickelt bei Berührung mit Säure sehr giftige Gase.",
			"EUH044"                => "Explosionsgefahr bei Erhitzen unter Einschluss.",
			"EUH059"                => "Die Ozonschicht schädigend., durch H420 ersetzt",
			"EUH066"                => "Wiederholter Kontakt kann zu spröder oder rissiger Haut führen.",
			"EUH070"                => "Giftig bei Berührung mit den Augen.",
			"EUH071"                => "Wirkt ätzend auf die Atemwege.",
			"EUH201"                => "Enthält Blei. Nicht für den Anstrich von Gegenständen verwenden, die von Kindern gekaut oder gelutscht werden könnten.",
			"EUH201A"               => "Achtung! Enthält Blei.",
			"EUH202"                => "Cyanacrylat. Gefahr. Klebt innerhalb von Sekunden Haut und Augenlider zusammen. Darf nicht in die Hände von Kindern gelangen.",
			"EUH203"                => "Enthält Chrom(VI). Kann allergische Reaktionen hervorrufen.",
			"EUH204"                => "Enthält Isocyanate. Kann allergische Reaktionen hervorrufen.",
			"EUH205"                => "Enthält epoxidhaltige Verbindungen. Kann allergische Reaktionen hervorrufen.",
			"EUH206"                => "Achtung! Nicht zusammen mit anderen Produkten verwenden, da gefährliche Gase (Chlor) freigesetzt werden können.",
			"EUH207"                => "Achtung! Enthält Cadmium. Bei der Verwendung entstehen gefährliche Dämpfe. Hinweise des Herstellers beachten. Sicherheitsanweisungen einhalten.",
			"EUH208"                => "Enthält (Name des sensibilisierenden Stoffes). Kann allergische Reaktionen hervorrufen.",
			"EUH209"                => "Kann bei Verwendung leicht entzündbar werden.",
			"EUH209A"               => "Kann bei Verwendung entzündbar werden.",
			"EUH210"                => "Sicherheitsdatenblatt auf Anfrage erhältlich.",
			"EUH211"                => "Achtung! Beim Sprühen können gefährliche lungengängige Tröpfchen entstehen. Aerosol oder Nebel nicht einatmen.",
			"EUH212"                => "Achtung! Bei der Verwendung kann gefährlicher lungengängiger Staub entstehen. Staub nicht einatmen.",
			"EUH380"                => "Kann beim Menschen endokrine Störungen verursachen.",
			"EUH381"                => "Steht in dem Verdacht, beim Menschen endokrine Störungen zu verursachen.",
			"EUH401"                => "Zur Vermeidung von Risiken für Mensch und Umwelt die Gebrauchsanleitung einhalten.",
			"EUH430"                => "Kann endokrine Störungen in der Umwelt verursachen.",
			"EUH431"                => "Steht in dem Verdacht, endokrine Störungen in der Umwelt zu verursachen.",
			"EUH440"                => "Anreicherung in der Umwelt und in lebenden Organismen einschließlich Menschen.",
			"EUH441"                => "Starke Anreicherung in der Umwelt und in lebenden Organismen einschließlich Menschen.",
			"EUH450"                => "Kann lang anhaltende und diffuse Verschmutzung von Wasserressourcen verursachen.",
			"EUH451"                => "Kann sehr lang anhaltende und diffuse Verschmutzung von Wasserressourcen verursachen.",
			"P101"                  => "Ist ärztlicher Rat erforderlich, Verpackung oder Kennzeichnungsetikett bereithalten.",
			"P102"                  => "Darf nicht in die Hände von Kindern gelangen.",
			"P103"                  => "Vor Gebrauch Kennzeichnungsetikett lesen.",
			"P201"                  => "Vor Gebrauch besondere Anweisungen einholen.",
			"P202"                  => "Vor Gebrauch alle Sicherheitshinweise lesen und verstehen.",
			"P210"                  => "Von Hitze, heißen Oberflächen, Funken, offenen Flammen sowie anderen Zündquellenarten fernhalten. Nicht rauchen.",
			"P211"                  => "Nicht gegen offene Flamme oder andere Zündquelle sprühen.",
			"P212"                  => "Erhitzen unter Einschluss und Reduzierung des Desensibilisierungs mittels vermeiden.",
			"P220"                  => "Von Kleidung und anderen brennbaren Materialien fernhalten. (Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: Von Kleidung /…/ brennbaren Materialien fernhalten/entfernt aufbewahren.)",
			"P221"                  => "(Mischen mit brennbaren Stoffen / … unbedingt verhindern.)",
			"P222"                  => "Keinen Kontakt mit Luft zulassen. (Bis zum Inkrafttreten der 4. ATP am 1. Dezember 2014: Kontakt mit Luft nicht zulassen; Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: Berührung mit Luft vermeiden.)",
			"P223"                  => "Keinen Kontakt mit Wasser zulassen. (Bis zum Inkrafttreten der 4. ATP am 1. Dezember 2014: Kontakt mit Wasser wegen heftiger Reaktion und möglichem Aufflammen unbedingt verhindern.)",
			"P230"                  => "Feucht halten mit … . (Die vom Gesetzgeber offen gelassene Einfügung ist vom Inverkehrbringer zu ergänzen)",
			"P231"                  => "Inhalt unter inertem Gas/… handhaben und aufbewahren. (Die vom Gesetzgeber offen gelassene Einfügung ist vom Inverkehrbringer zu ergänzen) (Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: Unter inertem Gas handhaben.)",
			"P232"                  => "Vor Feuchtigkeit schützen.",
			"P233"                  => "Behälter dicht verschlossen halten.",
			"P234"                  => "Nur in Originalverpackung aufbewahren. (Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: Nur im Originalbehälter aufbewahren.)",
			"P235"                  => "Kühl halten.",
			"P240"                  => "Behälter und zu befüllende Anlage erden.",
			"P241"                  => "Explosionsgeschützte [elektrische … / Lüftungs-… / Beleuchtungs-… / …] Geräte verwenden. (Die vom Gesetzgeber offen gelassene Einfügung ist vom Inverkehrbringer zu ergänzen) (Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: Explosionsgeschützte elektrische Betriebsmittel / Lüftungsanlagen / Beleuchtung / … verwenden.)",
			"P242"                  => "Funkenarmes Werkzeug verwenden. (Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: Nur funkenfreies Werkzeug verwenden.)",
			"P243"                  => "Maßnahmen gegen elektrostatische Entladungen treffen.",
			"P244"                  => "Druckminderer frei von Fett und Öl halten.",
			"P250"                  => "Nicht schleifen / stoßen / reiben / … . (Die vom Gesetzgeber offen gelassene Einfügung ist vom Inverkehrbringer zu ergänzen)",
			"P251"                  => "Nicht durchstechen oder verbrennen, auch nicht nach der Verwendung.",
			"P260"                  => "Staub / Rauch / Gas / Nebel / Dampf / Aerosol nicht einatmen.",
			"P261"                  => "Einatmen von Staub / Rauch / Gas / Nebel / Dampf / Aerosol vermeiden.",
			"P262"                  => "Nicht in die Augen, auf die Haut oder auf die Kleidung gelangen lassen.",
			"P263"                  => "Berührung während der Schwangerschaft und Stillzeit vermeiden. (Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: Kontakt während der Schwangerschaft / und der Stillzeit vermeiden.)",
			"P264"                  => "Nach Gebrauch … gründlich waschen. (Die vom Gesetzgeber offen gelassene Einfügung ist vom Inverkehrbringer zu ergänzen)",
			"P270"                  => "Bei Gebrauch nicht essen, trinken oder rauchen.",
			"P271"                  => "Nur im Freien oder in gut belüfteten Räumen verwenden.",
			"P272"                  => "Kontaminierte Arbeitskleidung nicht außerhalb des Arbeitsplatzes tragen.",
			"P273"                  => "Freisetzung in die Umwelt vermeiden.",
			"P280"                  => "Schutzhandschuhe / Schutzkleidung / Augenschutz / Gesichtsschutz tragen.",
			"P281"                  => "(Vorgeschriebene persönliche Schutzausrüstung verwenden.)",
			"P282"                  => "Schutzhandschuhe mit Kälteisolierung und zusätzlich Gesichtsschild oder Augenschutz tragen. (Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: Schutzhandschuhe / Gesichtsschild / Augenschutz mit Kälteisolierung tragen.)",
			"P283"                  => "Schwer entflammbare oder flammhemmende Kleidung tragen. (Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: Schwer entflammbare / flammhemmende Kleidung tragen.)",
			"P284"                  => "[Bei unzureichender Belüftung] Atemschutz tragen. (Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: Atemschutz tragen.)",
			"P285"                  => "(Bei unzureichender Belüftung Atemschutz tragen.)",
			"P231+P232"             => "Inhalt unter inertem Gas/… handhaben und aufbewahren. Vor Feuchtigkeit schützen. (Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: Unter inertem Gas handhaben. Vor Feuchtigkeit schützen.)",
			"P235+P410"             => "Kühl halten. Vor Sonnenbestrahlung schützen.",
			"P301"                  => "Bei Verschlucken:",
			"P302"                  => "Bei Berührung mit der Haut:",
			"P303"                  => "Bei Berührung mit der Haut (oder dem Haar):",
			"P304"                  => "Bei Einatmen:",
			"P305"                  => "Bei Kontakt mit den Augen:",
			"P306"                  => "Bei kontaminierter Kleidung:",
			"P307"                  => "(Bei Exposition: … )",
			"P308"                  => "Bei Exposition oder falls betroffen:",
			"P309"                  => "(Bei Exposition oder Unwohlsein: … )",
			"P310"                  => "Sofort Giftinformationszentrum, Arzt oder … anrufen.",
			"P311"                  => "Giftinformationszentrum, Arzt oder … anrufen.",
			"P312"                  => "Bei Unwohlsein Giftinformationszentrum / Arzt / … anrufen.",
			"P313"                  => "Ärztlichen Rat einholen / ärztliche Hilfe hinzuziehen.",
			"P314"                  => "Bei Unwohlsein ärztlichen Rat einholen / ärztliche Hilfe hinzuziehen.",
			"P315"                  => "Sofort ärztlichen Rat einholen / ärztliche Hilfe hinzuziehen.",
			"P320"                  => "Besondere Behandlung dringend erforderlich (siehe … auf diesem Kennzeichnungsetikett). (Die vom Gesetzgeber offen gelassene Einfügung ist vom Inverkehrbringer zu ergänzen)",
			"P321"                  => "Besondere Behandlung (siehe … auf diesem Kennzeichnungsetikett). (Die vom Gesetzgeber offen gelassene Einfügung ist vom Inverkehrbringer zu ergänzen)",
			"P322"                  => "(Gezielte Maßnahmen (siehe … auf diesem Kennzeichnungsetikett).)",
			"P330"                  => "Mund ausspülen.",
			"P331"                  => "Kein Erbrechen herbeiführen.",
			"P332"                  => "Bei Hautreizung:",
			"P333"                  => "Bei Hautreizung oder -ausschlag:",
			"P334"                  => "In kaltes Wasser tauchen [oder nassen Verband anlegen]. (Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: In kaltes Wasser tauchen / nassen Verband anlegen.)",
			"P335"                  => "Lose Partikel von der Haut abbürsten.",
			"P336"                  => "Vereiste Bereiche mit lauwarmem Wasser auftauen. Betroffenen Bereich nicht reiben.",
			"P337"                  => "Bei anhaltender Augenreizung:",
			"P338"                  => "Eventuell vorhandene Kontaktlinsen nach Möglichkeit entfernen. Weiter ausspülen.",
			"P340"                  => "Die betroffene Person an die frische Luft bringen und für ungehinderte Atmung sorgen.",
			"P341"                  => "(Bei Atembeschwerden an die frische Luft bringen und in einer Position ruhigstellen, die das Atmen erleichtert.)",
			"P342"                  => "Bei Symptomen der Atemwege:",
			"P350"                  => "(Behutsam mit viel Wasser und Seife waschen.)",
			"P351"                  => "Einige Minuten lang behutsam mit Wasser ausspülen.",
			"P352"                  => "Mit viel Wasser / … waschen. (Bis zum Inkrafttreten der 4. ATP am 1. Dezember 2014: Mit viel Wasser und Seife waschen.)",
			"P353"                  => "Haut mit Wasser abwaschen [oder duschen]. (Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: Haut mit Wasser abwaschen / duschen.)",
			"P360"                  => "Kontaminierte Kleidung und Haut sofort mit viel Wasser abwaschen und danach Kleidung ausziehen.",
			"P361"                  => "Alle kontaminierten Kleidungsstücke sofort ausziehen.",
			"P362"                  => "Kontaminierte Kleidung ausziehen. (Bis zum Inkrafttreten der 4. ATP am 1. Dezember 2014: Kontaminierte Kleidung ausziehen und vor erneutem Tragen waschen.)",
			"P363"                  => "Kontaminierte Kleidung vor erneutem Tragen waschen.",
			"P364"                  => "Und vor erneutem Tragen waschen.",
			"P370"                  => "Bei Brand:",
			"P371"                  => "Bei Großbrand und großen Mengen:",
			"P372"                  => "Explosionsgefahr. (Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: Explosionsgefahr bei Brand.)",
			"P373"                  => "Keine Brandbekämpfung, wenn das Feuer explosive Stoffe / Gemische / Erzeugnisse erreicht.",
			"P374"                  => "(Brandbekämpfung mit üblichen Vorsichtsmaßnahmen aus angemessener Entfernung.)",
			"P375"                  => "Wegen Explosionsgefahr Brand aus der Entfernung bekämpfen.",
			"P376"                  => "Undichtigkeit beseitigen, wenn gefahrlos möglich.",
			"P377"                  => "Brand von ausströmendem Gas: Nicht löschen, bis Undichtigkeit gefahrlos beseitigt werden kann.",
			"P378"                  => "… zum Löschen … verwenden. (Die vom Gesetzgeber offen gelassenen Einfügungen sind vom Inverkehrbringer zu ergänzen)",
			"P380"                  => "Umgebung räumen.",
			"P381"                  => "Bei Undichtigkeit alle Zündquellen entfernen (Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: Alle Zündquellen entfernen, wenn gefahrlos möglich.)",
			"P390"                  => "Verschüttete Mengen aufnehmen, um Materialschäden zu vermeiden.",
			"P391"                  => "Verschüttete Mengen aufnehmen.",
			"P301+P310"             => "Bei Verschlucken: Sofort Giftinformationszentrum, Arzt oder … anrufen.",
			"P301+P312"             => "Bei Verschlucken: Bei Unwohlsein Giftinformationszentrum / Arzt / … anrufen.",
			"P301+P330+P331"        => "Bei Verschlucken: Mund ausspülen. Kein Erbrechen herbeiführen. (Mit Inkrafttreten der 4. ATP am 1. Dezember 2014 aufgehoben, mit Inkrafttreten der 8. ATP am 1. Februar 2018 wieder aufgenommen)",
			"P302+P334"             => "Bei Berührung mit der Haut: In kaltes Wasser tauchen [oder nassen Verband anlegen]. (Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: Bei Kontakt mit der Haut: In kaltes Wasser tauchen / nassen Verband anlegen.)",
			"P302+P335+P334"        => "Bei Berührung mit der Haut: Lose Partikel von der Haut abbürsten. In kaltes Wasser tauchen [oder nassen Verband anlegen]. (Mit Inkrafttreten der 8. ATP am 1. Februar 2018 neu aufgenommen)",
			"P302+P350"             => "(Bei Kontakt mit der Haut: Behutsam mit viel Wasser und Seife waschen.)",
			"P302+P352"             => "Bei Berührung mit der Haut: Mit viel Wasser / … waschen. (Bis zum Inkrafttreten der 4. ATP am 1. Dezember 2014: Bei Kontakt mit der Haut: Mit viel Wasser und Seife waschen.)",
			"P303+P361+P353"        => "Bei Berührung mit der Haut [oder dem Haar]: Alle kontaminierten Kleidungsstücke sofort ausziehen. Haut mit Wasser abwaschen [oder duschen]. (Bis zum Inkrafttreten der 4. ATP am 1. Dezember 2014: Bei Kontakt mit der Haut [oder dem Haar]: Alle beschmutzten, getränkten Kleidungsstücke sofort ausziehen. Haut mit Wasser abwaschen/duschen. Mit Inkrafttreten der 4. ATP am 1. Dezember 2014 aufgehoben, mit Inkrafttreten der 8. ATP am 1. Februar 2018 wieder aufgenommen)",
			"P304+P340"             => "Bei Einatmen: Die Person an die frische Luft bringen und für ungehinderte Atmung sorgen.",
			"P304+P340+P310"        => "Bei Einatmen: Die Person an die frische Luft bringen und für ungehinderte Atmung sorgen. Sofort Giftinformationszentrum oder Arzt anrufen.",
			"P304+P341"             => "(Bei Einatmen: Bei Atembeschwerden an die frische Luft bringen und in einer Position ruhigstellen, die das Atmen erleichtert.)",
			"P305+P351+P338"        => "Bei Kontakt mit den Augen: Einige Minuten lang behutsam mit Wasser spülen. Eventuell vorhandene Kontaktlinsen nach Möglichkeit entfernen. Weiter spülen. (Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: Bei Kontakt mit den Augen: Einige Minuten lang behutsam mit Wasser spülen. Vorhandene Kontaktlinsen nach Möglichkeit entfernen. Weiter spülen.)",
			"P306+P360"             => "Bei Kontakt mit der Kleidung: Kontaminierte Kleidung und Haut sofort mit viel Wasser abwaschen und danach Kleidung ausziehen.",
			"P307+P311"             => "(Bei Exposition: Giftinformationszentrum oder Arzt anrufen.)",
			"P308+P311"             => "Bei Exposition oder falls betroffen: Giftinformationszentrum, Arzt oder … anrufen.",
			"P308+P313"             => "Bei Exposition oder falls betroffen: Ärztlichen Rat einholen / ärztliche Hilfe hinzuziehen.",
			"P309+P311"             => "(Bei Exposition oder Unwohlsein: Giftinformationszentrum oder Arzt anrufen.)",
			"P332+P313"             => "Bei Hautreizung: Ärztlichen Rat einholen / ärztliche Hilfe hinzuziehen.",
			"P333+P313"             => "Bei Hautreizung oder -ausschlag: Ärztlichen Rat einholen / ärztliche Hilfe hinzuziehen.",
			"P335+P334"             => "(Lose Partikel von der Haut abbürsten. In kaltes Wasser tauchen / nassen Verband anlegen.)",
			"P336+P315"             => "Vereiste Bereiche mit lauwarmem Wasser auftauen. Betroffenen Bereich nicht reiben. Sofort ärztlichen Rat einholen/ärztliche Hilfe hinzuziehen. (Mit Inkrafttreten der 8. ATP am 1. Februar 2018 neu aufgenommen)",
			"P337+P313"             => "Bei anhaltender Augenreizung: Ärztlichen Rat einholen / ärztliche Hilfe hinzuziehen.",
			"P342+P311"             => "Bei Symptomen der Atemwege: Giftinformationszentrum, Arzt oder … anrufen.",
			"P361+P364"             => "Alle kontaminierten Kleidungsstücke sofort ausziehen und vor erneutem Tragen waschen.",
			"P362+P364"             => "Kontaminierte Kleidung ausziehen und vor erneutem Tragen waschen.",
			"P370+P376"             => "Bei Brand: Undichtigkeit beseitigen, wenn gefahrlos möglich.",
			"P370+P378"             => "Bei Brand: … zum Löschen … verwenden. (Die vom Gesetzgeber offen gelassenen Einfügungen sind vom Inverkehrbringer zu ergänzen)",
			"P370+P380"             => "(Bei Brand: Umgebung räumen.)",
			"P370+P380+P375"        => "Bei Brand: Umgebung räumen. Wegen Explosionsgefahr Brand aus der Entfernung bekämpfen.",
			"P371+P380+P375"        => "Bei Großbrand und großen Mengen: Umgebung räumen. Wegen Explosionsgefahr Brand aus der Entfernung bekämpfen.",
			"P370+P372+P380+P373"   => "Bei Brand: Explosionsgefahr. Umgebung räumen. KEINE Brandbekämpfung, wenn das Feuer explosive Stoffe/Gemische/Erzeugnisse erreicht. (Mit Inkrafttreten der 8. ATP am 1. Februar 2018 neu aufgenommen)",
			"P370+P380+P375+[P378]" => "Bei Brand: Umgebung räumen. Wegen Explosionsgefahr Brand aus der Entfernung bekämpfen. [… zum Löschen verwenden.] (Die vom Gesetzgeber offen gelassene Einfügung ist vom Inverkehrbringer zu ergänzen) (Mit Inkrafttreten der 8. ATP am 1. Februar 2018 neu aufgenommen)",
			"P401"                  => "Aufbewahren gemäß … (Die vom Gesetzgeber offen gelassene Einfügung ist vom Inverkehrbringer zu ergänzen) (Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: … aufbewahren.)",
			"P402"                  => "An einem trockenen Ort aufbewahren.",
			"P403"                  => "An einem gut belüfteten Ort aufbewahren.",
			"P404"                  => "In einem geschlossenen Behälter aufbewahren.",
			"P405"                  => "Unter Verschluss aufbewahren.",
			"P406"                  => "In korrosionsbeständigem / … Behälter mit korrosionsbeständiger Innenauskleidung aufbewahren. (Die vom Gesetzgeber offen gelassene Einfügung ist vom Inverkehrbringer zu ergänzen) (Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: In korrosionsbeständigem / … Behälter mit korrosionsbeständiger Auskleidung aufbewahren.)",
			"P407"                  => "Luftspalt zwischen Stapeln / Paletten lassen.",
			"P410"                  => "Vor Sonnenbestrahlung schützen.",
			"P411"                  => "Bei Temperaturen nicht über … °C / … °F aufbewahren. (Die vom Gesetzgeber offen gelassene Einfügung ist vom Inverkehrbringer zu ergänzen) (Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: Bei Temperaturen von nicht mehr als … °C / … aufbewahren.)",
			"P412"                  => "Nicht Temperaturen über 50 °C / 122 °F aussetzen. (Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: Nicht Temperaturen von mehr als 50 °C aussetzen.)",
			"P413"                  => "Schüttgut in Mengen von mehr als … kg / … lbs bei Temperaturen nicht über … °C / … °F aufbewahren. (Die vom Gesetzgeber offen gelassene Einfügung ist vom Inverkehrbringer zu ergänzen) (Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: Schüttgut in Mengen von mehr als … kg bei Temperaturen von nicht mehr als … °C aufbewahren.)",
			"P420"                  => "Getrennt aufbewahren. (Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: Von anderen Materialien entfernt aufbewahren.)",
			"P422"                  => "(Inhalt in / unter … aufbewahren. (Die vom Gesetzgeber offen gelassene Einfügung ist vom Inverkehrbringer zu ergänzen))",
			"P402+P404"             => "In einem geschlossenen Behälter an einem trockenen Ort aufbewahren.",
			"P403+P233"             => "An einem gut belüfteten Ort aufbewahren. Behälter dicht verschlossen halten. (Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: Behälter dicht verschlossen an einem gut belüfteten Ort aufbewahren.)",
			"P403+P235"             => "An einem gut belüfteten Ort aufbewahren. Kühl halten. (Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: Kühl an einem gut belüfteten Ort aufbewahren.)",
			"P410+P403"             => "Vor Sonnenbestrahlung schützen. An einem gut belüfteten Ort aufbewahren. (Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: Vor Sonnenbestrahlung geschützt an einem gut belüfteten Ort aufbewahren.)",
			"P410+P412"             => "Vor Sonnenbestrahlung schützen. Nicht Temperaturen über 50 °C / 122 °F aussetzen. (Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: Vor Sonnenbestrahlung schützen und nicht Temperaturen von mehr als 50 °C aussetzen.)",
			"P411+P235"             => "(Kühl und bei Temperaturen von nicht mehr als … °C aufbewahren. (Die vom Gesetzgeber offen gelassene Einfügung ist vom Inverkehrbringer zu ergänzen))",
			"P501"                  => "Inhalt / Behälter … zuführen. (Die vom Gesetzgeber offen gelassene Einfügung ist vom Inverkehrbringer zu ergänzen)",
			"P502"                  => "Informationen zur Wiederverwendung oder Wiederverwertung bei Hersteller oder Lieferant erfragen. (Bis zum Inkrafttreten der 8. ATP am 1. Februar 2018: Informationen zur Wiederverwendung/Wiederverwertung beim Hersteller/Lieferanten erfragen.)",
			"P503"                  => "Informationen zur Entsorgung/Wiederverwendung/Wiederverwertung beim Hersteller/Lieferanten/… erfragen.",
		];

		$string = '';

		foreach (explode(' ', $hazardNumber) as $hn)
		{
			//apply json override
			if (!empty($hazardsJson[$hn]))
			{
				$hazards[$hn] = $hazardsJson[$hn];
			}

			if (!empty($hazards[$hn]))
			{
				if (!$printDescription)
				{
					$string .= '<span class="hazard-desc" title="' . $hazards[$hn] . '">' . $hn . '</span>';
				}
				else
				{
					$string .= '<div class="hazard-desc"><span class="hazard">' . $hn . '</span><span class="description">' . $hazards[$hn] . '</span></div>';
				}
			}
			else
			{
				$string .= $hn;
			}
			$string .= ' ';
		}

		return new \Twig\Markup($string, 'UTF-8');
	}


	/**
	 * Generates an HTML representation of manufacturer data based on the provided JSON input.
	 *
	 * If the given JSON string is empty, a default message will be returned indicating that no
	 * manufacturer data is available for this article. Otherwise, the JSON input is parsed,
	 * and its relevant fields are used to create a formatted HTML output, including fields
	 * such as name, address, postal code, city, and, if present, a validated email link.
	 *
	 * @param string|null $json The JSON-encoded string containing manufacturer data; can be null.
	 *
	 * @return \Twig\Markup The formatted manufacturer data as an HTML string, or a default
	 *                      message if no data is available.
	 */
	public function printManufacturerData(?string $json): \Twig\Markup
	{
		if (empty ($json)) return new \Twig\Markup('<p>Für diesen Bestandsartikel wurden bislang keine Herstellerdaten hinterlegt.</p>', 'UTF-8');
		$data = json_decode($json, true)[0];

		$lines   = [];
		$lines[] = $data['NAME1'];
		if (!empty($data['NAME2']))
		{
			$lines[] = $data['NAME2'];
		}
		if (!empty($data['STRASSE']))
		{
			$lines[] = $data['STRASSE'];
		}
		if (!empty($data['ORT']))
		{
			$lines[] = $data['PLZ'] . ' ' . $data['ORT'];
		}
		if (!empty($data['Internet_Kontakt']))
		{
			$email = $data['Internet_Kontakt'];
			if (filter_var($email, FILTER_VALIDATE_EMAIL))
			{
				$email = '<a href="mailto:' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</a>';
			}
			$lines[] = $email;
		}

		return new \Twig\Markup(implode('<br>', $lines), 'UTF-8');
	}
}
