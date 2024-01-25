<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;

use Luminance\Entities\User;
use Luminance\Entities\InviteTree;

class InviteTreeRepository extends Repository {

    protected $entityName = 'InviteTree';

    private function newTree($userID) {
        $treeID = $this->db->rawQuery(
            "SELECT MAX(TreeID) + 1
               FROM invite_tree"
        )->fetchColumn();

        $newTree = new InviteTree;
        $newTree->UserID = $userID;
        $newTree->InviterID = null;
        $newTree->TreePostition = 1;
        $newTree->TreeID = $treeID;
        $newTree->TreeLevel = 1;
        $this->save($newTree);

        return $newTree;
    }

    public function new($user) {
        if (!($user instanceof User)) {
            # Ensure the user object is loaded
            $user = $this->master->repos->users->load($user);
        }
        if ($user->inviter instanceof User) {
            # Check if inviter already has an invite tree
            if (!($user->inviter->inviteTree instanceof InviteTree)) {
                # This should only happen if invite tree is regenerated out of order
                $this->new($user->inviter->ID);
            }

            # Bail out if the user already has an invite tree
            if ($user->inviteTree instanceof InviteTree) {
                return $user->inviteTree;
            }

            $inviteTree = $user->inviter->inviteTree;

            $treePosition = $this->db->rawQuery(
                "SELECT TreePosition
                   FROM invite_tree
                  WHERE TreePosition > ?
                    AND TreeLevel <= ?
                    AND TreeID = ?
               ORDER BY TreePosition
                  LIMIT 1",
                [$inviteTree->TreePosition, $inviteTree->treeLevel, $inviteTree->TreeID]
            )->fetchColumn();

            if ($treePosition === false) {
                $treePosition = $this->db->rawQuery(
                    "SELECT TreePosition + 1
                       FROM invite_tree
                      WHERE TreeID = ?
                   ORDER BY TreePosition DESC
                      LIMIT 1",
                    [$inviteTree->TreeID]
                )->fetchColumn();
            } else {
                $this->db->rawQuery(
                    "UPDATE invite_tree
                        SET TreePosition = TreePosition + 1
                      WHERE TreeID = ?
                        AND TreePosition >= ?",
                    [$inviteTree->TreeID, $inviteTree->TreePosition]
                );
            }

            $treeLevel = $inviteTree->TreeLevel + 1;

            $newTree = new InviteTree;
            $newTree->UserID = $user->ID;
            $newTree->InviterID = $user->inviter->ID;
            $newTree->TreePosition = $treePosition;
            $newTree->TreeID = $inviteTree->TreeID;
            $newTree->TreeLevel = $treeLevel;
            $this->save($newTree);
        } else {
            # User registered when site was open registration.
            $newTree = $this->newTree($user->ID);
        }

        return $newTree;
    }
}
