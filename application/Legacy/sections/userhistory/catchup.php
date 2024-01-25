<?php
use Luminance\Entities\ForumThread;
use Luminance\Entities\ForumLastRead;
use Luminance\Entities\ForumSubscription;

$user = $master->repos->users->load($activeUser['ID']);
$subscriptions = $master->repos->forumsubscriptions->find('UserID = ?', [$user->ID]);

if (!empty($subscriptions)) {
    foreach ($subscriptions as $subscription) {
        if (!($subscription instanceof ForumSubscription)) {
            continue;
        }
        # Load the thread and lastRead objects first.
        $thread = $master->repos->forumthreads->load($subscription->ThreadID);

        if (!($thread instanceof ForumThread)) {
            $master->repos->forumsubscriptions->delete($subscription);
            continue;
        }

        $lastRead = $master->repos->forumlastreads->load([$thread->ID, $user->ID]);

        # If it doesn't already exist then fill in the blanks.
        if (!($lastRead instanceof ForumLastRead)) {
            $lastRead = new ForumLastRead;
            $lastRead->UserID = $user->ID;
            $lastRead->ThreadID = $thread->ID;
        }

        # Update the PostID and save it.
        $lastRead->PostID = $thread->lastPost->ID;
        $master->repos->forumlastreads->save($lastRead);
    }
}
$master->cache->deleteValue("subscriptions_user_new_{$user->ID}");
header('Location: userhistory.php?action=subscriptions');
