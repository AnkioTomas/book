<title id="title">仪表盘 - {$title}</title>
<style id="style">
    /* 主题自适应的渐变背景，使用 MDUI 颜色变量 */
    .stat-card-pages { background: linear-gradient(135deg, rgba(var(--mdui-color-primary), 0.08) 0%, rgba(var(--mdui-color-primary), 0.16) 100%); }
    .stat-card-comments { background: linear-gradient(135deg, rgba(var(--mdui-color-secondary), 0.08) 0%, rgba(var(--mdui-color-secondary), 0.16) 100%); }
    .stat-card-views { background: linear-gradient(135deg, rgba(var(--mdui-color-tertiary), 0.08) 0%, rgba(var(--mdui-color-tertiary), 0.16) 100%); }
    .stat-card-sites { background: linear-gradient(135deg, rgba(var(--mdui-color-error), 0.08) 0%, rgba(var(--mdui-color-error), 0.16) 100%); }

    /* 深色模式下稍微提高透明度，增强对比 */
    .mdui-theme-dark .stat-card-pages { background: linear-gradient(135deg, rgba(var(--mdui-color-primary), 0.18) 0%, rgba(var(--mdui-color-primary), 0.26) 100%); }
    .mdui-theme-dark .stat-card-comments { background: linear-gradient(135deg, rgba(var(--mdui-color-secondary), 0.18) 0%, rgba(var(--mdui-color-secondary), 0.26) 100%); }
    .mdui-theme-dark .stat-card-views { background: linear-gradient(135deg, rgba(var(--mdui-color-tertiary), 0.18) 0%, rgba(var(--mdui-color-tertiary), 0.26) 100%); }
    .mdui-theme-dark .stat-card-sites { background: linear-gradient(135deg, rgba(var(--mdui-color-error), 0.18) 0%, rgba(var(--mdui-color-error), 0.26) 100%); }

    /* 图标使用对应主题主色，文本仍走系统 on-surface 保证可读性 */
    .stat-card-pages mdui-icon { color: rgba(var(--mdui-color-primary)); }
    .stat-card-comments mdui-icon { color: rgba(var(--mdui-color-secondary)); }
    .stat-card-views mdui-icon { color: rgba(var(--mdui-color-tertiary)); }
    .stat-card-sites mdui-icon { color: rgba(var(--mdui-color-error)); }
</style>

