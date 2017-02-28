<?php

class text
{
    // tag=>max number of attributes 'link'=>1,
    private $ValidTags = array(
        'ratiolist'=>0,
        'codeblock' => 1,
        'you' => 0,
        'h5v' => 1,
        'yt' => 1,
        'vimeo' => 1,
        'video' => 1,
        'flash' => 1,
        'banner' => 0,
        'thumb' => 0,
        '#' => 1,
        'anchor' => 1,
        'mcom' => 0,
        'table' => 1,
        'th' => 1,
        'tr' => 1,
        'td' => 1,
        'bg' => 1,
        'cast' => 0,
        'details' => 0,
        'info' => 0,
        'plot' => 0,
        'screens' => 0,
        'br' => 0,
        'hr' => 0,
        'font' => 1,
        'center' => 0,
        'spoiler' => 1,
        'b' => 0,
        'u' => 0,
        'i' => 0,
        's' => 0,
        'sup' => 0,
        'sub' => 0,
        '*' => 0,
        'user' => 0,
        'n' => 0,
        'inlineurl' => 0,
        'inlinesize' => 1,
        'align' => 1,
        'color' => 1,
        'colour' => 1,
        'size' => 1,
        'url' => 1,
        'img' => 1,
        'quote' => 1,
        'pre' => 1,
        'code' => 1,
        'tex' => 0,
        'hide' => 1,
        'plain' => 0,
        'important' => 0,
        'torrent' => 0,
        'request' => 0,
        'rank' => 1,
        'tip' => 1,
        'imgnm' => 1,
        'imgalt' => 1,
        'article' =>1
    );

    private $AdvancedTagOnly = array(
        'mcom' => 1
    );

    private $Smileys = array(
        ':smile1:' => 'smile1.gif',
        ':smile2:' => 'smile2.gif',
        ':D' => 'biggrin.gif',
        ':grin:' => 'grin.gif',
        ':laugh:' => 'laugh.gif',
        ':w00t:' => 'w00t.gif',
        ':tongue:' => 'tongue.gif',
        ':wink:' => 'wink.gif',
        ':noexpression:' => 'noexpression.gif',
        ':confused:' => 'confused.gif',
        ':sad:' => 'sad.gif',
        ':cry:' => 'cry.gif',
        ':weep:' => 'weep.gif',
        ':voodoo:' => 'voodoo.gif',
        ':yaydance:' => 'yaydance.gif',
        ':lol:' => 'lol.gif',
        ':gjob:' => 'thumbup.gif',
        ':mad:' => 'mad2.gif',
        ':banghead:' => 'banghead.gif',
        ':gunshot:' => 'gunshot.gif',
        ':no2:' => 'no2.gif',
        ':yes2:' => 'yes2.gif',
        ':wanker:' => 'wanker.gif',
        ':sorry:' => 'sorry.gif',
        //===========================================

        ':borg:' => 'borg.gif',
        ':nasher:' => 'gnasher.gif',
        ':panic:' => 'panic.gif',
        ':worm:' => 'worm2.gif',
        ':evilbunny:' => 'evilimu.gif',
        //=============================================

        ':ohmy:' => 'ohmy.gif',
        ':sleeping:' => 'sleeping.gif',
        ':innocent:' => 'innocent.gif',
        ':whistle:' => 'whistle.gif',
        ':unsure:' => 'unsure.gif',
        ':closedeyes:' => 'closedeyes.gif',
        ':cool:' => 'cool.gif',
        ':cool1:' => 'cool2.gif',
        ':cool2:' => 'cool1.gif',
        ':fun:' => 'fun.gif',
        ':thumbsup:' => 'thumbsup.gif',
        ':thumbsdown:' => 'thumbsdown.gif',
        ':blush:' => 'blush.gif',
        ':yes:' => 'yes.gif',
        ':no:' => 'no.gif',
        ':love:' => 'love.gif',
        ':question:' => 'question.gif',
        ':excl:' => 'excl.gif',
        ':idea:' => 'idea.gif',
        ':arrow:' => 'arrow.gif',
        ':arrow2:' => 'arrow2.gif',
        ':hmm:' => 'hmm.gif',
        ':hmmm:' => 'hmmm.gif',
        ':tiphat:' => 'tiphat.gif',
        ':huh:' => 'huh.gif',
        ':dunno:' => 'dunno.gif',
        ':geek:' => 'geek.gif',
        ':look:' => 'look.gif',
        ':rolleyes:' => 'rolleyes.gif',
        ':punk:' => 'punk.gif',
        ':shifty:' => 'shifty.gif',
        ':blink:' => 'blink.gif',
        ':smart:' => 'smartass.gif',
        ':sick:' => 'sick.gif',
        ':crazy:' => 'crazy.gif',
        ':wacko:' => 'wacko.gif',
        ':wave:' => 'wave.gif',
        ':wavecry:' => 'wavecry.gif',
        ':baby:' => 'baby.gif',
        ':angry:' => 'angry.gif',
        ':ras:' => 'ras.gif',
        ':sly:' => 'sly.gif',
        ':devil:' => 'devil.gif',
        ':evil:' => 'evil.gif',
        ':evilmad:' => 'evilmad.gif',
        ':sneaky:' => 'sneaky.gif',
        ':icecream:' => 'icecream.gif',
        ':hooray:' => 'hooray.gif',
        ':slap:' => 'slap.gif',
        ':sigh:' => 'facepalm.gif',
        ':wall:' => 'wall.gif',
        ':yucky:' => 'yucky.gif',
        ':nugget:' => 'nugget.gif',
        ':smartass:' => 'smart.gif',
        ':shutup:' => 'shutup.gif',
        ':shutup2:' => 'shutup2.gif',
        ':weirdo:' => 'weirdo.gif',
        ':yawn:' => 'yawn.gif',
        ':snap:' => 'snap.gif',

        ':cupcake:' => 'cupcake.gif',
        ':emplove:' => 'loveemp.gif',

        ':strongbench:' => 'strongbench.gif',
        ':weakbench:' => 'weakbench.gif',
        ':dumbells:' => 'dumbells.gif',
        ':music:' => 'music.gif',
        ':guns:' => 'guns.gif',
        ':clap2:' => 'clap2.gif',
        ':kiss:' => 'kiss.gif',
        ':clown:' => 'clown.gif',
        ':cake:' => 'cake.gif',
        ':alien:' => 'alien.gif',
        ':wizard:' => 'wizard.gif',
        ':beer:' => 'beer.gif',
        ':beer2:' => 'beer2.gif',
        ':drunken:' => 'drunk.gif',
        ':rant:' => 'rant.gif',
        ':tease:' => 'tease.gif',
        ':daisy:' => 'daisy.gif',
        ':demon:' => 'demon.gif',
        ':fdevil:' => 'flamingdevil.gif',
        ':flipa:' => 'flipa.gif',
        ':flirty:' => 'flirtysmile1.gif',
        ':lollol:' => 'lolalot.gif',
        ':lovelove:' => 'lovelove.gif',
        ':ninja1:' => 'ninja1.gif',
        ':nom:' => 'nom.gif',
        ':samurai:' => 'samurai.gif',
        ':sasmokin:' => 'sasmokin.gif',
        ':smallprint:' => 'deal.gif',
        //----------------------------

        ':tick:' => 'tick.gif',
        ':cross:' => 'cross.gif',

        ':happydancing:' => 'happydancing.gif',
        ':argh:' => 'frustrated.gif',
        ':tumble:' => 'tumbleweed.gif',
        ':popcorn:' => 'popcorn.gif',
        ':fishing:' => 'fishing.gif',
        ':clover:' => 'clover.gif',
        ':shit:' => 'shit.gif',
        ':whip:' => 'whip.gif',
        ':judge:' => 'judge.gif',
        ':chair:' => 'chair.gif',
        ':pythfoot:' => 'pythfoot.gif',

        ':flowers:' => 'flowers.gif',
        ':wub:' => 'wub.gif',
        ':lovers:' => 'lovers.gif',
        ':kissing:' => 'kissing.gif',
        ':kissing2:' => 'kissing2.gif',
        ':console:' => 'console.gif',
        ':group:' => 'group.gif',
        ':hump:' => 'hump.gif',
        ':happy2:' => 'happy2.gif',
        ':clap:' => 'clap.gif',
        ':crockett:' => 'crockett.gif',
        ':zorro:' => 'zorro.gif',
        ':bow:' => 'bow.gif',
        ':dawgie:' => 'dawgie.gif',
        ':cylon:' => 'cylon.gif',
        ':book:' => 'book.gif',
        ':fish:' => 'fish.gif',
        ':mama:' => 'mama.gif',
        ':pepsi:' => 'pepsi.gif',
        ':medieval:' => 'medieval.gif',
        ':rambo:' => 'rambo.gif',
        ':ninja:' => 'ninja.gif',
        ':party:' => 'party.gif',
        ':snorkle:' => 'snorkle.gif',
        ':king:' => 'king.gif',
        ':chef:' => 'chef.gif',
        ':mario:' => 'mario.gif',
        ':fez:' => 'fez.gif',
        ':cap:' => 'cap.gif',
        ':cowboy:' => 'cowboy.gif',
        ':gunslinger:' => 'cowboygun.gif',
        ':pirate:' => 'pirate.gif',
        ':pirate2:' => 'pirate2.gif',
        ':piratehook:' => 'piratehook.gif',
        ':piratewhistle:' => 'pirate_whistle.gif',
        ':punk:' => 'punk.gif',
        ':rock:' => 'rock.gif',
        ':rocker:' => 'rock-smiley.gif',
        ':cigar:' => 'cigar.gif',
        ':oldtimer:' => 'oldtimer.gif',
        ':trampoline:' => 'trampoline.gif',
        ':bananadance:' => 'bananadance.gif',
        ':smurf:' => 'smurf.gif',
        ':yikes:' => 'yikes.gif',

        ':box:' => 'box.gif',
        ':boxing:' => 'boxing.gif',
        ':shoot:' => 'shoot.gif',
        ':shoot2:' => 'shoot2.gif',

        ':jail:' => 'jail.gif',
        ':jailmad:' => 'jail_mad.gif',

        //----------------------------
        //----------------------------
        ':sing:' => 'singing.gif',
        ':sing1:' => 'smiley_singing.gif',
        ':sing2:' => 'blobsing.gif',
        ':singrain:' => 'singrain.gif',
        ':choir:' => 'choir.gif',
        ':alarmed:' => 'alarmed-smiley.gif',
        ':amen:' => 'amen.gif',
        ':applause:' => 'appl.gif',
        ':argue:' => 'argue.gif',
        ':asshole:' => 'asshole.gif',
        ':backingout:' => 'backingout.gif',
        ':badday:' => 'badday.gif',
        ':bann:' => 'bann.gif',
        ':banstamp:' => 'banned.gif',
        ':blahblah:' => 'blahblah.gif',
        ':coffee:' => 'coffee1.gif',
        ':coffee1:' => 'coffee3.gif',
        ':coffee2:' => 'coffee4.gif',
        ':coffee3:' => 'coffee5.gif',
        ':coffee4:' => 'bulbar-smiley.gif',
        ':bum:' => 'bum.gif',
        ':bk:' => 'burgerking.gif',
        ':carryon:' => 'carryon.gif',
        ':chat:' => 'chatsmileys.gif',
        ':delete:' => 'delete1-smiley.gif',
        ':drooling:' => 'drooling_smiley.gif',
        ':drunk:' => 'drunk2.gif',
        ':rain:' => 'rain.gif',
        ':cheer:' => 'cheerlead.gif',
        ':broccolidance:' => 'broccolidance.gif',
        ':bonerdance:' => 'bonerdance.gif',
        ':dupe:' => 'dupe.gif',
        ':fly:' => 'fly.gif',
        ':goldstars:' => 'goldstars.gif',
        ':hangin:' => 'hangin.gif',
        ':hat:' => 'hat.gif',
        ':help:' => 'help.gif',
        ':high5:' => 'high5.gif',
        ':hungry:' => 'hungry.gif',
        ':paper:' => 'icon_paper.gif',
        ':ideabulb:' => 'ideabulb.gif',
        ':ignore:' => 'ignore.gif',
        ':impatient:' => 'impatient.gif',
        ':irule:' => 'irule.gif',
        ':kickme:' => 'kickme.gif',
        ':kissass:' => 'kissass.gif',
        ':lgh:' => 'lgh.gif',
        ':lurking:' => 'Lurking.gif',
        ':paranoia:' => 'paranoia.gif',
        ':paranoid:' => 'paranoid.gif',
        ':pillowfight:' => 'pillowfight.gif',
        ':pplease:' => 'pplease.gif',
        ':pray:' => 'pray.gif',
        ':preach:' => 'preach.gif',
        ':protected:' => 'protected.gif',
        ':rantpin:' => 'rant_pin.gif',
        ':reap:' => 'reaper2.gif',
        ':run:' => 'run.gif',
        ':shower:' => 'Shower.gif',
        ':soapboxrant:' => 'soapboxrant.gif',
        ':preacher:' => 'preacher.gif',
        ':talkhand:' => 'talkhand.gif',
        ':pizza:' => 'th_smiley_pizza.gif',
        ':therethere:' => 'therethere.gif',
        ':topicclosed:' => 'topicclosed.gif',
        ':viking:' => 'viking.gif',
        ':whatdidimiss:' => 'whatdidimiss.gif',
        ':ding:' => 'winnersmiley.gif',
        ':ridicule:' => 'ridicule.gif',
        //----------------------------

        ':vader-smiley:' => 'vader-smiley.gif',
        ':lsvader:' => 'lsvader.gif',
        ':emperor:' => 'emperor.gif',
        //----------------------------

        ':punish:' => 'punish.gif',
        ':puppykisses:' => 'puppykisses.gif',
        ':allbetter:' => 'allbetter.gif',
        ':bitchfight:' => 'bitchfight.gif',
        ':buddies:' => 'buddies.gif',
        ':chase:' => 'chase.gif',
        ':bugchases:' => 'bugchases.gif',
        ':hello:' => 'hellopink.gif',
        //----------------------------

        ':bombie:' => 'bomb_ie.gif',
        ':alcapone:' => 'alcapone.gif',
        ':aliendance:' => 'Aliendance.gif',
        ':badboy:' => 'badboy.gif',
        ':bash:' => 'bash.gif',
        ':bashfutile:' => 'bashfutile.gif',
        ':bath:' => 'bath.gif',
        ':bolt:' => 'Bolt.gif',
        ':boxerko:' => 'boxerknockedout.gif',
        ':bunnysmiley:' => 'bunny-smiley.gif',
        ':buttshake:' => 'buttshake.gif',
        ':buttlicker:' => 'sfun_butt-biting.gif',
        ':cavitysearch:' => 'cavitysearch.gif',
        ':cheerleader:' => 'cheerleader.gif',
        ':cigarpuff:' => 'cigar-smiley.gif',
        ':climbing:' => 'climbing-smiley.gif',
        ':clownhat:' => 'clown-smiley.gif',
        ':computerpunch:' => 'computer_punch_smiley.gif',
        ':dancelessons:' => 'Dance_lessons.gif',
        ':dancing:' => 'dancing-smiley.gif',
        ':diggin:' => 'digg-in-smiley.gif',
        ':drool:' => 'drool.gif',
        ':egyptdance:' => 'egyptdance.gif',
        ':usb:' => 'fairy-smiley.gif',
        ':fiesta:' => 'fiesta-smiley.gif',
        ':flirty2:' => 'flirty2-smiley.gif',
        ':flirty3:' => 'flirty3-smiley.gif',
        ':flirty4:' => 'flirty4-smiley.gif',
        ':flower1:' => 'flower1-smiley.gif',
        ':flower2:' => 'flower2-smiley.gif',
        ':flower3:' => 'flower3-smiley.gif',
        ':flowerrow:' => 'flowersrow.gif',
        ':swede:' => 'free_swe34.gif',
        ':freezin:' => 'freez-in1-smiley.gif',
        ':ghost:' => 'ghost.gif',
        ':ghostie:' => 'ghostie.gif',
        ':giraffe:' => 'giraffe-smiley.gif',
        ':giveup:' => 'giveup.gif',
        ':goldmedal:' => 'gold-medalist-smiley.gif',
        ':star:' => 'goldstar.gif',
        ':goldstar:' => 'gold-star-smiley.gif',
        ':greenchilli:' => 'greenchilli.gif',
        ':tinfoil:' => 'hat-tin-foil-smiley.gif',
        ':tinfoilhat:' => 'tinfoilhatsmile.gif',
        ':hooked:' => 'hooked.gif',
        ':horsie:' => 'horsiecharge.gif',
        ':chainsaw:' => 'icon_chainsaw.gif',
        ':inlove:' => 'inlove.gif',
        ':jeeves:' => 'jeeves-smiley.gif',
        ':mrdick:' => 'Mrdick.gif',
        ':sofanap:' => 'napsmileyff.gif',
        ':saw:' => 'newsaw.gif',
        ':chairspin:' => 'officechair.gif',
        ':offtobed:' => 'off-to-bed-smiley.gif',
        ':otpatrol:' => 'offtopic8mg.gif',
        ':pickme:' => 'pickme.gif',
        ':poker:' => 'playing poker.gif',
        ':pray:' => 'pray.gif',
        ':punchballs:' => 'punchballs.gif',
        ':puzzled:' => 'puzzled.gif',
        ':ranting:' => 'ranting.gif',
        ':reaper:' => 'reaper.gif',
        ':redchilli:' => 'redchilli.gif',
        ':redx:' => 'redx-smiley.gif',
        ':robot:' => 'robot.gif',
        ':rockingchair:' => 'rocking-chair2-smiley.gif',
        ':roman:' => 'roman-smiley.gif',
        ':rtfm:' => 'rtfm.gif',
        ':running:' => 'running-smiley.gif',
        ':goodevil:' => 'samvete-smiley.gif',
        ':savage:' => 'savage1-smiley.gif',
        ':scorer:' => 'scorer-smiley.gif',
        ':smoking:' => 'smoking-smiley.gif',
        ':snapoutofit:' => 'snap-out-of-it-smiley.gif',
        ':sneaking:' => 'sneaking-smiley.gif',
        ':snooty:' => 'snooty.gif',
        ':snow:' => 'snow.gif',
        ':soapbox:' => 'soapbox.gif',
        ':starescreen:' => 'stare-screen-smiley.gif',
        ':stir:' => 'stir.gif',
        ':surrender:' => 'surrender.gif',
        ':swear1:' => 'swear2-smiley.gif',
        ':swear2:' => 'swear1-smiley.gif',
        ':crazyjacket:' => 'th_crazy2.gif',
        ':twocents:' => 'twocents.gif',
        ':underweather:' => 'underweather.gif',
        ':zzz:' => 'zzz.gif',
        //----------------------------

        ':santa:' => 'santa.gif',
        ':indian:' => 'indian.gif',
        ':pimp:' => 'pimp.gif',
        ':nuke:' => 'nuke.gif',
        ':jacko:' => 'jacko.gif',
        ':greedy:' => 'greedy.gif',
        ':super:' => 'super.gif',
        ':wolverine:' => 'wolverine.gif',
        ':spidey:' => 'spidey.gif',
        ':spider:' => 'spider.gif',
        ':bandana:' => 'bandana.gif',
        ':construction:' => 'construction.gif',
        ':sheep:' => 'sheep.gif',
        ':police:' => 'police.gif',
        ':detective:' => 'detective.gif',
        ':bike:' => 'bike.gif',
        ':badass:' => 'badass.gif',
        ':blind:' => 'blind.gif',

        ':picard:' => 'picard.png',
        ':why:' => 'why.gif',
        ':blah:' => 'blah.gif',
        ':boner:' => 'boner.gif',
        ':goodjob:' => 'gjob.gif',
        ':dist:' => 'dist.gif',
        ':urock:' => 'urock.gif',
        ':megarant:' => 'megarant.gif',
        ':adminpower:' => 'hitler_admin.gif',
        ':wtfsign:' => 'wtfsign.gif',
        ':stupid:' => 'stupid.gif',
        ':dots:' => 'dots.gif',
        ':offtopic:' => 'offtopic.gif',
        ':spam:' => 'spam.gif',
        ':oops:' => 'oops.gif',
        ':lttd:' => 'lttd.gif',
        ':please:' => 'please.gif',
        ':imsorry:' => 'imsorry.gif',
        ':hi:' => 'hi.gif',
        ':liessign:' => 'liessign.gif',
        ':goodnight:' => 'goodnightsign.gif',
        ':respect:' => 'sign_respect1.gif',
        ':welcome3:' => 'welcome3.gif',
        ':banned:' => 'banned1-smiley.gif',
        ':hamster:' => 'hamster.gif',
        ':elder:' => 'elder-smiley.gif',
        ':emu:' => 'Emu.gif',
        ':evilgenius:' => 'evil-genius-smiley.gif',
        ':lesson:' => 'explanation-smiley.gif',
        //=======================================================================

        ':lmao:' => 'lmao.gif',
        ':rules:' => 'rules.gif',
        ':tobi:' => 'tobi.gif',
        ':jump:' => 'jump.gif',
        ':yay:' => 'yay.gif',
        ':hbd:' => 'hbd.gif',
        ':band:' => 'band.gif',
        ':rofl:' => 'rofl.gif',
        ':bounce:' => 'bounce.gif',
        ':mbounce:' => 'mbounce.gif',
        ':thankyou:' => 'thankyou.gif',
        ':gathering:' => 'gathering.gif',
        ':colors:' => 'colors.gif',
        ':oddoneout:' => 'oddoneout.gif',
        ':ufo:' => 'ufo-smiley.gif',
        ':tank:' => 'tank.gif',
        ':guillotine:' => 'guillotine.gif',
        ':yesno:' => 'yesno.gif',
        ':erection:' => 'erection1.gif',
        ':fucku:' => 'fucku.gif',
        ':spamhammer:' => 'spamhammer.gif',
        ':atomic:' => 'atomic.gif',
        ':ttiwwp:' => 'ttiwwp.gif',
        ':fireworks:' => 'fireworks.gif',
        ':genie:' => 'genie.gif',
        ':volleygoal:' => 'volleygoal.gif',
        ':brick:' => 'brick.gif',
        ':banaalien:' => 'banaAlien.gif',
        ':banababydance:' => 'banaBabydance.gif',
        ':banabye:' => 'banaBye.gif',
        ':banacheer:' => 'banaCheer.gif',
        ':banacomputer:' => 'banaComputer.gif',
        ':banadoggy:' => 'banaDoggy.gif',
        ':banadressedup:' => 'banaDressedup.gif',
        ':banadrinking:' => 'banaDrinking.gif',
        ':banaelvis:' => 'banaElvis.gif',
        ':banaevildance:' => 'banaEvildance.gif',
        ':banaexercise:' => 'banaExercise.gif',
        ':banagunman:' => 'banaGunman.gif',
        ':banainvisible:' => 'banaInvisible.gif',
        ':banarasta:' => 'banaRasta.gif',
        ':banaskiing:' => 'banaSkiing.gif',
        ':banawhip:' => 'banaWhip.gif',
    );
    //  font name (display) => fallback fonts (css)
    private $Fonts = array(
        'Arial' => "Arial, 'Helvetica Neue', Helvetica, sans-serif;",
        'Arial Black' => "'Arial Black', 'Arial Bold', Gadget, sans-serif;",
        'Comic Sans MS' => "'Comic Sans MS', cursive, sans-serif;",
        'Courier New' => "'Courier New', Courier, 'Lucida Sans Typewriter', 'Lucida Typewriter', monospace;",
        'Franklin Gothic Medium' => "'Franklin Gothic Medium', 'Franklin Gothic', 'ITC Franklin Gothic', Arial, sans-serif;",
        'Georgia' => "Georgia, Times, 'Times New Roman', serif;",
        'Helvetica' => "'Helvetica Neue', Helvetica, Arial, sans-serif;",
        'Impact' => "Impact, Haettenschweiler, 'Franklin Gothic Bold', Charcoal, 'Helvetica Inserat', 'Bitstream Vera Sans Bold', 'Arial Black', sans-serif;",
        'Lucida Console' => "'Lucida Console', Monaco, monospace;",
        'Lucida Sans Unicode' => "'Lucida Sans Unicode', 'Lucida Grande', 'Lucida Sans', Geneva, Verdana, sans-serif;",
        'Microsoft Sans Serif' => "'Microsoft Sans Serif', Helvetica, sans-serif;",
        'Palatino Linotype' => "Palatino, 'Palatino Linotype', 'Palatino LT STD', 'Book Antiqua', Georgia, serif;",
        'Tahoma' => "Tahoma, Verdana, Segoe, sans-serif;",
        'Times New Roman' => "TimesNewRoman, 'Times New Roman', Times, Baskerville, Georgia, serif;",
        'Trebuchet MS' => "'Trebuchet MS', 'Lucida Grande', 'Lucida Sans Unicode', 'Lucida Sans', Tahoma, sans-serif;",
        'Verdana' => "Verdana, Geneva, sans-serif;"
    );
    //  icon tag => img  //[cast][details][info][plot][screens]
    private $Icons = array(
        'cast' => "cast11.png",
        'details' => "details11.png",
        'info' => "info11.png",
        'plot' => "plot11.png",
        'screens' => "screens11.png"
    );
    private $NoMedia = 0; // If media elements should be turned into URLs
    private $Levels = 0; // nesting level
    private $Advanced = false; // allow advanced tags to be printed
    private $ShowErrors = false;
    private $Errors = array();

