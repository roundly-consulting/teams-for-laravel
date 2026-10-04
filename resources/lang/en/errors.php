<?php

declare(strict_types=1);

return [
    'already_member' => 'The given model is already a member of team #:team.',
    'invite_expired' => 'The invite ":code" has expired.',
    'invite_email_mismatch' => 'This invite is addressed to ":email".',
    'invite_exhausted' => 'The invite ":code" has reached its usage limit.',
    'invite_not_found' => 'No invite was found for the code ":code".',
    'invite_team_missing' => 'The team of invite #:id no longer exists.',
    'invite_unavailable' => 'Invite #:id is no longer available.',
    'invite_not_in_team' => 'Invite #:id does not belong to this team.',
    'join_request_not_pending' => 'Join request #:id has already been resolved.',
    'join_request_not_in_team' => 'Join request #:id does not belong to this team.',
    'member_not_found' => 'The given model is not a member of team #:team.',
    'join_policy_forbids_requests' => 'This team is invite-only and does not accept join requests.',
    'max_seats_reached' => 'This team has reached its seat limit (:count seat).|This team has reached its seat limit (:count seats).',
];
