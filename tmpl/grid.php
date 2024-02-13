<?php
/*
 * @package   RadicalMart - Related
 * @version   __DEPLOY_VERSION__
 * @author    Dmitriy Vasyukov - https://fictionlabs.ru
 * @copyright Copyright (c) 2024 Fictionlabs. All rights reserved.
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
    <div class="h3"><?php echo Text::_($block->title); ?></div>

    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3">
		<?php foreach ($products as $product)
		{
			echo '<div class="mb-3">' . LayoutHelper::render('plugins.radicalmart.related.display.grid', ['product' => $product, 'mode' => $mode]) . '</div>';
		} ?>
    </div>
<?php endif; ?>
