<?php

namespace hobaIT;

ini_set('error_reporting', E_ALL);
ini_set('display_errors', true);
ini_set('xdebug.var_display_max_depth', 10);
ini_set('xdebug.var_display_max_children', 256);
ini_set('xdebug.var_display_max_data', 2048);
set_time_limit(90000);
const IMAGE_DUMMY_ID = 'b05fb506006044688c1d67eff1f7b33c';


require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/models/autoload.php';

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use phpDocumentor\Reflection\Types\Mixed_;
use Swaggest\JsonDiff\Exception;


class APIClient
{

	const STATUSCODES = [200, 201, 202, 203, 204, 205, 206, 208, 226];

	protected array $auth = [
		'grant_type'    => 'client_credentials',
		'client_id'     => 'SWIATW11VFPXNM5LEGD2MVZSSG',
		'client_secret' => 'UjJncUlkenFhOVlUcnNBWllQem43a0ZCeHNWb1Rsa1NnRW91dnI',
	];
	protected string $authURL = 'oauth/token';
	protected string $base = 'https://staging.reinhold-sohn-hygiene.de/api/';
	protected string $mediaFolderDefault = '3d736a2219904376bc8f3803b318f6a8';

	protected string $accessToken = '';
	protected int $accessTokenTimeout = 0;
	protected GuzzleClient $client;


	public function __construct()
	{
		self::setClient(new GuzzleClient([
			'base_uri' => self::getBase(),
			'timeout'  => 60.0,
		]));
	}

	/**
	 * Logs a message with an optional flag to write to an error file.
	 *
	 * @param string $string           The message to be logged.
	 * @param bool   $writeToErrorFile Indicates whether to write the log to the error file.
	 */
	public static function log(string $string, bool $writeToErrorFile = false)
	{
		$log = date('d.m.Y H:i:s') . " {$string} \n";
		if ($writeToErrorFile)
		{
			file_put_contents('errors.txt', $log, FILE_APPEND);
		}
		echo $log;
	}

	/**
	 * @return string
	 */
	public function getAccessToken(): string
	{
		return $this->accessToken;
	}

	/**
	 * @return int
	 */
	public function getAccessTokenTimeout(): int
	{
		return $this->accessTokenTimeout;
	}

	/**
	 * @param int $accessTokenTimeout
	 */
	public function setAccessTokenTimeout(int $accessTokenTimeout): void
	{
		$this->accessTokenTimeout = $accessTokenTimeout;
	}

	/**
	 * @return array|string[]
	 */
	public function getAuth(): array
	{
		return $this->auth;
	}

	/**
	 * @param array|string[] $auth
	 */
	public function setAuth(array $auth): void
	{
		$this->auth = $auth;
	}

	/**
	 * @return string
	 */
	public function getAuthURL(): string
	{
		return $this->authURL;
	}

	/**
	 * @param string $authURL
	 */
	public function setAuthURL(string $authURL): void
	{
		$this->authURL = $authURL;
	}

	/**
	 * @return string
	 */
	public function getBase(): string
	{
		return $this->base;
	}

	/**
	 * @param string $base
	 */
	public function setBase(string $base): void
	{
		$this->base = $base;
	}

	/**
	 * @return string
	 */
	public function getMediaFolderDefault(): string
	{
		return $this->mediaFolderDefault;
	}

	/**
	 * @param string $mediaFolderDefault
	 */
	public function setMediaFolderDefault(string $mediaFolderDefault): void
	{
		$this->mediaFolderDefault = $mediaFolderDefault;
	}

	/**
	 * @return GuzzleClient
	 */
	public function getClient(): GuzzleClient
	{
		return $this->client;
	}

	/**
	 * @param GuzzleClient $client
	 */
	public function setClient(GuzzleClient $client): void
	{
		$this->client = $client;
	}

	/**
	 * Sends an HTTP request to the specified URL with the given data, method, and headers.
	 *
	 * @param string     $url          The target URL for the request.
	 * @param mixed|null $data         The request payload, optional.
	 * @param string     $method       The HTTP method for the request (e.g., 'GET', 'POST'). Default is 'GET'.
	 * @param bool       $debug        Whether debugging is enabled. Default is false.
	 * @param array|null $extraHeaders Additional headers to include in the request. Optional, default is an empty array.
	 *
	 * @return string The response body from the HTTP request.
	 *
	 * @throws \Exception|GuzzleException If the request results in an error or receives an unexpected status code.
	 */
	protected function request(string $url, $data = null, string $method = 'GET', bool $debug = false, ?array $extraHeaders = []): string
	{

		$headers = [
			'Content-Type' => 'application/json'
		];

		if (self::getAuthURL() != $url) //no recursion
		{
			$remaining = self::getAccessTokenTimeout() - time();
			if ($remaining < 60)
			{
				self::setAccessToken();
				$remaining = self::getAccessTokenTimeout() - time();
			}
		}

		if (!empty(self::getAccessToken()))
		{
			//we got a token so it's a "normal" request
			$headers['Authorization'] = self::getAccessToken();
			$headers['Accept']        = 'application/json';
		}

		if (!empty($extraHeaders))
		{
			$headers = array_merge($headers, $extraHeaders);
		}

		$options = [
			'headers' => $headers,
			'json'    => $data,
		];

		if ($debug)
		{
			$options['debug'] = true;
		}

		try
		{
			$response = $this->client->request(
				$method,
				$url,
				$options
			);
		}
		catch (\Exception $e)
		{
			throw new \Exception($e->getResponse()->getBody()->getContents());
		}


		$output = $response->getBody();
		$status = $response->getStatusCode();


		if (in_array($status, self::STATUSCODES))
		{
			return $output;
		}
		else
		{
			throw new \Exception('Got status code: ' . $status);
		}
	}

	/**
	 * Post data to API Endpoint
	 *
	 * @param      $url
	 * @param      $data
	 * @param bool $debug
	 *
	 * @return string
	 * @throws GuzzleException
	 */
	protected function post($url, $data = null, bool $debug = false): string
	{
		return self::request($url, $data, 'POST', $debug);
	}

	/**
	 * Delete data at API Endpoint
	 *
	 * @param      $url
	 * @param      $data
	 * @param bool $debug
	 *
	 * @return string
	 * @throws GuzzleException
	 */
	protected function delete($url, $data = null, bool $debug = false): string
	{
		return self::request($url, $data, 'DELETE', $debug);
	}

	/**
	 * Update data at API Endpoint
	 *
	 * @param      $url
	 * @param      $data
	 * @param bool $debug
	 *
	 * @return string
	 * @throws GuzzleException
	 */
	protected function patch($url, $data = null, bool $debug = false): string
	{
		return self::request($url, $data, 'PATCH', $debug);
	}

