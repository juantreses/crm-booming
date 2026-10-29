<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Isset_\IssetOnPropertyObjectToPropertyExistsRector;
use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;

/**
 * Rector only touches our own code in custom/. Espo core (application/, vendor/)
 * is replaced on every Espo upgrade and must never be rewritten.
 *
 * Run via bin/qa (see README): `bin/qa rector` = dry-run, `bin/qa rector-fix` = apply.
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/custom/Espo',
    ])
    // Espo's own autoloader knows Espo\, Espo\Custom\ and Espo\Modules\,
    // so Rector can resolve the core types our code extends/uses.
    ->withAutoloadPaths([
        __DIR__ . '/vendor/autoload.php',
    ])
    ->withSkip([
        __DIR__ . '/custom/Espo/Modules/*/vendor',
        // Licensed EspoCRM extension: replaced on extension updates, not our code.
        __DIR__ . '/custom/Espo/Modules/Google',
        // Changes runtime behaviour (scalar coercion -> TypeError). Enable deliberately, module by module.
        SafeDeclareStrictTypesRector::class,
        // isset() is false for null values, property_exists() is not; also breaks on Espo's magic entity props.
        IssetOnPropertyObjectToPropertyExistsRector::class,
    ])
    // Target = the PHP version production runs today (Espo 9.3 requires >= 8.3).
    // Bump to php84/php85 only once production actually runs that version,
    // otherwise Rector introduces syntax the server can't parse.
    ->withPhpSets(php83: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        earlyReturn: true,
    )
    ->withImportNames(importShortClasses: false, removeUnusedImports: true);
