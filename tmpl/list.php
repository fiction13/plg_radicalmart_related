<?php
/*
 * @package   RadicalMart - Related
 * @version   1.0.0
 * @author    Dmitriy Vasyukov - https://fictionlabs.ru
 * @copyright Copyright (c) 2026 Fictionlabs. All rights reserved.
 * @license   GNU/GPL license: http://www.gnu.org/copyleft/gpl.html
 * @link      https://fictionlabs.ru/
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

/**
 * Layout variables
 * -----------------
 *
 * @var  array  $items Products array.
 * @var  string $mode  RadicalMart mode.
 * @var  object $block Related block
 *
 */

?>

<?php if (!empty($products)): ?>
    <div class="mt-3">
        <div class="h3"><?php echo Text::_($block->title); ?></div>

		<?php foreach ($products as $product)
		{
			$layout = (int) ($block->component_layouts ?? false) ? 'components.radicalmart.products.item.list' : 'plugins.radicalmart.related.display.list';
			echo LayoutHelper::render($layout, ['product' => $product, 'mode' => $mode]);
		} ?>
    </div>
<?php endif; ?>