    //public $Smilies = array(); // for testing only
    public function __construct()
    {
        foreach ($this->Smileys as $Key => $Val) {
            $this->Smileys[$Key] = '<img src="' . STATIC_SERVER . 'common/smileys/' . $Val . '" alt="' . $Key . '" />';
            //$this->Smilies[] = $Val;
        }
        reset($this->Smileys);

        foreach ($this->Icons as $Key => $Val) {
            $this->Icons[$Key] = '<img src="' . STATIC_SERVER . 'common/icons/' . $Val . '" alt="' . $Key . '" />';
        }
        reset($this->Icons);
    }

    public function has_errors()
    {
        return count($this->Errors) > 0;
    }

    public function get_errors()
    {
        return $this->Errors;
    }

    public function full_format($Str, $AdvancedTags = false, $ShowErrors = false)
    {
        $this->Advanced = $AdvancedTags;
        $this->ShowErrors = $ShowErrors;
        $this->Errors = array();

        $Str = display_str($Str);

        $Str = str_replace('  ', '&nbsp;&nbsp;', $Str);
        //Inline links
        $Str = preg_replace('/\[bg\=/i', '[bgg=', $Str);
        $Str = preg_replace('/\[td\=/i', '[tdd=', $Str);
        $Str = preg_replace('/\[tr\=/i', '[trr=', $Str);
        $Str = preg_replace('/\[th\=/i', '[thh=', $Str);

        $URLPrefix = '(\[url\]|\[url\=|\[vide|\[vime|\[img\=|\[img\]|\[thum|\[bann|\[h5v\]|\[h5v\=|\[bgg\=|\[tdd\=|\[trr\=|\[tabl|\[imgn|\[imga)';  // |\[h5v\]   |\[h5v\=        //$URLPrefix = '(\[url\]|\[url\=|\[vid\=|\[img\=|\[img\]|\[thu\]|\[ban\]|\[h5v\]|\[h5v\=|\[bgg\=|\[tabl)';  // |\[h5v\]   |\[h5v\=
        $Str = preg_replace('/' . $URLPrefix . '\s+/i', '$1', $Str);
        $Str = preg_replace('/(?<!' . $URLPrefix . ')http(s)?:\/\//i', '$1[inlineurl]http$2://', $Str);
        // For anonym.to and archive.org links, remove any [inlineurl] in the middle of the link
        $callback = create_function('$matches', 'return str_replace("[inlineurl]","",$matches[0]);');
        $Str = preg_replace_callback('/(?<=\[inlineurl\]|' . $URLPrefix . ')(\S*\[inlineurl\]\S*)/m', $callback, $Str);

        $Str = preg_replace('/\=\=\=\=([^=].*)\=\=\=\=/i', '[inlinesize=3]$1[/inlinesize]', $Str);
        $Str = preg_replace('/\=\=\=([^=].*)\=\=\=/i', '[inlinesize=5]$1[/inlinesize]', $Str);
        $Str = preg_replace('/\=\=([^=].*)\=\=/i', '[inlinesize=7]$1[/inlinesize]', $Str);

        $Str = preg_replace('/\[bgg\=/i', '[bg=', $Str);
        $Str = preg_replace('/\[tdd\=/i', '[td=', $Str);
        $Str = preg_replace('/\[trr\=/i', '[tr=', $Str);
        $Str = preg_replace('/\[thh\=/i', '[th=', $Str);

        $Str = $this->parse($Str);

        $HTML = $this->to_html($Str);

        $HTML = nl2br($HTML);

        return $HTML;
    }

