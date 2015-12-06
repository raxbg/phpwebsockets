<?hh
$server_config = Map {
    65000 => Map {
        'WebSocket' => Map {
            'hosts' => Map {
                'localhost' => Vector {'WebChat', 'SimpleEcho'},
                '*.ivo.com' => Vector {'SimpleEcho'}
            }
        },
        'ssl' => Map {
            'file' => '',
            'passphrase' => ''
        }
    },
    65001 => Map {
        'RawTcp' => Map {}
    }
};
