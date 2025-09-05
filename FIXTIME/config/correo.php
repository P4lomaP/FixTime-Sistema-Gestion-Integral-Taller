<?php
// config/correo.php
return [
    'smtp' => [
        // Ejemplo Gmail (usa contraseña de aplicación)
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'secure' => 'tls',      // 'tls' o 'ssl'
        'auth' => true,
        'usuario' => 'sebaxcabre692@gmail.com',
        'clave' => 'ueiabjfivjmipuwq', // no tu clave normal
        'from_email' => 'tu-correo@gmail.com',
        'from_name'  => 'Fixtime',

        // Si usas Hostinger u otro, ajusta:
        // 'host' => 'smtp.hostinger.com',
        // 'port' => 465,
        // 'secure' => 'ssl',
        // 'usuario' => 'no-reply@tudominio.com',
        // 'clave' => 'CLAVE_DE_ESE_CORREO',
        // 'from_email' => 'no-reply@tudominio.com',
        // 'from_name'  => 'Fixtime',
    ],
];