    /**
     * Validates the bbcode for bad tags (uncloesd/mixed tags)
     *
     * @param  string  $Str          The text to be validated
     * @param  boolean $AdvancedTags Whether AdvancedTags are allowed (this is only for the preview if errorout=true)
     * @param  boolean $ErrorOut     If $ErrorOut=true then on errors the error page will be displayed with a preview of the errors (If false the function just returns the validate result)
     * @return boolean True if there are no bad tags and false otherwise
     */
    public function validate_bbcode($Str, $AdvancedTags = false, $ErrorOut = true)
    {
        $preview = $this->full_format($Str, $AdvancedTags, true, true);
        if ($this->has_errors()) {
            if ($ErrorOut) {
                $bbErrors = implode('<br/>', $this->get_errors());
                error("There are errors in your bbcode <br/><br/>$bbErrors<br/>If the tag(s) highlighted do actually have a closing tag then you probably have overlapping tags
                        <br/>ie.<br/><span style=\"font-weight:bold\">[b]</span> [i] your text <span style=\"font-weight:bold\">[/b] </span>[/i] <span style=\"color:red\">(wrong)</span> - <em>tags must be nested, when they overlap like this it throws an error</em>
                        <br/><span style=\"font-weight:bold\">[b]</span> [i] your text [/i] <span style=\"font-weight:bold\">[/b]</span> <span style=\"color:green\">(correct)</span> - <em>properly nested tags</em></div><br/><div class=\"head\">Your post</div><div class=\"box pad\">
                        <div class=\"box\"><div class=\"post_content\">$preview</div></div><br/>
                        <div style=\"font-style:italic;text-align:center;cursor:pointer;\"><a onclick=\"window.history.go(-1);\">click here or use the back button in your browser to return to your message</a></div>");
            }

            return false;
        }

        return true;
    }

    /**
     * Validates the bbcode for bad image URLs
     *
     * @param  string  $imageurl          The text to be validated
     * @return boolean True if there are no bad tags and false otherwise
     */
    public function validate_imageurl($imageurl)
    {
        if (check_perms('site_skip_imgwhite')) return true;
        $whitelist_regex = get_whitelist_regex();
        $result = validate_imageurl($imageurl, 10, 255, $whitelist_regex, '');
        if ($result !== TRUE) $result=FALSE;
	return $result;
    }

    public function proxify_url($url) {
        global $master;

        if ($master->settings->site->image_proxy_url && !$this->validate_imageurl($url)) {
            $host = strtolower(parse_url($url, PHP_URL_HOST));
            $sha256 = hash('sha256', $url, true);
            $hash_str = strtr(base64_encode($sha256), '+/', '-_');
            $parts = [
                $master->settings->site->image_proxy_url,
                'u',
                $host,
                substr($hash_str, 0, 2),
                substr($hash_str, 2, 8)."?url=".urlencode($url)
            ];
            $target_url = implode('/', $parts);
        } else {
            $target_url = $url;
        }
        return $target_url;
    }

    public function prepare_url($url, $always_full = false) {
        global $master;

        $regex = $master->settings->main->internal_urls_regex;
        if (preg_match($regex, $url)) {
            $prepared_url = '';
            if ($always_full) {
                $prepared_url .= ($master->request->ssl) ? 'https://' : 'http://';
                $prepared_url .= $master->server['HTTP_HOST'];
            }
            $prepared_url .= preg_replace('#^(://|[^/])+#', '', $url);
            if (!strlen($prepared_url)) {
                $prepared_url = '/';
            }
        } else {
            $prepared_url = $url;
        }
        return $prepared_url;
    }

    public function strip_bbcode($Str)
    {
        $Str = display_str($Str);

        //Inline links
        $Str = preg_replace('/(?<!(\[url\]|\[url\=|\[img\=|\[img\]))http(s)?:\/\//i', '$1[inlineurl]http$2://', $Str);
        $Str = $this->parse($Str);
        $Str = $this->raw_text($Str);

        $Str = nl2br($Str);

        return $Str;
    }

    // how much readable text is in string
    public function text_count($Str)
    {
        //remove tags
        $Str = $this->db_clean_search($Str);
        //remove endofline
        $Str = str_replace(array("\r\n", "\n", "\r"), '', $Str);
        $Str = trim($Str);

        return mb_strlen($Str);
    }

    // I took a shortcut here and made this function instead of using strip_bbcode since it's purpose is a bit
    // different.
    public function db_clean_search($Str)
    {
        foreach ($this->Smileys as $key => $value) {
            $remove[] = "/$key/i";
        }

        // anchors
        $remove[] = '/\[\#.*?\]/i';
        $remove[] = '/\[\/\#\]/i';
        $remove[] = '/\[anchor.*?\]/i';
        $remove[] = '/\[\/anchor\]/i';

        $remove[] = '/\[align.*?\]/i';
        $remove[] = '/\[\/align\]/i';

        $remove[] = '/\[article.*?\]/i';
        $remove[] = '/\[\/article\]/i';

        $remove[] = '/\[audio\].*?\[\/audio\]/i';

        $remove[] = '/\[b\]/i';
        $remove[] = '/\[\/b\]/i';

        $remove[] = '/\[banner\].*?\[\/banner\]/i';

        $remove[] = '/\[bg.*?\]/i';
        $remove[] = '/\[\/bg\]/i';

        $remove[] = '/\[br\]/i';

        $remove[] = '/\[cast\]/i';

        $remove[] = '/\[center.*?\]/i';
        $remove[] = '/\[\/center\]/i';

        $remove[] = '/\[codeblock.*?\]/i';
        $remove[] = '/\[\/codeblock\]/i';

        $remove[] = '/\[code.*?\]/i';
        $remove[] = '/\[\/code\]/i';

        $remove[] = '/\[color.*?\]/i';
        $remove[] = '/\[\/color\]/i';

        $remove[] = '/\[colour.*?\]/i';
        $remove[] = '/\[\/colour\]/i';

        $remove[] = '/\[details\]/i';

        $remove[] = '/\[flash.*?\].*?\[\/flash\]/i';

        $remove[] = '/\[font.*?\]/i';
        $remove[] = '/\[\/font\]/i';

        $remove[] = '/\[link.*?\]/i';
        $remove[] = '/\[\/link\]/i';

        $remove[] = '/\[h5v.*?\].*?\[\/h5v\]/i';

        $remove[] = '/\[hide\]/i';
        $remove[] = '/\[\/hide\]/i';

        $remove[] = '/\[hr\]/i';

        $remove[] = '/\[i\]/i';
        $remove[] = '/\[\/i\]/i';

        $remove[] = '/\[img.*?\].*?\[\/img\]/i';
        $remove[] = '/\[imgalt.*?\].*?\[\/imgalt\]/i';
        $remove[] = '/\[imgnm.*?\].*?\[\/imgnm\]/i';

        $remove[] = '/\[important\]/i';
        $remove[] = '/\[\/important\]/i';

        $remove[] = '/\[info\]/i';

        $remove[] = '/\[list\]/i';
        $remove[] = '/\[\/list\]/i';

        $remove[] = '/\[mcom\]/i';
        $remove[] = '/\[\/mcom\]/i';

        $remove[] = '/\[media.*?\].*?\[\/media\]/i';

        $remove[] = '/\[plain\]/i';
        $remove[] = '/\[\/plain\]/i';

        $remove[] = '/\[plot\]/i';

        $remove[] = '/\[pre\]/i';
        $remove[] = '/\[\/pre\]/i';

        $remove[] = '/\[quote\]/i';
        $remove[] = '/\[\/quote\]/i';

        $remove[] = '/\[rank.*?\]/i';
        $remove[] = '/\[\/rank\]/i';

        $remove[] = '/\[s\]/i';
        $remove[] = '/\[\/s\]/i';

        $remove[] = '/\[size.*?\]/i';
        $remove[] = '/\[\/size\]/i';

        $remove[] = '/\[spoiler\]/i';
        $remove[] = '/\[\/spoiler\]/i';

        // Table elements
        $remove[] = '/\[table.*?\]/i';
        $remove[] = '/\[\/table\]/i';
        $remove[] = '/\[tr.*?\]/i';
        $remove[] = '/\[\/tr\]/i';
        $remove[] = '/\[th.*?\]/i';
        $remove[] = '/\[\/th\]/i';
        $remove[] = '/\[td.*?\]/i';
        $remove[] = '/\[\/td\]/i';

        $remove[] = '/\[tex\].*?\[\/tex\]/i';

        $remove[] = '/\[tip.*?\]/i';
        $remove[] = '/\[\/tip\]/i';

        $remove[] = '/\[thumb\].*?\[\/thumb\]/i';

        $remove[] = '/\[torrent\].*?\[\/torrent\]/i';
        $remove[] = '/\[request\].*?\[\/request\]/i';

        $remove[] = '/\[u\]/i';
        $remove[] = '/\[\/u\]/i';

        $remove[] = '/\[url.*?\].*?\[\/url\]/i';

        $remove[] = '/\[user\]/i';
        $remove[] = '/\[\/user\]/i';

        $remove[] = '/\[video.*?\].*?\[\/video.*?\]/i';

        $remove[] = '/\[you\]/i';

        $remove[] = '/\[yt.*?\]/i';
        $remove[] = '/\[vimeo.*?\]/i';

        $Str = preg_replace($remove, '', $Str);
        $Str = preg_replace('/[\r\n]+/', ' ', $Str);

        return $Str;
    }

    public function valid_url($Str, $Extension = '', $Inline = false)
    {
        $Regex = '/^';
        $Regex .= '(https?|ftps?|irc):\/\/'; // protocol
        $Regex .= '(\w+(:\w+)?@)?'; // user:pass@
        $Regex .= '(';
        $Regex .= '(([0-9]{1,3}\.){3}[0-9]{1,3})|'; // IP or...
        $Regex .= '(([a-z0-9\-\_]+\.)+\w{2,6})'; // sub.sub.sub.host.com
        $Regex .= ')';
        $Regex .= '(:[0-9]{1,5})?'; // port
        $Regex .= '\/?'; // slash?
        $Regex .= '(\/?[0-9a-z\-_.,&=@~%\/:;()+|!#]+)*'; // /file
        if (!empty($Extension)) {
            $Regex.=$Extension;
        }

        // query string
        if ($Inline) {
            $Regex .= '(\?([0-9a-z\-_.,%\/\@~&=:;()+*\^$!#|]|\[\d*\])*)?';
        } else {
            $Regex .= '(\?[0-9a-z\-_.,%\/\@[\]~&=:;()+*\^$!#|]*)?';
        }

        $Regex .= '(#[a-z0-9\-_.,%\/\@[\]~&=:;()+*\^$!]*)?'; // #anchor
        $Regex .= '$/i';

        return preg_match($Regex, $Str, $Matches);
    }

    public function local_url($Str)
    {
        $URLInfo = parse_url($Str);
        if (!$URLInfo) {
            return false;
        }
        $Host = $URLInfo['host'];
        // If for some reason your site does not require subdomains or contains a directory in the SITE_URL, revert to the line below.
        //if ($Host == SITE_URL || $Host == SSL_SITE_URL || $Host == 'www.'.SITE_URL) {
        if (preg_match('/(\S+\.)*' . SITE_URL . '/', $Host)) {
            $URL = $URLInfo['path'];
            if (!empty($URLInfo['query'])) {
                $URL.='?' . $URLInfo['query'];
            }
            if (!empty($URLInfo['fragment'])) {
                $URL.='#' . $URLInfo['fragment'];
            }

            return $URL;
        } else {
            return false;
        }
    }

    /* How parsing works

      Parsing takes $Str, breaks it into blocks, and builds it into $Array.
      Blocks start at the beginning of $Str, when the parser encounters a [, and after a tag has been closed.
      This is all done in a loop.

      EXPLANATION OF PARSER LOGIC

      1) Find the next tag (regex)
      1a) If there aren't any tags left, write everything remaining to a block and return (done parsing)
      1b) If the next tag isn't where the pointer is, write everything up to there to a text block.
      2) See if it's a [[wiki-link]] or an ordinary tag, and get the tag name
      3) If it's not a wiki link:
      3a) check it against the $this->ValidTags array to see if it's actually a tag and not [bullshit]
      If it's [not a tag], just leave it as plaintext and move on
      3b) Get the attribute, if it exists [name=attribute]
      4) Move the pointer past the end of the tag
      5) Find out where the tag closes (beginning of [/tag])
      5a) Different for different types of tag. Some tags don't close, others are weird like [*]
      5b) If it's a normal tag, it may have versions of itself nested inside - eg:
      [quote=bob]*
      [quote=joe]I am a redneck!**[/quote]
      Me too!
     * **[/quote]
      If we're at the position *, the first [/quote] tag is denoted by **.
      However, our quote tag doesn't actually close there. We must perform
      a loop which checks the number of opening [quote] tags, and make sure
      they are all closed before we find our final [/quote] tag (***).

      5c) Get the contents between [open] and [/close] and call it the block.
      In many cases, this will be parsed itself later on, in a new parse() call.
      5d) Move the pointer past the end of the [/close] tag.
      6) Depending on what type of tag we're dealing with, create an array with the attribute and block.
      In many cases, the block may be parsed here itself. Stick them in the $Array.
      7) Increment array pointer, start again (past the end of the [/close] tag)

     */

