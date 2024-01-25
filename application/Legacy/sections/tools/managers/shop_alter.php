<?php
if (!check_perms('admin_manage_shop')) { error(403); }

authorize();

if ($_POST['submit'] == 'Delete') {

    if (!is_integer_string($_POST['id']) || $_POST['id'] == '') { error(0); }
    $master->db->rawQuery('DELETE FROM bonus_shop_actions WHERE ID = ?', [$_POST['id']]);
      $master->cache->deleteValue('shop_items');
      $master->cache->deleteValue('shop_items_other');
      $master->cache->deleteValue('shop_items_ufl');
      $master->cache->deleteValue('shop_items_gifts');
      $master->cache->deleteValue('shop_item_'.$_POST['id']);

} elseif ($_POST['autosynch'] == 'autosynch') {

      // Auto update shop items with applicable badge items
      if ($_POST['delete']=='1') {
            $master->db->rawQuery("DELETE FROM bonus_shop_actions WHERE Action='badge'");
      }
      $Sort = (int) $_POST['sort'];

      $master->db->rawQuery("SET @sort = ? - 1", [$Sort]);
      $master->db->rawQuery(
          "INSERT INTO bonus_shop_actions (Title, Description, Action, Value, Cost, Sort)
                SELECT Title, Description, 'badge', ID, Cost, @sort := @sort + 1
                  FROM badges
                 WHERE Type = 'shop'
              ORDER BY Sort"
      );
      $master->cache->deleteValue('shop_items');
      $master->cache->deleteValue('shop_items_other');
      $master->cache->deleteValue('shop_items_ufl');
      $master->cache->deleteValue('shop_items_gifts');

} else {

    $Val->SetFields('name', '1', 'string', 'The name must be set, and has a max length of 64 characters', ['maxlength'=>64, 'minlength'=>1]);
    $Val->SetFields('desc', '1', 'string', 'The description must be set, and has a max length of 255 characters', ['maxlength'=>255, 'minlength'=>1]);
    $Val->SetFields('shopaction', '1', 'inarray', 'Invalid shop action was set.', ['inarray'=>$shopActions]);
    $Err=$Val->ValidateForm($_POST); // Validate the form
    if ($Err) { error($Err); }

      $Name = $_POST['name'];
      $Desc = $_POST['desc'];
      $Action = $_POST['shopaction'];
      $Value = (int) $_POST['value'];
      $Cost = (int) $_POST['cost'];
      $Sort = (int) $_POST['sort'];
      if ($_POST['gift']=='1' &&
         (($Action == 'givegb') ||
         ($Action == 'givecredits'))) {
          $Gift = 1;
      } else {
          $Gift = 0;
      }

    if ($_POST['submit'] == 'Edit') { //Edit
        if (!is_integer_string($_POST['id']) || $_POST['id'] == '') { error(0); }
        $master->db->rawQuery(
            "UPDATE bonus_shop_actions
                SET Title = ?,
                    Description = ?,
                    Action = ?,
                    Value = ?,
                    Cost = ?,
                    Sort = ?,
                    Gift = ?
              WHERE ID = ?",
            [$Name, $Desc, $Action, $Value, $Cost, $Sort, $Gift, $_POST['id']]
        );
           $master->cache->deleteValue('shop_item_'.$_POST['id']);
    } else { //Create
        $master->db->rawQuery(
            "INSERT INTO bonus_shop_actions (Title, Description, Action, Value, Cost, Sort, Gift)
                  VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$Name, $Desc, $Action, $Value, $Cost, $Sort, $Gift]
        );
    }
    $master->cache->deleteValue('shop_items');
    $master->cache->deleteValue('shop_items_other');
    $master->cache->deleteValue('shop_items_ufl');
}

// Go back
header('Location: tools.php?action=shop_list');
