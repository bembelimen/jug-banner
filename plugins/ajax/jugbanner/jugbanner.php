<?php
/**
 * @copyright   Copyright (C) 2019 Benjamin Trenkle. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Cache\Cache;
use Joomla\Registry\Registry;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Uri\Uri;

class plgAjaxJugbanner extends CMSPlugin
{
	protected $autoloadLanguage = true;

	protected static $hash = 'sha512';

	public function onAjaxBannerList()
	{
		echo $this->getXml();
	}

	public function onAjaxBannerHash()
	{
		$xml = $this->getXml();

		echo hash(static::$hash, $xml);
	}

	protected function getXml()
	{
		$options = [
			'lifetime' => 60,
			'storage' => Factory::getApplication()->get('cache_handler', 'file'),
			'defaultgroup' => 'jugbanner',
			'caching' => true
		];

		$cache = Cache::getInstance('callback', $options);

		$xml = $cache->get('plgAjaxJugbanner::generateXml', [$this->params]);

		return $xml;
	}

	protected static function getBanners($params)
	{
		BaseDatabaseModel::addIncludePath(JPATH_ROOT . '/components/com_banners/models', 'BannersModel');

		$model = BaseDatabaseModel::getInstance('Banners', 'BannersModel', ['ignore_request' => true]);

		$model->setState('filter.published', 1);
		$model->setState('filter.category_id', (array) $params->get('catid', []));

		return $model->getItems();
	}

	/**
	 * Generates the SimpleXMLElement structure for the banners
	 *
	 * @return  SimpleXMLElement
	 */
	public static function generateXml($params)
	{
		$banners = static::getBanners($params);

		$xml = new SimpleXMLElement('<banners></banners>');

		$uri = Uri::getInstance();

		foreach ($banners as $banner)
		{
			$bannerparams = new Registry();

			$image = $banner->params->get('imageurl');

			$abspath = Path::check(JPATH_ROOT . '/' . $image);

			if (is_file($abspath))
			{
				$uri->setPath(Uri::root(true) . '/' . $image);

				$child = $xml->addChild('banner');

				$child->addChild('image', $uri->toString(['scheme', 'user', 'pass', 'host', 'port', 'path']));
				$child->addChild('link', $banner->clickurl);
				$child->addChild('verify', hash_file(static::$hash, $abspath));
			}
		}

		return $xml->asXml();
	}
}