    public function parse($Str)
    {
        $i = 0; // Pointer to keep track of where we are in $Str
        $Len = strlen($Str);
        $Array = array();
        $ArrayPos = 0;

        while ($i < $Len) {
            $Block = '';

            // 1) Find the next tag (regex)
            // [name(=attribute)?]|[[wiki-link]]
            $IsTag = preg_match("/((\[[a-zA-Z*#5]+)(=(?:[^\n'\"\[\]]|\[\d*\])+)?\])|(\[\[[^\n\"'\[\]]+\]\])/", $Str, $Tag, PREG_OFFSET_CAPTURE, $i);

            // 1a) If there aren't any tags left, write everything remaining to a block
            if (!$IsTag) {
                // No more tags
                $Array[$ArrayPos] = substr($Str, $i);
                break;
            }

            // 1b) If the next tag isn't where the pointer is, write everything up to there to a text block.
            $TagPos = $Tag[0][1];
            if ($TagPos > $i) {
                $Array[$ArrayPos] = substr($Str, $i, $TagPos - $i);
                ++$ArrayPos;
                $i = $TagPos;
            }

            // 2) See if it's a [[wiki-link]] or an ordinary tag, and get the tag name
            if (!empty($Tag[4][0])) { // Wiki-link
                $WikiLink = true;
                $TagName = substr($Tag[4][0], 2, -2);
                $Attrib = '';
            } else { // 3) If it's not a wiki link:
                $WikiLink = false;
                $TagName = strtolower(substr($Tag[2][0], 1));

                //3a) check it against the $this->ValidTags array to see if it's actually a tag and not [bullshit]
                if (!isset($this->ValidTags[$TagName])) {
                    $Array[$ArrayPos] = substr($Str, $i, ($TagPos - $i) + strlen($Tag[0][0]));
                    $i = $TagPos + strlen($Tag[0][0]);
                    ++$ArrayPos;
                    continue;
                }

                // Check if user is allowed to use moderator tags (different from Advanced, which is determined
                // by the original post author).
                // We're using ShowErrors as a proxy for figuring out if we're editing or just viewing
                if ($this->ShowErrors && $this->AdvancedTagOnly[$TagName] && !check_perms('site_moderate_forums')) {
                    $this->Errors[] = "<span class=\"error_label\">illegal tag [$TagName]</span>";
                }

                $MaxAttribs = $this->ValidTags[$TagName];

                // 3b) Get the attribute, if it exists [name=attribute]
                if (!empty($Tag[3][0])) {
                    $Attrib = substr($Tag[3][0], 1);
                } else {
                    $Attrib = '';
                }
            }

            // 4) Move the pointer past the end of the tag
            $i = $TagPos + strlen($Tag[0][0]);

            // 5) Find out where the tag closes (beginning of [/tag])
            // Unfortunately, BBCode doesn't have nice standards like xhtml
            // [*], [img=...], and http:// follow different formats
            // Thus, we have to handle these before we handle the majority of tags
            //5a) Different for different types of tag. Some tags don't close, others are weird like [*]
            if ($TagName == 'img' && !empty($Tag[3][0])) { //[img=...]
                $Block = ''; // Nothing inside this tag
                // Don't need to touch $i
            } elseif ($TagName == 'video' || $TagName == 'yt' || $TagName == 'vimeo') {
                $Block = '';
            } elseif ($TagName == 'inlineurl') { // We did a big replace early on to turn http:// into [inlineurl]http://
                // Let's say the block can stop at a newline or a space
                $CloseTag = strcspn($Str, " \n\r", $i);
                if ($CloseTag === false) { // block finishes with URL
                    $CloseTag = $Len;
                }
                if (preg_match('/[!;,.?:]+$/', substr($Str, $i, $CloseTag), $Match)) {
                    $CloseTag -= strlen($Match[0]);
                }
                $URL = substr($Str, $i, $CloseTag);
                if (substr($URL, -1) == ')' && substr_count($URL, '(') < substr_count($URL, ')')) {
                    $CloseTag--;
                    $URL = substr($URL, 0, -1);
                }
                $Block = $URL; // Get the URL
                // strcspn returns the number of characters after the offset $i, not after the beginning of the string
                // Therefore, we use += instead of the = everywhere else
                $i += $CloseTag; // 5d) Move the pointer past the end of the [/close] tag.
            } elseif ($WikiLink == true || $TagName == 'ratiolist' || $TagName == 'n' || $TagName == 'br' || $TagName == 'hr' || $TagName == 'cast' || $TagName == 'details' || $TagName == 'info' || $TagName == 'plot' || $TagName == 'screens' || $TagName == 'you') {
                // Don't need to do anything - empty tag with no closing
            } elseif ($TagName === '*') {   //  || $TagName === '#' - no longer list tag
                // We're in a list. Find where it ends
                $NewLine = $i;
                do { // Look for \n[*]
                    $NewLine = strpos($Str, "\n", $NewLine + 1);
                } while ($NewLine !== false && substr($Str, $NewLine + 1, 3) == '[' . $TagName . ']');

                $CloseTag = $NewLine;
                if ($CloseTag === false) { // block finishes with list
                    $CloseTag = $Len;
                }
                $Block = substr($Str, $i, $CloseTag - $i); // Get the list
                $i = $CloseTag; // 5d) Move the pointer past the end of the [/close] tag.
            } else {
                //5b) If it's a normal tag, it may have versions of itself nested inside
                $CloseTag = $i - 1;
                $InTagPos = $i - 1;
                $NumInOpens = 0;
                $NumInCloses = -1;

                $InOpenRegex = '/\[(' . $TagName . ')';
                if ($MaxAttribs > 0) {
                    $InOpenRegex.="(=[^\n'\"\[\]]+)?";
                }
                $InOpenRegex.='\]/i';

                $closetaglength = strlen($TagName) + 3;

                // Every time we find an internal open tag of the same type, search for the next close tag
                // (as the first close tag won't do - it's been opened again)
                do {
                    $CloseTag = stripos($Str, '[/' . $TagName . ']', $CloseTag + 1);
                    if ($CloseTag === false) {
                        if ($TagName == '#' || $TagName == 'anchor') {
                            // automatically close open anchor tags (otherwise it wraps the entire text
                            // in an <a> tag which then get stripped from the end as they are way out of place and you have
                            // open <a> tags in the code - but links without any href so its a subtle break
                            $CloseTag = $i;
                            $closetaglength = 0;
                        } else {
                            // lets try and deal with badly formed bbcode in a better way
                            $istart = max(  $TagPos- 20, 0 );
                            $iend = min( $i + 20, $Len );
                            $errnum = count($this->Errors); // &nbsp; <a class="error" href="#err'.$errnum.'">goto error</a>

                            $postlink = '<a class="postlink error" href="#err'.$errnum.'" title="scroll to error"><span class="postlink"></span></a>';

                            $this->Errors[] = "<span class=\"error_label\">unclosed [$TagName] tag: $postlink</span><blockquote class=\"bbcode error\">..." . substr($Str, $istart, $TagPos - $istart)
                                    .'<code class="error">'.$Tag[0][0].'</code>'. substr($Str, $i, $iend - $i) .'... </blockquote>';

                            if ($this->ShowErrors) {
                                $Block = "[$TagName]";
                                $TagName = 'error';
                                $Attrib = $errnum;
                            } else {
                                $TagName = 'ignore'; // tells the parser to skip this empty tag
                            }
                            $CloseTag = $i;
                            $closetaglength = 0;

                        }
                        break;
                    } else {
                        $NumInCloses++; // Majority of cases
                    }

                    // Is there another open tag inside this one?
                    $OpenTag = preg_match($InOpenRegex, $Str, $InTag, PREG_OFFSET_CAPTURE, $InTagPos + 1);
                    if (!$OpenTag || $InTag[0][1] > $CloseTag) {
                        break;
                    } else {
                        $InTagPos = $InTag[0][1];
                        $NumInOpens++;
                    }
                } while ($NumInOpens > $NumInCloses);

                // Find the internal block inside the tag
                if (!$Block)
                    $Block = substr($Str, $i, $CloseTag - $i); // 5c) Get the contents between [open] and [/close] and call it the block.

                $i = $CloseTag + $closetaglength; // 5d) Move the pointer past the end of the [/close] tag.
            }

            // 6) Depending on what type of tag we're dealing with, create an array with the attribute and block.
            switch ($TagName) {
                case 'h5v': // html5 video tag
                    $Array[$ArrayPos] = array('Type' => 'h5v', 'Attr' => $Attrib, 'Val' => $Block);
                    break;
                case 'video': // youtube and vimeo only
                case 'yt':
                case 'vimeo':
                    $Array[$ArrayPos] = array('Type' => 'video', 'Attr' => $Attrib, 'Val' => '');
                    break;
                case 'flash':
                    $Array[$ArrayPos] = array('Type' => 'flash', 'Attr' => $Attrib, 'Val' => $Block);
                    break;
                /* case 'link':
                  $Array[$ArrayPos] = array('Type'=>'link', 'Attr'=>$Attrib, 'Val'=>$this->parse($Block));
                  break; */
                case 'anchor':
                case '#':
                    $Array[$ArrayPos] = array('Type' => $TagName, 'Attr' => $Attrib, 'Val' => $this->parse($Block));
                    break;
                case 'br':
                case 'hr':
                case 'cast':
                case 'details':
                case 'info':
                case 'plot':
                case 'screens':
                case 'you':
                case 'ratiolist':
                    $Array[$ArrayPos] = array('Type' => $TagName, 'Val' => '');
                    break;
                case 'font':
                    $Array[$ArrayPos] = array('Type' => 'font', 'Attr' => $Attrib, 'Val' => $this->parse($Block));
                    break;
                case 'center': // lets just swap a center tag for an [align=center] tag
                    $Array[$ArrayPos] = array('Type' => 'align', 'Attr' => 'center', 'Val' => $this->parse($Block));
                    break;
                case 'inlineurl':
                    $Array[$ArrayPos] = array('Type' => 'inlineurl', 'Attr' => $Block, 'Val' => '');
                    break;
                case 'url':
                    if (empty($Attrib)) { // [url]http://...[/url] - always set URL to attribute
                        $Array[$ArrayPos] = array('Type' => 'url', 'Attr' => $Block, 'Val' => '');
                    } else {
                        $Array[$ArrayPos] = array('Type' => 'url', 'Attr' => $Attrib, 'Val' => $this->parse($Block));
                    }
                    break;
                case 'quote':
                    $Array[$ArrayPos] = array('Type' => 'quote', 'Attr' => $Attrib, 'Val' => $this->parse($Block));
                    break;

                case 'imgnm':
                    $Array[$ArrayPos] = array('Type' => 'imgnm',  'Attr' => $Attrib, 'Val' => $Block);
                    break;
                case 'imgalt':
                    $Array[$ArrayPos] = array('Type' => 'imgalt', 'Attr' => $Attrib, 'Val' => $Block);
                    break;

                case 'img':
                case 'image':
                    if (empty($Block)) {
                        $Block = $Attrib;
                        $Attrib = '';
                    }
                    $Array[$ArrayPos] = array('Type' => 'img', 'Attr' => $Attrib, 'Val' => $Block);
                    break;
                case 'banner':
                case 'thumb':
                    if (empty($Block)) {
                        $Block = $Attrib;
                    }
                    $Array[$ArrayPos] = array('Type' => $TagName, 'Val' => $Block);
                    break;
                case 'aud':
                case 'mp3':
                case 'audio':
                    if (empty($Block)) {
                        $Block = $Attrib;
                    }
                    $Array[$ArrayPos] = array('Type' => 'aud', 'Val' => $Block);
                    break;
                case 'user':
                    $Array[$ArrayPos] = array('Type' => 'user', 'Val' => $Block);
                    break;

                case 'torrent':
                    $Array[$ArrayPos] = array('Type' => 'torrent', 'Val' => $Block);
                    break;
                case 'request':
                    $Array[$ArrayPos] = array('Type' => 'request', 'Val' => $Block);
                    break;
                case 'tex':
                    $Array[$ArrayPos] = array('Type' => 'tex', 'Val' => $Block);
                    break;
                case 'pre':
                case 'code':
                case 'codeblock':
                case 'plain':
                    $Block = strtr($Block, array('[inlineurl]' => ''));
                    $Block = preg_replace('/\[inlinesize\=3\](.*?)\[\/inlinesize\]/i', '====$1====', $Block);
                    $Block = preg_replace('/\[inlinesize\=5\](.*?)\[\/inlinesize\]/i', '===$1===', $Block);
                    $Block = preg_replace('/\[inlinesize\=7\](.*?)\[\/inlinesize\]/i', '==$1==', $Block);

                    $Array[$ArrayPos] = array('Type' => $TagName, 'Val' => $Block);
                    break;
                case 'hide':
                    $ArrayPos--;
                    break; // not seen
                case 'spoiler':
                    $Array[$ArrayPos] = array('Type' => $TagName, 'Attr' => $Attrib, 'Val' => $this->parse($Block));
                    break;
                //case '#': using this for anchor short tag... not used on old emp so figure should be okay
                case '*':
                    $Array[$ArrayPos] = array('Type' => 'list');
                    $Array[$ArrayPos]['Val'] = explode('[' . $TagName . ']', $Block);
                    $Array[$ArrayPos]['ListType'] = $TagName === '*' ? 'ul' : 'ol';
                    $Array[$ArrayPos]['Tag'] = $TagName;
                    foreach ($Array[$ArrayPos]['Val'] as $Key => $Val) {
                        $Array[$ArrayPos]['Val'][$Key] = $this->parse(trim($Val));
                    }
                    break;
                case 'n':
                case 'ignore': // not a tag but can be used internally
                    $ArrayPos--;
                    break; // n serves only to disrupt bbcode (backwards compatibility - use [pre])
                case 'error':  // not a tag but can be used internally
                    $Array[$ArrayPos] = array('Type' => 'error', 'Attr' => $Attrib, 'Val' => $Block);
                    break;
                default:
                    if ($WikiLink == true) {
                        $Array[$ArrayPos] = array('Type' => 'wiki', 'Val' => $TagName);
                    } else {

                        // Basic tags, like [b] or [size=5]

                        $Array[$ArrayPos] = array('Type' => $TagName, 'Val' => $this->parse($Block));
                        if (isset($Attrib) && $MaxAttribs > 0) {
                            // $Array[$ArrayPos]['Attr'] = strtolower($Attrib);
                            $Array[$ArrayPos]['Attr'] = $Attrib;
                        }
                    }
            }

            $ArrayPos++; // 7) Increment array pointer, start again (past the end of the [/close] tag)
        }

        return $Array;
    }

