<?php
/*
 * @package   RadicalMart - Related
 * @version   __DEPLOY_VERSION__
 * @author    Dmitriy Vasyukov - https://fictionlabs.ru
 * @copyright Copyright (c) 2024 Fictionlabs. All rights reserved.
 * @license   GNU/GPL license: http://www.gnu.org/copyleft/gpl.html
 * @link      https://fictionlabs.ru/
 */

namespace Joomla\Plugin\RadicalMart\Related\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Component\RadicalMart\Administrator\Helper\ParamsHelper;
use Joomla\Component\RadicalMart\Site\Mapping\CategoryMapping;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;
use SimpleXMLElement;

class Related extends CMSPlugin implements SubscriberInterface
{
	/**
	 * Load the language file on instantiation.
	 *
	 * @var    bool
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected $autoloadLanguage = true;

	/**
	 * Loads the application object.
	 *
	 * @var  \Joomla\CMS\Application\CMSApplication
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected $app = null;

	/**
	 * Loads the database object.
	 *
	 * @var  \Joomla\Database\DatabaseDriver
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected $db = null;

	/**
	 * Returns an array of events this subscriber will listen to.
	 *
	 * @return  array
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onContentPrepareForm'              => 'onContentPrepareForm',
			'onRadicalMartPrepareConfigForm'    => 'onRadicalMartPrepareConfigForm',
			'onRadicalMartNormaliseRequestData' => 'onRadicalMartNormaliseRequestData',
			'onContentAfterTitle'               => 'onContentAfterTitle',
			'onContentBeforeDisplay'            => 'onContentBeforeDisplay',
			'onContentAfterDisplay'             => 'onContentAfterDisplay',
			'onExtensionAfterSave'              => 'onExtensionAfterSave'
		];
	}

	/**
	 * Listener for the `onContentNormaliseRequestData` event.
	 *
	 * @param   Event  $event  The event.
	 *
	 * @throws  \Exception
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public function onRadicalMartNormaliseRequestData($context, &$item, $form)
	{
		if ($context === 'com_radicalmart.category')
		{
			$params = new Registry($item->params);
			$params = $this->normaliseParams($params);

			// Set params
			$item->params = $params->toArray();
		}
	}

	/**
	 * Listener for the `onExtensionAfterSave` event.
	 *
	 * @param   Event  $event  The event.
	 *
	 * @throws  \Exception
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public function onExtensionAfterSave(Event $event)
	{
		$context = $event->getArgument(0);
		$table   = $event->getArgument(1);

		if ($context === 'com_config.component' && $table->element === 'com_radicalmart')
		{
			$params = new Registry($table->params);
			$params = $this->normaliseParams($params);

			// Store params
			$table->params = $params->toString();
			$table->store();
		}
	}

	/**
	 * Add form override.
	 *
	 * @param   Event  $event
	 *
	 * @throws  \Exception
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public function onContentPrepareForm(Event $event)
	{
		/** @var Form $form */
		$form = $event->getArgument(0);
		$data = $event->getArgument(1);

		$formName = $form->getName();

		if (!Factory::getApplication()->isClient('administrator'))
		{
			return;
		}

		// Product
		if ($formName === 'com_radicalmart.product')
		{
			// Fields
			$fields   = array();
			$formData = Factory::getApplication()->getInput()->get('jform');

			$category = (new Registry($data))->get('category') ?? $formData ?? $formData['category'] ?? null;
//			$config   = !empty($category) ? ParamsHelper::getCategoryParams($category) : ParamsHelper::getComponentParams();
			$config = ParamsHelper::getComponentParams();

			if (empty($category))
			{
				return;
			}

			// Create form
			$formXML = new \SimpleXMLElement('<form/>');

			// Add plugins fields block
			$fieldsXML = $formXML->addChild('fields');
			$fieldsXML->addAttribute('name', 'plugins');

			// Add related fields block
			$relatedXML = $fieldsXML->addChild('fields');
			$relatedXML->addAttribute('name', 'related');

			// Check related enable
			if ((int) $config->get('related_enable', 0))
			{
				$rows = $config->get('related_blocks', array());

				foreach ($rows as $key => $row)
				{
					if ($row->type === 'manual')
					{
						$name = 'plugins_' . $row->alias;

						// Add fields
						$file = Path::find(JPATH_PLUGINS . '/' . $this->_type . '/' . $this->_name . '/forms', 'related_product.xml');

						if (!$file)
						{
							continue;
						}

						$xmlField = simplexml_load_file($file);

						// This is important for display field!
						$xmlField->attributes()->name = $row->alias;
						$xmlField->addAttribute('label', 'PLG_RADICALMART_RELATED_PRODUCT_FIELD_MANUAL_ADD_LABEL');

						if ($xmlField)
						{
							if (!isset($fields[$name]))
							{
								$fields[$name] = [];
							}

							$fields[$name][] = $xmlField;
						}

						if (!empty($fields[$name]))
						{
							// Create new related block fieldset
							$newFieldsetXML = $relatedXML->addChild('fieldset');
							$newFieldsetXML->addAttribute('name', 'plugins_' . $row->alias);
							$newFieldsetXML->addAttribute('label', Text::sprintf('PLG_RADICALMART_RELATED_PRODUCT_FIELDSET_LABEL', Text::_($row->title)));
							$newFieldsetXML->addAttribute('description', Text::sprintf('PLG_RADICALMART_RELATED_PRODUCT_FIELDSET_DESCRIPTION_DISPLAY_' . $row->display));
						}
					}
				}

				// Load fieldset xml
				$form->load($formXML);

				// Add fields to form
				foreach ($fields as $key => $array)
				{
					$form->setFields($array, 'related', true, $key);
				}
			}
		}

