{function name="renderMenu" items=[] pathPrefix=''}
    {foreach $items as $idx => $node}

        {* 使用基础 if 和字符串拼接(.)，完美绕过原生正则的解析 Bug *}
        {if $pathPrefix == ''}
            {$currentPath = $idx}
        {else}
            {$currentPath = "$pathPrefix-$idx"}
        {/if}

        {if !empty($node.sub)}
            <mdui-collapse>
                <mdui-collapse-item value="item-{$currentPath}">
                    <mdui-list-item slot="header" rounded icon="{$node.icon}">
                        <span>{$node.title}</span>
                        <mdui-icon slot="end-icon" name="keyboard_arrow_left"></mdui-icon>
                    </mdui-list-item>
                    <div style="margin-left: 2.5rem">
                        {* 完美支持递归调用 *}
                        {call name="renderMenu" items=$node.sub pathPrefix=$currentPath}
                    </div>
                </mdui-collapse-item>
            </mdui-collapse>
        {else}
            <mdui-list-item
                    rounded
                    icon="{$node.icon}"
                    data-link="{$node.url}"
                    data-pjax="{$node.pjax ? 'true' : 'false'}"
                    data-match="{if isset($node.match)}{$node.match}{/if}">
                {$node.title}
            </mdui-list-item>
        {/if}
    {/foreach}
{/function}

{* 一键渲染！ *}
{call name="renderMenu" items=$menuConfig}