    public function get_allowed_colors()
    {
        static $ColorAttribs;
        if (!$ColorAttribs) { // only define it once per page
            // now with more colors!
            $ColorAttribs = array( 'aliceblue', 'antiquewhite', 'aqua', 'aquamarine', 'azure', 'beige', 'bisque', 'black', 'blanchedalmond', 'blue', 'blueviolet',
                'brown','burlywood','cadetblue','chartreuse','chocolate','coral','cornflowerblue','cornsilk','crimson','cyan','darkblue','darkcyan','darkgoldenrod',
                'darkgray','darkgreen','darkgrey','darkkhaki','darkmagenta','darkolivegreen','darkorange','darkorchid','darkred','darksalmon','darkseagreen','darkslateblue',
                'darkslategray','darkslategrey','darkturquoise','darkviolet','deeppink','deepskyblue','dimgray','dimgrey','dodgerblue','firebrick','floralwhite','forestgreen',
                'fuchsia','gainsboro','ghostwhite','gold','goldenrod','gray','grey','green','greenyellow','honeydew','hotpink','indianred','indigo','ivory','khaki','lavender',
                'lavenderblush','lawngreen','lemonchiffon','lightblue','lightcoral','lightcyan','lightgoldenrodyellow','lightgray','lightgreen','lightgrey','lightpink',
                'lightsalmon','lightseagreen','lightskyblue','lightslategray','lightslategrey','lightsteelblue','lightyellow','lime','limegreen','linen','magenta','maroon',
                'mediumaquamarine','mediumblue','mediumorchid','mediumpurple','mediumseagreen','mediumslateblue','mediumspringgreen','mediumturquoise','mediumvioletred',
                'midnightblue','mintcream','mistyrose','moccasin','navajowhite','navy','oldlace','olive','olivedrab','orange','orangered','orchid','palegoldenrod','palegreen',
                'paleturquoise','palevioletred','papayawhip','peachpuff','peru','pink','plum','powderblue','purple','red','rosybrown','royalblue','saddlebrown','salmon',
                'sandybrown','seagreen','seashell','sienna','silver','skyblue','slateblue','slategray','slategrey','snow','springgreen','steelblue','tan','teal','thistle',
                'tomato','turquoise','violet','wheat','white','whitesmoke','yellow','yellowgreen' );
        }

        return $ColorAttribs;
    }

