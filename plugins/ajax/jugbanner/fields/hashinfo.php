<?php
/**
 * @copyright   Copyright (C) 2019 Benjamin Trenkle. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Language\Text;

JFormHelper::loadFieldClass('spacer');

class JFormFieldHashInfo extends JFormFieldSpacer {

	protected $type = 'HashInfo';

	/**
	 * Method to attach a JForm object to the field.
	 *
	 * @param   \SimpleXMLElement  $element  The SimpleXMLElement object representing the `<field>` tag for the form field object.
	 * @param   mixed              $value    The form field value to validate.
	 * @param   string             $group    The field name group control value. This acts as as an array container for the field.
	 *                                       For example if the field has name="foo" and the group value is set to "bar" then the
	 *                                       full field name would end up being "bar[foo]".
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   1.7.0
	 */
	public function setup(\SimpleXMLElement $element, $value, $group = null)
	{
		$element['label'] = Text::sprintf($element['label'], $this->getHash());

		return parent::setup($element, $value, $group);
	}

	protected function getHash()
	{
		PluginHelper::importPlugin('ajax');

		ob_start();

		Factory::getApplication()->triggerEvent('onAjaxBannerHash');

		return ob_get_clean();
	}
}
