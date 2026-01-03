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
 * @var  array  $products Products array.
 * @var  string $mode     RadicalMart mode.
 * @var  object $block    Related block
 *
 */

?>

<?php if (!empty($products)): ?>
    <div class="mt-3">
        <div class="h3"><?php echo Text::_($block->title); ?></div>

        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3">
			<?php foreach ($products as $product)
			{
				$layout = (int) ($block->component_layouts ?? false) ? 'components.radicalmart.products.item.grid' : 'plugins.radicalmart.related.display.grid';
				echo '<div class="mb-3">' . LayoutHelper::render($layout, ['product' => $product, 'mode' => $mode]) . '</div>';
			} ?>
        </div>
    </div>
<?php endif; ?>
