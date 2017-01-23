# 从0开始写laravel-配置文件与动态加载

## 踏上征程

希望你能愉快地学习laravel的源代码， 先创建项目目录： laravel.learn.com
首先，我们在项目下创建3个文件目录：

* application - 用于存放项目业务代码
* public - 存放nginx可访问文件，如index.php
* system - 存放框架文件

项目使用 Nginx + phpfpm 访问

## 配置常量

在public目录下创建 index.php 并添加如下代码：

    //定义项目启动时间
    define('LARAVEL_START', microtime(true));

    //定义文件路径
    define('APP_PATH', realpath('../application').'/');
    define('SYS_PATH', realpath('../system').'/');
    define('BASE_PATH', realpath('../').'/');

	//定义文件后缀
    define('EXT', '.php');

通过上面的代码，创建一些系统常用常量。

## 配置文件

一个项目中，可能有多个类型的配置项，比如关于数据库连接信息的配置项，比如关于session信息的配置项，我们希望不同类型的配置存放到不同的文件中，
我们使用一个 Config.php 文件来集中管理这些配置。实际上，这里用到了工厂方法模式，Config.php可以理解成一个工厂，根据不同的参数来返回不同的配置。

### 功能需求：

1. 框架使用者可以添加不同类型的配置文件
2. 框架的使用者可以根据配置文件的类型和key来获取对应的值

### 功能设计：

1. 在 application 目录下创建 config 目录，用于存放所有的配置文件，
2. 采用形如 Config::get('application.aliases') 的方式，来获取application配置文件下key为 aliases 的值

### 功能实现：

在 application 目录下创建 config 目录， 创建 application.php 并添加如下代码：

	<?php

	return array(
      'aliases' => array(
          'Auth' => 'System\\Auth',
          'Benchmark' => 'System\\Benchmark',
          'Cache' => 'System\\Cache',
          'Config' => 'System\\Config',
          'Cookie' => 'System\\Cookie',
          'Crypt' => 'System\\Crypt',
          'Date' => 'System\\Date',
          'DB' => 'System\\DB',
          'Download' => 'System\\Download',
          'Eloquent' => 'System\\DB\\Eloquent',
          'Form' => 'System\\Form',
          'Hash' => 'System\\Hash',
          'HTML' => 'System\\HTML',
          'Inflector' => 'System\\Inflector',
          'Input' => 'System\\Input',
          'Lang' => 'System\\Lang',
          'URL' => 'System\\URL',
          'Redirect' => 'System\\Redirect',
          'Request' => 'System\\Request',
          'Response' => 'System\\Response',
          'Session' => 'System\\Session',
          'Str' => 'System\\Str',
          'Text' => 'System\\Text',
          'View' => 'System\View',
      )
	);

这个配置文件用于设置类的别名，使得调用类的时候写法更加简便。

在 system 目录下新建文件 config.php， 并添加静态变量 $item 和 get 方法

	namespace System;
	class Config
	{
      // 用于存放配置
      private static $items = array();

      public static function get($key)
      {

      }
	}


当开发者想要获取配置文件application中的aliases的值时，我们希望他这样传参数： Config::get('application.aliases')，所以增加私有个对参数的解析函数


	private static function parse($key)
    {
    	$segments = explode('.', $key);

        // 当参数格式不对的时候，抛出异常
        if (count($segments) < 2)
        {
        	throw new \Exception("Invalid configuration key [$key].");
        }

        return array($segments[0], implode('.', array_slice($segments, 1)));
    }

对参数进行解析之后，需要制定参数去加载配置文件，因此，增加 load() 方法

    public static function load($file)
	{
		// 当配置文件已经被加载过，不再重复加载
		if (array_key_exists($file, static::$items))
		{
			return;
		}

		//配置文件不存在的时候，抛出异常
		if ( ! file_exists($path = APP_PATH.'config/'.$file.EXT))
		{
			throw new \Exception("Configuration file [$file] does not exist.");
		}

		//加载配置文件并赋值到static::$items中
		static::$items[$file] = require $path;
	}

在 Config::get() 方法中加上 parse 和 load 的调用，最终完成功能：


    public static function get($key)
	{
		//调用参数解析函数
		list($file, $key) = static::parse($key);

		//加载配置文件
		static::load($file);

		//返回对应的配置
		return (array_key_exists($key, static::$items[$file])) ? static::$items[$file][$key] : null;
	}

最后，在 public/index.php 文件添加这句话，

	require SYS_PATH . 'config' . EXT;

## 动态加载

在没有使用动态加载的情况下，每一个php文件都需要使用 require(或者include等) 方法来引入第三法 php 文件。使用动态加载，可以避免这样的重复书写。

在 system 目录下创建文件 loader.php, 并添加以下代码：

	<?php

    return function($class)
    {

    };

在 public/index.php 文件添加这句话，

	spl_autoload_register(require SYS_PATH . 'loader' . EXT);

这样，会引入 system/loader.php 文件，并将其返回的函数作为 __autoload 的实现，当 php 程序调用一个类的时候，会将类的名称作为参数传入 loader.php 的函数中。

接下来，我们修改 loader.php 来实现动态加载。

### 功能需求

1.  支持使用配置文件中的别名来加载类
2.  自动加载 application 目录下的所有类

## 功能实现

将 loader 里面的类名称中的 '\\' 替换成 '/' , 目的是将命名空间中的 ‘\\’替换成系统目录路经， loader.php 加上这句：

	$file = str_replace('\\', '/', $class);

获取配置文件中 aliases 的描述，为类赋予别名，loader.php 加上这句

	// 先判断当前调用的类在配置文件中是否定义了别名
	if (array_key_exists($class, $aliases = System\Config::get('application.aliases')))
	{
	    return class_alias($aliases[$class], $class);
    }

加载 根目录 目录下的所有类，loader.php 加上这句

	elseif (file_exists($path = BASE_PATH.$file.EXT))
	{
		require $path;
	}

加载 application 目录下的所有类，loader.php 加上这句

	elseif (file_exists($path = APP_PATH.$file.EXT))
	{
		require $path;
	}

最后，我们测试下配置管理和动态加载两个功能是否完成：

## 测试配置文件加载

在 application/config 下新建文件并添加以下代碼：

	<?php

	return array(
	    "testConfigKey" => "testConfigValue"
	);

在 public/index.php 添加调用并查看结果

	var_dump(\System\Config::get('test.testConfigKey'));


## 测试动态加载
在 application 目录下添加 testloader.php 文件：

	<?php

	namespace Application;

	class TestLoader
	{
	    public static function exec()
	    {
	        return "I am test TestLoader";
	    }
	}

在 public/index.php 添加调用并查看结果

	var_dump(\Application\TestLoader::exec());

## 测试使用别名的动态加载

在 application/config/application.php文件中的 aliases  数组中加上这一项：

	'TestAliasLoader' => 'Application\\TestAliasLoader',

在 application 目录下添加 testaliasloader.php 文件：

	<?php

	namespace Application;

	class TestAliasLoader
	{
	    public static function exec()
	    {
	        return "I am test TestAliasLoader";
	    }
	}

在 public/index.php 添加调用并查看结果

	var_dump(TestAliasLoader::exec());

测试完成之后，删除测试代码，整个框架项目结构形如：

* application
	* |- config
		* |-applicationi.php
* public
	* |- index.php
* system
	* |- config.php
	* |- loader.php
