<?php

/**
 * @copyright Copyright (c) 2022 程序员咸菜
 * @license https://opensource.org/licenses/Apache-2.0
 * @link https://www.ylcmcn.com
 */
 
declare(strict_types=1);

use think\facade\Event;
use think\facade\Route;
use think\helper\{Str,Arr};

\think\Console::starting(function (\think\Console $console) {
    // 命令 添加主题初始化
    $console->addCommands([
        'themes:config' => '\\think\\themes\\command\\SendConfig'
    ]);
});

// 主题类库自动载入
spl_autoload_register(function ($class) {

    $class = ltrim($class, '\\');

    $dir = app()->getRootPath();
    $namespace = 'themes';

    if (strpos($class, $namespace) === 0) {
        $class = substr($class, strlen($namespace));
        $path = '';
        if (($pos = strripos($class, '\\')) !== false) {
            $path = str_replace('\\', '/', substr($class, 0, $pos)) . '/';
            $class = substr($class, $pos + 1);
        }
        $path .= str_replace('_', '/', $class) . '.php';
        $dir .= $namespace . $path;

        if (file_exists($dir)) {
            include $dir;
            return true;
        }

        return false;
    }

    return false;

});

if (!function_exists('hook')) {
    /**
     * 处理主题钩子
     * @param string $event 钩子名称
     * @param array|null $params 传入参数
     * @param bool $once 是否只返回一个结果
     * @return mixed
     */
    function hook($event, $params = null, bool $once = false)
    {
        $result = Event::trigger($event, $params, $once);

        return join('', $result);
    }
}

if (!function_exists('get_themes_info')) {
    /**
     * 读取主题的基础信息
     * @param string $name 主题名
     * @return array
     */
    function get_themes_info($name)
    {
        $theme = get_themes_instance($name);
        if (!$theme) {
            return [];
        }

        return $theme->getInfo();
    }
}

if (!function_exists('get_themes_instance')) {
    /**
     * 获取主题的单例
     * @param string $name 主题名
     * @return mixed|null
     */
    function get_themes_instance($name)
    {
        static $_themes = [];
        if (isset($_themes[$name])) {
            return $_themes[$name];
        }
        $class = get_themes_class($name);
        if (class_exists($class)) {
            $_themes[$name] = new $class(app());

            return $_themes[$name];
        } else {
            return null;
        }
    }
}

if (!function_exists('get_themes_class')) {
    /**
     * 获取主题类的类名
     * @param string $name 主题名
     * @param string $type 返回命名空间类型
     * @param string $class 当前类名
     * @return string
     */
    function get_themes_class($name, $type = 'hook', $class = null)
    {
        $name = trim($name);
        // 处理多级控制器情况
        if (!is_null($class) && strpos($class, '.')) {
            $class = explode('.', $class);

            $class[count($class) - 1] = Str::studly(end($class));
            $class = implode('\\', $class);
        } else {
            $class = Str::studly(is_null($class) ? $name : $class);
        }
        switch ($type) {
            case 'controller':
                $namespace = '\\themes\\' . $name . '\\controller\\' . $class;
                break;
            default:
                $namespace = '\\themes\\' . $name . '\\Plugin';  //  入口
        }

        return class_exists($namespace) ? $namespace : '';
    }
}

if (!function_exists('themes_url')) {
    /**
     * 主题显示内容里生成访问主题的url
     * @param $url
     * @param array $param
     * @param bool|string $suffix 生成的URL后缀
     * @param bool|string $domain 域名
     * @return bool|string
     */
    function themes_url($url = '', $param = [], $suffix = true, $domain = false)
    {
        $request = app('request');
        if (empty($url)) {
            // 生成 url 模板变量
            $themes = $request->theme;
            $controller = $request->controller();
            $controller = str_replace('/', '.', $controller);
            $action = $request->action();
        } else {
            $url = Str::studly($url);
            $url = parse_url($url);
            if (isset($url['scheme'])) {
                $themes = strtolower($url['scheme']);
                $controller = $url['host'];
                $action = trim($url['path'], '/');
            } else {
                $route = explode('/', $url['path']);
                $themes = $request->theme;
                $action = array_pop($route);
                $controller = array_pop($route) ?: $request->controller();
            }
            $controller = Str::snake((string)$controller);

            /* 解析URL带的参数 */
            if (isset($url['query'])) {
                parse_str($url['query'], $query);
                $param = array_merge($query, $param);
            }
        }

        return Route::buildUrl("@themes/{$themes}/{$controller}/{$action}", $param)->suffix($suffix)->domain($domain);
    }
}