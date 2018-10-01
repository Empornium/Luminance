<?php

/**
 * Sections: array
 *      Section: array
 *           name: string # Section name
 *           rule: string # Imploded array, RegEx
 */

return [
    [
        'name'  => 'Staff',
        'rule' => implode('|', [
                'Notes? added by ',
                'Account ',
                'Linked accounts updated',
                'Reset ',
                'Warning ',
                'Warned ',
                '[\w ]+ (changed|modified) ',
                'Disabled '
            ]
        )
    ],
    [
        'name'  => 'User actions',
        'rule' => implode('|', [
                'Someone requested'
            ]
        )
    ],
    [
        'name'  => 'Credits & bounty',
        'rule' => implode('|', [
                'User gave a gift of ',
                'User received a gift of ',
                'User received a bounty ',
                'User bought ',
                'Bounty of ',
                'Added +',
                'Removed -'
            ]
        )
    ],
    [
        'name'  => 'Badges',
        'rule' => implode('|', [
                'Badge '
            ]
        )
    ]
];