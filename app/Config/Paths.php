<?php

namespace Config;

class Paths
{
    /**
     * ---------------------------------------------------------------
     * SYSTEM FOLDER NAME
     * ---------------------------------------------------------------
     * This variable must contain the name of your "system" folder.
     * Set the path if the folder is not in the same directory as
     * this file.
     */
    public string $systemDirectory = __DIR__ . '/../../vendor/codeigniter4/framework/system';

    /**
     * ---------------------------------------------------------------
     * APPLICATION FOLDER NAME
     * ---------------------------------------------------------------
     * If you want this front controller to use a different "app"
     * folder than the default one you can set its full path here.
     */
    public string $appDirectory = __DIR__ . '/..';

    /**
     * ---------------------------------------------------------------
     * WRITABLE FOLDER NAME
     * ---------------------------------------------------------------
     * Set the path to the "writable" directory. Must be writable by
     * the web server.
     */
    public string $writableDirectory = __DIR__ . '/../../writable';

    /**
     * ---------------------------------------------------------------
     * TESTS FOLDER NAME
     * ---------------------------------------------------------------
     */
    public string $testsDirectory = __DIR__ . '/../../tests';

    /**
     * ---------------------------------------------------------------
     * VIEW FOLDER NAME
     * ---------------------------------------------------------------
     */
    public string $viewDirectory = __DIR__ . '/../Views';
}
