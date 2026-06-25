<?php
// Kopiatu fitxategi hau "config.php" izenarekin eta bete balio errealekin.
// config.php EZ da git-era igo behar (ikus .gitignore).
return [
    // MySQL datu-basea (zerbitzaritik konektatzen denez, urruneko baimenik EZ da behar)
    'host' => 'PMYSQL104.dns-servicio.com',
    'port' => 3306,
    'user' => 'oier',
    'pass' => 'ALDATU_PASAHITZA',
    'name' => '6437239_aramaixoporra',

    // Admin paneleko sarbide-pasahitza (HTTP Basic Auth).
    // Webgune publikoan dagoenez, NAHITAEZKOA da.
    'auth_user' => 'admin',
    'auth_pass' => 'ALDATU_ADMIN_PASAHITZA',
];
