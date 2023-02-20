<?php
/**
 * @copyright Copyright (c) 2022 程序员咸菜
 * @license https://opensource.org/licenses/Apache-2.0
 * @link https://www.ylcmcn.com
 */
 
declare(strict_types=1);

namespace think\themes;

use think\helper\Str;
use think\facade\Event;
use think\facade\Config;
use think\exception\HttpException;

class Route
{
    /**
     * 主题路由请求
     * @param null $theme
     * @param null $controller
     * @param null $action
     * @return mixed
     */
    public static function execute($theme = null, $controller = null, $action = null)
    {
        $app = app();
        $request = $app->request;
        $convert = Config::get('route.url_convert');
        $filter = $convert ? 'strtolower' : 'trim';
        $theme = $theme ? trim(call_user_func($filter, $theme)) : '';
        $controller = $controller ? trim(call_user_func($filter, $controller)) : 'index';
        $action = $action ? trim(call_user_func($filter, $action)) : 'index';
        Event::trigger('themes_begin', $request);

        if (empty($theme) || empty($controller) || empty($action)) {
            throw new HttpException(500, lang('theme can not be empty'));
        }

        $request->theme = $theme;
        // 设置当前请求的控制器、操作
        $request->setController($controller)->setAction($action);
         
        // 获取主题基础信息
        $info = get_themes_info($theme);
        
        if (!$info) {
            throw new HttpException(404, lang('theme %s not found', [$theme]));
        }
        if (!$info['status']) {
            throw new HttpException(500, lang('theme %s is disabled', [$theme]));
        }

        // 监听theme_module_init
        Event::trigger('theme_module_init', $request);
        $class = get_themes_class($theme, 'controller', $controller);
        if (!$class) {
            throw new HttpException(404, lang('theme controller %s not found', [Str::studly($controller)]));
        }

        // 重写视图基础路径
        $config = Config::get('view');
        $config['view_path'] = $app->themes->getThemesPath() . $theme . DIRECTORY_SEPARATOR . 'view' . DIRECTORY_SEPARATOR;
        Config::set($config, 'view');

        // 生成控制器对象
        $instance = new $class($app);
        $vars = [];
        if (is_callable([$instance, $action])) {
            // 执行操作方法
            $call = [$instance, $action];
        } elseif (is_callable([$instance, '_empty'])) {
            // 空操作
            $call = [$instance, '_empty'];
            $vars = [$action];
        } else {
            // 操作不存在
            throw new HttpException(404, lang('theme action %s not found', [get_class($instance).'->'.$action.'()']));
        }
        Event::trigger('themes_action_begin', $call);

        return call_user_func_array($call, $vars);
    }
}
