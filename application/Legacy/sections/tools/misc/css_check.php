<?php
if (!check_perms('site_debug')) { error(403); }
show_header('Style Validator');
?>
<div class="thin">
    <h2>Style Validator</h2>
        <div class="box pad">
          <table width="100%">
              <tr class="colhead">
                  <td>File</td>
                  <td>Errors</td>
              </tr>
<?php

    foreach (glob($master->publicPath."/static/styles/*/style.css") as $style) {
        $settings = Sabberworm\CSS\Settings::create()->beStrict();
        $Row = ($Row == 'b') ? 'a' : 'b';
        $subRow = 'b';
?>
        <tr class="row<?=$Row?>">
            <td>
                <?=$style?>
            </td>
<?php
        try {
            $styleSheet = new Sabberworm\CSS\Parser(file_get_contents($style), $settings);
            $styleSheet = $styleSheet->parse();
        } catch (Exception $e) {
            $subRow = ($subRow == 'b') ? 'a' : 'b';
?>
              <td>
              <?=$e->getMessage()?>
              </td>
<?php
        }
?>
            </tr>
<?php
    }

?>
        </table>
        </div>
<?php
    show_footer();
