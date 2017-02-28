<?php
enforce_login();

$eur_rate = get_current_btc_rate();

show_header('Donate','bitcoin');
?>
<!-- Donate -->
<div class="thin">
    <h2>Donate</h2>

    <div class="head">Thank-you for considering to make us a donation</div>
    <div class="box pad">
        <?php
        $Body = get_article('donateinline');
        if ($Body) {
            include(SERVER_ROOT.'/classes/class_text.php');
            $Text = new TEXT;
            echo $Text->full_format($Body , get_permissions_advtags($LoggedUser['ID']));
        }
        ?>
        <br/>
        <p style="font-size: 1.1em" title="rate is Mt.Gox weighted average: <?=$eur_rate?>">The current bitcoin exchange rate is 1 bitcoin = &euro;<?=number_format($eur_rate,2);?></p>

        <div style="text-align: center">
            <a style="font-weight: bold;font-size: 1.6em;" href="donate.php?action=my_donations&new=1"><span style="color:red;"> >> </span>click here to get a personal donation address<span style="color:red;"> << </span></a>
        </div>
    </div>

    <div class="head">Donate for <img src="<?= STATIC_SERVER ?>common/symbols/donor.png" alt="love" /></div>
    <div class="box pad">
        <p><span style="font-size:1.1em;font-weight: bold;">What you will receive for a suggested minimum &euro;5 donation (<?=number_format(5.0/$eur_rate,3)?> bitcoins) :</span> </p>
        <ul>
            <?php if ($LoggedUser['Donor']) { ?>
                <li>Even more love! (You will not get multiple hearts.)</li>
                <li>A warmer fuzzier feeling than before!</li>
            <?php } else { ?>
                <li>Our eternal love, as represented by the <img src="<?= STATIC_SERVER ?>common/symbols/donor.png" alt="Donor" /> you get next to your name.</li>
                <li>A warm fuzzy feeling.</li>
            <?php }

            $DB->query("SELECT Title, Description, Image, Cost FROM badges WHERE Type='Donor' ORDER BY Cost");
            if ($DB->record_count()>0) {
                ?>
                <li>In order to recognise large contributers we have the following donor medals</li>
                <?php
                while ( list($title, $desc, $image, $cost) = $DB->next_record()) {
                    ?>
                    <br/> &nbsp; &nbsp;<img style="vertical-align: middle;" src="<?= STATIC_SERVER ?>common/badges/<?=$image?>" alt="<?=$title?>" title="<?=$title?>" />  &nbsp; If you donate <span style="font-size: 1.3em;font-weight: bolder">&euro;<?=$cost?></span> you will get a <?=$title?>  <strong>(<?=number_format($cost/$eur_rate,3)?> bitcoins)</strong>
                    <br/>
                    <?php
                }
                ?>
                <br/>
                <?php
            }
            ?>
            <li><span  style="font-size: 1.2em;">If you want to donate for <img src="<?= STATIC_SERVER ?>common/symbols/donor.png" alt="love" title="love" />
                    <a style="font-weight: bold;" href="donate.php?action=my_donations&new=1"><span style="color:red;"> >> </span>click here to get a personal donation address<span style="color:red;"> << </span></a></span></li>
        </ul>
    </div>

    <div class="head">Donate for <strong>GB</strong></div>
    <div class="box pad">
        <p><span style="font-size:1.1em;font-weight: bold;">What you will receive for your donation:</span></p>
        <ul>
            <?php

            foreach ($DonateLevels as $level=>$rate) {
                ?>
                    <li>If you donate &euro;<?=$level?> you will get <?=number_format($level * $rate)?> GB removed from your <u>download</u>   <strong>(rate: <?=$rate?>gb per &euro;) &nbsp; ( <?=number_format($level/$eur_rate,6)?> bitcoins)</strong></li>

                <?php
            }

            ?><br/>
            <li><span style="font-size: 1.2em;">If you want to donate for GB
                    <a style="font-weight: bold;" href="donate.php?action=my_donations&new=1"><span style="color:red;"> >> </span>click here to get a personal donation address<span style="color:red;"> << </span></a></span></li>
        </ul>

    </div>

    <div class="head">What you will <strong>not</strong> receive</div>
    <div class="box pad">
        <ul>
            <li>Immunity from the rules.</li>
            <li>Additional <u>upload</u> credit.</li>
        </ul>
        <p>Please be aware that by making a donation you are not purchasing donor status or invites. You are helping us pay the bills and cover the costs of running the site. We are doing our best to give our love back to donors but sometimes it might take more than 48 hours. Feel free to contact us by sending us a <a href="staffpm.php?action=user_inbox">Staff Message</a> regarding any matter. We will answer as quickly as possible.</p>
    </div>
</div>
<!-- END Donate -->
<?php
show_footer();