<div id="container" class="container p-4 h-fit">
    <div>
        <!-- 全局统计卡片 -->
        <div class="row col-space16 mb-4">
            <div class="col-xs12 col-sm6 col-md3">
                <mdui-card class="w-100 p-3 stat-card-pages">
                    <div class="label-medium d-flex items-center">
                        <mdui-icon name="description" class="mr-1"></mdui-icon>
                        总页面数
                    </div>
                    <div class="display-medium mt-3 mb-3">
                        <span class="animate-number" data-value="{$globalStats.totalPages}">0</span>
                    </div>
                    <div class="body-small d-flex items-center">
                        <mdui-icon name="article" size="small" class="mr-1"></mdui-icon>
                        文章: {$globalStats.articleCount} | 分类: {$globalStats.folderCount}
                    </div>
                </mdui-card>
            </div>
            <div class="col-xs12 col-sm6 col-md3">
                <mdui-card class="w-100 p-3 stat-card-comments">
                    <div class="label-medium d-flex items-center">
                        <mdui-icon name="comment" class="mr-1"></mdui-icon>
                        总评论数
                    </div>
                    <div class="display-medium mt-3 mb-3">
                        <span class="animate-number" data-value="{$globalStats.totalComments}">0</span>
                    </div>
                    <div class="body-small d-flex items-center">
                        <mdui-icon name="schedule" size="small" class="mr-1"></mdui-icon>
                        今日: {$globalStats.todayComments}
                    </div>
                </mdui-card>
            </div>
            <div class="col-xs12 col-sm6 col-md3">
                <mdui-card class="w-100 p-3 stat-card-views">
                    <div class="label-medium d-flex items-center">
                        <mdui-icon name="visibility" class="mr-1"></mdui-icon>
                        总浏览量
                    </div>
                    <div class="display-medium mt-3 mb-3">
                        <span class="animate-number" data-value="{$globalStats.totalViews}">0</span>
                    </div>
                    <div class="body-small d-flex items-center">
                        <mdui-icon name="trending_up" size="small" class="mr-1"></mdui-icon>
                        今日: {$globalStats.todayViews}
                    </div>
                </mdui-card>
            </div>
            <div class="col-xs12 col-sm6 col-md3">
                <mdui-card class="w-100 p-3 stat-card-sites">
                    <div class="label-medium d-flex items-center">
                        <mdui-icon name="public" class="mr-1"></mdui-icon>
                        站点数量
                    </div>
                    <div class="display-medium mt-3 mb-3">
                        <span class="animate-number" data-value="{$globalStats.totalSites}">0</span>
                    </div>
                    <div class="body-small d-flex items-center">
                        <mdui-icon name="book" size="small" class="mr-1"></mdui-icon>
                        博客: {$globalStats.blogSites} | 文档: {$globalStats.docSites}
                    </div>
                </mdui-card>
            </div>
        </div>

        {if $sitesData}
        <!-- 站点数据选项卡 -->
        <mdui-tabs value="tab-all" class="mb-4">
            <mdui-tab value="tab-all">
                <mdui-icon name="dashboard"></mdui-icon>
                全部站点汇总
            </mdui-tab>
            {foreach $sitesData as $index => $siteData}
            <mdui-tab value="tab-{$index}">
                <mdui-icon name="{if $siteData.site.type == 'blog'}rss_feed{else}description{/if}"></mdui-icon>
                {$siteData.site.name}
                <span class="py-1 px-3 rounded-2xl font-500 ml-2 {if $siteData.site.type == 'blog'}bg-error text-on-error{else}bg-primary text-on-primary{/if}">
                    {if $siteData.site.type == 'blog'}博客{else}文档{/if}
                </span>
            </mdui-tab>
            {/foreach}
        </mdui-tabs>

        <!-- 全部站点汇总面板 -->
        <mdui-tab-panel slot="panel" value="tab-all" class="pt-3">
            <div class="row col-space16">
                <div class="col-xs12">
                    <mdui-card>
                        <div class="label-medium d-flex items-center">
                            <mdui-icon name="public" class="mr-1"></mdui-icon>
                            站点概览
                        </div>
                        <table class="w-100">
                            <thead class="bg-surface-variant">
                            <tr>
                                <th>站点名称</th>
                                <th>类型</th>
                                <th>页面数</th>
                                <th>评论数</th>
                                <th>浏览量</th>
                                <th>域名</th>
                            </tr>
                            </thead>
                            <tbody>
                            {foreach $sitesData as $siteData}
                                <tr>
                                    <td>
                                        <div class="d-flex items-center">
                                            {if $siteData.site.logo}
                                                <img src="{$siteData.site.logo}" style="width: 24px; height: 24px;" class="mr-2 rounded-md">
                                            {/if}
                                            <strong>{$siteData.site.name}</strong>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="py-1 px-2 rounded-md font-500 {if $siteData.site.type == 'blog'}bg-error text-on-error{else}bg-primary text-on-primary{/if}">
                                            {if $siteData.site.type == 'blog'}博客{else}文档{/if}
                                        </span>
                                    </td>
                                    <td>{$siteData.stats.totalPages}</td>
                                    <td>{$siteData.stats.totalComments}</td>
                                    <td>{$siteData.stats.totalViews}</td>
                                    <td><code>{$siteData.site.domain}</code></td>
                                </tr>
                            {/foreach}
                            </tbody>
                        </table>
                    </mdui-card>
                </div>
            </div>
        </mdui-tab-panel>

        <!-- 各站点详细数据面板 -->
        {foreach $sitesData as $index => $siteData}
        <mdui-tab-panel slot="panel" value="tab-{$index}" class="pt-3">
            <!-- 站点统计卡片 -->
            <div class="row col-space16">
                <div class="col-xs12 col-sm6 col-md3">
                    <mdui-card class="w-100 p-3">
                        <div class="label-medium d-flex items-center">
                            <mdui-icon name="description" class="mr-1"></mdui-icon>
                            页面数
                        </div>
                        <div class="display-medium mt-3 mb-3">
                            <span class="animate-number" data-value="{$siteData.stats.totalPages}">0</span>
                        </div>
                        <div class="body-small d-flex items-center">
                            <mdui-icon name="article" size="small" class="mr-1"></mdui-icon>
                            文章: {$siteData.stats.articleCount} | 分类: {$siteData.stats.folderCount}
                        </div>
                    </mdui-card>
                </div>
                <div class="col-xs12 col-sm6 col-md3">
                    <mdui-card class="w-100 p-3">
                        <div class="label-medium d-flex items-center">
                            <mdui-icon name="comment" class="mr-1"></mdui-icon>
                            评论数
                        </div>
                        <div class="display-medium mt-3 mb-3">
                            <span class="animate-number" data-value="{$siteData.stats.totalComments}">0</span>
                        </div>
                        <div class="body-small d-flex items-center">
                            <mdui-icon name="schedule" size="small" class="mr-1"></mdui-icon>
                            今日: {$siteData.stats.todayComments}
                        </div>
                    </mdui-card>
                </div>
                <div class="col-xs12 col-sm6 col-md3">
                    <mdui-card class="w-100 p-3">
                        <div class="label-medium d-flex items-center">
                            <mdui-icon name="visibility" class="mr-1"></mdui-icon>
                            浏览量
                        </div>
                        <div class="display-medium mt-3 mb-3">
                            <span class="animate-number" data-value="{$siteData.stats.totalViews}">0</span>
                        </div>
                        <div class="body-small d-flex items-center">
                            <mdui-icon name="trending_up" size="small" class="mr-1"></mdui-icon>
                            今日: {$siteData.stats.todayViews}
                        </div>
                    </mdui-card>
                </div>
                <div class="col-xs12 col-sm6 col-md3">
                    <mdui-card class="w-100 p-3">
                        <div class="label-medium d-flex items-center">
                            <mdui-icon name="{if $siteData.site.type == 'blog'}rss_feed{else}description{/if}" class="mr-1"></mdui-icon>
                            站点类型
                        </div>
                        <div class="display-medium mt-3 mb-3">
                            {if $siteData.site.type == 'blog'}博客{else}文档{/if}
                        </div>
                        <div class="body-small d-flex items-center">
                            <mdui-icon name="domain" size="small" class="mr-1"></mdui-icon>
                            {$siteData.site.domain}
                        </div>
                    </mdui-card>
                </div>
            </div>

            <!-- 图表区域 -->
            <div class="row col-space16 mt-3">
                <!-- 页面类型分布 -->
                <div class="col-xs12 col-md6">
                    <mdui-card>
                        <div class="label-medium d-flex items-center">
                            <mdui-icon name="pie_chart" class="mr-1"></mdui-icon>
                            页面类型分布
                        </div>
                        <div class="chart-container w-100" style="height: 400px;">
                            <div id="pageTypeChart-{$index}" style="width: 100%; height: 100%;"></div>
                        </div>
                    </mdui-card>
                </div>

                <!-- 热门标签 -->
                <div class="col-xs12 col-md6">
                    <mdui-card>
                        <div class="label-medium d-flex items-center">
                            <mdui-icon name="label" class="mr-1"></mdui-icon>
                            热门标签
                        </div>
                        <div class="chart-container w-100" style="height: 400px;">
                            <div id="popularTagsChart-{$index}" style="width: 100%; height: 100%;"></div>
                        </div>
                    </mdui-card>
                </div>
            </div>

            <!-- 内容统计 -->
            <div class="row col-space16 mt-3">
                <!-- 热门页面 -->
                <div class="col-xs12 col-md6">
                    <mdui-card>
                        <div class="label-medium d-flex items-center">
                            <mdui-icon name="trending_up" class="mr-1"></mdui-icon>
                            热门页面
                        </div>
                        <div style="max-height: 400px; overflow-y: auto;">
                            {foreach $siteData.popularPages as $page}
                                <div class="trending-item">
                                    <div class="d-flex items-center justify-between py-3 border-bottom">
                                        <div class="flex-1 mr-3">
                                            <div class="font-500">{$page.title}</div>
                                            <div class="body-small text-surface-variant mt-1">
                                                <span class="py-1 px-2 rounded-md font-500 {if $page.type == 2}bg-secondary text-on-secondary{else}bg-outline text-on-surface{/if}">
                                                    {if $page.type == 2}文章{else}分类{/if}
                                                </span>
                                                {if $page.category} · {$page.category}{/if}
                                            </div>
                                        </div>
                                        <div class="body-small text-surface-variant">{$page.view} 次浏览</div>
                                    </div>
                                </div>
                            {/foreach}
                        </div>
                    </mdui-card>
                </div>

                <!-- 最新页面 -->
                <div class="col-xs12 col-md6">
                    <mdui-card>
                        <div class="label-medium d-flex items-center">
                            <mdui-icon name="schedule" class="mr-1"></mdui-icon>
                            最新页面
                        </div>
                        <div style="max-height: 400px; overflow-y: auto;">
                            {foreach $siteData.recentPages as $page}
                                <div class="trending-item">
                                    <div class="d-flex items-center justify-between py-3 border-bottom">
                                        <div class="flex-1 mr-3">
                                            <div class="font-500">{$page.title}</div>
                                            <div class="body-small text-surface-variant mt-1">
                                                <span class="py-1 px-2 rounded-md font-500 {if $page.type == 2}bg-secondary text-on-secondary{else}bg-outline text-on-surface{/if}">
                                                    {if $page.type == 2}文章{else}分类{/if}
                                                </span>
                                                {if $page.category} · {$page.category}{/if}
                                            </div>
                                        </div>
                                        <div class="body-small text-surface-variant">{$page.date}</div>
                                    </div>
                                </div>
                            {/foreach}
                        </div>
                    </mdui-card>
                </div>
            </div>

            <!-- 最近评论 -->
            <div class="row col-space16 mt-3">
                <div class="col-xs12">
                    <mdui-card>
                        <div class="label-medium d-flex items-center">
                            <mdui-icon name="comment" class="mr-1"></mdui-icon>
                            最近评论
                        </div>
                        <table class="w-100">
                            <thead class="bg-surface-variant">
                            <tr>
                                <th>评论者</th>
                                <th>页面</th>
                                <th>评论内容</th>
                                <th>来源</th>
                                <th>时间</th>
                            </tr>
                            </thead>
                            <tbody>
                            {foreach $siteData.recentComments as $comment}
                                <tr>
                                    <td>
                                        <div class="d-flex items-center">
                                            <img src="{$comment.head}"
                                                 style="width: 32px; height: 32px;" class="rounded-full mr-2">
                                            <div>
                                                <div class="font-500">{$comment.nickname}</div>
                                                <div class="body-small text-surface-variant">{$comment.mail}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>{$comment.page_title}</div>
                                        {if $comment.staff}
                                            <span class="py-1 px-2 rounded-md font-500 bg-primary text-on-primary">工作人员</span>
                                        {/if}
                                    </td>
                                    <td>
                                        <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <div class="text-ellipsis" style="max-width: 300px;">{$comment.comment}</div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="body-small">
                                            <div>{$comment.address}</div>
                                            <div class="text-surface-variant">{$comment.browser} · {$comment.system}</div>
                                        </div>
                                    </td>
                                    <td>
                                        {$comment.formatted_date}
                                    </td>
                                </tr>
                            {/foreach}
                            </tbody>
                        </table>
                    </mdui-card>
                </div>
            </div>

            <script>
                // 为每个站点设置图表数据
                window['pageTypeData_{$index}'] = {$siteData.pageTypeData nofilter};
                window['popularTagsData_{$index}'] = {$siteData.popularTagsData nofilter};
            </script>
        </mdui-tab-panel>
        {/foreach}
        {else}
        <!-- 无站点数据时显示 -->
        <div class="row col-space16">
            <div class="col-xs12">
                <mdui-card class="w-100 p-5 text-center">
                    <mdui-icon name="public" class="mb-3 text-surface-variant" style="font-size: 64px;"></mdui-icon>
                    <div class="title-small text-surface-variant mb-2">暂无站点数据</div>
                    <div class="text-surface-variant-60">请先添加站点配置</div>
                </mdui-card>
            </div>
        </div>
        {/if}
    </div>
</div>

<script id="script" src="/static/js/dashboard.js?v={$__v}"></script>





