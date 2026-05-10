{foreach $menuNavItems as $node}
{if isset($node._collapseGroup)}
<mdui-collapse>
{foreach $node.items as $item}
    <mdui-collapse-item value="item-{$item.__navPath}">
                <mdui-list-item slot="header" rounded icon="{$item.icon}">
                    <span>{$item.title}</span>
                    <mdui-icon slot="end-icon" name="keyboard_arrow_left"></mdui-icon>
                </mdui-list-item>
                <div style="margin-left: 2.5rem">
{ $menuNavSavedItems = $menuNavItems }
{ $menuNavItems = $item['sub'] }
{include file="menuNav.tpl"}
{ $menuNavItems = $menuNavSavedItems }
                </div>
    </mdui-collapse-item>
{/foreach}
</mdui-collapse>
{else}
        <mdui-list-item rounded data-match="{isset($node['match'])?$node['match']:''}"
                        data-pjax="{$node.pjax ? 'true' : 'false'}"
                        data-target="{isset($node['self']) ? 'self' : ''}" data-link="{$node.url}"
                        icon="{$node.icon}">{$node.title}</mdui-list-item>
{/if}
{/foreach}
