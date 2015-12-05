<?hh
$hosts = Map {
    'localhost' => Map {
        'ports' => Map {
            65000 => 'Echo',
            65001 => 'WebChat'
        }
    },
    '*.ivo.com' => Map {
        'ports' => Map {
            65000 => 'Echo'
        }
    }
};