	/**
	 * Alias for patch
	 *
	 * @param      $url
	 * @param      $data
	 * @param bool $debug
	 *
	 * @return string
	 * @throws GuzzleException
	 */
	protected function update($url, $data = null, bool $debug = false): string
	{
		return self::patch($url, $data, $debug);
	}

	/**
	 * Get data from API Endpoint
	 *
	 * @param $url
	 * @param $data
	 *
	 * @return string
	 * @throws GuzzleException
	 */
	protected function get($url, $data = null): string
	{
		return self::request($url, $data, 'GET');
	}

	/**
	 * Set access token
	 *
	 * @return bool
	 * @throws GuzzleException
	 */
	protected function setAccessToken(): bool
	{
		try
		{
			$response = self::post(self::getAuthURL(), self::getAuth());
			$response = json_decode($response);
			if ('Bearer' == $response->token_type)
			{
				$this->accessToken = 'Bearer ' . $response->access_token;
				$this->setAccessTokenTimeout(time() + (int) $response->expires_in);

				return true;
			}
		}
		catch (\Exception $e)
		{
			echo $e->getMessage();
		}

		return false;
	}

	/**
	 * Output Bearer for API tests
	 */
	public function printBearer()
	{
		self::setAccessToken();
		echo self::getAccessToken();
	}

	/**
	 * @return array|null
	 * @throws \Exception
	 */
	public function getProducts()
	{
		try
		{
			$products = json_decode(self::get('product'));
		}
		catch (\Exception $e)
		{
			self::log('Could not find products ' . $e->getMessage());
		}

		return $products->data;
	}

	/**
	 * Get all categories
	 * @return string
	 * @throws GuzzleException
	 */
	public function getCategories(): string
	{
		return self::get('category');
	}

	/**
	 * Add Category
	 * data has to be like
	 * [
	 *  'name'         => 'Category Name',
	 *  'customFields' => [
	 *          'pleasant_id            => 9999,
	 *          'pleasant_internal_id'  => '4615esaf35d01230-214em1n34-314-13413-2'
	 *        ]
	 *    ];
	 *
	 * @param array $data
	 *
	 * @return string
	 * @throws GuzzleException
	 */
	public function addCategory(array $data): string
	{
		try
		{
			$response = json_decode(self::post('category?_response=basic', $data));
		}
		catch (\Exception $e)
		{
			self::log('Could not add category ' . $e->getMessage());
		}

		return $response->data->id;
	}

	/**
	 * Deletes a category by its ID.
	 *
	 * @param string $id The ID of the category to delete
	 *
	 * @return bool Returns true if the deletion process is initiated
	 * @throws GuzzleException
	 */
	public function deleteCategory(string $id): bool
	{
		try
		{
			self::delete('category/' . $id, []);
		}
		catch (\Exception $e)
		{
			self::log('Could not delete Category ' . $e->getMessage());
		}

		return true;
	}

	/**
	 * Updates a category by sending a patch request and handles exceptions if any occur.
	 *
	 * @param array $category Associative array containing category details, including the 'id' key.
	 *
	 * @return string The ID of the updated category.
	 * @throws GuzzleException
	 */
	public function updateCategory(array $category): string
	{
		try
		{
			self::patch('category/' . $category['id'], $category);
		}
		catch (\Exception $e)
		{
			self::log('Could not update Category ' . $e->getMessage());
		}

		return $category['id'];
	}

	/**
	 * Retrieves a category based on the provided filter criteria.
	 *
	 * @param object $filter The filter criteria for retrieving the category.
	 *
	 * @return object The category retrieved based on the filter.
	 *
	 * @throws \Exception|GuzzleException If there is an error during the request or processing.
	 */
	public function getCategoryByFilter(object $filter): object
	{
		try
		{
			$response = json_decode(self::post('search/category', $filter));
		}
		catch (\Exception $e)
		{
			self::log('Could not get category ' . $e->getMessage());
		}

		return $response;
	}

	/**
	 * Handles the addition of a media file and retrieves the media ID.
	 *
	 * @return string The ID of the added media file.
	 * @throws \Exception|GuzzleException If an error occurs during the process.
	 */
	protected function addMediaFile(): string
	{
		try
		{
			$response = json_decode(self::post('media?_response=basic', [
				'mediaFolderId' => self::getMediaFolderDefault()
			]));
		}
		catch (\Exception $e)
		{
			self::log('Could not add media ' . $e->getMessage());
		}

		return $response->data->id;
	}

	/**
	 * Uploads a local media file to a remote server.
	 *
	 * @param string      $filePath         The file path to the local media file.
	 * @param string|null $mediaId          The ID of the media to associate the file with. Defaults to null if a new media record should be created.
	 * @param string|null $appendToFilename A string to append to the filename. Defaults to an empty string.
	 *
	 * @return string|null              The media ID if the upload was successful, or null otherwise.
	 * @throws GuzzleException
	 */
	public function uploadMediaFromLocalFile(string $filePath, ?string $mediaId = null, ?string $appendToFilename = ''): ?string
	{
		if (file_exists($filePath))
		{
			$headers = [
				'Content-Type' => mime_content_type($filePath)
			];

			if (!empty(self::getAccessToken()))
			{
				//we got a token so it's a "normal" request
				$headers['Authorization'] = self::getAccessToken();
				$headers['Accept']        = 'application/json';
			}

			$options = [
				'headers' => $headers,
				'body'    => file_get_contents($filePath),
			];

			$imageInfo = pathinfo($filePath);
			$filename  = str_replace('#', '', $imageInfo['filename']) . $appendToFilename;

			try
			{
				if (empty($mediaId))
				{
					$mediaId = self::getMediaIdByFileName($filename . '.' . $imageInfo['extension']);
//					var_dump('Found media file:'.$filename . '.' . $imageInfo['extension']);

					if (empty($mediaId))
					{
						$mediaId = $this->addMediaFile();
//						var_dump('Did not find media file:'.$filename . '.' . $imageInfo['extension']);
					}
				}


				$url = '/api/_action/media/' . $mediaId . '/upload?extension=' . $imageInfo['extension'] . '&fileName=' . $filename . '&_response=true';
//				var_dump($url);

				$response = $this->client->request(
					'POST',
					$url,
					$options
				);
//				var_dump('file uploaded');
				$status = $response->getStatusCode();

				if (in_array($status, self::STATUSCODES))
				{
					return $mediaId;
				}
				else
				{
					self::log('Got status code: ' . $status);
				}

			}
			catch (\Exception $e)
			{
				self::log($e->getMessage());
			}
		}
		else
		{
			self::log('Fehler:' . $filePath . 'existiert nicht', true);
		}

		return null;
	}

