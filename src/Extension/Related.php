<?php
/*
 * @package   RadicalMart - Related
 * @version   __DEPLOY_VERSION__
 * @author    Dmitriy Vasyukov - https://fictionlabs.ru
 * @copyright Copyright (c) 2026 Fictionlabs. All rights reserved.
 * @license   GNU/GPL license: http://www.gnu.org/copyleft/gpl.html
 * @link      https://fictionlabs.ru/
 */

namespace Joomla\Plugin\RadicalMart\Related\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\Component\RadicalMart\Administrator\View\FormView;
use Joomla\Filesystem\Path;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Component\RadicalMart\Administrator\Helper\ParamsHelper;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Plugin\RadicalMart\Related\Helper\RelatedHelper;
use Joomla\Registry\Registry;

class Related extends CMSPlugin implements SubscriberInterface
{
	/**
	 * Load the language file on instantiation.
	 *
	 * @var    bool
	 *
	 * @since  1.0.0
	 */
    protected $autoloadLanguage = true;

	/**
	 * Loads the application object.
	 *
	 * @var  \Joomla\CMS\Application\CMSApplication
	 *
	 * @since  1.0.0
	 */
	protected $app = null;

	/**
	 * Loads the database object.
	 *
	 * @var  \Joomla\Database\DatabaseDriver
	 *
	 * @since  1.0.0
	 */
	protected $db = null;

	/**
	 * Returns an array of events this subscriber will listen to.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onContentPrepareForm'              => 'onContentPrepareForm',
			'onRadicalMartPrepareConfigForm'    => 'onRadicalMartPrepareConfigForm',
			'onRadicalMartNormaliseRequestData' => 'onRadicalMartNormaliseRequestData',
			'onRadicalMartPrepareConfigGroups'  => 'onRadicalMartPrepareConfigGroups',
			'onRadicalMartPrepareViewTabs'      => 'onRadicalMartPrepareViewTabs',
			'onContentAfterTitle'               => 'onContentAfterTitle',
			'onContentBeforeDisplay'            => 'onContentBeforeDisplay',
			'onContentAfterDisplay'             => 'onContentAfterDisplay',
		];
	}

	/**
	 * Trigger `onRadicalMartPrepareConfigGroups` event.
	 *
	 * @param   array  $groups  Modified groups data.
	 *
	 * @return void
	 */
	public function onRadicalMartPrepareConfigGroups(array &$groups)
	{
		Factory::getApplication()->getLanguage()->load('plg_radicalmart_related', JPATH_ADMINISTRATOR);

		$groups['related'] = [
			'title'    => 'PLG_RADICALMART_RELATED_CONFIG_TITLE',
			'key'      => 'related',
			'sections' => [
				'settings' => [
					'title'     => 'PLG_RADICALMART_RELATED_CONFIG_SETTINGS',
					'key'       => 'related_settings',
					'type'      => 'fieldsets',
					'fieldsets' => [
						'related'
					],
				]
			]
		];
	}

	/**
	 * Trigger `onRadicalMartPrepareViewTabs` event.
	 *
	 * @param   array     $tabs  Modified view tabs.
	 * @param   FormView  $view  Current form view class object.
	 *
	 * @return void
	 */
	public function onRadicalMartPrepareViewTabs(array &$tabs, FormView $view): void
	{
		Factory::getApplication()->getLanguage()->load('plg_radicalmart_related', JPATH_ADMINISTRATOR);

		$tabs['related'] = [
			'title'     => 'PLG_RADICALMART_RELATED_PRODUCT_FIELDSET_LABEL',
			'fieldsets' => [],
			'ordering'  => 210,
		];
	}

	/**
	 * Trigger `onRadicalMartNormaliseRequestData` event.
	 *
	 * @param   string        $context  The execution context.
	 * @param   object|null  &$objData  Reference to the form data object.
	 * @param   Form|null     $form     The form object, if available.
	 *
	 * @return void
	 */
	public function onRadicalMartNormaliseRequestData($context, &$item, $form): void
	{
		if ($context === 'com_radicalmart.config')
		{
			$this->normaliseParams($item);
		}
	}

