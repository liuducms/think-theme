<?php
/**
 * @copyright Copyright (c) 2022 程序员咸菜
 * @license https://opensource.org/licenses/Apache-2.0
 * @link https://www.ylcmcn.com
 */

declare(strict_types=1);

namespace think\themes\middleware;

use think\App;

class Theme
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
        hook('theme_middleware', $request);

        return $next($request);
    }
}