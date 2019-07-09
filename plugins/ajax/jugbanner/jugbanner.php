<?php
/**
 * @copyright   Copyright (C) 2019 Benjamin Trenkle. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Cache\Cache;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

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
		$jugbanner = Factory::getApplication()->input->get('jugbanner', [], 'array');

		$options = [
			'storage' => Factory::getApplication()->get('cache_handler', 'file'),
			'defaultgroup' => 'jugbanner'
		];

		$cache = Cache::getInstance('callback', $options);

		// We need our own cache ID, because the cache is also based on the given parameters
		$id = md5(serialize(array('plgAjaxJugbanner::generateXml', [$this->params], $jugbanner)));

		$xml = $cache->get('plgAjaxJugbanner::generateXml', [$this->params], $id);

		return $xml;
	}

	protected static function getBanners($params)
	{
		BaseDatabaseModel::addIncludePath(JPATH_ROOT . '/components/com_banners/models', 'BannersModel');

		$jugbanner = Factory::getApplication()->input->get('jugbanner', [], 'array');

		$events = ArrayHelper::getValue($jugbanner, 'events', [], 'array');

		$client_ids = [];

		foreach ($events as $event)
		{
			if ($params->exists('event_' . $event))
			{
				$client_ids[] = (int) $params->get('event_' . $event);
			}
		}

		$client_ids = array_unique(array_filter($client_ids));

		// We break when the user requested specific events which does not exists anymore
		// Otherwise events from not selected clients will be displayed
		if (empty($client_ids) && !empty($events))
		{
			return [];
		}

		$cat_ids = (array) $params->get('catid', [0]);
		$cat_ids = ArrayHelper::toInteger($cat_ids);
		$cat_ids = array_unique(array_filter($cat_ids));

		if (empty($cat_ids))
		{
			return [];
		}

		$list_limit = min(10, max(1, ArrayHelper::getValue($jugbanner, 'num_banners', 5, 'int')));

		$banners = [];

		do
		{
			$model = BaseDatabaseModel::getInstance('Banners', 'BannersModel', ['ignore_request' => true]);

			$model->setState('list.limit', 0);
			$model->setState('filter.published', 1);
			$model->setState('filter.category_id', $cat_ids);

			$client_id = 0;

			if (count($client_ids))
			{
				$client_id = (int) array_shift($client_ids);
			}

			$model->setState('filter.client_id', $client_id);

			$temp = $model->getItems();

			$banners = array_merge($banners, $temp);

		} while(count($client_ids) > 0 && $client_id > 0);

		$result = [];

		$size = ArrayHelper::getValue($jugbanner, 'size');

		foreach ($banners as $banner)
		{
			if (isset($result[$banner->id]))
			{
				continue;
			}

			switch ($size)
			{
				case 'squared':
					if ($banner->params->get('width') > 0 && $banner->params->get('width') == $banner->params->get('height'))
					{
						$result[$banner->id] = $banner;
					}
					break;

				case 'edgewise':
					if ($banner->params->get('width') < $banner->params->get('height'))
					{
						$result[$banner->id] = $banner;
					}
					break;

				default:
					if ($banner->params->get('width') == 0 || $banner->params->get('width') > $banner->params->get('height'))
					{
						$result[$banner->id] = $banner;
					}
					break;
			}
		}

		return array_slice($result, 0, $list_limit);
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
