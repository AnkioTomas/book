<!doctype html>
<html lang="zh-CN" class="mdui-theme-light scroll-line">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no"/>
    <meta name="renderer" content="webkit"/>
    <title id="title">{$title}</title>
    {include file="publicHeader.tpl"}
    <style id="style"></style>
</head>

<body class="bg">

<mdui-layout class="">
    <mdui-top-app-bar scroll-behavior="elevate" scroll-target=".layout-main" class="position-fixed">
        <mdui-button-icon icon="menu" id="navigation-drawer-switch"></mdui-button-icon>
        <mdui-top-app-bar-title>{$title}</mdui-top-app-bar-title>
        <div style="flex-grow: 1"></div>
        <div class="layout-header-right d-flex items-center gap-2">
            <div class="top-bar-utils d-flex items-center gap-2">
                <theme-switcher iconBtn="true"></theme-switcher>
                <lang-switcher iconBtn="true"></lang-switcher>
            </div>
            <mdui-dropdown class="layout-header-user-info ">
                <span slot="trigger" class="p-0">
                    {if $user->avatar != ''}
                        <mdui-avatar src="{$user->avatar}"></mdui-avatar>
                    {else}
                        <mdui-avatar icon="account_circle"></mdui-avatar>
                    {/if}
                    <span>{$userDisplayName}</span>
                </span>
                <mdui-menu>
                    <mdui-menu-item disabled>
                        <div style="display:flex;flex-direction:column;gap:0.15rem;min-width:10rem;">
                            <span style="font-size:0.85rem;opacity:0.72;">{$userRole}</span>
                            <span style="font-size:0.8rem;opacity:0.6;">@{$user->username}</span>
                        </div>
                    </mdui-menu-item>
                    <mdui-divider></mdui-divider>
                    <mdui-menu-item icon="lock" data-pjax-item data-href="/login/pwd">修改密码</mdui-menu-item>
                    <mdui-menu-item icon="logout" href="/login/logout">退出登录</mdui-menu-item>
                </mdui-menu>
            </mdui-dropdown>
        </div>
    </mdui-top-app-bar>

    <mdui-navigation-drawer open class="navigation-drawer position-fixed" id="navigation-drawer" close-on-overlay-click>
        <mdui-list class="m-2">
            { $menuNavItems = $menuConfig }
            {include file="menuNav.tpl"}
        </mdui-list>
    </mdui-navigation-drawer>

    <mdui-layout-main class="layout-main">
        <div id="bodyContainer">
            <div id="container" class="container mt-0">

            </div>
        </div>
    </mdui-layout-main>



</mdui-layout>
<script id="script"></script>
{include file="publicScript.tpl"}
</body>
</html>

