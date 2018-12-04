<?php
/*
 * This file is part of MODX Revolution.
 *
 * Copyright (c) MODX, LLC. All Rights Reserved.
 *
 * For complete copyright and license information, see the COPYRIGHT and LICENSE
 * files found in the top-level directory of this distribution.
 */

/**
 * @var modInstall $install
 * @var modInstallParser $parser
 * @var modInstallRequest $this
 * @package setup
 */
$install->settings->check();
if (!empty($_POST['proceed'])) {
    unset($_POST['proceed']);
    $install->settings->store($_POST);
    $this->proceed('complete');
}

$mode = $install->settings->get('installmode');
$install->getService('runner','runner.modInstallRunnerWeb');
$results = array();
if ($install->runner) {
    $success = $install->runner->run($mode);
    $results = $install->runner->getResults();

    $failed= false;
    foreach ($results as $item) {
        if ($item['class'] === 'failed') {
            $failed= true;
            break;
        }
    }
} else {
    $failed = true;
}
$parser->set('failed', $failed);
$parser->set('itemClass', $failed ? 'error' : '');
$parser->set('results',$results);

// Импортируем файл базы данных
if (!include(MODX_SETUP_PATH . 'includes/modMigrateToDev.class.php')) {
    die('<html><head><title></title></head><body><h1>FATAL ERROR: MODX Migrate cannot continue.</h1><p>Make sure you have uploaded all of the migrate/ files; your migrate/includes/modMigrateToDev.class.php file is missing.</p></body></html>');
}
$migrate = new modMigrateToDev($install);
var_dump($migrate);
$migrate->migrate();
// Конец импорта

return $parser->render('install.tpl');
                    
