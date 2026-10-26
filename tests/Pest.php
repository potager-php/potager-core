<?php

use Tests\Support\FrameworkIntegrationTestCase;
use Tests\Support\ModelTestCase;

if (!is_writable(session_save_path() ?: '/tmp')) {
    session_save_path(sys_get_temp_dir());
}

pest()->extend(FrameworkIntegrationTestCase::class)->in('Feature');
pest()->extend(ModelTestCase::class)->in('Unit/Models');
