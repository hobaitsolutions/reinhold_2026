<?php

namespace Reinhold\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class Datasheets extends AbstractExtension
{
	public function getFilters()
	{
		return [
			new TwigFilter('datasheets', [$this, 'datasheets']),
		];
	}

	public function getIcon(string $extension)
	{
		switch (strtolower($extension))
		{
			case 'pdf':
				$icon = 'file';
				break;
			case 'gif':
			case 'jpeg':
			case 'jpg':
			case 'png':
				$icon = 'image';
				break;
			case 'rtf':
			case 'doc':
			case 'docx':
			case 'xls':
			case 'xlsx':
			case 'csv':
				$icon = 'paper-pencil';
				break;
			default:
				$icon = 'cloud-download';
		}

		return "{% sw_include '@Storefront/storefront/utilities/icon.html.twig' with { 'name': '" . $icon . "', 'size': 'xl'} %}";
	}


	/**
	 * Print hazards from json encoded string
	 *
	 * @param mixed $product
	 *
	 * @return string
	 */
	public function datasheets(\Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity $product)
	{
		$ds         = '';
		$html       = '';
		$mediaFiles = [];

		$datasheets = $product->getCustomFields();

		if (!empty($datasheets))
		{
			if (!empty($datasheets['custom_product_datasheets']))
			{
				$datasheets = $datasheets['custom_product_datasheets'];
			}
			else
			{
				$datasheets = null;
			}
		}
		if (empty($datasheets))
		{
			return '';
		}

		$datasheets = json_decode($datasheets);

		if (!empty($datasheets))
		{
			$types = [
				'Produktinformation',
				'Betriebsanweisung',
				'Sicherheitsdatenblatt',
				'RKI',
				'VAH',
				'Öko',
				'Video',
				'Foto',
			];
			//starts with
			$count = 0;
			foreach ($datasheets as $file => $name)
			{
				$goOn = false;
				foreach ($types as $t)
				{
					if (str_starts_with(strtolower($name), strtolower($t)))
					{
						$goOn = true;
					}
				}
				if ($goOn) //skip if not of a given type
				{
					if ( str_starts_with(strtolower($name), 'video'))
					{

						$mediaFiles[] = '<a href="' . $file . '" class="lightbox col-4 video" data-lightbox-gallery="gallery1">
										<span class="wrap position-relative">
											<span class="overlay">&#x25B6;</span>
											<video>
											  <source src="' . $file . '" type="video/mp4">
											</video>
											<span class="title">'.$name.'</span>
										</span>
									</a>';
					}
					else if (str_starts_with(strtolower($name),  'foto'))
					{
						$mediaFiles[] = '<a href="' . $file . '" class="lightbox col-4" data-lightbox-gallery="gallery1">
											<span class="wrap">
												<img src="' . $file . '" alt="" />
												<span class="title">'.$name.'</span>
											</span>
										</a>';
					}
					else
					{
						if (empty($ds))
						{
							$ds = '<h2 class="datasheets">Datenblätter und Gebrauchsanweisungen</h2>';
						}
						$extension = pathinfo($file)['extension'];
						$ds        .= '<div class="datasheet type-'.strtolower($name).'">';
						$ds        .= '<a href="' . $file . '">' . $this->getIcon($extension) . ' ' . $name . '</a>';
						$ds        .= '</div>';
					}
				}
				$count++;
			}
			if ($count > 1)
			{
				$ds .= '<p></p><a class="btn btn-sm btn-primary" href="/customAPI/product-datasheets.php?id=' . $product->getId() . '">Alle herunterladen</a>';
			}
		}

		if (!empty($mediaFiles))
		{
			$html .= '<h2 class="datasheets">Medien</h2><div class="custom-media-gallery row">' . implode($mediaFiles) . '</div>';
		}

		return '<div class="product-datasheets"><div class="datasheets">' . $html . $ds . '</div></div>';
	}

}
