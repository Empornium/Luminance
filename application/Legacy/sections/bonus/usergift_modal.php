<?php
    $ItemId=(int) $_REQUEST['itemid'];
    $Item = get_shop_item($ItemId);
    list($ItemID, $Title, $Description, $Action, $Value, $Cost) = $Item;
?>
<div id="modal_content">
    <div class="thin">
        <h2>User Gift</h2>
        <div class="box pad">
            <?php if (isset($_REQUEST['sendname'])): ?>
                <div>Are you sure you want to send <strong><?=$Title?></strong> to <strong><?=$_REQUEST['sendname']?></strong>?</div>
            <?php else: ?>
                <div>Who do you want to send <strong><?=$Title?></strong> to?</div>
            <?php endif ?>
            <form action="/bonus.php" id="sendgift_form" method="POST">
                <input type="hidden" name="action" value="buy" />
                <input type="hidden" name="itemid" value="<?=$ItemID?>" />
                <input type="hidden" name="userid" value="<?=$activeUser['ID']?>" />
                <input type="hidden" name="auth" value="<?=$activeUser['AuthKey']?>" />
                <input type="hidden" name="shopaction" value="<?=$_REQUEST['shopaction']?>" />
                <?php if (isset($_REQUEST['retu'])): ?>
                    <input type="hidden" name="retu" value="<?=$_REQUEST['retu']?>" />
                <?php endif ?>
                <?php if (isset($_REQUEST['sendname'])): ?>
                    <input type="hidden" class="othername" name="othername" value="<?=$_REQUEST['sendname']?>" />
                <?php else: ?>
                    <h3 style="padding-top: 10px;">Username</h3>
                    <input class="othername" name="othername" placeholder="Username" />
                <?php endif ?>
                <h3 style="padding-top: 10px;">Send a message (leave blank for no message)</h3>
                <textarea class="message" name="message" placeholder="Gift Message" style="width: 100%; resize: vertical; height: 4em;"></textarea>
                <h3 style="padding-top: 10px;">Gift Anonymously?</h3>
                <input class="anon_gift" name="anon_gift" type="checkbox" value="true" />
                <div class="clear"></div>
                <button  type="submit" value="Submit">Send</button>
            </form>
            <div class="clear"></div>
        </div>
    </div>
</div>