    public function is_color_attrib(&$Attrib)
    {
        global $ClassNames;

        $Att = strtolower($Attrib);

        // convert class names to class colors
        if (isset($ClassNames[$Att]['Color'])) {
            $Attrib = '#' . $ClassNames[$Att]['Color'];
            $Att = strtolower($Attrib);
        }
        // if in format #rgb hex then return as is
        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $Att)) return true;

        // check and capture #rgba format
        if (preg_match('/^#(?|([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})|([0-9a-f]{1})([0-9a-f]{1})([0-9a-f]{1})([0-9a-f]{1}))$/', $Att, $matches) ) {
            // capture #rgba hex and convert into rgba(r,g,b,a) format (from base 16 to base 10 0->255)
            for ($i=1;$i<4;$i++) {
                if (strlen($matches[$i])==1) $matches[$i] = "$matches[$i]$matches[$i]";
                $matches[$i] = base_convert($matches[$i], 16, 10);
            }
            if (strlen($matches[4])==1) $matches[4] = "$matches[4]$matches[4]";
            // alpha channel is in 0->1.0 range not 0->255 (!)
            $matches[4] = number_format( base_convert($matches[4], 16, 10) /255 , 2);
            // attribute is in rgb(r,g,b,a) format for alpha channel
            $Attrib = "rgba($matches[1],$matches[2],$matches[3],$matches[4])";

            return true;
        }

        // if not in #rgb or #rgba format then check for allowed colors
        return in_array($Att, $this->get_allowed_colors());
    }

    public function extract_attributes($Attrib, $MaxNumber=-1)
    {
        $Elements=array();
        if (isset($Attrib) && $Attrib) {
            $attributes = explode(",", $Attrib);
            if ($attributes) {
                foreach ($attributes as &$att) {

                    if ($this->is_color_attrib($att)) {
                        $Elements['color'][] = $att;

                    } elseif (preg_match('/^([0-9]*)$/', $att, $matches)) {
                        if ($MaxNumber>-1 && $att>$MaxNumber) $att = $MaxNumber;
                        $Elements['number'][] = $att;

                    } elseif ( $this->valid_url($att) ) {
                        $Elements['url'][] = $att;
                    }
                }
                $InlineStyle .= '"';
            }
        }

        return $Elements;
    }

    public function get_css_attributes($Attrib, $AllowMargin=true, $AllowColor=true, $AllowWidth=true, $AllowNoBorder=true, $AllowImage=true)
    {
        $InlineStyle = '';
        if (isset($Attrib) && $Attrib) {
            $attributes = explode(",", $Attrib);
            if ($attributes) {
                $InlineStyle = ' style="';
                foreach ($attributes as $att) {
                    if ($AllowColor && substr($att, 0, 9) == 'gradient:') {
                        $InlineStyle .= 'background: linear-gradient(';
                        $LinearArr = explode(';', substr($att, 9));
                        $LinearAttr = array();
                        // Check integrity
                        if (sizeof($LinearArr) < 2) return '';
                        foreach ($LinearArr as $arr) {
                            // Check so that the gradient is using the correct attributes
                            if (preg_match('/^to left bottom|right bottom|bottom left|bottom right|'.
                                         'left top|right top|top left|top right|left|right|top|bottom$/', $arr) ||
                                    preg_match('/^[0-9]{1,3}deg$/', $arr) ||
                                    $this->is_color_attrib($arr)) {
                                $LinearAttr[] = $arr;
                            // People love shortcuts..
                            } elseif (preg_match('/^lb|rb|bl|br|lt|rt|tl|tr|l|r|t|b$/', $arr)) {
                                $arr = preg_replace('/^lb$/', 'to left bottom', $arr);
                                $arr = preg_replace('/^rb$/', 'to right bottom', $arr);
                                $arr = preg_replace('/^bl$/', 'to left bottom', $arr);
                                $arr = preg_replace('/^br$/', 'to right bottom', $arr);
                                $arr = preg_replace('/^lt$/', 'to left top', $arr);
                                $arr = preg_replace('/^rt$/', 'to right top', $arr);
                                $arr = preg_replace('/^tl$/', 'to left top', $arr);
                                $arr = preg_replace('/^tr$/', 'to right top', $arr);
                                $arr = preg_replace('/^l$/', 'to left', $arr);
                                $arr = preg_replace('/^r$/', 'to right', $arr);
                                $arr = preg_replace('/^t$/', 'to top', $arr);
                                $arr = preg_replace('/^b$/', 'to bottom', $arr);
                                $LinearAttr[] = $arr;
                            } else {
                                // Is the attribute using color stops? (#rgb xx%)
                                $split = explode(' ', $arr);
                                if (sizeof($split) == 2 && $this->is_color_attrib($split[0]) && preg_match('/^[0-9]{1,3}%$/', $split[1])) {
                                    $LinearAttr[] = implode(' ', $split);
                                } else {
                                    return '';
                                }
                            }
                        }
                        $InlineStyle .= implode(',', $LinearAttr) . ');';
                    } elseif ($AllowColor && $this->is_color_attrib($att)) {
                        $InlineStyle .= 'background-color:' . $att . ';';
                    } elseif ($AllowImage && $this->valid_url($att) ) {
                        if($this->ShowErrors && !$this->validate_imageurl($att)) {
                            $this->Errors[] = "<span class=\"error_label\">Not an approved Imagehost:</span><blockquote class=\"bbcode error\">".'<code class="error">'.$att.'</code></blockquote>';
                            break;
                        }
                        $escapees = array( "'",   '"',  "(",  ")",  " ");
                        $escaped  = array("\'", '\"', "\(", "\)", "\ ");
                        $sanitisedurl = str_replace($escapees, $escaped, $att);
                        $InlineStyle .= "background-image: url(".$sanitisedurl.");";
                        //$InlineStyle .= "background: url('$att') no-repeat center center;";

                    } elseif ($AllowWidth && preg_match('/^([0-9]{1,3})px$/', $att, $matches)) {
                        if ((int) $matches[1] > 920) $matches[1] = '920';
                        $InlineStyle .= 'width:' . $matches[1] . 'px;';

                    } elseif ($AllowWidth && preg_match('/^([0-9]{1,3})%?$/', $att, $matches)) {
                        if ((int) $matches[1] > 100) $matches[1] = '100';
                        $InlineStyle .= 'width:' . $matches[1] . '%;';

                    } elseif ($AllowMargin && in_array($att, array('left', 'center', 'right'))) {
                        switch ($att) {
                            case 'left':
                                $InlineStyle .= 'margin: 0px auto 0px 0px;';
                                break;
                            case 'right':
                                $InlineStyle .= 'margin: 0px 0px 0px auto;';
                                break;
                            case 'center':
                            default:
                                $InlineStyle .= 'margin: 0px auto;';
                                break;
                        }
                    } elseif ($AllowNoBorder && in_array($att, array('nball', 'nb', 'noborder'))) { //  'nball',
                        $InlineStyle .= 'border:none;';
                    } elseif ($AllowMargin && in_array($att, array('nopad'))) {
                        $InlineStyle .= 'padding:0px;';
                    }
                }
                $InlineStyle .= '"';
            }
        }

        return $InlineStyle;
    }

    public function get_css_classes($Attrib, $MatchClasses)
    {
        if ($Attrib == '') return '';
        $classes='';
        foreach ($MatchClasses as $class) {
            if ( is_array($class)) {
                $class_match = $class[0];
                $class_replace = $class[1];
            } else {
                $class_match = $class;
                $class_replace = $class;
            }
            if (stripos($Attrib, $class_match) !== FALSE) $classes .= " $class_replace";
        }

        return $classes;
    }

    public function remove_text_between_tags($Array, $MatchTagRegex = false)
    {
        $count = count($Array);
        for ($i = 0; $i <= $count; $i++) {
            if (is_string($Array[$i])) {
                $Array[$i] = '';
            } elseif ($MatchTagRegex !== false && !preg_match($MatchTagRegex, $Array[$i]['Type'])) {
                $Array[$i] = '';
            }
        }

        return $Array;
    }

    public function get_size_attributes($Attrib)
    {
        if ($Attrib == '') {
            return '';
        }
        if (preg_match('/([0-9]{2,4})\,([0-9]{2,4})/', $Attrib, $matches)) {
            if (count($matches) < 3) {
                if (!$matches[1]) $matches[1] = 640;
                if (!$matches[2]) $matches[2] = 385;
            }

            return ' width="' . $matches[1] . '" height="' . $matches[2] . '" ';
        }

        return '';
    }

    public function to_html($Array)
    {
        global $LoggedUser;
        $this->Levels++;
        if ($this->Levels > 20) {
            return $Block['Val'];
        } // Hax prevention
        $Str = '';

        $anon_url = (defined('ANONYMIZER_URL')) ? ANONYMIZER_URL : 'http://anonym.to/?';

        foreach ($Array as $Block) {
            if (is_string($Block)) {
                $Str.=$this->smileys($Block);
                continue;
            }
            switch ($Block['Type']) {
                case 'article': // link to article
                    if (!empty($Block['Attr']) && preg_match('/^[a-z0-9\-\_.()\@&]+$/', strtolower($Block['Attr'])))
                        $Str.='<a class="bbcode article" href="articles.php?topic=' .strtolower($Block['Attr']). '">' . $this->to_html($Block['Val']) . '</a>';
                    else
                        $Str.='[article='. $Block['Attr'] . ']' . $this->to_html($Block['Val']) . '[/article]';
                    break;
                case 'tip': // a tooltip
                    if (!empty($Block['Attr']))
                        $Str.='<span class="bbcode tooltip" title="' .display_str($Block['Attr']) . '">' . $this->to_html($Block['Val']) . '</span>';
                    else
                        $Str.='[tip='. $Block['Attr'] . ']' . $this->to_html($Block['Val']) . '[/tip]';
                    break;
                case 'quote':
                    $this->NoMedia++; // No media elements inside quote tags
                    if (!empty($Block['Attr'])) {
                        // [quote=name,[F|T|R|C]number1,number2]
                        list($qname, $qID1, $qID2) = explode(",", $Block['Attr']);
                        if ($qID1) {  // if we have numbers
                            $qType = substr($qID1, 0, 1); /// F or T or C or R (forums/torrents/collags/requests)
                            $qID1 = substr($qID1, 1);
                            if (in_array($qType, array('f', 't', 'c', 'r')) && is_number($qID1) && is_number($qID2)) {
                                switch ($qType) {
                                    case 'f':
                                        $postlink = '<a class="postlink" href="forums.php?action=viewthread&threadid=' . $qID1 . '&postid=' . $qID2 . '#post' . $qID2 . '"><span class="postlink"></span></a>';
                                        break;
                                    case 't':
                                        $postlink = '<a class="postlink" href="torrents.php?id=' . $qID1 . '&postid=' . $qID2 . '#post' . $qID2 . '"><span class="postlink"></span></a>';
                                        break;
                                    case 'c':
                                        $postlink = '<a class="postlink" href="collages.php?action=comments&collageid=' . $qID1 . '#post' . $qID2 . '"><span class="postlink"></span></a>';
                                        break;
                                    case 'r':
                                        $postlink = '<a class="postlink" href="requests.php?action=view&id=' . $qID1 . '#post' . $qID2 . '"><span class="postlink"></span></a>';
                                        break;
                                }
                            }
                        }
                        $Str.= '<span class="quote_label"><strong>' . display_str($qname) . '</strong>: ' . $postlink . '</span>';
                    }
                    $Str.='<blockquote class="bbcode">' . $this->to_html($Block['Val']) . '</blockquote>';
                    $this->NoMedia--;
                    break;
                case 'error': // used internally to display bbcode errors in preview
                    // haha, a legitimate use of the blink tag (!)
                    $Str.="<a id=\"err$Block[Attr]\"></a><blink><code class=\"error\" title=\"You have an unclosed $Block[Val] tag in your bbCode!\">$Block[Val]</code></blink>";
                    break;
                case 'you':
                    if ($this->Advanced)
                        $Str.='<a href="user.php?id=' . $LoggedUser['ID'] . '">' . $LoggedUser['Username'] . '</a>';
                    else
                        $Str.='[you]';
                    break;
                case 'video':
                    // Supports youtube and vimeo for now.
                    if($this->ShowErrors && !$this->validate_imageurl($Block['Attr'])) {
                        $this->Errors[] = "<span class=\"error_label\">Not an approved Imagehost:</span><blockquote class=\"bbcode error\">".'<code class="error">'.$Block['Attr'].'</code></blockquote>';
                        break;
                    }

                    $videoUrl = '';
                    if (preg_match('/^https?:\/\/www\.youtube\.com\/.*v=([^&]*).*$/i', $Block['Attr'], $matches))
                        $videoUrl = 'https://www.youtube-nocookie.com/embed/'.$matches[1];
                    elseif (preg_match('/^https?:\/\/vimeo.com\/([0-9]+)$/i', $Block['Attr'], $matches))
                        $videoUrl = 'https://player.vimeo.com/video/'.$matches[1];

                    if ($this->NoMedia > 0) {
                        $Str .= '<a rel="noreferrer" target="_blank" href="' . $videoUrl . '">' . $videoUrl . '</a> (video)';
                        break;
                    }
                    else {
                        if ($videoUrl != '')
                            $Str.='<iframe class="bb_video" src="'.$videoUrl.'" allowfullscreen></iframe>';
                        else
                            $Str.='[video=' . $Block['Attr'] . ']';
                    }
                    break;
                case 'h5v':
                    // html5 video tag
                    $Attributes= $this->extract_attributes($Block['Attr'], 920);
                    if($this->ShowErrors && !$this->validate_imageurl($Block['Val'])) {
                        $this->Errors[] = "<span class=\"error_label\">Not an approved Imagehost:</span><blockquote class=\"bbcode error\">".'<code class="error">'.$Block['Val'].'</code></blockquote>';
                        break;
                    }
                    if($this->ShowErrors && !$this->validate_imageurl($Block['Attr'])) {
                        $this->Errors[] = "<span class=\"error_label\">Not an approved Imagehost:</span><blockquote class=\"bbcode error\">".'<code class="error">'.$Block['Attr'].'</code></blockquote>';
                        break;
                    }

                    if ( ($Block['Attr'] != '' && count($Attributes)==0) || $Block['Val'] == '' ) {
                        $Str.='[h5v' . ($Block['Attr'] != ''?'='. $Block['Attr']:'')  . ']' . $this->to_html($Block['Val']) . '[/h5v]';
                    } else {
                        $Sources = explode(',', $Block['Val']);

                        if ($this->NoMedia > 0) {
                            foreach ($Sources as $Source) {
                                $videoUrl = str_replace('[inlineurl]', '', $Source);
                                $Str .= '<a rel="noreferrer" target="_blank" href="' . $videoUrl . '">' . $videoUrl . '</a> (video)';
                            }
                            break;
                        }
                        else {
                            $parameters = '';
                            if (isset($Attributes['number']) && count($Attributes['number']) >= 2) {
                                $parameters = ' width="'.$Attributes['number'][0].'" height="'.$Attributes['number'][1].'" ';
                            }
                            if (isset($Attributes['url']) && count($Attributes['url']) >= 1) {
                                $parameters .= ' poster="'.$Attributes['url'][0].'" ';
                            }

                            $Str .= '<video '.$parameters.' controls>';
                            foreach ($Sources as $Source) {
                                $lastdot = strripos($Source, '.');
                                $mime = substr($Source, $lastdot+1);
                                if($mime=='ogv')$mime='ogg'; // all others are same as ext (webm=webm, mp4=mp4, ogg=ogg)
                                $Str .= '<source src="'. str_replace('[inlineurl]', '', $Source).'" type="video/'.$mime.'">';
                            }
                            $Str .= 'Your browser does not support the html5 video tag. Please upgrade your browser.</video>';
                        }
                    }
                    break;
                case 'flash':
                    if($this->ShowErrors && !$this->validate_imageurl($Block['Attr'])) {
                        $this->Errors[] = "<span class=\"error_label\">Not an approved Imagehost:</span><blockquote class=\"bbcode error\">".'<code class="error">'.$Block['Attr'].'</code></blockquote>';
                        break;
                    }
                    // note: as a non attribute the link has been auto-formatted as [inlinelink]link.url
                    if (($Block['Attr'] != '' && !preg_match('/^([0-9]{2,4})\,([0-9]{2,4})$/', $Block['Attr'], $matches))
                            || strpos($Block['Val'], '[inlineurl]') === FALSE) {
                        $Str.='[flash=' . ($Block['Attr'] != ''?'='. $Block['Attr']:'') . ']' . $this->to_html($Block['Val']) . '[/flash]';
                    } else {
                        if ($Block['Attr'] == '' || count($matches) < 3) {
                            if (!$matches[1])
                                $matches[1] = 500;
                            if (!$matches[2])
                                $matches[2] = $matches[1];
                        }
                        $Block['Val'] = str_replace('[inlineurl]', '', $Block['Val']);

                        if ($this->NoMedia > 0)
                            $Str .= '<a rel="noreferrer" target="_blank" href="' . $Block['Val'] . '">' . $Block['Val'] . '</a> (flash)';
                        else
                            $Str .= '<object classid="clsid:D27CDB6E-AE6D-11CF-96B8-444553540000" codebase="http://active.macromedia.com/flash2/cabs/swflash.cab#version=5,0,0,0" height="' . $matches[2] . '" width="' . $matches[1] . '"><param name="movie" value="' . $Block['Val'] . '"><param name="play" value="false"><param name="loop" value="false"><param name="quality" value="high"><param name="allowScriptAccess" value="never"><param name="allowNetworking" value="internal"><embed  type="application/x-shockwave-flash" pluginspage="http://www.macromedia.com/shockwave/download/index.cgi?P1_Prod_Version=ShockwaveFlash" play="false" loop="false" quality="high" allowscriptaccess="never" allownetworking="internal"  src="' . $Block['Val'] . '" height="' . $matches[2] . '" width="' . $matches[1] . '"><param name="wmode" value="transparent"></object>';
                    }
                    break;

                case 'url':
                    // Make sure the URL has a label
                    if (empty($Block['Val'])) {
                        $Block['Val'] = $Block['Attr'];
                        $NoName = true; // If there isn't a Val for this
                    } else {
                        $Block['Val'] = $this->to_html($Block['Val']);
                        $NoName = false;
                    }
                    //remove the local host/anonym.to from address if present
                    $Block['Attr'] = str_replace(array('http://' . SITE_URL, $anon_url), '', $Block['Attr']);

                    // first test if is in format /local.php or #anchorname
                    if (preg_match('/^#[a-zA-Z0-9\-\_.,%\@~&=:;()+*\^$!#|]+$|^\/[a-zA-Z0-9\-\_.,%\@~&=:;()+*\^$!#|]+\.php[a-zA-Z0-9\?\-\_.,%\@~&=:;()+*\^$!#|]*$/', $Block['Attr'])) {
                        // a local link or anchor link
                        $Str.='<a class="link" href="' . $Block['Attr'] . '">' . $Block['Val'] . '</a>';
                    } elseif (!$this->valid_url($Block['Attr'])) {
                        // not a valid tag
                        $Str.='[url=' . $Block['Attr'] . ']' . $Block['Val'] . '[/url]';
                    } else {
                        $LocalURL = $this->local_url($Block['Attr']);
                        if ($LocalURL) {
                            if ($NoName) {
                                $Block['Val'] = substr($LocalURL, 1);
                            }
                            $Str.='<a href="' . $LocalURL . '">' . $Block['Val'] . '</a>';
                        } else {
                            if (!$LoggedUser['NotForceLinks']) $target = 'target="_blank"';
                            if (!preg_match(INTERNAL_URLS_REGEX, $Block['Attr'])) $anonto = $anon_url;
                            $Str.='<a rel="noreferrer" ' . $target . ' href="' . $anonto . $Block['Attr'] . '">' . $Block['Val'] . '</a>';
                        }
                    }
                    break;

                case 'anchor':
                case '#':
                    if (!preg_match('/^[a-zA-Z0-9\-\_]+$/', $Block['Attr'])) {
                        $Str.='[' . $Block['Type'] . '=' . $Block['Attr'] . ']' . $this->to_html($Block['Val']) . '[/' . $Block['Type'] . ']';
                    } else {
                        $Str.='<a class="anchor" id="' . $Block['Attr'] . '">' . $this->to_html($Block['Val']) . '</a>';
                    }
                    break;


                case 'mcom':
                    $innerhtml = $this->to_html($Block['Val']);
                    while (ends_with($innerhtml, "\n")) {
                        $innerhtml = substr($innerhtml, 0, -strlen("\n"));
                    }
                    $Str.='<div class="modcomment">' . $innerhtml . '<div class="after">[ <a href="articles.php?topic=tutorials">Help</a> | <a href="articles.php?topic=rules">Rules</a> ]</div><div class="clear"></div></div>';
                    break;

                case 'table':
                    $InlineStyle = $this->get_css_attributes($Block['Attr']);
                    if ($InlineStyle === FALSE) {
                        $Str.='[' . $Block['Type'] . '=' . $Block['Attr'] . ']' . $this->to_html($Block['Val']) . '[/' . $Block['Type'] . ']';
                    } else {
                        $Block['Val'] = $this->remove_text_between_tags($Block['Val'], "/^tr$/");
                        $tableclass = $this->get_css_classes($Block['Attr'], array(array('nball','noborder'),'nopad','vat','vam','vab'));
                        $Str.='<table class="bbcode' . $tableclass . '"' . $InlineStyle . '><tbody>' . $this->to_html($Block['Val']) . '</tbody></table>';
                    }
                    break;
                case 'tr':
                    $InlineStyle = $this->get_css_attributes($Block['Attr'], false, true, false, true);

                    if ($InlineStyle === FALSE) {
                        $Str.='[' . $Block['Type'] . '=' . $Block['Attr'] . ']' . $this->to_html($Block['Val']) . '[/' . $Block['Type'] . ']';
                    } else {
                        $Block['Val'] = $this->remove_text_between_tags($Block['Val'], "/^th$|^td$/");
                        $tableclass = $this->get_css_classes($Block['Attr'], array( 'nopad'));
                        $Str.='<' . $Block['Type'] . ' class="bbcode'.$tableclass.'"' . $InlineStyle . '>' . $this->to_html($Block['Val']) . '</' . $Block['Type'] . '>';
                    }
                    break;
                case 'th':
                case 'td':
                    $InlineStyle = $this->get_css_attributes($Block['Attr'], false);
                    if ($InlineStyle === FALSE) {
                        $Str.='[' . $Block['Type'] . '=' . $Block['Attr'] . ']' . $this->to_html($Block['Val']) . '[/' . $Block['Type'] . ']';
                    } else {
                        $tableclass = $this->get_css_classes($Block['Attr'], array( 'nopad','vat','vam','vab'));
                        $Str.='<'. $Block['Type'] .' class="bbcode'.$tableclass.'"' . $InlineStyle . '>' . $this->to_html($Block['Val']) . '</' . $Block['Type'] . '>';
                    }
                    break;

                case 'bg':
                    $InlineStyle = $this->get_css_attributes($Block['Attr'], true, true, true, false);
                    if (!$InlineStyle || $InlineStyle == '') {
                        $Str.='[bg=' . $Block['Attr'] . ']' . $this->to_html($Block['Val']) . '[/bg]';
                    } else {
                        $tableclass = $this->get_css_classes($Block['Attr'], array( 'nopad'));
                        $Str.='<div class="bbcode'.$tableclass.'"' . $InlineStyle . '>' . $this->to_html($Block['Val']) . '</div>';
                    }
                    break;

                case 'cast':
                case 'details':
                case 'info':
                case 'plot':
                case 'screens': // [cast] [details] [info] [plot] [screens]
                    if (!isset($this->Icons[$Block['Type']])) {
                        $Str.='[' . $Block['Type'] . ']';
                    } else {
                        $Str.= $this->Icons[$Block['Type']];
                    }
                    break;
                case 'br':
                    $Str.='<br />';
                    break;
                case 'hr':
                    $Str.='<hr />';
                    break;
                case 'font':
                    if (!isset($this->Fonts[$Block['Attr']])) {
                        $Str.='[font=' . $Block['Attr'] . ']' . $this->to_html($Block['Val']) . '[/font]';
                    } else {
                        $Str.='<span style="font-family: ' . $this->Fonts[$Block['Attr']] . '">' . $this->to_html($Block['Val']) . '</span>';
                    }
                    break;
                case 'b':
                    $Str.='<strong>' . $this->to_html($Block['Val']) . '</strong>';
                    break;
                case 'u':
                    $Str.='<span style="text-decoration: underline;">' . $this->to_html($Block['Val']) . '</span>';
                    break;
                case 'i':
                    $Str.='<em>' . $this->to_html($Block['Val']) . "</em>";
                    break;
                case 's':
                    $Str.='<span style="text-decoration: line-through">' . $this->to_html($Block['Val']) . '</span>';
                    break;
                case 'sup':
                    $Str.='<sup>' . $this->to_html($Block['Val']) . '</sup>';
                    break;
                case 'sub':
                    $Str.='<sub>' . $this->to_html($Block['Val']) . '</sub>';
                    break;
                case 'important':
                    $Str.='<strong class="important_text">' . $this->to_html($Block['Val']) . '</strong>';
                    break;
                case 'user':
                    $Str.='<a href="user.php?action=search&amp;search=' . urlencode($Block['Val']) . '">' . $Block['Val'] . '</a>';
                    break;
                case 'torrent':
                    $Pattern = '/(' . SITE_URL . '\/torrents\.php.*[\?&]id=)?(\d+)($|&|\#).*/i';
                    $Matches = array();
                    if (preg_match($Pattern, $Block['Val'], $Matches)) {
                        if (isset($Matches[2])) {
                            $Groups = get_groups(array($Matches[2]), true, false);
                            if (!empty($Groups['matches'][$Matches[2]])) {
                                $Group = $Groups['matches'][$Matches[2]];
                                $Str .= '<a href="torrents.php?id=' . $Matches[2] . '">' . $Group['Name'] . '</a>';
                            } else {
                                $Str .= '[torrent]' . str_replace('[inlineurl]', '', $Block['Val']) . '[/torrent]';
                            }
                        }
                    } else {
                        $Str .= '[torrent]' . str_replace('[inlineurl]', '', $Block['Val']) . '[/torrent]';
                    }
                    break;
                case 'request':
                    $Pattern = '/(' . SITE_URL . '\/requests\.php.*[\?&]id=)?(\d+)($|&|\#).*/i';
                    $Matches = array();
                    if (preg_match($Pattern, $Block['Val'], $Matches)) {
                        if (isset($Matches[2])) {
                            $Request = get_requests(array($Matches[2]), true);
                            if (!empty($Request['matches'][$Matches[2]])) {
                                $Request = $Request['matches'][$Matches[2]];
                                $Str .= '<a href="requests.php?action=view&id=' . $Matches[2] . '">' . $Request['Title'] . '</a>';
                            } else {
                                $Str .= '[request]' . str_replace('[inlineurl]', '', $Block['Val']) . '[/request]';
                            }
                        }
                    } else {
                        $Str .= '[request]' . str_replace('[inlineurl]', '', $Block['Val']) . '[/request]';
                    }
                    break;
                case 'tex':
                    $Str.='<img style="vertical-align: middle" src="' . STATIC_SERVER . 'blank.gif" onload="if (this.src.substr(this.src.length-9,this.src.length) == \'blank.gif\') { this.src = \'https://chart.apis.google.com/chart?cht=tx&amp;chf=bg,s,FFFFFF00&amp;chl=' . urlencode(mb_convert_encoding($Block['Val'], "UTF-8", "HTML-ENTITIES")) . '&amp;chco=\' + hexify(getComputedStyle(this.parentNode,null).color); }" />';
                    break;
                case 'plain':
                    $Str.=$Block['Val'];
                    break;
                case 'pre':
                    $Str.='<pre>' . $Block['Val'] . '</pre>';
                    break;
                case 'code':
                    $Str.='<code class="bbcode">' . $Block['Val'] . '</code>';
                    break;
                case 'codeblock':

                    $Str.='<code class="bbcodeblock">' . $Block['Val'] . '</code>';
                    break;
                case 'list':
                    $Str .= '<' . $Block['ListType'] . '>';
                    foreach ($Block['Val'] as $Line) {

                        $Str.='<li>' . $this->to_html($Line) . '</li>';
                    }
                    $Str.='</' . $Block['ListType'] . '>';
                    break;
                case 'align':
                    $ValidAttribs = array('left', 'center', 'justify', 'right');
                    if (!in_array($Block['Attr'], $ValidAttribs)) {
                        $Str.='[align=' . $Block['Attr'] . ']' . $this->to_html($Block['Val']) . '[/align]';
                    } else {
                        $Str.='<div style="text-align:' . $Block['Attr'] . '">' . $this->to_html($Block['Val']) . '</div>';
                    }
                    break;
                case 'color':
                case 'colour':
                    if (!$this->is_color_attrib($Block['Attr'])) {
                        $Str.='[color=' . $Block['Attr'] . ']' . $this->to_html($Block['Val']) . '[/color]';
                    } else {
                        $Str.='<span style="color:' . $Block['Attr'] . '">' . $this->to_html($Block['Val']) . '</span>';
                    }
                    break;
                case 'rank':
                    if (!$this->is_color_attrib($Block['Attr'])) {
                        $Str.='[rank=' . $Block['Attr'] . ']' . $this->to_html($Block['Val']) . '[/rank]';
                    } else {
                        $Str.='<span style="font-weight:bold;color:' . $Block['Attr'] . ';">' . $this->to_html($Block['Val']) . '</span>';
                    }
                    break;
                case 'inlinesize':
                case 'size':
                    $ValidAttribs = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10');
                    if (!in_array($Block['Attr'], $ValidAttribs)) {
                        $Str.='[size=' . $Block['Attr'] . ']' . $this->to_html($Block['Val']) . '[/size]';
                    } else {
                        $Str.='<span class="size' . $Block['Attr'] . '">' . $this->to_html($Block['Val']) . '</span>';
                    }
                    break;
                case 'hide':
                    $Str.='<strong>' . (($Block['Attr']) ? $Block['Attr'] : 'Hidden text') . '</strong>: <a href="javascript:void(0);" onclick="BBCode.spoiler(this);">Show</a>';
                    $Str.='<blockquote class="hidden spoiler">' . $this->to_html($Block['Val']) . '</blockquote>';
                    break;
                case 'spoiler':
                    $Str.='<strong>' . (($Block['Attr']) ? $Block['Attr'] : 'Hidden text') . '</strong>: <a href="javascript:void(0);" onclick="BBCode.spoiler(this);">Show</a>';
                    $Str.='<blockquote class="hidden spoiler">' . $this->to_html($Block['Val']) . '</blockquote>';
                    break;

                case 'img':
                case 'imgnm':
                case 'imgalt':
                case 'banner':
                    $Block['Val'] = str_replace('[inlineurl]', '', $Block['Val']);
                    if (!$this->valid_url($Block['Val'])) {
                        $Str.="[$Block[Type]". ( $Block['Attr'] ? '='.$Block['Attr'] : '' ). "]$Block[Val][/$Block[Type]]";
                        break;
                    }
                    $LocalURL = $this->local_url($Block['Val']);
                    if($this->ShowErrors && !$this->validate_imageurl($Block['Val'])) {
                        $this->Errors[] = "<span class=\"error_label\">Not an approved Imagehost:</span><blockquote class=\"bbcode error\">".'<code class="error">'.$Block['Val'].'</code></blockquote>';
                        break;
                    }

                    if (!$LocalURL && $this->NoMedia > 0) {
                        $Str.='<a rel="noreferrer" target="_blank" href="' . $Block['Val'] . '">' . $Block['Val'] . '</a> (image)';
                        break;
                    }
                    $cssclass = "";
                    $Block['Val'] = $this->proxify_url($Block['Val']);
                    if ($Block['Type'] == 'imgnm' ) $cssclass .= ' nopad';
                    if ($Block['Attr'] != '' && ($Block['Type'] == 'imgnm' || $Block['Type'] == 'imgalt') ) $alttext = $Block['Attr'];
                    else $alttext = $Block['Val'];
                    $Str.='<img class="scale_image'.$cssclass.'" onclick="lightbox.init(this,500);" alt="'.$alttext.'" src="'.$Block['Val'].'" />';
                    break;

                case 'thumb':
                    if ($this->NoMedia > 0 && $this->valid_url($Block['Val'])) {
                        $Str.='<a rel="noreferrer" target="_blank" href="' . $Block['Val'] . '">' . $Block['Val'] . '</a> (image)';
                        break;
                    }
                    if($this->ShowErrors && !$this->validate_imageurl($Block['Val'])) {
                        $this->Errors[] = "<span class=\"error_label\">Not an approved Imagehost:</span><blockquote class=\"bbcode error\">".'<code class="error">'.$Block['Val'].'</code></blockquote>';
                        break;
                    }
                    if (!$this->valid_url($Block['Val'])) {
                        $Str.='[thumb]' . $Block['Val'] . '[/thumb]';
                    } else {
                        $Str.='<img class="thumb_image" onclick="lightbox.init(this,300);" alt="' . $Block['Val'] . '" src="' . $Block['Val'] . '" />';
                    }
                    break;

                case 'audio':
                    if ($this->NoMedia > 0 && $this->valid_url($Block['Val'])) {
                        $Str.='<a rel="noreferrer" target="_blank" href="' . $Block['Val'] . '">' . $Block['Val'] . '</a> (audio)';
                        break;
                    }
                    if($this->ShowErrors && !$this->validate_imageurl($Block['Val'])) {
                        $this->Errors[] = "<span class=\"error_label\">Not an approved Imagehost:</span><blockquote class=\"bbcode error\">".'<code class="error">'.$Block['Val'].'</code></blockquote>';
                        break;
                    }
                    if (!$this->valid_url($Block['Val'], '\.(mp3|ogg|wav)')) {
                        $Str.='[aud]' . $Block['Val'] . '[/aud]';
                    } else {
                        //TODO: Proxy this for staff?
                        $Str.='<audio controls="controls" src="' . $Block['Val'] . '"><a rel="noreferrer" target="_blank" href="' . $Block['Val'] . '">' . $Block['Val'] . '</a></audio>';
                    }
                    break;

                case 'inlineurl':
                    $Block['Attr'] = str_replace($anon_url, '', $Block['Attr']);
                    if (!$this->valid_url($Block['Attr'], '', true)) {
                        $Array = $this->parse($Block['Attr']);
                        $Block['Attr'] = $Array;
                        $Str.=$this->to_html($Block['Attr']);
                    } else {
                        $LocalURL = $this->local_url($Block['Attr']);
                        if ($LocalURL) {
                            $Str.='<a href="' . $LocalURL . '">' . substr($LocalURL, 1) . '</a>';
                        } else {
                            if (!$LoggedUser['NotForceLinks'])
                                $target = 'target="_blank"';
                            if (!preg_match(INTERNAL_URLS_REGEX, $Block['Attr']))
                                $anonto = $anon_url;
                            $Str.='<a rel="noreferrer" ' . $target . ' href="' . $anonto . $Block['Attr'] . '">' . $Block['Attr'] . '</a>';
                        }
                    }

                    break;

                case 'ratiolist':
                    if (!$this->Advanced)
                        $Str.= '[ratiolist]';
                    else {

                    $table = '<table>
                      <tr class="colhead">
                            <td>Amount downloaded</td>
                            <td>Required ratio (0% seeded)</td>
                            <td>Required ratio (100% seeded)</td>
                      </tr>
                      <tr class="row'.($LoggedUser['BytesDownloaded'] < 5*1024*1024*1024?'a':'b').'">
                            <td>0-5GB</td>
                            <td>0.00</td>
                            <td>0.00</td>
                      </tr>
                      <tr class="row'.($LoggedUser['BytesDownloaded'] >= 5*1024*1024*1024 && $LoggedUser['BytesDownloaded'] < 10*1024*1024*1024?'a':'b').'">
                            <td>5-10GB</td>
                            <td>0.10</td>
                            <td>0.00</td>
                      </tr>
                      <tr class="row'.($LoggedUser['BytesDownloaded'] >= 10*1024*1024*1024 && $LoggedUser['BytesDownloaded'] < 20*1024*1024*1024?'a':'b').'">
                            <td>10-20GB</td>
                            <td>0.15</td>
                            <td>0.00</td>
                      </tr>
                      <tr class="row'.($LoggedUser['BytesDownloaded'] >= 20*1024*1024*1024 && $LoggedUser['BytesDownloaded'] < 30*1024*1024*1024?'a':'b').'">
                            <td>20-30GB</td>
                            <td>0.20</td>
                            <td>0.00</td>
                      </tr>
                      <tr class="row'.($LoggedUser['BytesDownloaded'] >= 30*1024*1024*1024 && $LoggedUser['BytesDownloaded'] < 40*1024*1024*1024?'a':'b').'">
                            <td>30-40GB</td>
                            <td>0.30</td>
                            <td>0.05</td>
                      </tr>
                      <tr class="row'.($LoggedUser['BytesDownloaded'] >= 40*1024*1024*1024 && $LoggedUser['BytesDownloaded'] < 50*1024*1024*1024?'a':'b').'">
                            <td>40-50GB</td>
                            <td>0.40</td>
                            <td>0.10</td>
                      </tr>
                      <tr class="row'.($LoggedUser['BytesDownloaded'] >= 50*1024*1024*1024 && $LoggedUser['BytesDownloaded'] < 60*1024*1024*1024?'a':'b').'">
                            <td>50-60GB</td>
                            <td>0.50</td>
                            <td>0.20</td>
                      </tr>
                      <tr class="row'.($LoggedUser['BytesDownloaded'] >= 60*1024*1024*1024 && $LoggedUser['BytesDownloaded'] < 80*1024*1024*1024?'a':'b').'">
                            <td>60-80GB</td>
                            <td>0.50</td>
                            <td>0.30</td>
                      </tr>
                      <tr class="row'.($LoggedUser['BytesDownloaded'] >= 80*1024*1024*1024 && $LoggedUser['BytesDownloaded'] < 100*1024*1024*1024?'a':'b').'">
                            <td>80-100GB</td>
                            <td>0.50</td>
                            <td>0.40</td>
                      </tr>
                      <tr class="row'.($LoggedUser['BytesDownloaded'] >= 100*1024*1024*1024?'a':'b').'">
                            <td>100+GB</td>
                            <td>0.50</td>
                            <td>0.50</td>
                      </tr>
                </table>';
                        $table = str_replace("\n", '', $table);
                        $Str .= $table;
                    }
                    break;
            }
        }
        $this->Levels--;

        return $Str;
    }

    public function raw_text($Array, $StripURL = false)
    {
        $Str = '';
        foreach ($Array as $Block) {
            if (is_string($Block)) {
                $Str.=$Block;
                continue;
            }
            switch ($Block['Type']) {

                case 'b':
                case 'u':
                case 'i':
                case 's':
                case 'sup':
                case 'sub':
                case 'color':
                case 'colour':
                case 'size':
                case 'quote':
                case 'spoiler':
                case 'align':
                case 'center':
                case 'mcom':
                case 'anchor':
                case '#':
                case 'rank':
                case 'tip':
                case 'bg':
                case 'table':
                case 'td':
                    $Str.=$this->raw_text($Block['Val']);
                    break;
                case 'tr':
                    $Str.=$this->raw_text($Block['Val'])."\n";
                    break;
                case 'br':
                    $Str.= "\n";
                    break;
                case 'tex': //since this will never strip cleanly, just remove it
                    break;
                case 'user':
                case 'pre':
                case 'code':
                case 'audio':
                case 'img':
                case 'imgalt':
                case 'imgnm':
                    $Str.=$Block['Val'];
                    break;
                case 'list':
                    foreach ($Block['Val'] as $Line) {
                        $Str.=$Block['Tag'] . $this->raw_text($Line);
                    }
                    break;

                case 'url':
                case 'link':
                    if ($StripURL)
                        break;
                    // Make sure the URL has a label
                    if (empty($Block['Val'])) {
                        $Block['Val'] = $Block['Attr'];
                    } else {
                        $Block['Val'] = $this->raw_text($Block['Val']);
                    }

                    $Str.=$Block['Val'];
                    break;

                case 'inlineurl':
                    if (!$this->valid_url($Block['Attr'], '', true)) {
                        $Array = $this->parse($Block['Attr']);
                        $Block['Attr'] = $Array;
                        $Str.=$this->raw_text($Block['Attr']);
                        $Str.="RAW";
                    } else {
                        $Str.=$Block['Attr'];
                    }

                    break;
                default:
                    break;
            }
        }

        return $Str;
    }

    public function smileys($Str)
    {
        global $LoggedUser;
        if (!empty($LoggedUser['DisableSmileys'])) {
            return $Str;
        }
        $Str = strtr($Str, $this->Smileys);

        return $Str;
    }

    /*
     * --------------------- BBCode assistant -----------------------------
     * added 2012.04.21 - mifune
     * --------------------------------------------------------------------
      // pass in the id of the textarea this bbcode helper affects
      // start_num == num of smilies to load when created
      // $load_increment == number of smilies to add each time user presses load button
      // $load_increment_first == if passed this number of smilies are added the first time user presses load button
      // NOTE: its probably best to call this with default parameters because then the user's browser will cache the
      // ajax result and all subsequent calls will use the cached result - if differetn pages use different parameters
      // they will not get that benefit
     */

    public function display_bbcode_assistant($textarea, $AllowAdvancedTags, $start_num_smilies = 0, $load_increment = 240, $load_increment_first = 30)
    {
        global $LoggedUser;
        if ($load_increment_first == -1) {
            $load_increment_first = $load_increment;
        }
        ?>
        <div id="hover_pick<?= $textarea; ?>" style="width: auto; height: auto; position: absolute; border: 0px solid rgb(51, 51, 51); display: none; z-index: 20;"></div>

        <table class="bb_holder">
            <tbody><tr>
                    <td class="colhead" style="padding: 2px 2px 0px 6px">

                        <div class="bb_buttons_left">

                            <a class="bb_button" onclick="tag('b', '<?= $textarea; ?>')" title="Bold text: [b]text[/b]" alt="B"><b>B</b></a>
                            <a class="bb_button" onclick="tag('i', '<?= $textarea; ?>')" title="Italic text: [i]text[/i]" alt="I"><i>I</i></a>
                            <a class="bb_button" onclick="tag('u', '<?= $textarea; ?>')" title="Underline text: [u]text[/u]" alt="U"><u>U</u></a>
                            <a class="bb_button" onclick="tag('s', '<?= $textarea; ?>')" title="Strikethrough text: [s]text[/s]" alt="S"><s>S</s></a>
                            <a class="bb_button" onclick="insert('[hr]', '<?= $textarea; ?>')" title="Horizontal Line: [hr]" alt="HL">hr</a>

                            <a class="bb_button" onclick="url('<?= $textarea; ?>')" title="URL: [url]http://url[/url] or [url=http://url]URL text[/url]" alt="Url">Url</a>
                            <a class="bb_button" onclick="anchor('<?= $textarea; ?>')" title="Anchored heading: [anchor=name]Heading text[/anchor] or [#=name]Heading text[/#]" alt="Anchor">Anchor</a>

                            <a class="bb_button" onclick="image_prompt('<?= $textarea; ?>')" title="Image: [img]http://image_url[/img]" alt="Image">Img</a>
                            <a class="bb_button" onclick="tag('code', '<?= $textarea; ?>')" title="Code display: [code]code[/code]" alt="Code">Code</a>
                            <a class="bb_button" onclick="tag('quote', '<?= $textarea; ?>')" title="Quote text: [quote]text[/quote]" alt="Quote">Quote</a>

                            <a class="bb_button" onclick="video('<?= $textarea; ?>')" title="Youtube video: [video=http://www.youtube.com/v/abcd]" alt="Youtube">Video</a>
        <?php if (check_perms('site_moderate_forums')) { ?>
                            <a class="bb_button" onclick="flash('<?= $textarea; ?>')" title="Flash object: [flash]http://url.swf[/flash]" alt="Flash">Flash</a>
        <?php       } ?>
                            <a class="bb_button" onclick="spoiler('<?= $textarea; ?>')" title="Spoiler: [spoiler=title]hidden text[/spoiler]" alt="Spoiler">Spoiler</a>

                            <a class="bb_button" onclick="insert('[*]', '<?= $textarea; ?>')" title="List item: [*]text" alt="List">List</a>

                            <a class="bb_button" onclick="colorpicker('<?= $textarea; ?>','bg');" title="Background: [bg=color,width% or widthpx,align]text[/bg]" alt="Background">Bg</a>

                            <a class="bb_button" onclick="table('<?= $textarea; ?>')" title="Table: [table=color,width% or widthpx,align][tr][td]text[/td][td]text[/td][/tr][/table]" alt="Table">Table</a>

                        </div>
                        <div class="bb_buttons_right">
                            <img class="bb_icon" src="<?= get_symbol_url('align_center.png') ?>" onclick="wrap('align','','center', '<?= $textarea; ?>')" title="Center Align text: [align=center]text[/align]" alt="Center" />
                            <img class="bb_icon" src="<?= get_symbol_url('align_left.png') ?>" onclick="wrap('align','','left', '<?= $textarea; ?>')" title="Left Align text: [align=left]text[/align]" alt="Left" />
                            <img class="bb_icon" src="<?= get_symbol_url('align_justify.png') ?>" onclick="wrap('align','','justify', '<?= $textarea; ?>')" title="Justify text: [align=justify]text[/align]" alt="Justify" />
                            <img class="bb_icon" src="<?= get_symbol_url('align_right.png') ?>" onclick="wrap('align','','right', '<?= $textarea; ?>')" title="Right Align text: [align=right]text[/align]" alt="Right" />
                            <img class="bb_icon" src="<?= get_symbol_url('text_uppercase.png') ?>" onclick="text('up', '<?= $textarea; ?>')" title="To Uppercase" alt="Up" />
                            <img class="bb_icon" src="<?= get_symbol_url('text_lowercase.png') ?>" onclick="text('low', '<?= $textarea; ?>')" title="To Lowercase" alt="Low" />
                        </div>
                    </td><td class="colhead" style="padding: 2px 6px 0px 0px" rowspan=2>
                        <div class="bb_buttons_right">
                            <a class="bb_help" href="<?= "http://" . SITE_URL . "/articles.php?topic=bbcode" ?>" target="_blank">Help</a>
                        </div>
                    </td></tr><tr>
                    <td class="colhead" style="padding: 2px 2px 0px 6px">
                        <div class="bb_buttons_left">
                            <select class="bb_button" name="fontfont" id="fontfont<?= $textarea; ?>" onchange="font('font',this.value,'<?= $textarea; ?>');" title="Font: [font=fontfamily]text[/font]">
                                <option value="-1">Font Type</option>
        <?php
        foreach ($this->Fonts as $Key => $Val) {
            echo '
                                <option value="' . $Key . '"  style="font-family: ' . $Val . '">' . $Key . '</option>';
        }
        ?>
                            </select>

                            <select  class="bb_button" name="fontsize" id="fontsize<?= $textarea; ?>" onchange="font('size',this.value,'<?= $textarea; ?>');" title="Text Size: [size=number]text[/size]">
                                <option value="-1" selected="selected">Font size</option>
                                <option value="0">0</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                                <option value="6">6</option>
                                <option value="7">7</option>
                                <option value="8">8</option>
                                <option value="9">9</option>
                                <option value="10">10</option>
                            </select>
                            <a class="bb_button" onclick="colorpicker('<?= $textarea; ?>','color');" title="Text Color: [color=colorname]text[/color] or [color=#hexnumber]text[/color]" alt="Color">Color</a>

                            <a class="bb_button" onclick="insert('[cast]', '<?= $textarea; ?>')" title="Cast icon: [quote]" alt="cast">cast</a>
                            <a class="bb_button" onclick="insert('[details]', '<?= $textarea; ?>')" title="Details icon: [details]" alt="details">details</a>
                            <a class="bb_button" onclick="insert('[info]', '<?= $textarea; ?>')" title="Info icon: [info]" alt="info">info</a>
                            <a class="bb_button" onclick="insert('[plot]', '<?= $textarea; ?>')" title="Plot icon: [plot]" alt="plot">plot</a>
                            <a class="bb_button" onclick="insert('[screens]', '<?= $textarea; ?>')" title="Screens icon: [screens]" alt="screens">screens</a>

        <?php if (check_perms('site_moderate_forums')) { ?>
                                <a class="bb_button" style="border: 2px solid #600;" onclick="tag('mcom', '<?= $textarea; ?>')" title="Staff Comment: [mcom]text[/mcom]" alt="Mod comment">Mod</a>
        <?php } ?>
                        </div>
                        <div class="bb_buttons_right">
                            <a class="bb_button" onclick="tag('sup', '<?= $textarea; ?>')" title="Superscript text: [sup]text[/sup]" alt="supX">sup<sup>x</sup></a>
                            <a class="bb_button" onclick="tag('sub', '<?= $textarea; ?>')" title="Subscript text: [sub]text[/sub]" alt="subX">sub<sub>x</sub></a>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td colspan=2>
                        <div id="pickerholder<?= $textarea; ?>" class="picker_holder"></div>
                        <div id="smiley_overflow<?= $textarea; ?>" class="bb_smiley_holder">
        <?php if ($start_num_smilies > 0) {
            $this->draw_smilies_from(0, $start_num_smilies, $textarea);
        } ?>
                        </div>
                        <div class="overflow_button">
                            <a href="#" id="open_overflow<?= $textarea; ?>" onclick="if (this.isopen) {Close_Smilies('<?= $textarea; ?>');} else {Open_Smilies(<?= "$start_num_smilies,$load_increment_first,'$textarea'" ?>);};return false;">Show smilies</a>
                            <a href="#" id="open_overflow_more<?= $textarea; ?>" onclick="Open_Smilies(<?= "$start_num_smilies,$load_increment,'$textarea'" ?>);return false;"></a>
                            <span id="smiley_count<?= $textarea; ?>" class="number" style="float:right;"></span>
                            <span id="smiley_max<?= $textarea; ?>" class="number" style="float:left;"></span>
                        </div>
                    </td></tr></tbody></table>
        <?php
    }

    // output smiley data in xml (we dont just draw the html because we want maxsmilies in js)
    public function draw_smilies_from_XML($indexfrom = 0, $indexto = -1)
    {
        $count = 0;
        echo "<smilies>";
        foreach ($this->Smileys as $Key => $Val) {
            if ($indexto >= 0 && $count >= $indexto) {
                break;
            }
            if ($count >= $indexfrom) {
                echo '    <smiley>
        <bbcode>' . $Key . '</bbcode>
        <url>' . htmlentities($Val) . '</url>
    </smiley>';
            }
            $count++;
        }
        reset($this->Smileys);
        echo '    <maxsmilies>' . count($this->Smileys) . '</maxsmilies>
</smilies>';
    }

    public function draw_smilies_from($indexfrom = 0, $indexto = -1, $textarea)
    {
        $count = 0;
        foreach ($this->Smileys as $Key => $Val) {
            if ($indexto >= 0 && $count >= $indexto) {
                break;
            }
            if ($count >= $indexfrom) {  // ' &nbsp;' .$Key. - jsut for printing in dev
                echo '<a class="bb_smiley" title="' . $Key . '" href="javascript:insert(\' ' . $Key . ' \',\'' . $textarea . '\');">' . $Val . '</a>';
            }
            $count++;
        }
        reset($this->Smileys);
    }

    public function draw_all_smilies($Sort = true, $AZ = true)
    {
        $count = 0;
        if ($Sort) {
            if ($AZ)
                ksort($this->Smileys, SORT_STRING | SORT_FLAG_CASE);
            else
                krsort($this->Smileys, SORT_STRING | SORT_FLAG_CASE);
        }
        echo '<div class="box center" style="font-size:1.2em;">';
        foreach ($this->Smileys as $Key => $Val) {
            echo '<div class="bb_smiley pad center" style="display:inline-block;margin:8px 8px;">';
            echo "      $Val <br /><br />$Key";
            echo '</div>';

            $count++;
        }
        echo '</div>';
        reset($this->Smileys);
    }

    public function clean_bbcode($Str, $Advanced)
    {
        // Change mcom tags into quote tags for non-mod users
        if (!check_perms('site_moderate_forums')) {
            $Str = preg_replace('/\[mcom\]/i', '[quote=Staff Comment]', $Str);
            $Str = preg_replace('/\[\/mcom\]/i', '[/quote]', $Str);
            $Str = preg_replace('/\[flash=([^\]])*\]/i', '[quote=Flash Object]', $Str);
            $Str = preg_replace('/\[\/flash\]/i', '[/quote]', $Str);
        }

        return $Str;
    }

}

/*
  //Uncomment this part to test the class via command line:
  public function display_str($Str) {return $Str;}
  public function check_perms($Perm) {return true;}
  $Str = "hello
  [pre]http://anonym.to/?http://whatshirts.portmerch.com/
  ====hi====
  ===hi===
  ==hi==[/pre]
  ====hi====
  hi";
  $Text = NEW TEXT;
  echo $Text->full_format($Str);
  echo "\n"
 */