	/**
	 * Add form override.
	 *
	 * @param   Event  $event
	 *
	 * @throws  \Exception
	 *
	 * @since  1.0.0
	 */
	public function onContentPrepareForm(Event $event)
	{
		/** @var Form $form */
		$form = $event->getArgument(0);
		$data = $event->getArgument(1);

		$formName = $form->getName();

		if (!Factory::getApplication()->isClient('administrator')) return;

		// Product
		if ($formName === 'com_radicalmart.product')
		{
			// Fields
			$fields   = array();
			$formData = Factory::getApplication()->getInput()->get('jform');

			$category = (new Registry($data))->get('category') ?? $formData ?? $formData['category'] ?? null;
			// $config   = !empty($category) ? ParamsHelper::getCategoryParams($category) : ParamsHelper::getComponentParams();
			$config = ParamsHelper::getComponentParams();

			if (empty($category)) return;

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
						$name = 'related_' . $row->alias;

						// Add fields
						$file = Path::find(JPATH_PLUGINS . '/' . $this->_type . '/' . $this->_name . '/forms', 'related_product.xml');

						if (!$file) continue;

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
							$newFieldsetXML->addAttribute('name', $name);
							$newFieldsetXML->addAttribute('tab', 'related');
							$newFieldsetXML->addAttribute('label', Text::_($row->title));
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
		if ($formName === 'com_radicalmart.category')
		{
			// Add path
			Form::addFormPath(JPATH_PLUGINS . '/' . $this->_type . '/' . $this->_name . '/forms');
			$form->loadFile('related_category');
		}
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
	 * @since   1.0.0
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
	 * @since   1.0.0
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
	 * @since   1.0.0
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
	 * @since   1.0.0
	 */
	private function display($context, $item, $params, $displayType)
	{
		$result = array();
		$params = ParamsHelper::getProductParams($item->id);
		$app    = Factory::getApplication();

		if ((int) !$params->get('related_enable')) return '';

		$relatedBlocks = $params->get('related_blocks');

		if (empty($relatedBlocks)) return '';

		// Load language
		Factory::getApplication()->getLanguage()->load('com_radicalmart', JPATH_SITE);

		// Get mode
		$mode = ParamsHelper::getComponentParams()->get('mode', 'shop');

		foreach ($relatedBlocks as $block)
		{
			if ($app->isClient('site') && $context === 'com_radicalmart.product' && $displayType === (int) $block->display)
			{
				// Get html
				$path     = PluginHelper::getLayoutPath('radicalmart', 'related', $block->layout);
				$products = RelatedHelper::getProducts($item, $block);

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
	 * @since 1.0.0
	 */
	public function onRadicalMartPrepareConfigForm(Form $form, $data)
	{
		Form::addFormPath(JPATH_PLUGINS . '/' . $this->_type . '/' . $this->_name . '/forms');
		$form->loadFile('related_config');
	}

	/**
	 * @param   \stdClass  $params
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function normaliseParams(\stdClass &$item): void
	{
		$relatedBlocks = $item->related_blocks;

		if (empty($relatedBlocks)) return;

		$i = 0;

		foreach ($relatedBlocks as &$block)
		{
			// Create title if empty
			if (empty($block['title']))
			{
				$block['title'] = Text::sprintf('PLG_RADICALMART_RELATED_EMPTY_BLOCK_TITLE', $i + 1);
			}

			// Create alias
			if (empty($block['alias']))
			{
				$alias          = OutputFilter::stringURLSafe($block['title'], 'ru-RU') . '-' . uniqid(rand());
				$block['alias'] = $alias;
			}

			$i++;
		}

		$item->related_blocks = $relatedBlocks;
	}
}