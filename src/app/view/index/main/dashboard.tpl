<title id="title">仪表盘 - {$title}</title>
<style id="style">
    .continue-cover {
        width: 130px;
        min-width: 130px;
        aspect-ratio: 3 / 4;
        border-radius: 10px;
        overflow: hidden;
    }

    .progress-track {
        width: 100%;
        height: 8px;
        border-radius: 99px;
        background: rgba(var(--mdui-color-outline), 0.25);
        overflow: hidden;
    }

    .progress-bar {
        height: 100%;
        border-radius: 99px;
        background: rgb(var(--mdui-color-primary));
    }

    .recently-read-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
        gap: 12px;
        margin-bottom: 18px;
    }

    .recently-read-cover {
        width: 64px;
        min-width: 64px;
        height: 90px;
        border-radius: 8px;
        overflow: hidden;
    }

    .added-wall {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(136px, 136px));
        justify-content: start;
        gap: 14px;
    }

    .added-item,
    .added-item book-card {
        width: 136px;
    }

    @media (max-width: 768px) {
        .continue-layout {
            flex-direction: column;
            gap: 12px;
        }

        .continue-cover {
            width: 100%;
            min-width: 0;
            max-height: 260px;
        }

        .continue-actions mdui-button {
            width: 100%;
        }

        .added-wall {
            grid-template-columns: repeat(auto-fill, minmax(120px, 120px));
            gap: 10px;
        }

        .added-item,
        .added-item book-card {
            width: 120px;
        }
    }
</style>

<div id="container" class="container h-fit py-3">
    <h2 class="title-medium mb-2 " style="font-weight: bold">继续阅读</h2>
    <mdui-card class="p-3 rounded-lg mb-3 w-full">

        {if $currentReading}
        <div class="d-flex items-stretch gap-4 continue-layout">
            <image-loader src="{$currentReading.coverUrl}" class="continue-cover"></image-loader>
            <div class="d-flex flex-col flex-1 min-w-0">
                <div class="title-large text-ellipsis" title="{$currentReading.bookName}">{$currentReading.bookName}</div>
                <div class="body-medium text-on-surface-variant text-ellipsis mt-1">{$currentReading.author}</div>
                <div class="body-small text-on-surface-variant mt-2">阅读进度</div>
                <div class="body-small mt-1">{$currentReading.percent}%</div>
                <div class="progress-track mt-1">
                    <div class="progress-bar" style="width:{$currentReading.percent}%;"></div>
                </div>
                <div class="mt-auto pt-3 continue-actions">
                    <mdui-button class="js-resume-reading rounded-full" data-file="{$currentReading.filename}" data-title="{$currentReading.bookName}" variant="filled" icon="menu_book">
                        继续阅读
                    </mdui-button>
                </div>
            </div>
        </div>
        {else}
        <div class="body-medium text-on-surface-variant">暂无可展示书籍</div>
        {/if}
    </mdui-card>

    <h2 class="title-medium mb-2"  style="font-weight: bold">最近阅读</h2>
    <div class="recently-read-grid">
        {if $recentlyReadBooks}
        {foreach $recentlyReadBooks as $book}
        <mdui-card class="p-2 rounded-lg bg-surface">
            <div class="d-flex gap-2">
                <image-loader src="{$book.coverUrl}" class="recently-read-cover"></image-loader>
                <div class="d-flex flex-col flex-1 min-w-0">
                    <div class="title-small text-ellipsis" title="{$book.bookName}">{$book.bookName}</div>
                    <div class="body-small text-on-surface-variant text-ellipsis mt-1">{$book.author}</div>
                    <div class="progress-track mt-2"><div class="progress-bar" style="width:{$book.percent}%;"></div></div>
                    <div class="mt-auto pt-2 d-flex items-center">
                        <mdui-button-icon class="js-resume-reading" data-file="{$book.filename}" data-title="{$book.bookName}" icon="arrow_forward"></mdui-button-icon>
                    </div>
                </div>
            </div>
        </mdui-card>
        {/foreach}
        {else}
        <div class="body-medium text-on-surface-variant">暂无数据</div>
        {/if}
    </div>

    <h2 class="title-medium mb-2"  style="font-weight: bold">最近添加</h2>
    <div class="added-wall">
        {if $recentBooks}
        {foreach $recentBooks as $book}
        <div class="added-item">
            <book-card cover="{$book.coverUrl}" title="{$book.bookName}" author="{$book.author}"></book-card>
            <div class="label-small text-on-surface-variant mt-2">添加于 {$book.formattedDate}</div>
        </div>
        {/foreach}
        {else}
        <div class="body-medium text-on-surface-variant">暂无数据</div>
        {/if}
    </div>
</div>

<script id="script" src="/static/js/dashboard.js?v={$__v}"></script>
