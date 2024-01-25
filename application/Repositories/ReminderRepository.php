<?php
namespace Luminance\Repositories;

use Luminance\Core\Repository;

use Luminance\Entities\User;

class ReminderRepository extends Repository {

    protected $entityName = 'Reminder';

    /**
     * Load a group's reminders from DB
     * @param Entity $entity object to be saved
     * @return Reminders
     *
     */
    public function loadGroup(User $user, int $flags) {
        if ($user instanceof User) {
            return $this->db->rawQuery(
                "SELECT * FROM users_reminders
                    WHERE StaffLevel <= ?
                    AND Flags = ?",
                [$user->class->Level, $flags]
            )->fetchAll(\PDO::FETCH_ASSOC);
        }
    }

    /**
     * Load a user's reminders from DB
     * @param Entity $entity object to be saved
     * @return Reminders
     *
     */
    public function loadUser(User $user, int $flags) {
        if ($user instanceof User) {
            return $this->db->rawQuery(
                "SELECT * FROM users_reminders
                    WHERE UserID = ?
                    AND Flags = ?",
                [$user->ID, $flags]
            )->fetchAll(\PDO::FETCH_ASSOC);
        }
    }

    /**
     * Load a user's reminders from DB
     * @param Entity $entity object to be saved
     * @return Reminder
     *
     */
    public function loadReminder(int $id) {
        return $this->db->rawQuery(
            "SELECT * FROM users_reminders
                WHERE ID = ?",
            [$id]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }
}