	/**
	 * add a media file from given url
	 *
	 * @param string       $url
	 * @param ?string|null $filename
	 * @param ?string|null $mediaId
	 *
	 * @return ?string
	 * @throws GuzzleException
	 */
	public function addMediaFileFromURL(string $url, ?string $filename = null, ?string $mediaId = null): ?string
	{

		if (empty($mediaId))
		{
			$mediaId = self::getMediaIdByFileName($url);
		}

		if (empty($mediaId))
		{
			//still nothing? add empty media file (!important)
			$mediaId = self::addMediaFile();
		}

		//upload new image
		$fileInfo = pathinfo($url);
		if (!empty($filename))
		{
			$fileInfo['filename'] = $filename;
		}
		$data = [
			'url' => $url
		];

		try
		{
			//set image properties and data
			self::post('_action/media/' . $mediaId . '/upload?extension=' . $fileInfo['extension'] . '&fileName=' . self::sanitizeFileName($fileInfo['filename']), $data);
		}
		catch (\Exception $e)
		{
			self::log('Could not add media from URL' . $e->getMessage());
		}

		return $mediaId;
	}

	/**
	 * Delete a media file
	 *
	 * @param string $id
	 *
	 * @return bool
	 * @throws GuzzleException
	 */
	public function deleteMediaFile(string $id): bool
	{
		try
		{
			self::delete('media/' . $id, []);
		}
		catch (\Exception $e)
		{
			self::log('Could not delete media file ' . $e->getMessage());
		}

		return true;
	}

	/**
	 * @param $filter
	 *
	 * @return object
	 * @throws GuzzleException
	 */
	public function getMediaByFilter(filter $filter): object
	{
		try
		{
			$response = json_decode(self::post('search/media', $filter));
		}
		catch (\Exception $e)
		{
			self::log('Could not find media file ' . $e->getMessage());
		}

		return $response;
	}

	/**
	 * check, if file exists, get media id by file name,
	 *
	 * @param string $filename
	 *
	 * @return string|null
	 * @throws GuzzleException
	 */
	public function getMediaIdByFileName(string $filename): ?string
	{
		$fileInfo = pathinfo($filename);
		$filename = self::sanitizeFileName($fileInfo['filename']);

		$filter = new filter([
			new filterCriteria('fileName', $filename),
			new filterCriteria('fileExtension', $fileInfo['extension']),
		]);

		$response = self::getMediaByFilter($filter);
		if (!empty($response->data))
		{
			return ($response->data[0]->id);
		}

		return null;
	}

	/**
	 * Sanitize file name for use in filesystem
	 *
	 * @param string $file
	 *
	 * @return string
	 */
	public static function sanitizeFileName(string $file): string
	{
		$file = str_replace(' ', '_', rtrim($file));
		$file = mb_ereg_replace("([^a-zA-z0-9_-])", '', $file);

		return $file;
	}

	/**
	 * Get an array of available unirs
	 *
	 * @return array
	 * @throws GuzzleException
	 */
	public function getAvailableUnits(): array
	{
		try
		{
			$response = json_decode(self::get('unit'));
			$units    = [];
			foreach ($response->data as $unit)
			{
				$units[$unit->name] = $unit->id;
			}

			return $units;
		}
		catch (\Exception $e)
		{
			self::log('Could not get avail. units ' . $e->getMessage());
		}

		return [];
	}

	/**
	 * Add unit
	 *
	 * @param string      $name
	 * @param string|null $short
	 *
	 * @return string
	 * @throws GuzzleException
	 */
	public function addUnit(string $name, ?string $short = null): string
	{

		$shortCode = empty($short) ? $name : $short;
		$data      = [
			'name'      => $name,
			'shortCode' => $shortCode
		];
		try
		{
			$response = json_decode(self::post('unit?_response=basic', $data));
		}
		catch (\Exception $e)
		{
			self::log('Could not add Unit ' . $e->getMessage());
		}

		return $response->data->id;
	}

	/**
	 * delete unit
	 *
	 * @param string $id
	 *
	 * @return bool
	 * @throws GuzzleException
	 */
	public function deleteUnit(string $id): bool
	{
		try
		{
			self::delete('unit/' . $id . '?_response=json', []);
		}
		catch (\Exception $e)
		{
			self::log('Could not delete unit ' . $e->getMessage());
		}

		return true;
	}

	/**
	 * Adds a product to the database. If the product exists, it returns the product ID or null.
	 * If the product does not exist, assigns media if available, logs and optionally echoes the process.
	 *
	 * @param pleasantProduct $product The product to be added.
	 * @param bool            $echo    Whether to output the process details or not.
	 *
	 * @return string|null Returns the ID of the added product or null if unsuccessful or exists.
	 */
	public function addProduct(pleasantProduct $product, bool $echo = false): ?string
	{
		//check if product exists
		$check = self::getProductIdByProductNumber($product->getProductNumber());
		if (empty($check))
		{
			try
			{
				if (!empty($product->image))
				{
					$mediaId = self::uploadMediaFromLocalFile($product->image, null, $product->productNumber);
					$product->setCover((string) $mediaId);
				}
				if ($echo)
				{
					echo " Adding product: " . $product->getProductNumber() . ' -- ' . $product->getName() . "\n";
				}
				$response = json_decode(self::post('product?_response=basic', $product));

				return $response->data->id;

			}
			catch (\Exception $e)
			{
				self::log(' Could not add Product ' . $e->getMessage());

				return null;
			}
		}
		if ($echo)
		{
			echo " Product exists: " . $product->getName() . "\n";

			return null;
		}

		return $check;
	}

	/**
	 * Update product
	 *
	 * @param pleasantProduct $product
	 *
	 * @return string
	 * @throws GuzzleException
	 */
	public function updateProduct(pleasantProduct $product): ?string
	{
		//check if product exists
		$product->setId(self::getProductIdByProductNumber($product->getProductNumber()));

		if ($product->id !== null)
		{
			try
			{
				if (!empty($product->image))
				{
					$apiProduct = self::getProduct($product->getId());
					$cover      = null;
					if (!empty($apiProduct->coverId))
					{
						$cover = self::getProductMediaId($apiProduct->coverId);
					}
//					var_dump($cover);
					$mediaId = self::uploadMediaFromLocalFile($product->image, $cover, $product->productNumber);
//					if ($cover != $mediaId && !empty($cover))
//					{
					$product->setCover((string) $mediaId);
//					}
				}
				unset($product->visibilities); //causing 500 error, not really needed here
				//delete category assignments
				self::deleteCategoryAssignments($product->id);
				$response = json_decode(self::patch('product/' . $product->getId() . '?_response=basic', $product));

				return $response->data->id;

			}
			catch (\Exception $e)
			{
				self::log('Could not add Product ' . $e->getMessage());
			}

		}
		else
		{
			return self::addProduct($product);
		}

		return null;
	}

