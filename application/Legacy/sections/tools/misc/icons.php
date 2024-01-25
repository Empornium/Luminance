<?php
if (!check_perms('site_debug')) { error(403); }
show_header('Icons');
?>
<div class="thin">
    <h2>Icons</h2>
        <div class="box pad" style="font-size: 28pt;">
<?php
    $icons = new Sabberworm\CSS\Parser(file_get_contents($master->publicPath . '/static/common/icons.css'));
    $icons = $icons->parse();

    foreach ($icons->getAllDeclarationBlocks() as $block) {
	     foreach ($block->getSelectors() as $selector) {
           $selector = $selector->getSelector();
           if (strpos($selector, '.icon_') === 0) {
              $iconName = str_replace('.icon_', '', $selector);
              $iconName = str_replace(':before', '', $iconName);
?>
              <?=$master->render->icon('', $iconName);?>&nbsp;&nbsp;&nbsp;<?=$iconName?><br/>
<?php
           }
	      }
    }

?>
        </div>
<?php
    show_footer();
