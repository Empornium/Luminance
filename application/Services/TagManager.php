<?php

namespace Luminance\Services;

use Luminance\Core\Service;

use Luminance\Entities\Tag;
use Luminance\Entities\User;
use Luminance\Entities\TagSynonym;
use Luminance\Entities\TorrentTag;
use Luminance\Entities\TorrentTagVote;
use Luminance\Entities\TorrentGroup;

use Luminance\Errors\NotFoundError;

class TagManager extends Service {

    protected static $useServices = [
        'db'          => 'DB',
        'cache'       => 'Cache',
        'auth'        => 'Auth',
        'options'     => 'Options',
        'repos'       => 'Repos',
    ];

    public function splitTags($str) {
        $list = str_replace(',', ' ', $str);
        $list = preg_replace('/ {2,}/', ' ', $list);

        # Trim input - we add a dot to the charlist
        $list = trim($list, " \t\n\r\0\x0B.");

        return explode(' ', strtolower($list));
    }

    public function sanitizeTag($str) {
        $str = strtolower(trim($str));
        $str = preg_replace('/[^a-z0-9.-_]/', '', $str);
        $str = preg_replace('/\.+/', '.', $str);

        return $str;
    }

    public function getTagSynonym($str, $sanitise = true) {
        if ($sanitise === true) {
            $str = $this->sanitizeTag($str);
        }

        $synonym = $this->repos->tagSynonyms->getBySynonym($str);
        if ($synonym instanceof TagSynonym) {
            $tag = $this->repos->tags->load($synonym->TagID);
        } else {
            $tag = $this->repos->tags->getByName($str);
        }

        if ($tag instanceof Tag) {
            return $tag->Name;
        } else {
            return $str;
        }
    }

    private function getVote(TorrentTag $torrentTag): TorrentTagVote|null {
        $user = $this->master->request->user;
        if (!($user instanceof User)) {
            throw new NotFoundError('unknown user');
        }

        $vote = $this->repos->torrentTagVotes->get(
            '`GroupID` = ? AND `TagID` = ? AND `UserID` = ?',
            [$torrentTag->GroupID, $torrentTag->TagID, $user->ID]
        );

        return $vote;
    }


    private function getTorrentTag(Tag|int &$tag, TorrentGroup|int &$group): TorrentTag|null {
        $tag = $this->repos->tags->load($tag);
        if (!($tag instanceof Tag)) {
            throw new NotFoundError('unknown tag');
        }

        $group = $this->repos->torrentGroups->load($group);
        if (!($group instanceof TorrentGroup)) {
            throw new NotFoundError('unknown torrent');
        }

        # Annoying that the primary index on this is reverse order
        # of the torrentTagVote repo
        $torrentTag = $this->repos->torrentTags->load([$tag->ID, $group->ID]);

        return $torrentTag;
    }

    private function voteTag(TorrentTag &$torrentTag, string $way, array &$msg = []): TorrentTagVote|null {
        $userVote = 1;
        if ($this->auth->isAllowed('site_vote_tag_enhanced')) {
            $userVote = ENHANCED_VOTE_POWER;
        }

        $user = $this->master->request->user;
        if (!($user instanceof User)) {
            throw new NotFoundError('unknown user');
        }

        $vote = $this->getVote($torrentTag);
        if ($vote instanceof TorrentTagVote) {
            if ($vote->Way === $way) {
                $msg[] = [0, "Already voted {$way} for tag "];
            } else {
                if ($vote->Way === 'up') {
                    $torrentTag->PositiveVotes -= $userVote;
                } else {
                    $torrentTag->NegativeVotes -= $userVote;
                }
                $msg[] = [$torrentTag->upDown, "Removed {$vote->Way} vote for tag "];
                $this->repos->torrentTagVotes->delete($vote);
                $this->repos->torrentTags->save($torrentTag);
            }
            return null;
        }

        $vote = new TorrentTagVote([
            'GroupID' => $torrentTag->GroupID,
            'TagID'   => $torrentTag->TagID,
            'UserID'  => $user->ID,
            'Way'     => $way,
        ]);

        $vote->Power = $userVote;
        $this->repos->torrentTagVotes->save($vote, true);

        if ($way === 'up') {
            $torrentTag->PositiveVotes += $userVote;
        } else {
            $torrentTag->NegativeVotes += $userVote;
        }
        $msg[] = [$torrentTag->upDown, "Voted {$way} for tag "];
        $this->repos->torrentTags->save($torrentTag);

        return $vote;
    }