	public function getProductMediaId(string $id): string
	{
		try
		{
			$response = json_decode(self::get('product-media/' . $id));
			if (!empty($response->data))
			{
				return $response->data->mediaId;
			}
		}
		catch (\Exception $e)
		{
			return '';
		}

		return true;
	}

	/**
	 * Delete a product by a given product number
	 *
	 * @param string $productNumber
	 *
	 * @return bool
	 * @throws GuzzleException
	 */
	public function deleteProductByProductNumber(string $productNumber)
	{
		return self::deleteProduct(self::getProductIdByProductNumber($productNumber));
	}

	/**
	 * Delete product by id
	 *
	 * @param ?string $id
	 *
	 * @return bool
	 * @throws GuzzleException
	 */
	public function deleteProduct(?string $id): bool
	{
		if (!empty($id))
		{
			try
			{
				self::delete('product/' . $id, []);
			}
			catch (\Exception $e)
			{
				self::log('Could not delete product ' . $e->getMessage() . "\n");
			}
		}

		return true;
	}

	/**
	 * @return void
	 * @throws GuzzleException
	 */
	public function deleteAllProducts()
	{
		$products = self::getProducts();
		foreach ($products as $product)
		{
			self::deleteProduct($product->id);
			echo 'Deleting ' . $product->name . "\n";
		}
	}

	/**
	 * get a product id by a given product number
	 *
	 * @param string $productNumber
	 *
	 * @return string|null
	 * @throws GuzzleException
	 */
	public function getProductIdByProductNumber(string $productNumber): ?string
	{
		$filter   = new filter([
			new filterCriteria('productNumber', $productNumber),
		]);
		$response = self::getProductByFilter($filter);
		if (!empty($response->data))
		{
			return ($response->data[0]->id);
		}

		return null;
	}

	/**
	 * get a product by filter
	 *
	 * @param filter $filter
	 *
	 * @return object
	 * @throws \GuzzleHttp\Exception\GuzzleExceptionĳ
	 */
	public function getProductByFilter(filter $filter): object
	{
		$response = (object) [];
		try
		{
			$response = json_decode(self::post('search/product', $filter));
		}
		catch (\Exception $e)
		{
			self::log('Could not find product by filter ' . $e->getMessage());
		}

		return $response;
	}

	/**
	 * Get product by id
	 *
	 * @param string $id
	 *
	 * @return mixed
	 * @throws GuzzleException
	 */
	public function getProduct(string $id)
	{
		try
		{
			$response = json_decode(self::get('product/' . $id));
		}
		catch (\Exception $e)
		{
			self::log('Could not get product' . $e->getMessage());
		}

		return $response->data;
	}

	/**
	 * Get salutations
	 * @return mixed
	 * @throws GuzzleException
	 */
	public function getSalutations()
	{
		try
		{
			$response = json_decode(self::get('salutation'));
		}
		catch (\Exception $e)
		{
			self::log('Could not get salutation ' . $e->getMessage());
		}

		return $response;
	}

	/**
	 * Get array of salutations array['displayName'] = id
	 * @return array
	 * @throws GuzzleException
	 */
	public function getSalutationsArray(): array
	{
		$response    = self::getSalutations();
		$salutations = [];

		foreach ($response->data as $salutation)
		{
			$salutations[$salutation->displayName] = $salutation->id;
		}

		return $salutations;
	}

	/**
	 * @param string $name
	 *
	 * @return object|null
	 * @throws GuzzleException
	 */
	public function getSalutationIdByDisplayName(string $name): ?string
	{

		try
		{
			$filter   = new filter(
				[
					new filterCriteria('displayName', $name)
				]
			);
			$response = json_decode(self::post('search/salutation', $filter));
			if (0 != $response->total)
			{
				return $response->data[0]->id;
			}
		}
		catch (\Exception $e)
		{
			self::log('Could not get salutation by name ' . $e->getMessage());
		}

		return null;
	}

	/**
	 * Add salutation if not existing, return id otherwise
	 *
	 * @param salutation $salutation
	 *
	 * @return object|string|null
	 * @throws GuzzleException
	 */
	public function addSalutation(salutation $salutation)
	{
		$check = self::getSalutationIdByDisplayName($salutation->getDisplayName());

		if (empty($check))
		{
			try
			{
				$response = json_decode(self::post('salutation?_response=basic', $salutation));
			}
			catch (\Exception $e)
			{
				self::log('Could not add salutation ' . $e->getMessage());
			}

			return $response->data->id;
		}

		return $check;
	}

	/**
	 * @param string $name
	 *
	 * @return object|string|null
	 * @throws GuzzleException
	 */
	public function addSalutationByName(string $name)
	{
		$salutation = new salutation($name);

		return self::addSalutation($salutation);
	}

	/**
	 * get country id by name matching
	 *
	 * @param string $name
	 *
	 * @return string|null
	 * @throws GuzzleException
	 */
	public function getCountryIdByName(string $name): ?string
	{
		$filter = new filter(
			[
				new filterCriteria('name', $name)
			]
		);
		try
		{
			$response = json_decode(self::post('search/country', $filter));
			if (0 != $response->total)
			{
				return $response->data[0]->id;
			}
		}
		catch (\Exception $e)
		{
			self::log('Could not get country by name' . $e->getMessage());
		}

		return null;
	}

	/**
	 * get country id by iso matching
	 *
	 * @param string $name
	 *
	 * @return string|null
	 * @throws GuzzleException
	 */
	public function getCountryIdByISO(?string $iso): ?string
	{
		if (empty($iso))
		{
			return null;
		}

		if ('d' == strtolower($iso))
		{
			$iso = 'DE';
		}
		else if ('vae' == strtolower($iso))
		{
			$iso = 'VA';
		}
		else if ('a' == strtolower($iso) || 'aut' == strtolower($iso))
		{
			$iso = 'AT';
		}
		else if ('i' == strtolower($iso))
		{
			$iso = 'IT';
		}

		$filter = new filter(
			[
				new filterCriteria('iso', strtoupper($iso))
			]
		);
		try
		{
			$response = json_decode(self::post('search/country', $filter));
			if (0 != $response->total)
			{
				return $response->data[0]->id ?? null;
			}
		}
		catch (\Exception $e)
		{
			self::log('Could not get country by iso' . $e->getMessage());
		}

		return null;
	}

