<?php
/*
 * @package   RadicalMart - Related
 * @version   1.0.0
 * @author    Dmitriy Vasyukov - https://fictionlabs.ru
 * @copyright Copyright (c) 2026 Fictionlabs. All rights reserved.
 * @license   GNU/GPL license: http://www.gnu.org/copyleft/gpl.html
 * @link      https://fictionlabs.ru/
 */

namespace Joomla\Plugin\RadicalMart\Related\Helper;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Component\RadicalMart\Administrator\Helper\ParamsHelper;
use Joomla\Component\RadicalMart\Site\Mapping\CategoryMapping;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

class RelatedHelper
{
	/**
	 * Method for resize images
	 *
	 * @param   string  $src     Image src
	 * @param   int     $width   Image width
	 * @param   int     $height  Image height
	 *
	 * @return string|void
	 *
	 * @since 1.0.0
	 */
	public static function render(\stdClass $item, string $alias): string
	{
		if (empty($alias)) return '';

		$params = ParamsHelper::getComponentParams();

		if ((int) !$params->get('related_enable')) return '';

		$relatedBlocks = $params->get('related_blocks');
		$block         = false;

		foreach ($relatedBlocks as $relatedBlock)
		{
			if ($relatedBlock->alias === $alias && (int) $relatedBlock->display === 4)
			{
				$block = $relatedBlock;
				break;
			}
		}

		if (!$block) return '';

		// Load language
		Factory::getApplication()->getLanguage()->load('com_radicalmart', JPATH_SITE);

		// Get html
		$path     = PluginHelper::getLayoutPath('radicalmart', 'related', $block->layout);
		$products = self::getProducts($item, $block);

		// Get mode
		$mode = $params->get('mode', 'shop');

		// Render the layout
		ob_start();
		include $path;
		$html = ob_get_clean();

		return $html;
	}

	/**
	 * Method to add field value to products list.
	 *
	 * @param   object  $product  The product object.
	 * @param   object  $block    The related block object.
	 *
	 * @return  string|false  Field string values on success, False on failure.
	 *
	 * @since  1.0.0
	 */
	public static function getProducts($product, $block)
	{
		$ids  = array();
		$mode = $block->type;

		if ($mode !== 'manual')
		{
			// Get products
			$db    = Factory::getContainer()->get('DatabaseDriver');
			$query = $db->getQuery(true)
				->select('id')
				->from($db->quoteName('#__radicalmart_products'))
				->where($db->qn('id') . ' != :id')
				->where($db->qn('state') . ' = 1')
				->setLimit($block->limit)
				->bind(':id', $product->id, ParameterType::INTEGER);

			if ($mode === 'fields')
			{
				$sql        = array();
				$fieldAlias = $block->field;

				// Check field
				if (!$fieldAlias)
				{
					return false;
				}

				$values = (new Registry($product->fields))->get($fieldAlias)->rawvalue ?? '';

				// Check value exist
				if (empty($values))
				{
					return false;
				}

				if (!is_array($values))
				{
					$values = (array) $values;
				}

				foreach ($values as $val)
				{
					// Only simple fields
					if (is_object($val) || is_array($val))
					{
						continue;
					}

					if ($val = trim($val))
					{
						$val   = '"' . $val . '"';
						$sql[] = 'JSON_CONTAINS(fields, ' . $db->q($val) . ', ' . $db->q('$."' . $fieldAlias . '"') . ')';
					}
				}

				if (!empty($sql))
				{
					$query->where('(' . implode(' OR ', $sql) . ')');
				}
			}
			elseif ($mode === 'category')
			{
				// Filter by category state
				$category = ((int) $block->category === -1) ? Factory::getApplication()->getInput()->get('category') : $block->category;

				if (is_numeric($category) && $category > 1)
				{
					$conditionsCategory = ['FIND_IN_SET(' . $category . ', categories_all)'];
					foreach (CategoryMapping::getSubCategories($category) as $catid)
					{
						$conditionsCategory[] = 'FIND_IN_SET(' . $catid . ', categories_all)';
					}
					$query->extendWhere('AND', $conditionsCategory, 'OR');
				}
			}

			// Rand order
			$query->order('RAND()');

			$ids = $db->setQuery($query)->loadColumn();
		}
		else
		{
			// Get values
			$products = json_decode(json_encode($product->plugins->get('related.' . $block->alias, array())), true);
			$ids      = ArrayHelper::getColumn($products, 'id');
			$ids      = array_values(array_unique($ids));
		}

		if (empty($ids)) return false;

		// Get products via model
		if (!$model = Factory::getApplication()->bootComponent('com_radicalmart')->getMVCFactory()->createModel('Products', 'Site', ['ignore_request' => true]))
		{
			throw new \Exception(Text::_('PLG_RADICALMART_RELATED_ERROR_MODEL_NOT_FOUND'), 500);
		}

		$model->setState('params', ParamsHelper::getComponentParams());
		$model->setState('filter.item_id', $ids);
		$model->setState('filter.published', 1);
		$model->setState('list.limit', count($ids));

		// Set rand mode
		if ($mode !== 'manual')
		{
			$model->setState('list.ordering', $db->getQuery(true)->Rand());
		}

		// Set language filter state
		$model->setState('filter.language', Multilanguage::isEnabled());

		// Get items
		return $model->getItems();
	}
}