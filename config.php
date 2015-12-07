<?php
$server_config =  array(
    65000 => array(
        'WebSocket' => array(
            'hosts' => array(
                'localhost' => array('WebChat', 'SimpleEcho'),
                '*.ivo.com' => array('SimpleEcho')
            )
        ),
        'ssl' => array(
            'file' => '',
            'passphrase' => ''
        )
    ),
    65001 => array(
        'RawTcp' => array()
    )
);