		// Category
//		if ($formName === 'com_radicalmart.category')
//		{
//			// Add path
//			Form::addFormPath(JPATH_PLUGINS . '/' . $this->_type . '/' . $this->_name . '/forms');
//
//			$form->loadFile('related_category');
//		}
	}

	/**
	 * The display event.
	 *
	 * @param   Event  $event  The event.
	 *
	 * @return  void
	 *
	 * @throws  \Exception
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onContentAfterTitle(Event $event)
	{
		$context = $event->getArgument(0);
		$item    = $event->getArgument(1);
		$params  = $event->getArgument(2);
		$result  = $event->getArgument('result', []);

		$result[] = $this->display($context, $item, $params, 1);

		$event->setArgument('result', $result);
	}

	/**
	 * The display event.
	 *
	 * @param   Event  $event  The event.
	 *
	 * @return  void
	 *
	 * @throws  \Exception
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onContentBeforeDisplay(Event $event)
	{
		$context = $event->getArgument(0);
		$item    = $event->getArgument(1);
		$params  = $event->getArgument(2);
		$result  = $event->getArgument('result', []);

		$result[] = $this->display($context, $item, $params, 2);

		$event->setArgument('result', $result);
	}

	/**
	 * The display event.
	 *
	 * @param   Event  $event  The event.
	 *
	 * @return  void
	 *
	 * @throws  \Exception
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onContentAfterDisplay(Event $event)
	{
		$context = $event->getArgument(0);
		$item    = $event->getArgument(1);
		$params  = $event->getArgument(2);
		$result  = $event->getArgument('result', []);

		$result[] = $this->display($context, $item, $params, 3);

		$event->setArgument('result', $result);
	}

	/**
	 * Performs the display event.
	 *
	 * @param   string     $context      The context
	 * @param   \stdClass  $item         The item object
	 * @param   Registry   $params       The params
	 * @param   int        $displayType  The display type
	 *
	 * @return  string
	 *
	 * @throws  \Exception
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function display($context, $item, $params, $displayType)
	{
		$result = array();
		$params = ParamsHelper::getProductParams($item->id);
		$app    = Factory::getApplication();

		if ((int) !$params->get('related_enable'))
		{
			return '';
		}

		$relatedBlocks = $params->get('related_blocks');

		if (empty($relatedBlocks))
		{
			return '';
		}

		// Get mode
		$mode = ComponentHelper::getParams('com_radicalmart')->get('mode', 'shop');

		foreach ($relatedBlocks as $block)
		{
			if ($app->isClient('site') && $context === 'com_radicalmart.product' && $displayType === (int) $block->display)
			{
				// Get html
				$path     = PluginHelper::getLayoutPath('radicalmart', 'related', $block->layout);
				$products = $this->getProducts($item, $block);

				// Render the layout
				ob_start();
				include $path;
				$result[] = ob_get_clean();
			}
		}

		return implode("\n", $result);
	}

	/**
	 * Method to add `onRadicalMartPrepareConfigForm` & `onRadicalMartPrepareConfigCurrencyForm` event.
	 *
	 * @param   Form   $form  The form to be altered.
	 * @param   mixed  $data  The associated data for the form.
	 *
	 * @throws \Exception
	 *
	 * @since __DEPLOY_VERSION__
	 */
	public function onRadicalMartPrepareConfigForm(Form $form, $data)
	{
		Form::addFormPath(JPATH_PLUGINS . '/' . $this->_type . '/' . $this->_name . '/forms');
		$form->loadFile('related_config');
	}

	/**
	 * Method to add field value to products list.
	 *
	 * @param   object  $product  The product object.
	 * @param   object  $block    The related block object.
	 *
	 * @return  string|false  Field string values on success, False on failure.
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected function getProducts($product, $block)
	{
		$ids  = array();
		$mode = $block->type;

		if ($mode !== 'manual')
		{
			// Get products
			$db    = $this->db;
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
				$category = ($block->category === -1) ? Factory::getApplication()->getInput()->get('category') : $block->category;

				if (is_numeric($category) && $category > 1)
				{
					$conditionsCategory = ['FIND_IN_SET(' . $category . ', categories)'];
					foreach (CategoryMapping::getSubCategories($category) as $catid)
					{
						$conditionsCategory[] = 'FIND_IN_SET(' . $catid . ', categories)';
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

		if (empty($ids))
		{
			return false;
		}

		// Get products via model
		if (!$model = Factory::getApplication()->bootComponent('com_radicalmart')->getMVCFactory()->createModel('Products', 'Site', ['ignore_request' => true]))
		{
			throw new \Exception(Text::_('PLG_RADICALMART_RELATED_ERROR_MODEL_NOT_FOUND'), 500);
		}

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


	/**
	 * @param   Registry  $params
	 *
	 * @return Registry
	 *
	 * @since __DEPLOY_VERSION__
	 */
	public function normaliseParams($params)
	{
		$relatedBlocks = ArrayHelper::fromObject($params->get('related_blocks', new \stdClass()));

		if (!empty($relatedBlocks))
		{
			$i = 0;

			foreach ($relatedBlocks as &$block)
			{
				// Create alias
				if (empty($block['alias']))
				{
					$alias          = md5(uniqid(rand(), true));
					$block['alias'] = $alias;
				}

				// Create title if empty
				if (empty($block['title']))
				{
					$block['title'] = Text::sprintf('PLG_RADICALMART_RELATED_EMPTY_BLOCK_TITLE', $i + 1);
				}

				$i++;
			}
			$params->set('related_blocks', $relatedBlocks);
		}

		return $params;
	}
}