	/**
	 * get a product by filter
	 *
	 * @param filter $filter
	 *
	 * @return ?object
	 * @throws GuzzleException
	 */
	public function getCustomerByFilter(filter $filter): ?array
	{
		try
		{
			$response = json_decode(self::post('search/customer', $filter));
		}
		catch (\Exception $e)
		{
			self::log('Could not get customer by filter' . $e->getMessage());
		}

		return $response->data ?? null;
	}

	/**
	 * Get customer id by customer number
	 *
	 * @param string $cutomerNumber
	 *
	 * @return string|null
	 * @throws GuzzleException
	 */
	public function getCustomerIdByCustomerNumber(string $cutomerNumber): ?string
	{
		$filter   = new filter([
			new filterCriteria('customerNumber', $cutomerNumber),
		]);
		$response = self::getCustomerByFilter($filter);
		if (!empty($response))
		{
			return ($response[0]->id);
		}

		return null;
	}

	/**
	 * Adds a customer to the database.
	 *
	 * @param customer $customer The customer entity to be added.
	 *
	 * @return string The unique ID of the added customer or an already existing customer ID.
	 *
	 * @throws \Exception|GuzzleException If there is an error while adding the customer.
	 */
	public function addCustomer(customer $customer): string
	{
		$check = self::getCustomerIdByCustomerNumber($customer->getCustomerNumber());

		if (empty($check))
		{
			try
			{
				$response = json_decode(self::post('customer?_response=basic', $customer));
			}
			catch (\Exception $e)
			{
				echo('Could not add customer ' . $e->getMessage());
			}

			return $response->data->id;
		}

		return $check;
	}

	/**
	 * Retrieves a customer by ID.
	 *
	 * @param string|null $id The ID of the customer to retrieve. Can be null.
	 *
	 * @return object|array An object or array containing the customer data.
	 *
	 * @throws \Exception|GuzzleException If an error occurs during the retrieval process.
	 */
	public function getCustomer(?string $id): object|array
	{
		try
		{
			$response = json_decode(self::get('customer/' . $id ?? ''));
		}
		catch (\Exception $e)
		{
			self::log('Could not get customer' . $e->getMessage());
		}

		return $response->data;
	}

	/**
	 * Deletes a customer by ID.
	 *
	 * @param string $id The ID of the customer to delete.
	 *
	 * @throws GuzzleException
	 */
	public function deleteCustomer(string $id)
	{
		try
		{
			self::delete('customer/' . $id);
		}
		catch (\Exception $e)
		{
			self::log('Could not delete customer' . $e->getMessage());
		}
	}

	/**
	 * @param customer $customer
	 *
	 * @return string
	 */
	public function updateCustomer(customer $customer): string
	{
		//@todo
	}

