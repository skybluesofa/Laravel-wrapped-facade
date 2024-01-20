<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Log in Given Environment
    |--------------------------------------------------------------------------
    |
    | In any given environment, should we log before/after method calls?
    |
    | Available options:
    | '*': Logs in all environments
    | '<env_name>': Logs only when in the given environment
    | ['<env1>', '<env2>']: Logs when in any of the provided environments
    | null: Does not log
    |
    */
    'log_in_environment' => '*',

    /*
    |--------------------------------------------------------------------------
    | Method Prefixes
    |--------------------------------------------------------------------------
    |
    | Adds the assigned prefixes to the pre- and post- method names
    |
    */
    'prefix' => [
        'pre' => 'pre',
        'post' => 'post',
    ],
];
