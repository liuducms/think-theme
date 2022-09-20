<?php

/**
 * @copyright Copyright (c) 2022 程序员咸菜
 * @license https://opensource.org/licenses/Apache-2.0
 * @link https://www.ylcmcn.com
 */
 
declare(strict_types=1);

namespace think;

use think\App;
use think\helper\Str;
use think\facade\Config;
use think\facade\View;


abstract class Themes
{
    // app 容器
    protected $app;
    // 请求对象
    protected $request;
    // 当前主题标识
    protected $name;
    // 主题路径
    protected $theme_path;
    // 视图模型
    protected $view;
    // 主题配置
    protected $theme_config;
    // 主题信息
    protected $theme_info;

    /**
     * 主题构造函数
     * Themes constructor.
     * @param \think\App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $app->request;
        $this->name = $this->getName();
        $this->theme_path = $app->themes->getThemesPath() . $this->name . DIRECTORY_SEPARATOR;
        $this->theme_config = "theme_{$this->name}_config";
        $this->theme_info = "theme_{$this->name}_info";
        $this->view = clone View::engine('Think');
        $this->view->config([
            'view_path' => $this->theme_path . 'view' . DIRECTORY_SEPARATOR
        ]);
        // 控制器初始化
        $this->initialize();
    }

    // 初始化
    protected function initialize()
    {}

    /**
     * 获取主题标识
     * @return mixed|null
     */
    final protected function getName()
    {
        $class = get_class($this);
        list(, $name, ) = explode('\\', $class);
        $this->request->theme = $name;

        return $name;
    }

    /**
     * 加载模板输出
     * @param string $template
     * @param array $vars           模板文件名
     * @return false|mixed|string   模板输出变量
     * @throws \think\Exception
     */
    protected function fetch($template = '', $vars = [])
    {
        return $this->view->fetch($template, $vars);
    }

    /**
     * 渲染内容输出
     * @access protected
     * @param  string $content 模板内容
     * @param  array  $vars    模板输出变量
     * @return mixed
     */
    protected function display($content = '', $vars = [])
    {
        return $this->view->display($content, $vars);
    }

    /**
     * 模板变量赋值
     * @access protected
     * @param  mixed $name  要显示的模板变量
     * @param  mixed $value 变量的值
     * @return $this
     */
    protected function assign($name, $value = '')
    {
        $this->view->assign([$name => $value]);

        return $this;
    }

    /**
     * 初始化模板引擎
     * @access protected
     * @param  array|string $engine 引擎参数
     * @return $this
     */
    protected function engine($engine)
    {
        $this->view->engine($engine);

        return $this;
    }

    /**
     * 主题基础信息
     * @return array
     */
    final public function getInfo()
    {
        $info = Config::get($this->theme_info, []);
        if ($info) {
            return $info;
        }

        // 文件属性
        $info = $this->info ?? [];
        // 文件配置
        $info_file = $this->theme_path . 'info.ini';
        if (is_file($info_file)) {
            $_info = parse_ini_file($info_file, true, INI_SCANNER_TYPED) ?: [];
            $_info['url'] = themes_url();
            $info = array_merge($_info, $info);
        }
        Config::set($info, $this->theme_info);

        return isset($info) ? $info : [];
    }

    /**
     * 获取配置信息
     * @param bool $type 是否获取完整配置
     * @return array|mixed
     */
    final public function getConfig($type = false)
    {
        $config = Config::get($this->theme_config, []);
        if ($config) {
            return $config;
        }
        $config_file = $this->theme_path . 'config.php';
        if (is_file($config_file)) {
            $temp_arr = (array)include $config_file;
            if ($type) {
                return $temp_arr;
            }
            foreach ($temp_arr as $key => $value) {
                $config[$key] = $value['value'];
            }
            unset($temp_arr);
        }
        Config::set($config, $this->theme_config);

        return $config;
    }

    //必须实现安装
    abstract public function install();

    //必须卸载插件方法
    abstract public function uninstall();
    
    // // 必须实现配置函数
    // abstract public function config();
    
    
}