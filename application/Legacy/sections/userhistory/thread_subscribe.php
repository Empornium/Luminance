<?php

use Luminance\Entities\ForumSubscription;

$master->repos->restrictions->checkRestricted($activeUser['ID'], Luminance\Entities\Restriction::FORUM);

if (!is_integer_string($_GET['threadid'])) {
    error(0, true);
}

$thread = $master->repos->forumthreads->load($_GET['threadid']);
$user = $master->repos->users->load($activeUser['ID']);

if (!$thread->forum->canRead($user)) {
    error(403, true);
}

$subscription = $master->repos->forumsubscriptions->load([$user->ID, $thread->ID]);

if ($subscription instanceof ForumSubscription) {
    $master->repos->forumsubscriptions->delete($subscription);
    echo -1;
} else {
    $subscription = new ForumSubscription;
    $subscription->UserID = $user->ID;
    $subscription->ThreadID = $thread->ID;
    $master->repos->forumsubscriptions->save($subscription, true);
    echo 1;
}

$master->cache->deleteValue('subscriptions_user_new_'.$activeUser['ID']);
die();
