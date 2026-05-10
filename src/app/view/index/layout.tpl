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
        <mdui-button-icon icon="home" href="/"></mdui-button-icon>
        <theme-switcher iconBtn="true"></theme-switcher>
        <lang-switcher iconBtn="true"></lang-switcher>
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

