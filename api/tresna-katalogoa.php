<?php
/**
 * Aramaixo Porra — tresna publikoen KATALOGOA (iturri bakarra).
 *
 * ⚠️ EZ da endpoint bat: `api/ezarpenak.php`-k eta `admin/lib.php`-k barnetik kargatzen
 * dute (`require_once`). Zuzeneko sarbidea `api/.htaccess`-en blokeatuta dago.
 *
 * Hemen dago `/tresnak/` orriko txartel bakoitzaren edukia (ikonoa, izena, azalpena).
 * Orria katalogo honetatik marrazten da (`tresnak/index.html`), beraz testuak LEKU
 * BAKARREAN daude: hemen aldatuz gero, webgunean eta admin panelean batera aldatzen dira.
 *
 * Tresna bat ikusgai dagoen `admin/ezarpenak.json`-ek erabakitzen du (admin panela →
 * Webgunea). Katalogoan egoteak EZ du esan nahi ikusgai dagoenik; katalogoan EZ egoteak
 * bai, ordea, ez dela inoiz agertuko eta ez dela adminetik kudeatzen.
 *
 * ⚠️ `sariak` (tresnak/sariak/) NAHITA dago katalogotik KANPO: sariak webgunetik kendu
 * ziren eta ez da berriz pizteko aukera gisa eskaini behar. Orria bere horretan dago.
 */

/** Tresna publikoak, `/tresnak/` orrian agertzen diren ordenan. */
const TRESNA_KATALOGOA = [
    [
        'id'       => 'grafikoak',
        'ikonoa'   => '📈',
        'izena'    => 'Eboluzio grafikoak',
        'azalpena' => 'Lasterketa batean zehar txirrindulari edo porralarien eboluzioa (puntuak edo postua).',
        'bidea'    => '/tresnak/grafikoak/',
    ],
    [
        'id'       => 'porra-fitxa',
        'ikonoa'   => '🔎',
        'izena'    => 'Porra fitxa',
        'azalpena' => 'Porra bat bilatu eta bere talde osoa, puntuak, postua eta eboluzioa ikusi.',
        'bidea'    => '/tresnak/porra-fitxa/',
    ],
    [
        'id'       => 'konparatzailea',
        'ikonoa'   => '⚔️',
        'izena'    => 'Porra konparatzailea',
        'azalpena' => '2-4 porra alderatu: aukera komun eta ezberdinak, puntuak eta eboluzioa.',
        'bidea'    => '/tresnak/konparatzailea/',
    ],
    [
        'id'       => 'bero-mapa',
        'ikonoa'   => '🟩',
        'izena'    => 'Aukeren bero-mapa',
        'azalpena' => 'Porra bakoitzak zein txirrindulari aukeratu zituen, matrize bisual batean.',
        'bidea'    => '/tresnak/bero-mapa/',
    ],
    [
        'id'       => 'porralari-fitxa',
        'ikonoa'   => '👤',
        'izena'    => 'Porralari fitxa',
        'azalpena' => 'Porralari batek bota dituen porrak eta ezaugarriak, batera botatakoak barne.',
        'bidea'    => '/tresnak/porralari-fitxa/',
    ],
    [
        'id'       => 'txirrindulari-fitxa',
        'ikonoa'   => '🚴',
        'izena'    => 'Txirrindulari fitxa',
        'azalpena' => 'Txirrindulari baten partaidetzak: dortsala, postua, puntuak eta zenbat porrak aukeratu duten.',
        'bidea'    => '/tresnak/txirrindulari-fitxa/',
    ],
    [
        'id'       => 'porra-ideala',
        'ikonoa'   => '🏆',
        'izena'    => 'Porra ideala',
        'azalpena' => 'Posible zen porrarik onena vs benetan aukeratutakoa; zenbat puntu galdu ziren.',
        'bidea'    => '/tresnak/porra-ideala/',
    ],
    [
        'id'       => 'porralariak-konparatzailea',
        'ikonoa'   => '👥',
        'izena'    => 'Porralariak konparatzailea',
        'azalpena' => '2-4 porralari alderatu txapelketa guztietan: posizioak, garaipenak eta aurrez-aurrekoa.',
        'bidea'    => '/tresnak/porralariak-konparatzailea/',
    ],
    [
        'id'       => 'porra-prestatu',
        'ikonoa'   => '📝',
        'izena'    => 'Porra prestatu',
        'azalpena' => 'Sailkatu txirrindulariak zure listetan (Etapa, Mendikoa, Generala...) eta osatu hortik zure porra zirriborroak, alderatzeko.',
        'bidea'    => '/tresnak/porra-prestatu/',
    ],
    [
        'id'       => 'porra-bidali',
        'ikonoa'   => '📥',
        'izena'    => 'Porra bidali',
        'azalpena' => 'Porra irekita dagoen txapelketetan, aukeratu zure txirrindulariak eta bidali porra aurretik. Gero ere Anboto tabernara joan behar duzu.',
        'bidea'    => '/tresnak/porra-bidali/',
    ],
    [
        'id'       => 'zuzenketak',
        'ikonoa'   => '✍️',
        'izena'    => 'Zuzenketak proposatu',
        'azalpena' => 'Akatsen bat ikusi duzu? Jakinarazi: apustu okerra, izen aldaketa, porra-lotura edo bi porralari bat direla.',
        'bidea'    => '/tresnak/zuzenketak/',
    ],
];
