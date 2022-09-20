<?php

/**
 * @copyright Copyright (c) 2022 程序员咸菜
 * @license https://opensource.org/licenses/Apache-2.0
 * @link https://www.ylcmcn.com
 */
 
declare(strict_types=1);

namespace think\themes;

use think\Route;
use think\helper\Str;
use think\facade\Config;
use think\facade\Lang;
use think\facade\Cache;
use think\facade\Event;
use think\themes\middleware\Themes; //中间件

/**
 * 主题服务
 * Class Service
 * @package think\theme
 */
class Service extends \think\Service
{
    protected $themes_path;
    // 注册
    public function register()
    {
        $this->themes_path = $this->getThemesPath();
        // 加载系统语言包
        Lang::load([
            $this->app->getRootPath() . '/vendor/liudu/think-theme/src/lang/zh-cn.php'
        ]);
        // 自动载入主题
        $this->autoload();
        // 加载主题事件
        $this->loadEvent();
        // 加载主题系统服务
        $this->loadService();
        // 绑定主题容器
        $this->app->bind('themes', Service::class);
    }

    public function boot()
    {
        $this->registerRoutes(function (Route $route) {
            // 路由脚本
            $execute = '\\think\\themes\\Route::execute';

            // 注册主题公共中间件
            if (is_file($this->app->themes->getThemesPath() . 'middleware.php')) {
                $this->app->middleware->import(include $this->app->themes->getThemesPath() . 'middleware.php', 'route');
            }

            // 注册控制器路由
            $route->rule("themes/:theme/[:controller]/[:action]", $execute)->middleware(Themes::class);
            // 自定义路由
            $routes = (array) Config::get('theme.route', []);
            foreach ($routes as $key => $val) {
                if (!$val) {
                    continue;
                }
                if (is_array($val)) {
                    $domain = $val['domain'];
                    $rules = [];
                    foreach ($val['rule'] as $k => $rule) {
                        [$theme, $controller, $action] = explode('/', $rule);
                        $rules[$k] = [
                            'themes'        => $theme,
                            'controller'    => $controller,
                            'action'        => $action,
                            'indomain'      => 1,
                        ];
                    }
                    $route->domain($domain, function () use ($rules, $route, $execute) {
                        // 动态注册域名的路由规则
                        foreach ($rules as $k => $rule) {
                            $route->rule($k, $execute)
                                ->name($k)
                                ->completeMatch(true)
                                ->append($rule);
                        }
                    });
                } else {
                    list($theme, $controller, $action) = explode('/', $val);
                    $route->rule($key, $execute)
                        ->name($key)
                        ->completeMatch(true)
                        ->append([
                            'theme' => $theme,
                            'controller' => $controller,
                            'action' => $action
                        ]);
                }
            }
        });
    }

    /**
     * 主题事件
     */
    private function loadEvent()
    {
        $hooks = $this->app->isDebug() ? [] : Cache::get('hooks', []);
        if (empty($hooks)) {
            $hooks = (array) Config::get('theme.hooks', []);
            // 初始化钩子
            foreach ($hooks as $key => $values) {
                if (is_string($values)) {
                    $values = explode(',', $values);
                } else {
                    $values = (array) $values;
                }
                $hooks[$key] = array_filter(array_map(function ($v) use ($key) {
                    return [get_theme_class($v), $key];
                }, $values));
            }
            Cache::set('hooks', $hooks);
        }
        //如果在主题中有定义 ThemeInit，则直接执行
        if (isset($hooks['ThemeInit'])) {
            foreach ($hooks['ThemeInit'] as $k => $v) {
                Event::trigger('ThemeInit', $v);
            }
        }
        Event::listenEvents($hooks); // 批量注册事件监听
    }

    /**
     * 挂载主题服务
     */
    private function loadService()
    {
        $results = scandir($this->theme_path);  // 主题列表
        $bind = [];
        foreach ($results as $name) {
            if ($name === '.' or $name === '..') {
                continue;
            }
            if (is_file($this->theme_path . $name)) {
                continue;
            }
            $themeDir = $this->theme_path . $name . DIRECTORY_SEPARATOR;
            if (!is_dir($themeDir)) {
                continue;
            }

            if (!is_file($themeDir . ucfirst($name) . '.php')) {
                continue;
            }

            $service_file = $themeDir . 'info.ini';  // 基本信息
            if (!is_file($service_file)) {
                continue;
            }
            $info = parse_ini_file($service_file, true, INI_SCANNER_TYPED) ?: [];
            $bind = array_merge($bind, $info);
        }
        $this->app->bind($bind); // 绑定
    }

    /**
     * 自动载入插件
     * @return bool
     */
    private function autoload()
    {
        // 是否处理自动载入
        if (!Config::get('theme.autoload', true)) {
            return true;
        }
        $config = Config::get('theme');
        // 读取主题目录及钩子列表
        $base = get_class_methods("\\think\\theme");
        // 读取主题目录中的php文件
        foreach (glob($this->getThemesPath() . '*/*.php') as $theme_file) {
            // 格式化路径信息
            $info = pathinfo($theme_file);
            // 获取主题目录名
            $name = pathinfo($info['dirname'], PATHINFO_FILENAME);
            // 找到主题入口文件
            if (strtolower($info['filename']) === 'plugin') {
                // 读取出所有公共方法
                $methods = (array)get_class_methods("\\theme\\" . $name . "\\" . $info['filename']);
                // 跟主题基类方法做比对，得到差异结果
                $hooks = array_diff($methods, $base);
                // 循环将钩子方法写入配置中
                foreach ($hooks as $hook) {
                    if (!isset($config['hooks'][$hook])) {
                        $config['hooks'][$hook] = [];
                    }
                    // 兼容手动配置项
                    if (is_string($config['hooks'][$hook])) {
                        $config['hooks'][$hook] = explode(',', $config['hooks'][$hook]);
                    }
                    if (!in_array($name, $config['hooks'][$hook])) {
                        $config['hooks'][$hook][] = $name;
                    }
                }
            }
        }
        Config::set($config, 'theme');  // 添加主题配置
    }

    /**
     * 获取 theme 路径
     * @return string
     */
    public function getThemesPath()
    {
        // 初始化主题目录
        $theme_path = $this->app->getRootPath() . 'theme' . DIRECTORY_SEPARATOR;
        // 如果主题目录不存在则创建
        if (!is_dir($theme_path)) {
            @mkdir($theme_path, 0755, true);
        }

        return $theme_path;
    }

    /**
     * 获取主题的配置信息
     * @param string $name
     * @return array
     */
    public function getThemesConfig()
    {
        $name = $this->app->request->theme;
        $theme = get_theme_instance($name);
        if (!$theme) {
            return [];
        }

        return $theme->getConfig();
    }
}