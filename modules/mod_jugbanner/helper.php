<?php
/**
 * @copyright   Copyright (C) 2019 Benjamin Trenkle. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;
use Joomla\Image\Image;

/**
 * Helper for mod_jugbanner
 *
 * @since  1.0
 */
class ModJugbannerHelper
{
	protected static $bannerurl = 'https://www.example.com/?option=com_ajax&format=raw&plugin=BannerList';

	protected static $verificationurl = 'https://www.example.com/?option=com_ajax&format=raw&plugin=BannerHash';

	protected static $banners = JPATH_ROOT . '/images';

	protected static $hash = 'sha512';

	public static function getBanners($params)
	{
		$xml = self::getXml($params);

		$result = [];

		if ($xml === false || !$xml instanceof SimpleXMLElement)
		{
			return $result;
		}

		$hash = md5((string) $params);

		$bannerpath = Path::check(self::$banners . '/' . $params->get('folder') . '/' . $hash);

		foreach ($xml->children() as $banner)
		{
			if (is_file($bannerpath . '/' . $banner->image))
			{
				$result[] = (object) [
					'image' => 'images/' . $params->get('folder') . '/' . $hash .'/' . (string) $banner->image,
					'link' => (string) $banner->link
				];
			}
		}

		return $result;
	}

	private static function getXml($params)
	{
		$hash = md5((string) $params);

		$xml = self::$banners . '/' . $params->get('folder') . '/' . $hash . '/banners.xml';

		$now = Factory::getDate()->toUnix();

		$updatetime = (int) $params->get('updateinterval');

		$xmlcontent = false;

		$file_exists = is_file($xml);

		$lastchange = $file_exists ? filemtime($xml) : 0;

		if (!$file_exists || ((int) $lastchange > 0 && (int) $updatetime > 0 && $now - $updatetime > $lastchange))
		{
			self::loadXml($params);
		}

		if (is_file($xml))
		{
			$xmlcontent = simplexml_load_file($xml);
		}

		return $xmlcontent;
	}

	private static function loadXml($params)
	{
		try
		{
			$hash = md5((string) $params);

			$bannerpath = self::$banners . '/' . $params->get('folder') . '/' . $hash;

			$http = HttpFactory::getHttp();

			$data = [];

			if (is_array($params->get('events')))
			{
				$data['events'] = [];

				foreach ($params->get('events') as $event)
				{
					switch ($event)
					{
						case 'jday_dach':
						case 'jday_int':
						case 'conferences':
						case 'pbf':
						case 'misc':
							$data['events'][] = $event;
					}
				}
			}

			switch ($params->get('size'))
			{
				case 'edgewise':
				case 'squared':
					$data['size'] = $params->get('size');
					break;

				default:
					$data['size'] = 'default';
			}

			$data['num_banners'] = min(max(1, (int) $params->get('num_banners')), 10);

			$content = $http->post(static::$bannerurl, ['jugbanner' => $data]);

			$valid = true;

			if ($params->get('verifyfile'))
			{
				$verify = $http->post(self::$verificationurl, ['jugbanner' => $data]);

				$valid = $verify->code == 200 && $content->code == 200 && hash(static::$hash, $content->body) == $verify->body;
			}

			if ($content->code == 200 && $valid)
			{
				$xml = simplexml_load_string($content->body);

				if (is_dir($bannerpath))
				{
					Folder::delete($bannerpath);
				}

				Folder::create($bannerpath);

				$banners = [];

				foreach ($xml->children() as $banner)
				{
					$ext = pathinfo($banner->image, PATHINFO_EXTENSION);
					$filename = basename($banner->image);

					switch ($ext)
					{
						case 'jpg':
							$type = IMAGETYPE_JPEG;
							break;
						case 'png':
							$type = IMAGETYPE_PNG;
							break;
						case 'gif':
							$type = IMAGETYPE_GIF;
							break;

						default:
							continue 2;
					}

					$image = $http->get((string) $banner->image);

					if ($image->code !== 200)
					{
						continue;
					}

					if (strlen($banner->verify) && (string) $banner->verify !== hash(static::$hash, $image->body))
					{
						continue;
					}

					$res = imagecreatefromstring($image->body);

					$img = new Image($res);

					$path = Path::check($bannerpath . '/' . $filename);

					if ($img->toFile($path, $type))
					{
						$banners[] = (object) array(
							'image' => $filename,
							'link' => (string) $banner->link
						);
					}
				}

				$newxml = new SimpleXMLElement('<banners></banners>');

				foreach ($banners as $banner)
				{
					$bxml = $newxml->addChild('banner');

					$bxml->addChild('image', $banner->image);
					$bxml->addChild('link', $banner->link);
				}

				$newxml->asXML($bannerpath . '/' . 'banners.xml');
			}

		} catch (Exception $ex) {

		}
	}
}
