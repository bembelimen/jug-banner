<?php
/**
 * @copyright   Copyright (C) 2019 Benjamin Trenkle. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\HTML\HTMLHelper;

JLoader::register('ModJugbannerHelper', __DIR__ . '/helper.php');

// Activate file check
$params->def('verifyfile', 1);

// Seconds after new file should be loaded from the server
$params->def('updateinterval', 86400);

// Loaded events
$params->def('events', []);

// Number of loaded banners
$params->def('num_banners', 5);

// Default image folder, banners are saved to
$params->def('folder', 'jugbanner');

$cacheid = md5($module->id);

$cacheparams               = new stdClass;
$cacheparams->cachemode    = 'id';
$cacheparams->class        = 'ModJugbannerHelper';
$cacheparams->method       = 'getBanners';
$cacheparams->methodparams = $params;
$cacheparams->modeparams   = $cacheid;

$banners = ModuleHelper::moduleCache($module, $params, $cacheparams);

HTMLHelper::_('stylesheet', 'mod_jugbanner/jugbanner.css', ['relative' => true]);

$layout = $params->get('layout', 'default');

require JModuleHelper::getLayoutPath('mod_jugbanner', $layout);
