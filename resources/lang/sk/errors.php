<?php

declare(strict_types=1);

return [
    'already_member' => 'Zadaný model je už členom tímu #:team.',
    'invite_expired' => 'Platnosť pozvánky „:code“ vypršala.',
    'invite_email_mismatch' => 'Táto pozvánka je určená pre „:email“.',
    'invite_exhausted' => 'Pozvánka „:code“ dosiahla limit použití.',
    'invite_not_found' => 'Pre kód „:code“ sa nenašla žiadna pozvánka.',
    'invite_team_missing' => 'Tím pozvánky #:id už neexistuje.',
    'invite_unavailable' => 'Pozvánka #:id už nie je k dispozícii.',
    'invite_not_in_team' => 'Pozvánka #:id nepatrí do tohto tímu.',
    'join_request_not_pending' => 'Žiadosť o pripojenie #:id už bola vybavená.',
    'join_request_not_in_team' => 'Žiadosť o pripojenie #:id nepatrí do tohto tímu.',
    'member_not_found' => 'Zadaný model nie je členom tímu #:team.',
    'join_policy_forbids_requests' => 'Tento tím je prístupný len na pozvanie a neprijíma žiadosti o pripojenie.',
    'invalid_config_value' => 'Konfiguračná hodnota [:key] musí byť :expected; nastavená hodnota je [:given].',
    'config_expectations' => [
        'positive_interval' => 'kladný interval, napríklad „7 days“',
        'non_negative_interval' => 'nulový alebo kladný interval, napríklad „30 days“',
    ],
    'max_seats_reached' => '{0} Kapacita tohto tímu (:count miest) je naplnená.|{1} Kapacita tohto tímu (:count miesto) je naplnená.|[2,4] Kapacita tohto tímu (:count miesta) je naplnená.|[5,*] Kapacita tohto tímu (:count miest) je naplnená.',
];
