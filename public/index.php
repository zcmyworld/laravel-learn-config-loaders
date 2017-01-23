<?php

//定义项目启动时间
define('LARAVEL_START', microtime(true));

//定义文件路径
define('APP_PATH', realpath('../application').'/');
define('SYS_PATH', realpath('../system').'/');
define('BASE_PATH', realpath('../').'/');

//定义文件后缀
define('EXT', '.php');

require SYS_PATH . 'config' . EXT;


spl_autoload_register(require SYS_PATH . 'loader' . EXT);


var_dump(\System\Config::get('test.testConfigKey'));
var_dump(\Application\TestLoader::exec());
var_dump(TestAliasLoader::exec());
