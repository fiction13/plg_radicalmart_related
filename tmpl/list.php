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
 * @var  array  $items Products array.
 * @var  string $mode  RadicalMart mode.
 * @var  object $block Related block
 *
 */

?>

<?php if (!empty($products)): ?>
    <div class="h3"><?php echo Text::_($block->title); ?></div>

	<?php foreach ($products as $product)
	{
		echo LayoutHelper::render('plugins.radicalmart.related.display.list', ['product' => $product, 'mode' => $mode]);
	} ?>
<?php endif; ?>