    public function voteUpTag(Tag|int $tag, TorrentGroup|int $group): array {
        $torrentTag = $this->getTorrentTag($tag, $group);
        if (!($torrentTag instanceof TorrentTag)) {
            throw new NotFoundError("This torrent does not have this tag");
        }

        $msg = [];
        $vote = $this->voteTag($torrentTag, 'up', $msg);
        $this->cache->deleteValue('torrents_details_'.$group->ID);

        return $msg;
    }

    public function voteDownTag(Tag|int $tag, TorrentGroup|int $group): array {
        $torrentTag = $this->getTorrentTag($tag, $group);
        if (!($torrentTag instanceof TorrentTag)) {
            throw new NotFoundError("This torrent does not have this tag");
        }

        $msg = [];
        $vote = $this->voteTag($torrentTag, 'down', $msg);

        # Delete the tag
        if ($torrentTag->upDown <= 0) {
            $this->repos->torrentTags->delete($torrentTag);
            $votes = $this->repos->torrentTagVotes->find('GroupID = ?', [$group->ID]);
            foreach ($votes as $vote) {
                $this->repos->torrentTagVotes->delete($vote);
            }

            $tag->Uses--;
            $this->repos->tags->save($tag);

            if ($tag->Uses === 0 && !($tag->Type === 'genre')) {
                $this->repos->tags->delete($tag);

                # Legacy cache
                $this->cache->deleteValue('tag_id_'.$tag->Name);
            }

            update_hash($group->ID);
        }

        $this->cache->deleteValue('torrents_details_'.$group->ID);

        return $msg;
    }

    public function addTorrentTags($groupID, $tagList, $user = null) {
        $list = $this->splitTags($tagList);
        if (!($user instanceof User)) {
            $user = $this->request->user;
        }

        $tagsAdded = [];
        foreach ($list as $str) {
            if (empty($str) || !$this->repos->tags->isValidTag($str)) {
                continue;
            }

            $str = $this->getTagSynonym($str);
            if (empty($str)) {
                continue;
            }

            if (in_array($str, $tagsAdded)) {
                continue;
            }

            $tagsAdded[] = $str;
            $tag = $this->repos->tags->getByName($str);
            if ($tag instanceof Tag) {
                $tag->Uses += 1;
            } else {
                $tag = new Tag;
                $tag->Name   = $str;
                $tag->UserID = $user->ID;
                $tag->Uses   = 1;
            }
            $this->repos->tags->save($tag);

            $torrentTag = $this->repos->torrentTags->load([$tag->ID, $groupID]);
            if ($torrentTag instanceof TorrentTag) {
                if ($user->options('NotVoteUpTags') === '1') {
                    $userVote = 1;
                    if ($this->auth->isAllowed('site_vote_tag_enhanced') === true) {
                        $userVote = ENHANCED_VOTE_POWER;
                    }
                    $torrentTag->PositiveVotes += $userVote;

                    $torrentTagVote = new TorrentTagVote;
                    $torrentTagVote->TagID    = $tag->ID;
                    $torrentTagVote->GroupID  = $groupID;
                    $torrentTagVote->UserID   = $user->ID;
                    $torrentTagVote->way      = 'up';
                    $this->repos->torrentTagVotes->save($torrentTagVote);
                }
            } else {
                $torrentTag = new TorrentTag;
                $torrentTag->TagID          = $tag->ID;
                $torrentTag->GroupID        = $groupID;
                $torrentTag->UserID         = $user->ID;
                $torrentTag->PositiveVotes  = 8;
            }
            $this->repos->torrentTags->save($torrentTag);
        }
    }
}
