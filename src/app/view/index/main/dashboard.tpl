<title id="title">仪表盘 - {$title}</title>
<style id="style">
    /* 统计卡片渐变背景 */
    .stat-card-primary { background: linear-gradient(135deg, rgba(var(--mdui-color-primary), 0.08) 0%, rgba(var(--mdui-color-primary), 0.16) 100%); }
    .stat-card-secondary { background: linear-gradient(135deg, rgba(var(--mdui-color-secondary), 0.08) 0%, rgba(var(--mdui-color-secondary), 0.16) 100%); }
    .stat-card-tertiary { background: linear-gradient(135deg, rgba(var(--mdui-color-tertiary), 0.08) 0%, rgba(var(--mdui-color-tertiary), 0.16) 100%); }
    .stat-card-error { background: linear-gradient(135deg, rgba(var(--mdui-color-error), 0.08) 0%, rgba(var(--mdui-color-error), 0.16) 100%); }

    .mdui-theme-dark .stat-card-primary { background: linear-gradient(135deg, rgba(var(--mdui-color-primary), 0.18) 0%, rgba(var(--mdui-color-primary), 0.26) 100%); }
    .mdui-theme-dark .stat-card-secondary { background: linear-gradient(135deg, rgba(var(--mdui-color-secondary), 0.18) 0%, rgba(var(--mdui-color-secondary), 0.26) 100%); }
    .mdui-theme-dark .stat-card-tertiary { background: linear-gradient(135deg, rgba(var(--mdui-color-tertiary), 0.18) 0%, rgba(var(--mdui-color-tertiary), 0.26) 100%); }
    .mdui-theme-dark .stat-card-error { background: linear-gradient(135deg, rgba(var(--mdui-color-error), 0.18) 0%, rgba(var(--mdui-color-error), 0.26) 100%); }

    .stat-card-primary mdui-icon { color: rgba(var(--mdui-color-primary)); }
    .stat-card-secondary mdui-icon { color: rgba(var(--mdui-color-secondary)); }
    .stat-card-tertiary mdui-icon { color: rgba(var(--mdui-color-tertiary)); }
    .stat-card-error mdui-icon { color: rgba(var(--mdui-color-error)); }
    
    .book-cover {
        width: 48px;
        height: 64px;
        object-fit: cover;
        border-radius: 4px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .book-item {
        padding: 12px 0;
        border-bottom: 1px solid rgba(var(--mdui-color-outline), 0.12);
    }
    
    .book-item:last-child {
        border-bottom: none;
    }
    
    .rating-stars {
        color: #ffa726;
        font-size: 16px;
    }
</style>

<div id="container" class="container py-4 h-fit">
    <!-- 全局统计卡片 -->
    <div class="row col-space16 mb-4">
        <div class="col-xs12 col-sm6 col-md3">
            <mdui-card class="w-100 p-3 stat-card-primary">
                <div class="label-medium d-flex items-center">
                    <mdui-icon name="library_books" class="mr-2"></mdui-icon>
                    总藏书
                </div>
                <div class="display-small mt-2">
                    <span class="animate-number" data-value="{$globalStats.totalBooks}">0</span>
                </div>
            </mdui-card>
        </div>
        <div class="col-xs12 col-sm6 col-md3">
            <mdui-card class="w-100 p-3 stat-card-secondary">
                <div class="label-medium d-flex items-center">
                    <mdui-icon name="collections_bookmark" class="mr-2"></mdui-icon>
                    系列数
                </div>
                <div class="display-small mt-2">
                    <span class="animate-number" data-value="{$globalStats.seriesCount}">0</span>
                </div>
            </mdui-card>
        </div>
        <div class="col-xs12 col-sm6 col-md3">
            <mdui-card class="w-100 p-3 stat-card-tertiary">
                <div class="label-medium d-flex items-center">
                    <mdui-icon name="category" class="mr-2"></mdui-icon>
                    分类数
                </div>
                <div class="display-small mt-2">
                    <span class="animate-number" data-value="{$globalStats.categoryCount}">0</span>
                </div>
            </mdui-card>
        </div>
        <div class="col-xs12 col-sm6 col-md3">
            <mdui-card class="w-100 p-3 stat-card-error">
                <div class="label-medium d-flex items-center">
                    <mdui-icon name="favorite" class="mr-2"></mdui-icon>
                    收藏夹
                </div>
                <div class="display-small mt-2">
                    <span class="animate-number" data-value="{$globalStats.favoriteCount}">0</span>
                </div>
            </mdui-card>
        </div>
    </div>

    <!-- 书籍列表 -->
    <div class="row col-space16">
        <!-- 最新添加 -->
        <div class="col-xs12 col-md6">
            <mdui-card class="p-3">
                <div class="title-medium d-flex items-center mb-3">
                    <mdui-icon name="schedule" class="mr-2"></mdui-icon>
                    最新添加
                </div>
                <mdui-list>
                    {foreach $recentBooks as $book}
                    <mdui-list-item class="book-item">
                        <div class="d-flex items-start w-100">
                            {if $book.coverUrl}
                            <image-loader src="{$book.coverUrl}" class="book-cover mr-3" style="height: 64px;"></image-loader>
                            {else}
                            <div class="book-cover mr-3 bg-surface-variant d-flex items-center justify-center">
                                <mdui-icon name="book"></mdui-icon>
                            </div>
                            {/if}
                            <div class="flex-1 min-w-0">
                                <div class="title-small mb-1">{$book.bookName}</div>
                                <div class="body-medium text-on-surface-variant mb-1">
                                    <mdui-icon name="person" class="text-xs"></mdui-icon>
                                    {$book.author}
                                </div>
                                {if $book.series}
                                <div class="body-small mb-1">
                                    <mdui-chip>{$book.series} #{$book.seriesNum}</mdui-chip>
                                </div>
                                {/if}
                            </div>
                            <div class="ml-3 text-right flex-shrink-0">
                                <div class="rating-stars mb-1">
                                    {$book.ratingStars nofilter}
                                </div>
                                <div class="body-small text-on-surface-variant">
                                    {$book.formattedDate}
                                </div>
                            </div>
                        </div>
                    </mdui-list-item>
                    {/foreach}
                </mdui-list>
            </mdui-card>
        </div>

        <!-- 高分推荐 -->
        <div class="col-xs12 col-md6">
            <mdui-card class="p-3 w-100">
                <div class="title-medium d-flex items-center mb-3">
                    <mdui-icon name="star" class="mr-2"></mdui-icon>
                    高分推荐
                </div>
                <mdui-list>
                    {foreach $highRatedBooks as $book}
                    <mdui-list-item class="book-item">
                        <div class="d-flex items-start w-100">
                            {if $book.coverUrl}
                            <image-loader src="{$book.coverUrl}" class="book-cover mr-3" style="height: 64px;"></image-loader>
                            {else}
                            <div class="book-cover mr-3 bg-surface-variant d-flex items-center justify-center">
                                <mdui-icon name="book"></mdui-icon>
                            </div>
                            {/if}
                            <div class="flex-1 min-w-0">
                                <div class="title-small mb-1">{$book.bookName}</div>
                                <div class="body-medium text-on-surface-variant mb-1">
                                    <mdui-icon name="person" class="text-xs"></mdui-icon>
                                    {$book.author}
                                </div>
                                {if $book.favorite}
                                <div class="body-small">
                                    <mdui-chip>
                                        <mdui-icon slot="icon" name="bookmark"></mdui-icon>
                                        {$book.favorite}
                                    </mdui-chip>
                                </div>
                                {/if}
                            </div>
                            <div class="ml-3 flex-shrink-0">
                                <div class="rating-stars">
                                    {$book.ratingStars nofilter}
                                </div>
                            </div>
                        </div>
                    </mdui-list-item>
                    {/foreach}
                </mdui-list>
            </mdui-card>
        </div>
    </div>
</div>

<script id="script" src="/static/js/dashboard.js?v={$__v}"></script>
