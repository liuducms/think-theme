<?php
/**
 * @copyright Copyright (c) 2022 程序员咸菜
 * @license https://opensource.org/licenses/Apache-2.0
 * @link https://www.ylcmcn.com
 */

declare(strict_types=1);

namespace think\themes\middleware;

use think\App;
use think\facade\Env;
use think\Exception;
class Themes
{
    protected $app;

    public function __construct(App $app)
    {
        $this->app  = $app;
    }

    /**
     * 主题中间件
     * @param $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        
        $path = $request->pathinfo();
        $arr = explode('/',$path);
	    $theme = Env::get("theme.name");
        // 如果有主题 就跳转到主题主页
        if(empty($theme)){
            abort(404,"未设置主题模版");
        }else{
            if($arr[1] == $theme){
              
                $theme_info = get_themes_info($theme);
                $theme_status = $theme_info['status'];
                if($theme_status != 1){
                    abort(404,"主题已关闭");
                }
            }else{
                abort(404,"主题不存在");
            }
        }
    //     // 主题中间件
        hook('theme_middleware', $request);

        return $next($request);
    }
}