	/**
	 * Retrieves the individual rule condition associated with a customer by their customer number.
	 *
	 * @param string $customerNumber The customer number to lookup.
	 *
	 * @return string|null Returns the rule ID if found, or null if no matching rule condition is found.
	 * @throws GuzzleException
	 */
	public function getCustomerIndividualRuleCondition(string $customerNumber): ?string
	{
		try
		{
			$filter   = '{"filter": [
	            { 
	                "type": "multi",
	                "operator": "and",
	                "queries":[
	                    {"type": "equals", "field": "value.numbers", "value": "[\"' . $customerNumber . '\"]" },
	                    {"type": "equals", "field": "type", "value": "customerCustomerNumber" }
	                ]
	            }
	        ]}';
			$filter   = json_decode($filter);
			$response = json_decode(self::post('search/rule-condition', $filter));
			if (0 != $response->total)
			{
				return $response->data[0]->ruleId;
			}
		}
		catch (\Exception $e)
		{
			self::log('Could not find rule ' . $e->getMessage());
		}

		return null;
	}

	/**
	 * Retrieves the individual rule condition for a specific customer group.
	 *
	 * This method constructs a filter query to match rule conditions based on the provided
	 * customer group ID and the type "customerCustomerGroup." It sends a POST request
	 * to the "search/rule-condition" endpoint and parses the response to extract the rule ID.
	 * If no matching rule condition is found, the method returns null. If an exception occurs during
	 * the process, it logs an error message.
	 *
	 * @param string $customerGroup The ID of the customer group for which the rule condition is retrieved.
	 *
	 * @return string|null The rule ID if a matching rule condition is found, null otherwise.
	 */
	public function getCustomergroupIndividualRuleCondition(string $customerGroup): ?string
	{
		try
		{
			$filter   = '{"filter": [
	            { 
	                "type": "multi",
	                "operator": "and",
	                "queries":[
	                    {"type": "equals", "field": "value.customerGroupIds", "value": "[\"' . $customerGroup . '\"]" },
	                    {"type": "equals", "field": "type", "value": "customerCustomerGroup" }
	                ]
	            }
	        ]}';
			$filter   = json_decode($filter);
			$response = json_decode(self::post('search/rule-condition', $filter));
			if (0 != $response->total)
			{
				return $response->data[0]->ruleId;
			}
		}
		catch (\Exception $e)
		{
			self::log('Could not find rule ' . $e->getMessage());
		}
		return null;
	}

	/**
	 * Adds a rule with a condition to the system.
	 *
	 * @param string      $type     The type of the condition.
	 * @param string      $value    The value for the condition match.
	 * @param string      $name     The name of the rule.
	 * @param string|null $property Optional property associated with the value.
	 *
	 * @return mixed The API response or null in case of an exception.
	 * @throws GuzzleException
	 */
	public function addRuleWithCondition(string $type, string $value, string $name, ?string $property = null)
	{
		try
		{
			$rc = new ruleCondition();
			$rc->setType($type);
			$rc->setValue(
				new value('=', [$value], $property)
			);

			$rule = new rule();
			$rule->setName($name);
			$rule->setConditions([$rc]);

			$response = json_decode(self::post('rule?_response=basic', $rule));

			return $response;
		}
		catch (\Exception $e)
		{
			self::log('Could not add rule ' . $e->getMessage());
		}

	}

	/**
	 * Adds a customer individual rule condition based on the provided customer number and rule name.
	 *
	 * @param string $customerNumber The customer's unique number.
	 * @param string $ruleName       The name of the rule to be added, defaults to 'Kundennummer: '.
	 *
	 * @return string|null The ID of the newly added rule condition, or null if the operation failed or the rule already exists.
	 * @throws GuzzleException
	 */
	public function addCustomerIndividualRuleCondition(string $customerNumber, string $ruleName = 'Kundennummer: '): ?string
	{
		$check = self::getCustomerIndividualRuleCondition($customerNumber);
		if (empty($check))
		{
			try
			{
				$response = self::addRuleWithCondition('customerCustomerNumber', $customerNumber, $ruleName . $customerNumber);

				return $response->data->id ?? null;
			}
			catch (\Exception $e)
			{
				self::log('Could not add rule ' . $e->getMessage());
			}
		}

		return $check;
	}

	/**
	 * Adds a custom customer group individual rule condition.
	 *
	 * @param string $groupID  The ID of the customer group.
	 * @param string $ruleName The base name of the rule, defaults to 'Kundengruppe: '.
	 *
	 * @return string|null  The ID of the newly added rule condition, or null if unsuccessful.
	 * @throws GuzzleException
	 */
	public function addCustomergroupIndividualRuleCondition(string $groupID, string $ruleName = 'Kundengruppe: '): ?string
	{
		$check = self::getCustomergroupIndividualRuleCondition($groupID);
		if (empty($check))
		{
			try
			{
				$response = self::addRuleWithCondition('customerCustomerGroup', $groupID, $ruleName . self::getCustomerGroupName($groupID), 'customerGroupIds');

				return $response->data->id ?? null;
			}
			catch (\Exception $e)
			{
				self::log('Could not add rule ' . $e->getMessage());
			}
		}

		return $check;
	}

	/**
	 * @param string   $ruleId        Identifier for the price rule.
	 * @param string   $productId     Identifier for the product.
	 * @param float    $price         The price to be applied.
	 * @param int      $quantityStart The starting quantity for the price rule. Defaults to 1.
	 * @param int|null $quantityEnd   The ending quantity for the price rule. Optional.
	 *
	 * @return string|null Returns the ID of the created price rule or null on failure.
	 * @throws GuzzleException
	 */
	public function setPriceRule(string $ruleId, string $productId, float $price, int $quantityStart = 1, ?int $quantityEnd = null): ?string
	{
		try
		{
			$rule     = new productPriceRule($ruleId, $productId, $price, $quantityStart, $quantityEnd);
			$response = json_decode(self::post('product-price?_response=basic', $rule));

			if (!empty($response->data))
			{
				return $response->data->id ?? null;
			}
		}
		catch (\Exception $e)
		{
			self::log('Could not add rule ' . $e->getMessage());
		}

		return null;
	}

	/**
	 * Checks if a remote file exists at the given URL.
	 *
	 * @param string $url The URL of the remote file.
	 *
	 * @return bool Returns true if the file exists, false otherwise.
	 */
	public function remoteFileExists(string $url): bool
	{

		return file_exists($url);
	}

	/**
	 * Retrieves order line items for the specified order ID.
	 *
	 * @param string $orderId The ID of the order to retrieve items for.
	 *
	 * @return array The list of order items.
	 *
	 * @throws \Exception|GuzzleException If an error occurs during the process.
	 */
	public function getOrderItems(string $orderId): array
	{
		try
		{
			$filter   = $filter = new filter([
				new filterCriteria('orderId', $orderId),
			]);
			$response = json_decode(self::post('search/order-line-item', $filter));
		}
		catch (\Exception $e)
		{
			self::log('Could not get order items' . $e->getMessage());
		}

		return $response->data;
	}


	/**
	 * Retrieves multiple products based on provided product IDs.
	 *
	 * @param array $productIds An array of product IDs to search for.
	 *
	 * @return mixed The response containing product data.
	 *
	 * @throws \Exception|GuzzleException If an error occurs during the product retrieval process.
	 */
	public function getMultipleProducts(array $productIds)
	{
		try
		{
			$filter   = ['ids' => $productIds];
			$response = json_decode(self::post('search/product', $filter));
		}
		catch (\Exception $e)
		{
			self::log('Could not get multiple products' . $e->getMessage());
		}

		return $response;
	}

	/**
	 * Validates if the provided string is a valid UUID.
	 *
	 * @param string $id The string to validate as UUID.
	 *
	 * @return bool Returns true if the string is a valid UUID, otherwise false.
	 */
	public static function isValidUUID(string $id): bool
	{
		if (!preg_match('/^[0-9a-f]{32}$/', $id))
		{
			return false;
		}

		return true;
	}

	/**
	 * Retrieves the customer number by a given customer ID.
	 *
	 * @param string $id The ID of the customer.
	 *
	 * @return string The customer number associated with the specified ID.
	 */
	public function getCustomerNumberById(string $id): string
	{
		return (self::getCustomer($id))->customerNumber;
	}

	/**
	 * Add a customer group
	 *
	 * @param customergroup $customergroup
	 *
	 * @return mixed
	 * @throws GuzzleException
	 * @todo here should be a check if group already exists, but you can tell by name only
	 */
	public function addCustomerGroup(customergroup $customergroup)
	{
		try
		{
			$response = json_decode(self::post('customer-group?_response=basic', $customergroup));

		}
		catch (\Exception $e)
		{
			echo('Could not add customergroup ' . $e->getMessage());
		}

		return $response->data->id ?? null;
	}


	/**
	 * Retrieves customer groups from the API.
	 *
	 * @return array|null
	 */
	public function getCustomerGroups(): ?array
	{
		try
		{
			$response = json_decode(self::get('customer-group/'));
		}
		catch (\Exception $e)
		{
			echo('Could not add customergroup ' . $e->getMessage());
		}

		return $response->data ?? null;
	}

	/**
	 * Retrieves the name of a customer group by its ID.
	 *
	 * @param string $id The ID of the customer group.
	 *
	 * @return string|null Returns the name of the customer group if available, or null if not.
	 */
	public function getCustomerGroupName(string $id): ?string
	{
		try
		{
			$response = json_decode(self::get('customer-group/' . $id));
		}
		catch (\Exception $e)
		{
			echo('Could not add customergroup ' . $e->getMessage());
		}

		return $response->data->name ?? null;
	}

	/**
	 * Retrieves customers based on the provided customer numbers.
	 *
	 * @param array|null $customerNumbers List of customer numbers or null to retrieve all customers
	 *
	 * @return object|array Returns a single customer object or an array of customer objects
	 * @throws GuzzleException
	 */
	public function getCustomers(?array $customerNumbers): object|array
	{
		$customers = [];
		if ($customerNumbers == null)
		{
			return self::getCustomer('');
		}
		else
		{
			foreach ($customerNumbers as $customerNumber)
			{
				$id          = self::getCustomerIdByCustomerNumber($customerNumber);
				$customers[] = self::getCustomer((string) $id);
			}
		}

		return $customers;
	}

	/**
	 * Retrieves customer group rules by applying a filter and sending a request.
	 *
	 * @return mixed|null Returns the data from the response if available, or null on failure.
	 * @throws GuzzleException
	 */
	public function getCustomerGroupRules()
	{
		try
		{
			$filter   = new filter(
				[
					new filterCriteria('type', 'customerCustomerGroup')
				]
			);
			$response = json_decode(self::post('search/rule-condition', $filter));
		}
		catch (\Exception $e)
		{
			self::log('Could not get customer group rules ' . $e->getMessage());
		}

		return $response->data ?? null;
	}

	/**
	 * Deletes the product price by its ID.
	 *
	 * @param string $id The ID of the product price to delete.
	 *
	 * @return bool Returns true if the operation was attempted.
	 * @throws GuzzleException
	 */
	public function deleteProductPrice(string $id): bool
	{
		try
		{
			self::delete('product-price/' . $id, []);
		}
		catch (\Exception $e)
		{
			self::log('Could not delete price' . $e->getMessage());
		}

		return true;
	}

	/**
	 * Deletes all extended prices associated with a given product ID.
	 *
	 * @param string $productId The ID of the product whose extended prices are to be deleted.
	 *
	 * @return array|null Returns the data of deleted prices, or null if none were found.
	 * @throws GuzzleException
	 */
	public function deleteAllExtendedPricesByProductId(string $productId)
	{
		try
		{
			$filter   = new filter(
				[
					new filterCriteria('productId', $productId)
				]
			);
			$response = json_decode(self::post('search/product-price', $filter));
			if (!empty($response->data))
			{
				foreach ($response->data as $row)
				{
					self::deleteProductPrice($row->id);
				}
			}
		}
		catch (\Exception $e)
		{
			self::log('Could not delete all extended prices' . $e->getMessage());
		}

		return $response->data ?? null;
	}

	/**
	 * Deletes all extended prices associated with a specific rule ID.
	 *
	 * @param string $ruleId The ID of the rule for which all extended prices should be deleted.
	 *
	 * @return array|null Returns an array of deleted data or null if no data was found or an exception occurred.
	 * @throws GuzzleException
	 */
	public function deleteAllExtendedPricesByRuleId(string $ruleId)
	{
		try
		{
			$filter   = new filter(
				[
					new filterCriteria('ruleId', $ruleId)
				]
			);
			$response = json_decode(self::post('search/product-price', $filter));
			if (!empty($response->data))
			{
				$ids = [];
				foreach ($response->data as $row)
				{
					$ids[] = $row->id;
				}
				self::bulkDelete($ids, 'product_price');
			}
		}
		catch (\Exception $e)
		{
			self::log('Could not delete all extended prices' . $e->getMessage());
		}

		return $response->data ?? null;
	}

	/**
	 * Deletes all extended prices associated with a given rule ID and product ID.
	 *
	 * @param string $ruleId    The identifier of the rule.
	 * @param string $productId The identifier of the product.
	 *
	 * @return array|null Returns the data of deleted prices or null if no data exists.
	 * @throws GuzzleException
	 */
	public function deleteAllExtendedPricesByRuleAndProductId(string $ruleId, string $productId)
	{
		try
		{
			$filter   = new filter(
				[
					new filterCriteria('ruleId', $ruleId),
					new filterCriteria('productId', $productId)
				]
			);
			$response = json_decode(self::post('search/product-price', $filter));
			if (!empty($response->data))
			{
				foreach ($response->data as $row)
				{
					self::deleteProductPrice($row->id);
				}
			}
		}
		catch (\Exception $e)
		{
			self::log('Could not delete all extended prices' . $e->getMessage());
		}

		return $response->data ?? null;
	}

	/**
	 * Retrieves all ordered product IDs by a specified customer ID.
	 *
	 * @param string $customerId The ID of the customer.
	 *
	 * @return array An array of product IDs associated with the customer's orders.
	 * @throws GuzzleException
	 */
	public function getAllOrderedProductIdsByCustomer(string $customerId): array
	{
		try
		{
			$filter   = new filter(
				[
					new filterCriteria('orderCustomer.customerId', $customerId)
				]
			);
			$response = json_decode(self::post('search/order', $filter));
			if (!empty($response->data))
			{
				$ids = [];
				foreach ($response->data as $data)
				{
					$ids[] = $data->id;
				}
				$criteria = new filterCriteria('orderId', $ids);
				$criteria->setType('equalsAny');
				$filter   = new filter([$criteria]);
				$response = json_decode(self::post('search/order-line-item', $filter));
				$ids      = [];
				if (!empty($response->data))
				{
					foreach ($response->data as $data)
					{
						$ids[] = $data->productId;
					}
				}

				return $ids;
			}
		}
		catch (\Exception $e)
		{
			self::log('Could not get all ordered products ' . $e->getMessage());
		}

		return [];
	}

	/**
	 * Fetches all customer price rules.
	 *
	 * @return array|null The data from the response or null if not available.
	 * @throws GuzzleException
	 */
	public function getAllCustomerPriceRules()
	{
		try
		{
			$filter   = new notFilter(
				[
					new filterCriteria('customFields.custom_rule_personennr', null)
				]
			);
			$response = json_decode(self::post('search/rule', $filter));
		}
		catch (\Exception $e)
		{
			self::log('Could not get all price rules' . $e->getMessage());
		}

		return $response->data ?? null;
	}

	/**
	 * Retrieves prices based on the specified rules.
	 *
	 * @param array $rules Array of rules to filter the prices by.
	 *
	 * @return mixed|null The data from the response, or null if an exception occurs.
	 * @throws GuzzleException
	 */
	public function getPricesByRules(array $rules)
	{
		try
		{
			$criteria = new filterCriteria('ruleId', $rules);
			$criteria->setType('equalsAny');
			$filter   = new filter([$criteria]);
			$response = json_decode(self::post('search/product-price', $filter));
		}
		catch (\Exception $e)
		{
			self::log('Could not get price rules' . $e->getMessage());
		}

		return $response->data ?? null;
	}

	/**
	 * Retrieves product IDs based on the given product numbers.
	 *
	 * @param array $productNumbers An array of product numbers to search for.
	 *
	 * @return array|null An associative array with product numbers as keys and their corresponding IDs as values, or null if no products are found.
	 */
	public function getProductIdsByProductNumbers(array $productNumbers): ?array
	{
		if (!empty($productNumbers))
		{
			$criteria = new filterCriteria('productNumber', $productNumbers);
			$criteria->setType('equalsAny');
			$filter   = new filter([
				$criteria
			]);
			$response = self::getProductByFilter($filter);
			if (!empty($response->data))
			{
				$ids = [];
				foreach ($response->data as $product)
				{
					$ids[$product->productNumber] = $product->id;
				}
			}
		}

		return $ids ?? null;
	}

	/**
	 * Fetches the members of a customer group by the provided customer group ID.
	 *
	 * @param string $customerGroupId The ID of the customer group.
	 *
	 * @return array|null Returns an array of customer IDs if found, or null if no members are available.
	 * @throws GuzzleException
	 */
	public function getCustomerGroupMembers(string $customerGroupId): ?array
	{
		$filter   = new filter(
			[
				new filterCriteria('groupId', $customerGroupId)
			]);
		$response = json_decode(self::post('search/customer', $filter));
		$ids      = null;
		if (!empty($response->data))
		{
			foreach ($response->data as $customer)
			{
				$ids[] = $customer->id;
			}
		}

		return $ids;
	}

	/**
	 * Executes a bulk action on the specified entity with the provided payload, action type, and optional headers.
	 *
	 * @param string     $entity  The name of the entity to be targeted by the bulk action.
	 * @param array      $payload The data to be processed in the bulk action.
	 * @param string     $action  The type of bulk operation to perform (default: 'upsert').
	 * @param array|null $headers Optional headers to customize the request (default: predefined headers).
	 *
	 * @throws GuzzleException
	 */
	public function bulkAction(string $entity, array $payload, string $action = 'upsert', ?array $headers = [])
	{

		$bulk = new bulkAction();
		$bulk->setEntity($entity);
		$bulk->setAction($action);
		$bulk->setPayload($payload);

		if (empty($headers))
		{
			$headers = [
				'single-operation'   => 1,
				'indexing-behaviour' => 'use-queue-indexing'
			];
		}

		$action             = new \stdClass();
		$action->bulkaction = $bulk;
		try
		{
			self::request('_action/sync', $action, 'POST', false, $headers);
		}
		catch (\Exception $e)
		{
			var_dump($e->getMessage());
		}
	}

	/**
	 * Retrieves an array of product IDs for products with names matching the given pattern.
	 *
	 * @param string $name The pattern to search for in product names.
	 *
	 * @return array|null An array of product IDs if matches are found, null otherwise.
	 */
	public function getProductIdsByNameLike(string $name): ?array
	{
		$criteria = new filterCriteria('name', $name);
		$criteria->setType('contains');
		$filter   = new filter([
			$criteria
		]);
		$response = self::getProductByFilter($filter);
		if (!empty($response->data))
		{
			$ids = [];
			foreach ($response->data as $product)
			{
				$ids[] = $product->id;
			}
		}

		return $ids ?? null;
	}

	/**
	 * Bulk delete entities
	 *
	 * @param array  $ids    List of IDs to be deleted
	 * @param string $entity Entity type for bulk deletion
	 *
	 * @throws GuzzleException
	 */
	public function bulkDelete(array $ids, string $entity = 'product')
	{
		$payload = [];
		foreach ($ids as $item)
		{
			$element     = new \stdClass();
			$element->id = $item;
			$payload[]   = $element;
		}

		self::bulkAction($entity, $payload, 'delete');
	}

	/**
	 * Executes a bulk update action on the specified entity.
	 *
	 * @param array  $payload The data to be updated in bulk.
	 * @param string $entity  The entity type to be updated (default is 'product').
	 *
	 * @throws GuzzleException
	 */
	public function bulkUpdate(array $payload, string $entity = 'product')
	{
		self::bulkAction($entity, $payload, 'update');
	}

	/**
	 * Deletes duplicate product media entries by analyzing product media data.
	 * Groups media files by file name and identifies duplicates based on their counts.
	 * Removes media records if they are identified as duplicates.
	 * Logs an error message in case of an exception during the process.
	 *
	 * @throws \Exception|GuzzleException If there is an error during product media processing.
	 */
	public function deleteDuplicateProductMedia()
	{
		try
		{
			$products = json_decode(self::get('product-media'));
			foreach ($products->data as $p)
			{
				if (empty($ids[$p->media->fileName]))
				{
					$ids[$p->media->fileName] = ['count' => 1, 'productNumbers' => [$p->productId], 'mediaId' => $p->mediaId];
				}
				else
				{
					$ids[$p->media->fileName]['count']++;
					$ids[$p->media->fileName]['productNumbers'][] = $p->productId;
				}
			}
			foreach ($ids as $key => $val)
			{
				if ($val['count'] < 2)
				{
					unset($ids[$key]);
				}
				else
				{
					self::delete('media/' . $val['mediaId']);
				}
			}
		}
		catch (\Exception $e)
		{
			self::log('Could not find products ' . $e->getMessage());
		}
	}

	/**
	 * Adds category assignments for a given product.
	 *
	 * @param string $productId The ID of the product.
	 * @param array  $catIds    An array of category IDs to be assigned to the product.
	 *
	 * @throws GuzzleException
	 */
	public function addCategoryAssignments(string $productId, array $catIds)
	{
		$payload = [];
		foreach ($catIds as $cat)
		{
			$payload[] = [
				'productId'  => $productId,
				'categoryId' => $cat
			];
		}
		if (!empty($payload))
		{
			self::bulkAction('product_category', $payload, 'upsert');
		}
	}

	/**
	 * Deletes category assignments for the specified product.
	 *
	 * @param string $productId The ID of the product whose category assignments are to be deleted.
	 *
	 * @throws GuzzleException
	 */
	public function deleteCategoryAssignments(string $productId)
	{
		$product = self::getProduct($productId);
		$payload = [];
		if (!empty($product->categoryIds))
		{
			foreach ($product->categoryIds as $cat)
			{
				$payload[] = [
					'productId'  => $product->id,
					'categoryId' => $cat
				];
			}
		}
		if (!empty($payload))
		{
			self::bulkAction('product_category', $payload, 'delete');
		}
	}

	/**
	 * Adds a batch of product prices.
	 *
	 * @param array $payload An array containing the product price data.
	 *
	 * @return bool Returns true on successful execution.
	 * @throws GuzzleException
	 */
	public function addProductPricesBatch(array $payload)
	{
		self::bulkAction('product_price', $payload);

		return true;
	}

}
