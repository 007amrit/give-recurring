<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit24cdbcf7dde8016b83c25f7706b619b9
{
    public static $prefixLengthsPsr4 = array (
        'G' => 
        array (
            'GiveRecurring\\' => 14,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'GiveRecurring\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit24cdbcf7dde8016b83c25f7706b619b9::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit24cdbcf7dde8016b83c25f7706b619b9::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit24cdbcf7dde8016b83c25f7706b619b9::$classMap;

        }, null, ClassLoader::class);
    }
}
